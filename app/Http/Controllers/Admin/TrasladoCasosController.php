<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\PropuestaCita;
use App\Models\SolicitudAtencion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Traslado de último recurso: el administrador pasa los casos activos de un profesional que no
 * puede entregarlos (retiro, incapacidad) a otro de la misma área. Lo normal es que el propio
 * profesional los cierre o remita. El administrador no ve contenido clínico del caso.
 */
class TrasladoCasosController extends Controller
{
    public const MOTIVOS = ['Retiro de la institución', 'Incapacidad o licencia', 'Otro'];

    public const JUSTIFICACION_MAX = 2000;

    public function index(User $usuario): View|RedirectResponse
    {
        $casos = $this->casosActivos($usuario)->load(['solicitud.persona', 'solicitud.facultad', 'citas'])
            ->sortByDesc(fn (Caso $caso) => $caso->esEmergencia())->values();

        if ($casos->isEmpty()) {
            return redirect()->route('admin.administracion')->with('status', 'Ese usuario no tiene casos activos por trasladar.');
        }

        return view('admin.traslados.index', [
            'usuario' => $usuario,
            'casos' => $casos,
            'destinos' => collect(SolicitudAtencion::SERVICIOS)->mapWithKeys(fn (string $servicio) => [
                $servicio => User::profesionalesActivos()->queAtienden($servicio)
                    ->whereKeyNot($usuario->id_usuario)
                    ->with('persona')
                    ->withCount(['casosAsignados as casos_activos' => fn ($casos) => $casos->activos()])
                    ->get()
                    ->sortBy(fn (User $profesional) => $profesional->persona?->nombre_completo)
                    ->values(),
            ]),
            'motivos' => self::MOTIVOS,
            'justificacionMax' => self::JUSTIFICACION_MAX,
        ]);
    }

    /**
     * Traslada los casos con destino elegido, todos o ninguno. Como en una remisión interna, las
     * citas y tareas pendientes se cierran: estaban en la agenda del profesional anterior.
     */
    public function store(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', Rule::in(self::MOTIVOS)],
            'justificacion' => ['required', 'string', 'min:10', 'max:'.self::JUSTIFICACION_MAX],
            'autoriza_nombre' => ['required', 'string', 'min:3', 'max:150'],
            'autoriza_cargo' => ['required', 'string', 'min:3', 'max:100'],
            'confirmacion' => ['accepted'],
            'destinos' => ['array'],
            'destinos.*' => ['nullable', 'uuid'],
        ], [
            'motivo.required' => 'Selecciona por qué el profesional no puede entregar sus casos.',
            'motivo.in' => 'Selecciona un motivo de la lista.',
            'justificacion.required' => 'Escribe la justificación del traslado.',
            'justificacion.min' => 'La justificación debe tener al menos 10 caracteres.',
            'autoriza_nombre.required' => 'Escribe el nombre de quien autorizó el traslado.',
            'autoriza_cargo.required' => 'Escribe el cargo de quien autorizó el traslado.',
            'confirmacion.accepted' => 'Confirma que el profesional no puede entregar sus casos.',
            'destinos.*.uuid' => 'Elige un profesional de la lista.',
        ]);

        $destinos = array_filter($datos['destinos'] ?? []);

        if (! $destinos) {
            return back()->withInput()->withErrors(['destinos' => 'Elige el profesional que recibirá al menos un caso.']);
        }

        try {
            $totales = DB::transaction(function () use ($destinos, $datos, $usuario, $request) {
                $totales = ['citas_canceladas' => 0, 'citas_sin_asistencia' => 0, 'tareas_canceladas' => 0];

                foreach ($destinos as $idCaso => $idDestino) {
                    $resumen = $this->trasladar((string) $idCaso, $idDestino, $usuario, $request->user(), $datos);

                    foreach ($resumen as $clave => $cantidad) {
                        $totales[$clave] += $cantidad;
                    }
                }

                return $totales;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No fue posible trasladar los casos en este momento. No se guardó ningún dato; intenta de nuevo en unos minutos.');
        }

        $cantidad = count($destinos);

        return redirect()->route('admin.administracion')->with('status', ($cantidad === 1 ? 'Se trasladó 1 caso' : "Se trasladaron {$cantidad} casos")
            .". Citas canceladas: {$totales['citas_canceladas']}. Avisa a los estudiantes: el sistema no les envía ningún mensaje.");
    }

    /**
     * @return array{citas_canceladas: int, citas_sin_asistencia: int, tareas_canceladas: int}
     */
    private function trasladar(string $idCaso, string $idDestino, User $origen, User $admin, array $datos): array
    {
        $caso = Caso::whereKey($idCaso)->lockForUpdate()->first();

        // El profesional pudo cerrar o remitir el caso mientras se llenaba el formulario.
        if (! $caso?->estaActivo() || $caso->id_profesional !== $origen->id_usuario) {
            throw ValidationException::withMessages([
                'destinos' => 'Uno de los casos ya no está activo o ya no está a cargo de este profesional. No se trasladó ninguno; revisa la lista de nuevo.',
            ]);
        }

        $destino = User::profesionalesActivos()->queAtienden($caso->solicitud?->tipo_servicio)
            ->whereKeyNot($origen->id_usuario)->with('persona')->find($idDestino);

        if (! $destino) {
            throw ValidationException::withMessages([
                'destinos' => "El profesional elegido para el caso {$caso->codigo_expediente} no está activo o no atiende esa área. No se trasladó ninguno.",
            ]);
        }

        $autorizo = "{$datos['autoriza_nombre']} ({$datos['autoriza_cargo']})";

        $traslado = Actuacion::create([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $origen->id_usuario,
            'id_admin_traslado' => $admin->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_REMISION,
            'fecha_hora' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            'descripcion' => "{$datos['motivo']}: {$datos['justificacion']}",
            'destino_remision' => Actuacion::DESTINO_INTERNA,
            'id_profesional_destino' => $destino->id_usuario,
            'autorizacion_traslado' => $autorizo,
        ]);

        $resumen = $traslado->liberarPendientesDelCaso();

        // Los horarios que propuso el profesional anterior ya no se pueden elegir.
        PropuestaCita::where('id_caso', $caso->id_caso)->where('estado', PropuestaCita::PROPUESTA)
            ->update(['estado' => PropuestaCita::DESCARTADA, 'fecha_respuesta' => $traslado->fecha_hora->toDateTimeString()]);

        $caso->forceFill(['id_profesional' => $destino->id_usuario, 'estado_caso' => Caso::ESTADO_ABIERTO])->save();

        AuditoriaExpediente::registrar(AuditoriaExpediente::CASO_TRASLADADO_ADMIN, $caso, [
            'origen' => $origen->persona?->nombre_completo ?? $origen->codigo_unico,
            'destino' => $destino->persona?->nombre_completo ?? $destino->codigo_unico,
            'motivo' => $datos['motivo'],
            'justificacion' => $datos['justificacion'],
            'autorizo' => $autorizo,
        ] + $resumen);

        return $resumen;
    }

    /**
     * @return Collection<int, Caso>
     */
    private function casosActivos(User $usuario): Collection
    {
        return $usuario->casosAsignados()->activos()->orderBy('fecha_apertura')->get();
    }
}

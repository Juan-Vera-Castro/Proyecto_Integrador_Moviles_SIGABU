<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCitaRequest;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CitaController extends Controller
{
    /**
     * Agenda del profesional: sus citas desde hoy o las de una fecha concreta.
     */
    public function index(Request $request): View
    {
        $fecha = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']])['fecha'] ?? null;
        $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();

        $citas = Actuacion::citas()
            ->where('id_profesional', $request->user()->id_usuario)
            ->whereHas('caso', fn ($caso) => $caso->deAreaDe($request->user()))
            ->when(
                $fecha,
                fn ($query) => $query->whereBetween('fecha_hora', ["$fecha 00:00:00", "$fecha 23:59:59"]),
                fn ($query) => $query->where('fecha_hora', '>=', "$hoy 00:00:00"),
            )
            ->with('caso.solicitud.persona')
            ->orderBy('fecha_hora')
            ->get();

        // Pacientes que ya tuvieron su cita, siguen con el caso abierto y no tienen la siguiente.
        $porReagendar = Caso::porReagendarDe($request->user())
            ->with(['solicitud.persona', 'citas'])
            ->get()
            ->sortByDesc(fn (Caso $caso) => $caso->ultimaCitaRealizada()?->fecha_hora)
            ->values();

        return view('citas.agenda', [
            'porReagendar' => $porReagendar,
            'citasPorDia' => $citas->groupBy(fn (Actuacion $cita) => $cita->fecha_hora->format('Y-m-d')),
            'totalCitas' => $citas->count(),
            'solicitudesPendientes' => Caso::porAtenderDe($request->user()),
            'fecha' => $fecha,
            'hoy' => $hoy,
        ]);
    }

    /**
     * Formulario para agendar una cita de asesoría psicológica (HU-011).
     */
    public function create(Request $request): View
    {
        $casos = Caso::activos()
            ->where('id_profesional', $request->user()->id_usuario)
            // Lo necesario para el resumen del paciente que se muestra al seleccionarlo.
            ->with([
                'solicitud.persona.casos' => fn ($casos) => $casos->deAreaDe($request->user())->withCount('citas')->with('solicitud', 'profesional.persona'),
                'citas', 'tareas', 'aceptacionesConsentimiento',
            ])
            ->orderBy('codigo_expediente')
            ->get();

        $casoInicial = $request->query('caso');
        $casoPedido = $casoInicial ? $casos->firstWhere('id_caso', $casoInicial) : null;

        return view('citas.agendar', [
            'casos' => $casos,
            'idsPorReagendar' => Caso::porReagendarDe($request->user())->pluck('id_caso')->all(),
            'casoInicial' => $casoPedido?->id_caso,
            'avisoCaso' => $casoInicial && ! $casoPedido ? $this->avisoCasoNoDisponible($casoInicial, $request->user()) : null,
            'volverA' => $casoPedido ? route('casos.show', $casoPedido) : route('citas.agenda'),
            'horarios' => Actuacion::HORARIOS,
            'observacionesMax' => Actuacion::OBSERVACIONES_MAX,
            'hoy' => now(Actuacion::ZONA_HORARIA)->toDateString(),
        ]);
    }

    /**
     * Explica por qué el caso con el que se abrió el formulario no aparece en la lista.
     */
    private function avisoCasoNoDisponible(string $idCaso, User $profesional): string
    {
        $caso = Str::isUuid($idCaso) ? Caso::find($idCaso) : null;

        return match (true) {
            ! $caso => 'El caso indicado no existe. Elige uno de tus casos activos.',
            ! $caso->estaActivo() => "El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\". Solo se pueden agendar citas para casos activos.",
            default => "El caso {$caso->codigo_expediente} no está asignado a ti. Solo puedes agendar citas en tus casos.",
        };
    }

    /**
     * Crea la cita asociada al caso y al profesional. La disponibilidad se vuelve a
     * comprobar dentro de la transacción, con la agenda del profesional bloqueada,
     * para que dos agendamientos simultáneos no ocupen la misma franja.
     */
    public function store(StoreCitaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $idProfesional = $request->user()->id_usuario;
        $inicio = $request->inicioCita();

        try {
            $cita = DB::transaction(function () use ($datos, $idProfesional, $inicio) {
                User::whereKey($idProfesional)->lockForUpdate()->first();
                $caso = Caso::whereKey($datos['id_caso'])->lockForUpdate()->firstOrFail();

                // El caso pudo cambiar de estado o de profesional después de la validación.
                if (! $caso->estaActivo() || $caso->id_profesional !== $idProfesional) {
                    throw ValidationException::withMessages([
                        'id_caso' => "El caso {$caso->codigo_expediente} ya no está activo o no está asignado a ti. Actualiza la página y verifica el caso.",
                    ]);
                }

                if (Actuacion::horarioOcupado($idProfesional, $inicio)) {
                    throw ValidationException::withMessages(['hora' => StoreCitaRequest::mensajeConflicto($inicio)]);
                }

                return Actuacion::create([
                    'id_caso' => $caso->id_caso,
                    'id_profesional' => $idProfesional,
                    'tipo_actuacion' => Actuacion::TIPO_CITA,
                    'fecha_hora' => $inicio,
                    'descripcion' => $datos['observaciones'] ?: null,
                ])->setRelation('caso', $caso);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No fue posible agendar la cita en este momento. No se guardó ningún dato; intenta de nuevo en unos minutos.');
        }

        return redirect()
            ->route('casos.show', $cita->caso)
            ->with('status', "Cita agendada para el {$inicio->format('Y-m-d')} a las {$inicio->format('g:i a')}. Ya aparece en tu agenda y en el caso {$cita->caso->codigo_expediente}.");
    }

    /**
     * Registra si el estudiante asistió a la cita (HU-012). Solo el profesional del
     * caso puede hacerlo, una única vez y a partir de la hora de la cita.
     */
    public function registrarAsistencia(Request $request, Actuacion $cita): RedirectResponse
    {
        abort_unless($cita->tipo_actuacion === Actuacion::TIPO_CITA, 404);
        abort_unless($cita->caso?->id_profesional === $request->user()->id_usuario, 403);

        $datos = $request->validateWithBag('asistencia', [
            'estado_asistencia' => ['required', Rule::in([Actuacion::ASISTIO, Actuacion::NO_ASISTIO])],
            'motivo_inasistencia' => ['nullable', 'string', 'max:'.Actuacion::MOTIVO_MAX],
        ], [
            'estado_asistencia.required' => 'Indica si el estudiante asistió o no a la cita.',
            'estado_asistencia.in' => 'Indica si el estudiante asistió o no a la cita.',
            'motivo_inasistencia.max' => 'El motivo no puede superar los '.Actuacion::MOTIVO_MAX.' caracteres.',
        ]);

        $volver = redirect()->to(route('casos.show', $cita->caso).'#citas');

        if ($cita->esProxima()) {
            return $volver->with('error', 'La asistencia solo se puede registrar cuando llegue la hora de la cita.');
        }

        if (! $cita->asistenciaPendiente()) {
            return $volver->with('error', "La asistencia de esta cita ya fue registrada como \"{$cita->estado_asistencia}\".");
        }

        $noAsistio = $datos['estado_asistencia'] === Actuacion::NO_ASISTIO;

        // "Asistió" es atender al estudiante: exige consentimiento firmado (RN-01). "No asistió" no.
        if (! $noAsistio && $motivo = $cita->caso->motivoSinAtencion()) {
            return $volver->with('error', $motivo);
        }

        $motivo = trim((string) ($datos['motivo_inasistencia'] ?? ''));

        $cita->update([
            'estado_asistencia' => $datos['estado_asistencia'],
            'motivo_inasistencia' => $noAsistio && $motivo !== '' ? $motivo : null,
        ]);

        return $volver->with('status', "Asistencia registrada: {$cita->estado_asistencia} a la cita del {$cita->fecha_hora->format('Y-m-d')} a las {$cita->fecha_hora->format('g:i a')}.");
    }

    /**
     * Franjas ocupadas o pasadas de un día en la agenda del profesional autenticado.
     */
    public function disponibilidad(Request $request): JsonResponse
    {
        $request->validate(['fecha' => ['required', 'date_format:Y-m-d']]);

        return response()->json(Actuacion::disponibilidadDelDia($request->user()->id_usuario, $request->query('fecha')));
    }
}

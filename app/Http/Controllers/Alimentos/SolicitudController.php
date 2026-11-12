<?php

namespace App\Http\Controllers\Alimentos;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Alimentos\Entrega;
use App\Models\Alimentos\Priorizado;
use App\Models\Alimentos\Solicitud;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Solicitud de apoyo alimentario ("Ucevistas de Corazón") desde el panel del estudiante.
 * Permite consultar la ficha socioeconómica (datos de SIGABU; la verificación académica con SIGA está pendiente)
 * y realizar la postulación directa con cálculo de prioridad por algoritmo de equidad.
 */
class SolicitudController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();
        $persona = $user->persona;
        $codigoEstudiante = $user->codigo_unico;

        $vinculacion = $persona ? DB::table('vinculacion')->where('id_persona', $persona->id_persona)->first() : null;

        $entregasHistoricas = $persona ? Entrega::whereHas('solicitud', fn ($q) => $q->where('id_persona', $persona->id_persona))->count() : 0;

        $misSolicitudes = $persona
            ? Solicitud::with(['entrega.lote'])
                ->where('id_persona', $persona->id_persona)
                ->orderByDesc('fecha_solicitud')
                ->get()
            : collect();

        $ultimaSolicitud = $misSolicitudes->first();

        $ficha = [
            'nombre' => $persona?->nombre_completo,
            'codigo' => $codigoEstudiante,
            'documento' => $persona?->numero_documento,
            'correo' => $persona?->correo_inst ?? $user->email,
            'programa' => $vinculacion->programa_academico ?? null,
            'semestre' => $vinculacion->semestre ?? null,
            'jornada' => $vinculacion->jornada ?? null,
            'matriculaActiva' => null,
            'estrato' => $ultimaSolicitud?->estrato,
            'sisben' => null,
            'situacionPrioritaria' => null,
            'mercadosRecibidos' => $entregasHistoricas,
        ];

        return view('alimentario.crear', [
            'misSolicitudes' => $misSolicitudes,
            'programa' => $ficha['programa'],
            'ficha' => $ficha,
        ]);
    }

    /**
     * Consulta interactiva de la Ficha Socioeconómica por Código de Estudiante
     * (Integración con base de datos de registro académico y bienestar).
     */
    public function consultarFicha(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
        ]);

        $codigo = trim($datos['codigo']);
        $usuarioActual = $request->user();

        // La consulta solo puede corresponder al estudiante autenticado.
        if ($codigo !== (string) $usuarioActual->codigo_unico) {
            return response()->json([
                'success' => false,
                'error' => 'El código no corresponde al estudiante autenticado.',
            ], 403);
        }

        // Solo consultar registros existentes en SIGABU.
        $usuario = User::where('codigo_unico', $codigo)
            ->with('persona')
            ->first();

        if (! $usuario || ! $usuario->persona) {
            return response()->json([
                'success' => false,
                'error' => 'No se encontró una ficha estudiantil registrada.',
            ], 404);
        }

        $persona = $usuario->persona;

        $vinculacion = DB::table('vinculacion')
            ->where('id_persona', $persona->id_persona)
            ->first();

        $entregasPrevias = Entrega::whereHas(
            'solicitud',
            fn ($q) => $q->where('id_persona', $persona->id_persona)
        )->count();

        $ultimaSolicitud = Solicitud::where(
            'id_persona',
            $persona->id_persona
        )->latest('fecha_solicitud')->first();

        return response()->json([
            'success' => true,
            'codigo' => $usuario->codigo_unico,
            'nombre' => $persona->nombre_completo,
            'documento' => $persona->numero_documento,
            'correo' => $persona->correo_inst,
            'programa' => $vinculacion->programa_academico ?? null,
            'semestre' => $vinculacion->semestre ?? null,
            'jornada' => $vinculacion->jornada ?? null,

            // Pendiente de validación con la fuente institucional.
            'matriculaActiva' => null,
            'sisben' => null,

            'estrato' => $ultimaSolicitud?->estrato,
            'situacionPrioritaria' => null,
            'mercadosRecibidos' => $entregasPrevias,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $persona = $request->user()->persona;
        abort_unless($persona, 403);

        $request->merge(['detalle' => trim((string) $request->input('detalle')) ?: null]);

        $datos = $request->validate([
            'situacion' => ['required', Rule::in(Solicitud::SITUACIONES)],
            'estrato' => ['nullable', 'integer', 'between:1,6'],
            'personas_a_cargo' => ['nullable', 'integer', 'between:0,20'],
            'detalle' => ['nullable', 'string', 'max:'.Solicitud::DETALLE_MAX],
            'acepta_datos' => ['accepted'],
        ], [
            'situacion.required' => 'Selecciona tu situación.',
            'situacion.in' => 'Selecciona una situación de la lista.',
            'estrato.between' => 'El estrato debe estar entre 1 y 6.',
            'personas_a_cargo.between' => 'Indica un número de personas a cargo entre 0 y 20.',
            'detalle.max' => 'El detalle no puede superar los '.Solicitud::DETALLE_MAX.' caracteres.',
            'acepta_datos.accepted' => 'Para enviar la solicitud debes autorizar el tratamiento de tus datos.',
        ]);

        if ($abierta = Solicitud::abiertas()->where('id_persona', $persona->id_persona)->first()) {
            return back()->withInput()->with('error', 'Ya tienes una solicitud '.mb_strtolower($abierta->estado)." del {$abierta->fecha_solicitud->format('Y-m-d')}. Cuando se resuelva podrás pedir otra.");
        }

        $ahora = now(Actuacion::ZONA_HORARIA);
        $entregasRecientes = Entrega::whereHas('solicitud', fn ($q) => $q->where('id_persona', $persona->id_persona))
            ->where('fecha_hora', '>=', $ahora->copy()->subDays(Solicitud::DIAS_ENTREGAS_RECIENTES)->toDateTimeString())
            ->count();

        $prioridad = Solicitud::calcularPrioridad($datos['situacion'], isset($datos['estrato']) ? (int) $datos['estrato'] : null, $entregasRecientes);

        // Priorizado por Trabajo Social: queda con la prioridad que indicó, si es mayor.
        $priorizado = Priorizado::de($persona);
        if ($priorizado) {
            $prioridad = Priorizado::mayor($priorizado->prioridad, $prioridad);
        }

        Solicitud::create([
            'id_persona' => $persona->id_persona,
            'situacion' => $datos['situacion'],
            'estrato' => $datos['estrato'] ?? $priorizado?->estrato,
            'personas_a_cargo' => $datos['personas_a_cargo'] ?? null,
            'detalle' => $datos['detalle'] ?? null,
            'acepta_datos' => true,
            'prioridad' => $prioridad,
            'fecha_solicitud' => $ahora->toDateTimeString(),
        ]);

        return redirect()->route('alimentario.crear')->with('status', '¡Solicitud registrada exitosamente! El equipo de Bienestar Universitario la priorizará según el cupo y condiciones socioeconómicas.');
    }
}

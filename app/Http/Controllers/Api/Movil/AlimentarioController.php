<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Models\Alimentos\Entrega;
use App\Models\Alimentos\Solicitud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Apoyo alimentario "Ucevistas de Corazón": ficha, solicitud abierta (con el pase QR si está
 * aprobada) e historial, como Alimentos\SolicitudController::create. La solicitud y la consulta
 * de la ficha van a ese mismo controlador.
 */
class AlimentarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $persona = $usuario->persona;
        $vinculacion = $persona ? DB::table('vinculacion')->where('id_persona', $persona->id_persona)->first() : null;

        $solicitudes = $persona
            ? Solicitud::with(['entrega.lote'])->where('id_persona', $persona->id_persona)->orderByDesc('fecha_solicitud')->get()
            : collect();

        $abierta = $solicitudes->first(fn (Solicitud $s) => in_array($s->estado, [Solicitud::PENDIENTE, Solicitud::APROBADA], true));

        return response()->json([
            'ficha' => [
                'nombre' => $persona?->nombre_completo,
                'codigo' => $usuario->codigo_unico,
                'documento' => $persona?->numero_documento,
                'correo' => $persona?->correo_inst,
                'programa' => $vinculacion->programa_academico ?? null,
                'semestre' => $vinculacion->semestre ?? null,
                'jornada' => $vinculacion->jornada ?? null,
                'matriculaActiva' => null,
                'estrato' => $solicitudes->first()?->estrato,
                'sisben' => null,
                'situacionPrioritaria' => null,
                'mercadosRecibidos' => $persona ? Entrega::whereHas('solicitud', fn ($q) => $q->where('id_persona', $persona->id_persona))->count() : 0,
            ],
            'abierta' => $abierta ? $this->solicitud($abierta) : null,
            'solicitudes' => $solicitudes->map(fn (Solicitud $s) => $this->solicitud($s))->values(),
            'situaciones' => [
                ['valor' => Solicitud::EMERGENCIA, 'icono' => '🆘', 'titulo' => 'Situación de emergencia económica', 'texto' => 'Prioridad inmediata por riesgo alimentario'],
                ['valor' => Solicitud::CABEZA_FAMILIA, 'icono' => '🧑‍🧑‍🧒', 'titulo' => 'Soy cabeza de familia', 'texto' => 'Hogar con dependientes a cargo'],
                ['valor' => Solicitud::ESTRATO_BAJO, 'icono' => '🏠', 'titulo' => 'Estrato 1 o 2 sin apoyo familiar', 'texto' => 'Estudiante foráneo o de escasos recursos'],
                ['valor' => Solicitud::DESEMPLEO, 'icono' => '📉', 'titulo' => 'Desempleo o reducción de ingresos familiares', 'texto' => 'Afectación económica reciente'],
            ],
            'detalle_max' => Solicitud::DETALLE_MAX,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitud(Solicitud $solicitud): array
    {
        $aprobada = $solicitud->estado === Solicitud::APROBADA;

        return [
            'id' => $solicitud->id_solicitud,
            'fecha' => $solicitud->fecha_solicitud?->format('d/m/Y'),
            'situacion' => $solicitud->situacion,
            'estado' => $solicitud->estado,
            'prioridad' => $solicitud->prioridad,
            'motivo_rechazo' => $solicitud->estado === Solicitud::RECHAZADA ? $solicitud->motivo_rechazo : null,
            'entrega' => $solicitud->estado === Solicitud::ENTREGADA && $solicitud->entrega ? [
                'fecha' => $solicitud->entrega->fecha_hora?->format('d/m/Y H:i'),
                'lote' => $solicitud->entrega->lote?->codigo_lote,
                'tipo' => $solicitud->entrega->lote?->tipo,
            ] : null,
            // El QR identifica la solicitud; en la ventanilla igual se verifica el documento.
            'pase' => $aprobada ? [
                'qr' => $solicitud->codigoPase(),
                'orden' => 'ORD-ALIM-'.strtoupper(substr((string) $solicitud->id_solicitud, 0, 8)),
            ] : null,
        ];
    }
}

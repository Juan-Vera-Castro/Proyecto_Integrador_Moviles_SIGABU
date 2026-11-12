<?php

namespace App\Http\Controllers\Api\Movil;

use App\Documentos\PdfConsentimiento;
use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Facultad;
use App\Models\ModeloConsentimiento;
use App\Models\PropuestaCita;
use App\Models\SolicitudAtencion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mis citas" y los datos del formulario de solicitud, como en EstudianteController::misCitas
 * y SolicitudCitaController::create. La solicitud se envía a SolicitudCitaController::store.
 */
class CitasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $casos = Caso::whereIn('id_caso', Formato::idsCasos($request))
            ->with(['solicitud.profesionalPreferido.persona', 'solicitud.facultad', 'profesional.persona', 'citas.profesional.persona', 'propuestasVigentes', 'consentimientos.version.modelo', 'aceptacionesConsentimiento.version', 'aceptacionesConsentimiento.detalleInclusion'])
            ->orderByDesc('fecha_apertura')
            ->get();

        $proximas = $casos
            ->flatMap(fn (Caso $caso) => $caso->citas->each(fn (Actuacion $cita) => $cita->setRelation('caso', $caso)))
            ->filter(fn (Actuacion $cita) => $cita->esProxima())
            ->sortBy('fecha_hora')
            ->values();

        return response()->json([
            'proximas' => $proximas->map(fn (Actuacion $cita) => Formato::cita($cita))->values(),
            'casos' => $casos->map(fn (Caso $caso) => $this->caso($caso))->values(),
        ]);
    }

    public function formulario(Request $request): JsonResponse
    {
        $academicos = $request->user()->persona?->datosAcademicos() ?? ['facultad' => null, 'programa' => null, 'jornada' => null, 'semestre' => null];

        return response()->json([
            'servicios' => collect(SolicitudAtencion::SERVICIOS)->map(function (string $tipo) {
                $version = ModeloConsentimiento::versionVigenteDe($tipo);

                return [
                    'tipo' => $tipo,
                    'titulo' => Formato::SERVICIOS[$tipo]['titulo'] ?? $tipo,
                    'icono' => Formato::SERVICIOS[$tipo]['icono'] ?? '',
                    'consentimiento' => $version ? [
                        'id_version' => $version->id_version,
                        'nombre' => $version->modelo?->nombre,
                        'numero_version' => $version->numero_version,
                        'texto' => $version->texto,
                    ] : null,
                ];
            })->values(),
            'facultades' => Facultad::activas()->get()->map(fn (Facultad $f) => ['id' => $f->id_facultad, 'nombre' => $f->nombre])->values(),
            'facultad_registrada' => $academicos['facultad'] ? ['id' => $academicos['facultad']->id_facultad, 'nombre' => $academicos['facultad']->nombre] : null,
            'jornada_registrada' => $academicos['jornada'],
            'programa' => $academicos['programa'],
            'semestre' => $academicos['semestre'],
            'jornadas' => SolicitudAtencion::JORNADAS,
            'franjas' => SolicitudAtencion::FRANJAS,
        ]);
    }

    /**
     * Una solicitud con su caso, como la tarjeta de "Mis solicitudes" en la web.
     *
     * @return array<string, mixed>
     */
    private function caso(Caso $caso): array
    {
        $solicitud = $caso->solicitud;
        $proxima = $caso->proximaCita();
        $firmado = $caso->consentimientoVigente();
        $legado = $caso->consentimientos->last();
        $ultimaDecision = $caso->aceptacionesConsentimiento->first();
        $profesional = $caso->profesional?->persona?->nombre_completo;

        $profesionalTexto = match (true) {
            $profesional !== null => $profesional,
            $solicitud?->fueRechazada() && $solicitud->profesionalPreferido?->persona !== null => $solicitud->profesionalPreferido->persona->nombre_completo.' no pudo atender tu solicitud',
            $solicitud?->profesionalPreferido?->persona !== null => 'Por asignar · pediste a '.$solicitud->profesionalPreferido->persona->nombre_completo,
            default => 'Por asignar',
        };

        $preferencia = $solicitud?->etiquetaAtencion() ? null : ($solicitud?->fecha_preferida ? collect([
            $solicitud->fecha_preferida->format('Y-m-d'),
            $solicitud->hora_preferida ? Actuacion::etiquetaHora($solicitud->hora_preferida) : null,
            $solicitud->modalidad,
        ])->filter()->implode(' · ') : null);

        $aviso = match (true) {
            $caso->estado_caso === Caso::ESTADO_PENDIENTE => 'Bienestar Universitario te asignará un profesional, que te contactará para acordar la fecha y la hora de tu cita.',
            $caso->estaActivo() && $caso->citas->isEmpty() && $profesional !== null => "Tu caso quedó a cargo de {$profesional}, que te contactará para acordar la fecha y la hora de tu primera cita.",
            default => null,
        };

        $mensajeFirma = null;
        if ($caso->requiereConsentimiento() && $caso->estaActivo() && ! $firmado) {
            $mensajeFirma = match (true) {
                (bool) $ultimaDecision?->esRevocacion() => "El {$ultimaDecision->fecha_hora->format('Y-m-d')} revocaste tu consentimiento informado. Sin él no se puede prestar la atención; puedes firmar uno nuevo si cambias de opinión.",
                $ultimaDecision !== null => "El {$ultimaDecision->fecha_hora->format('Y-m-d')} registraste que no aceptas el consentimiento informado. Sin él no se puede prestar la atención; puedes firmarlo si cambias de opinión.",
                default => 'Antes de tu atención debes leer y firmar el consentimiento informado.',
            };
        }

        $consentimiento = null;
        if ($firmado) {
            $consentimiento = [
                'fecha' => $firmado->fecha_hora->format('Y-m-d'),
                'version' => $firmado->version?->numero_version,
                'texto' => $firmado->texto_presentado,
                'id_aceptacion' => $firmado->id_aceptacion,
                // El PDF del flujo general; el de Inclusión se descarga desde "Mi consentimiento".
                'pdf' => PdfConsentimiento::aplicaA($firmado),
            ];
        } elseif ($legado?->aceptado) {
            $consentimiento = [
                'fecha' => $legado->fecha_hora_aceptacion?->format('Y-m-d'),
                'version' => $legado->version?->numero_version,
                'texto' => $legado->version?->texto ?? "Formato {$legado->version_formato}.",
                'id_aceptacion' => null,
                'pdf' => false,
            ];
        }

        $mostrarPropuestas = $caso->estaActivo() && ! $proxima && $caso->propuestasVigentes->isNotEmpty();

        return [
            'id' => $caso->id_caso,
            'codigo' => $caso->codigo_expediente,
            'servicio' => $solicitud?->tipo_servicio,
            'servicio_titulo' => Formato::SERVICIOS[$solicitud?->tipo_servicio]['titulo'] ?? $solicitud?->tipo_servicio,
            'servicio_icono' => Formato::SERVICIOS[$solicitud?->tipo_servicio]['icono'] ?? '',
            'estado' => $caso->etiquetaParaEstudiante(),
            'activo' => $caso->estaActivo(),
            'fecha_solicitud' => ($solicitud?->fecha_solicitud ?? $caso->fecha_apertura)?->format('Y-m-d'),
            'atencion' => $solicitud?->etiquetaAtencion(),
            'preferencia' => $preferencia,
            'profesional' => $profesionalTexto,
            'dia_hora_cita' => ($caso->estaActivo() || $caso->estado_caso === Caso::ESTADO_PENDIENTE)
                ? ($proxima ? $proxima->fecha_hora->format('Y-m-d').' · '.$proxima->fecha_hora->format('g:i a') : ($caso->propuestasVigentes->isNotEmpty() ? 'Pendiente: responde la solicitud de cita' : 'Pendiente'))
                : null,
            'motivo' => $solicitud?->descripcion,
            'aviso' => $aviso,
            'rechazo' => $solicitud?->fueRechazada() ? [
                'fecha' => $solicitud->fecha_respuesta?->format('Y-m-d'),
                'motivo' => $solicitud->motivo_rechazo,
            ] : null,
            'propuestas' => $mostrarPropuestas ? $caso->propuestasVigentes->map(fn (PropuestaCita $p) => [
                'id' => $p->id_propuesta,
                'fecha_hora' => Formato::fechaHora($p->fecha_hora),
                'etiqueta' => $p->etiqueta(),
            ])->values() : [],
            'historial' => $caso->citas->reject(fn (Actuacion $cita) => $cita->esProxima())->sortByDesc('fecha_hora')
                ->map(fn (Actuacion $cita) => Formato::cita($cita))->values(),
            'firma_pendiente' => $mensajeFirma,
            'es_inclusion' => $caso->esDeInclusion(),
            'tiene_consentimiento_inclusion' => $caso->esDeInclusion() && $caso->aceptacionesConsentimiento->isNotEmpty(),
            'consentimiento' => $consentimiento,
            'decision_consentimiento' => $ultimaDecision ? [
                'etiqueta' => $ultimaDecision->etiquetaDecision(),
                'aceptado' => $ultimaDecision->decision === ConsentimientoAceptacion::ACEPTADO,
            ] : null,
        ];
    }
}

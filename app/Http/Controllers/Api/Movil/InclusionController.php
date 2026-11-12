<?php

namespace App\Http\Controllers\Api\Movil;

use App\Documentos\ConsentimientoInclusion;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Inclusion\ConsentimientoController as ConsentimientoInclusionWeb;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Facultad;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\ConsentimientoDetalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Consentimiento del Proceso de Inclusión en la app: datos del formulario y consulta, como
 * Inclusion\ConsentimientoController::formulario y ::ver. El envío, la revocación, el borrador
 * y el PDF van a los controladores de la web.
 */
class InclusionController extends Controller
{
    private const NOTA_DECISIONES = '[Tus decisiones se marcan en la sección «Decisiones», después de este texto.]';

    public function formulario(Request $request, Caso $caso): JsonResponse
    {
        Gate::authorize('inclusion.diligenciarConsentimiento', $caso);

        if (! ConsentimientoInclusion::aplicaA($caso)) {
            return response()->json(['formato' => 'general']);
        }

        if (! $caso->estaActivo()) {
            return response()->json(['message' => "El caso {$caso->codigo_expediente} ya no está activo: no se puede diligenciar el consentimiento."], 409);
        }

        if ($caso->consentimientoVigente()) {
            return response()->json(['formato' => 'inclusion', 'vigente' => true, 'mensaje' => 'Ya tienes un consentimiento vigente en este caso.']);
        }

        $caso->load(['solicitud.persona', 'profesional.persona']);
        $persona = $caso->solicitud->persona;
        $version = ConsentimientoInclusion::versionDe($caso);
        $borrador = Borrador::de($caso, $request->user(), Borrador::CONSENTIMIENTO);

        return response()->json([
            'formato' => 'inclusion',
            'vigente' => false,
            'caso' => ['id' => $caso->id_caso, 'codigo' => $caso->codigo_expediente, 'servicio' => $caso->solicitud->tipo_servicio],
            'faltan_datos' => ! $persona->datosRemisionCompletos(),
            'profesional' => $caso->profesional?->persona?->nombre_completo ?? 'Bienestar Universitario',
            'menor' => $persona->esMenorEn(now(Actuacion::ZONA_HORARIA)) === true,
            'version' => [
                'id' => $version->id_version,
                'numero' => $version->numero_version,
                'nombre' => $version->modelo?->nombre,
                'texto' => $version->texto,
            ],
            'texto' => ConsentimientoController::plantillaTexto($caso, ConsentimientoInclusion::CAMPOS_DE_FIRMA, ['decisiones' => self::NOTA_DECISIONES]),
            'iniciales' => array_merge(ConsentimientoInclusionWeb::datosIniciales($persona), $borrador?->datos ?? []),
            'borrador_guardado' => $borrador?->fecha_actualizacion?->format('H:i'),
            'opciones' => [
                'vinculaciones' => array_keys(ConsentimientoInclusion::VINCULACIONES),
                'vinculaciones_estudiante' => collect(ConsentimientoInclusion::VINCULACIONES)->filter(fn ($grupo) => $grupo === ConsentimientoAceptacion::VINCULACION_ESTUDIANTE)->keys()->values(),
                'facultades' => Facultad::activas()->orderBy('nombre')->pluck('nombre'),
                'semestre_max' => ConsentimientoAceptacion::SEMESTRE_MAX,
                'tipos_documento' => ConsentimientoController::tiposDocumento(),
                'decisiones' => ConsentimientoInclusion::DECISIONES,
                'decision_a_confirmar' => 'decision_acompanamiento',
            ],
            'firma' => ConsentimientoController::reglasFirma(),
            'almacenamiento_listo' => ConsentimientoAceptacion::almacenamientoListo(),
        ]);
    }

    public function ver(Request $request, Caso $caso): JsonResponse
    {
        Gate::authorize('inclusion.verConsentimiento', $caso);

        $caso->load([
            'solicitud.persona',
            'aceptacionesConsentimiento.version.modelo',
            'aceptacionesConsentimiento.detalleInclusion',
            'aceptacionesConsentimiento.firmaProfesional',
        ]);

        $esDueno = Gate::allows('inclusion.diligenciarConsentimiento', $caso);
        $vigente = $caso->consentimientoVigente();
        $aceptacion = $caso->aceptacionesConsentimiento->first(fn (ConsentimientoAceptacion $fila) => $fila->esAceptado());
        $ultima = $caso->aceptacionesConsentimiento->first();
        $detalle = $aceptacion?->detalleInclusion;

        return response()->json([
            'caso' => ['id' => $caso->id_caso, 'codigo' => $caso->codigo_expediente],
            'estado' => $caso->estadoConsentimiento(),
            'revocado_el' => $ultima?->esRevocacion() ? $ultima->fecha_hora->format('d/m/Y H:i') : null,
            'aceptacion' => $aceptacion ? [
                'nombre' => $aceptacion->version?->modelo?->nombre,
                'version' => $aceptacion->version?->numero_version,
                'fecha' => $aceptacion->fecha_hora->format('d/m/Y H:i'),
                'texto' => $aceptacion->texto_presentado,
                'en_papel' => $aceptacion->esEnPapel(),
                'leyenda_papel' => $aceptacion->esEnPapel() ? ConsentimientoDetalle::LEYENDA_PAPEL : null,
                'fecha_firma_papel' => $detalle?->fecha_firma_papel?->format('d/m/Y'),
                'motivo_papel' => $detalle?->motivo_papel,
                'decisiones' => $detalle ? collect($detalle->decisiones())->map(fn (bool $marcada, string $texto) => ['texto' => $texto, 'marcada' => $marcada])->values() : [],
            ] : null,
            'historial' => $caso->aceptacionesConsentimiento->map(fn (ConsentimientoAceptacion $fila) => [
                'etiqueta' => $fila->etiquetaDecision().($fila->esEnPapel() ? ' (en papel)' : ''),
                'fecha' => $fila->fecha_hora->format('d/m/Y H:i'),
            ])->values(),
            'puede_revocar' => $esDueno && $vigente !== null,
            'puede_firmar' => $esDueno && $vigente === null && $caso->estaActivo(),
        ]);
    }
}

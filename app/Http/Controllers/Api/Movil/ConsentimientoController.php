<?php

namespace App\Http\Controllers\Api\Movil;

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\TextoConsentimiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarConsentimientoRequest;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Datos de la pantalla de firma del consentimiento informado (ConsentimientoFirmaController::create).
 * La decisión se envía a ConsentimientoFirmaController::store, que ya responde en JSON.
 */
class ConsentimientoController extends Controller
{
    public function formulario(Request $request, Caso $caso): JsonResponse
    {
        abort_unless($caso->esDelEstudiante($request->user()), 403);
        abort_unless($caso->requiereConsentimiento(), 404);

        // El de Inclusión tiene su propio formato: la app abre esa pantalla.
        if (ConsentimientoInclusion::aplicaA($caso)) {
            return response()->json(['formato' => 'inclusion']);
        }

        if (! $caso->estaActivo()) {
            return response()->json(['message' => "El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\": solo se registra el consentimiento en casos activos."], 409);
        }

        $caso->load(['solicitud.persona', 'profesional.persona']);
        $persona = $caso->solicitud->persona;
        $ahora = now(Actuacion::ZONA_HORARIA);
        $faltanDatos = ! $persona->datosRemisionCompletos();
        $menor = $persona->esMenorEn($ahora) === true;
        $version = $faltanDatos ? null : ModeloConsentimiento::versionVigenteDe(
            $caso->solicitud->tipo_servicio,
            $menor ? ModeloConsentimiento::POBLACION_MENORES : ModeloConsentimiento::POBLACION_GENERAL,
        );
        $vinculacion = DB::table('vinculacion')->where('id_persona', $persona->id_persona)->first();

        return response()->json([
            'formato' => 'general',
            'caso' => ['id' => $caso->id_caso, 'codigo' => $caso->codigo_expediente, 'servicio' => $caso->solicitud->tipo_servicio],
            'faltan_datos' => $faltanDatos,
            'profesional' => $caso->profesional?->persona?->nombre_completo ?? 'Bienestar Universitario',
            'menor' => $menor,
            'edad' => $persona->edadEn($ahora),
            'persona' => [
                'nombre' => $persona->nombre_completo,
                'tipo_documento' => $persona->tipo_documento,
                'numero_documento' => $persona->numero_documento,
            ],
            'vinculacion' => $vinculacion ? [
                'tipo' => $vinculacion->tipo_vinculacion ?? null,
                'facultad' => $vinculacion->facultad ?? null,
                'programa' => $vinculacion->programa_academico ?? null,
                'semestre' => $vinculacion->semestre ?? null,
            ] : null,
            'vinculacion_sugerida' => match ($vinculacion->tipo_vinculacion ?? null) {
                'Estudiante' => ConsentimientoAceptacion::VINCULACION_ESTUDIANTE,
                'Administrativo' => 'Funcionario',
                default => '',
            },
            'version' => $version ? [
                'id' => $version->id_version,
                'numero' => $version->numero_version,
                'nombre' => $version->modelo?->nombre,
                'texto' => $version->texto,
            ] : null,
            'texto' => self::plantillaTexto($caso),
            'opciones' => [
                'vinculaciones' => ConsentimientoAceptacion::VINCULACIONES,
                'vinculacion_estudiante' => ConsentimientoAceptacion::VINCULACION_ESTUDIANTE,
                'semestre_max' => ConsentimientoAceptacion::SEMESTRE_MAX,
                'parentescos' => ConsentimientoAceptacion::PARENTESCOS_URGENCIA,
                'max_contactos' => ConsentimientoAceptacion::MAX_CONTACTOS_URGENCIA,
                'tipos_documento' => self::tiposDocumento(),
            ],
            'firma' => self::reglasFirma(),
            'almacenamiento_listo' => ConsentimientoAceptacion::almacenamientoListo(),
        ]);
    }

    /**
     * Lo que la app necesita para mostrar el texto con los datos que se van llenando:
     * marcadores conocidos, datos que ya sabe el sistema y qué campo llena cada marcador.
     *
     * @param  array<string, string>|null  $camposDeFirma
     * @param  array<string, string|null>  $extra
     * @return array<string, mixed>
     */
    public static function plantillaTexto(Caso $caso, ?array $camposDeFirma = null, array $extra = []): array
    {
        return [
            'marcadores' => array_keys(TextoConsentimiento::MARCADORES),
            'campos_de_firma' => $camposDeFirma ?? TextoConsentimiento::CAMPOS_DE_FIRMA,
            'datos_base' => TextoConsentimiento::datosBase($caso, $caso->profesional, now(Actuacion::ZONA_HORARIA)) + $extra,
            'vacio' => '____',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposDocumento(): array
    {
        return collect(ConsentimientoAceptacion::TIPOS_DOCUMENTO_ACUDIENTE)->mapWithKeys(fn (string $tipo) => [$tipo => [
            'CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'TI' => 'Tarjeta de identidad', 'PA' => 'Pasaporte',
        ][$tipo] ?? $tipo])->all();
    }

    /**
     * Límites que el servidor exige a la imagen de la firma (RegistrarConsentimientoRequest::leerFirma).
     *
     * @return array<string, int|string>
     */
    public static function reglasFirma(): array
    {
        return [
            'prefijo' => RegistrarConsentimientoRequest::FIRMA_PREFIJO,
            'max_bytes' => RegistrarConsentimientoRequest::FIRMA_MAX_BYTES,
            'ancho_min' => 200,
            'alto_min' => 80,
            'ancho_max' => 2000,
            'alto_max' => 1000,
        ];
    }
}

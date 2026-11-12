<?php

namespace App\Documentos;

use App\Http\Controllers\Inclusion\ConsentimientoController;
use App\Models\Actuacion;
use App\Models\AjusteRazonable;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\FormatoInstitucional;
use App\Models\Inclusion\Recurso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Arma los datos de los PDF de Inclusión: encabezado de Calidad, logo y firmas como data URI.
 * Las firmas se leen del bucket privado y se incrustan; nunca se enlazan.
 */
class PdfInclusion
{
    public const FORMATO_CONSENTIMIENTO = 'CONSENTIMIENTO_INCLUSION';

    public const FORMATO_BITACORA = 'BITACORA_INCLUSION';

    public const FORMATO_PLANTILLA = 'RECURSO_INCLUSION';

    /**
     * Datos del consentimiento registrado: el texto aceptado, las decisiones y las firmas, o
     * la leyenda del papel. Si después se revocó, el PDF lo dice.
     *
     * @return array<string, mixed>
     */
    public static function consentimiento(Caso $caso, ConsentimientoAceptacion $aceptacion): array
    {
        $aceptacion->loadMissing(['detalleInclusion', 'version.modelo', 'profesional.persona', 'firmaProfesional.profesional.persona']);
        $caso->loadMissing(['solicitud.persona', 'aceptacionesConsentimiento.detalleInclusion']);

        $ultima = $caso->aceptacionesConsentimiento->first();
        $contrafirma = $aceptacion->firmaProfesional;

        return self::base(self::FORMATO_CONSENTIMIENTO) + [
            'caso' => $caso,
            'aceptacion' => $aceptacion,
            'detalle' => $aceptacion->detalleInclusion,
            'dependencia' => self::dependencia($aceptacion->dependencia_facultad, ConsentimientoInclusion::esEstudiante($aceptacion->detalleInclusion?->vinculacion_detalle) || $aceptacion->vinculacion === ConsentimientoAceptacion::VINCULACION_ESTUDIANTE),
            'parrafos' => self::parrafos((string) $aceptacion->texto_presentado),
            'firmaPersona' => $aceptacion->esEnPapel() ? null : self::firma($aceptacion->firma_ruta, $aceptacion->firma_sha256),
            'firmaProfesional' => $contrafirma ? self::firma($contrafirma->firma_ruta, $contrafirma->firma_sha256) : null,
            'contrafirma' => $contrafirma,
            'revocacion' => $ultima && $ultima->esRevocacion() && $ultima->fecha_hora->gte($aceptacion->fecha_hora) ? $ultima : null,
        ];
    }

    /**
     * Formato para imprimir y firmar a mano, con el texto vigente y los datos de la persona.
     *
     * @return array<string, mixed>
     */
    public static function imprimible(Caso $caso): array
    {
        $caso->loadMissing(['solicitud.persona', 'profesional.persona']);
        $persona = $caso->solicitud->persona;
        $version = ConsentimientoInclusion::versionDe($caso);
        $iniciales = ConsentimientoController::datosIniciales($persona);
        $menor = $persona->esMenorEn(now(Actuacion::ZONA_HORARIA)) === true;

        $texto = TextoConsentimiento::renderizar((string) $version?->texto, [
            'nombre_estudiante' => $iniciales['nombre_persona'],
            'documento_estudiante' => $iniciales['documento_persona'],
            'decisiones' => ConsentimientoInclusion::textoDecisiones([]),
        ]);

        return self::base(self::FORMATO_CONSENTIMIENTO) + [
            'caso' => $caso,
            'iniciales' => $iniciales,
            'menor' => $menor,
            'version' => $version,
            'parrafos' => self::parrafos($texto),
        ];
    }

    /**
     * Secciones I a IV de la bitácora, con la última constancia y su firma.
     *
     * @return array<string, mixed>
     */
    public static function bitacora(Caso $caso): array
    {
        $caso->loadMissing([
            'solicitud.persona',
            'bitacora.profesional.persona',
            'bitacora.soportes',
            'bitacora.entradas.profesional.persona',
            'bitacora.entradas.anotaciones.profesional.persona',
            'bitacora.entradas.anotaciones.evidencias',
            'bitacora.entradas.evidencias',
            'bitacora.constancias',
            'bitacora.reaperturas',
        ]);

        $constancia = $caso->bitacora?->constancias->first();
        $reabierta = $constancia && $caso->bitacora->reaperturas->contains('id_constancia', $constancia->id_constancia);

        return self::base(self::FORMATO_BITACORA) + [
            'caso' => $caso,
            'bitacora' => $caso->bitacora,
            'constancia' => $constancia,
            'reabierta' => $reabierta,
            'firmaConstancia' => $constancia ? self::firma($constancia->firma_ruta, $constancia->firma_sha256) : null,
        ];
    }

    /**
     * Plantilla de ajustes. La versión completa lleva el recurso y es solo para el expediente;
     * la versión para compartir no lleva el recurso ni nombra la condición (RN-38).
     *
     * @return array<string, mixed>
     */
    public static function plantilla(Caso $caso, bool $compartir): array
    {
        $caso->loadMissing(['solicitud.persona', 'solicitud.facultad', 'profesional.persona', 'ajustes.evidencias', 'ajustes.recurso']);

        $ajustes = $caso->ajustes->sortBy(fn (AjusteRazonable $a) => [$a->fecha_inicio, $a->fecha_registro])->values();
        $recursos = $compartir ? collect() : $ajustes->pluck('recurso')->filter()->unique('id_recurso')->values();

        if (! $compartir && $recursos->isEmpty() && ($activo = Recurso::activos()->orderBy('titulo')->first())) {
            $recursos = collect([$activo]);
        }

        return self::base(self::FORMATO_PLANTILLA) + [
            'caso' => $caso,
            'ajustes' => $ajustes,
            'recursos' => $recursos,
            'compartir' => $compartir,
            'programa' => $caso->solicitud?->persona?->datosAcademicos()['programa'] ?? null,
        ];
    }

    /**
     * Si al compartir hay que advertir que la persona no autorizó compartir (RN-39). Solo
     * aplica a los consentimientos nuevos, que tienen la decisión 4. No bloquea la descarga.
     */
    public static function avisoCompartir(Caso $caso): ?string
    {
        $vigente = $caso->consentimientoVigente();
        $detalle = $vigente?->loadMissing('detalleInclusion')->detalleInclusion;

        return match (true) {
            $vigente === null => 'El caso no tiene un consentimiento vigente que autorice compartir información con docentes o dependencias.',
            $detalle !== null && ! $detalle->decision_compartir => 'La persona no autorizó compartir información con docentes, programa y dependencias (decisión 4 del consentimiento).',
            default => null,
        };
    }

    /**
     * Genera el PDF y lo entrega para descargar, sin dejarlo en caché.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function descargar(string $vista, array $datos, string $archivo, string $orientacion = 'portrait'): Response
    {
        $pdf = Pdf::loadView($vista, $datos)->setPaper('letter', $orientacion);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$archivo.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Nombre de archivo con el expediente y la fecha de descarga.
     */
    public static function nombreArchivo(string $prefijo, Caso $caso): string
    {
        return $prefijo.'-'.$caso->codigo_expediente.'-'.now(Actuacion::ZONA_HORARIA)->format('d-m-y').'.pdf';
    }

    /**
     * Imagen de una firma como data URI, con su verificación de integridad. Si no se puede
     * leer, el PDF lo dice en vez de fallar.
     *
     * @return array{src: string|null, integra: bool}
     */
    public static function firma(?string $ruta, ?string $sha256): array
    {
        if (! $ruta) {
            return ['src' => null, 'integra' => false];
        }

        try {
            $png = (string) Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->get($ruta);
        } catch (Throwable $e) {
            report($e);
            $png = '';
        }

        if ($png === '') {
            return ['src' => null, 'integra' => false];
        }

        return [
            'src' => 'data:image/png;base64,'.base64_encode($png),
            'integra' => hash_equals((string) $sha256, hash('sha256', $png)),
        ];
    }

    /**
     * Separa el texto en párrafos y marca las líneas de casilla del formato.
     *
     * @return list<array{casilla: bool|null, texto: string}>
     */
    public static function parrafos(string $texto): array
    {
        return collect(preg_split('/\R/u', $texto))
            ->map(fn (string $linea) => trim($linea))
            ->filter(fn (string $linea) => $linea !== '')
            ->map(function (string $linea) {
                foreach (['☑' => true, '☐' => false] as $simbolo => $marcada) {
                    if (str_starts_with($linea, $simbolo)) {
                        return ['casilla' => $marcada, 'texto' => trim(mb_substr($linea, 1))];
                    }
                }

                return ['casilla' => null, 'texto' => $linea];
            })
            ->values()
            ->all();
    }

    /**
     * La facultad y la dependencia se guardan juntas ("Facultad · Dependencia" o "N/A"). La
     * facultad solo se pide a estudiantes, así que un valor solo es facultad si es estudiante.
     *
     * @return array{facultad: string|null, dependencia: string|null}
     */
    private static function dependencia(?string $valor, bool $esEstudiante): array
    {
        if (! $valor || strcasecmp($valor, 'N/A') === 0) {
            return ['facultad' => null, 'dependencia' => null];
        }

        $partes = explode(' · ', $valor, 2);

        return count($partes) === 2
            ? ['facultad' => $partes[0], 'dependencia' => $partes[1]]
            : ($esEstudiante ? ['facultad' => $partes[0], 'dependencia' => null] : ['facultad' => null, 'dependencia' => $partes[0]]);
    }

    /**
     * Encabezado de Calidad, logo, ciudad y fecha de generación. Si la fila del formato no
     * existe todavía, el PDF sale igual, con el código por asignar.
     *
     * @return array<string, mixed>
     */
    private static function base(string $clave): array
    {
        $formato = FormatoInstitucional::where('clave', $clave)->first();

        return [
            'formato' => [
                'proceso' => $formato?->proceso ?? 'PROCESO DE INCLUSIÓN UNIVERSITARIA',
                'nombre' => $formato?->nombre ?? '',
                'codigo' => $formato?->codigo ?? 'POR-ASIGNAR',
                'version' => $formato?->version ?? '1',
                'fecha' => $formato?->fecha_formato ? FormatoInstitucional::fechaComoFormato($formato->fecha_formato) : '',
            ],
            'logo' => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-uceva.jpg'))),
            'ciudad' => config('sigabu.ciudad'),
            'generado' => now(Actuacion::ZONA_HORARIA),
        ];
    }
}

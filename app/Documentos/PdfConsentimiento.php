<?php

namespace App\Documentos;

use App\Models\Actuacion;
use App\Models\ConsentimientoAceptacion;
use App\Models\FormatoInstitucional;

/**
 * Datos del PDF del consentimiento firmado de Salud Mental (formatos GBU-GPGR-F-032 y F-019):
 * el texto exacto que se aceptó, los datos del otorgante, las firmas incrustadas y la
 * constancia técnica de la firma, con la verificación de las huellas al momento de descargar.
 * Los de Inclusión tienen su propio PDF (PdfInclusion::consentimiento).
 */
class PdfConsentimiento
{
    public const FORMATO_GENERAL = 'CONSENTIMIENTO_ORIENTACION';

    public const FORMATO_MENORES = 'CONSENTIMIENTO_MENORES';

    /**
     * Si esta aceptación se descarga con este PDF: las firmadas en el expediente o desde la
     * cuenta del estudiante. Las de Inclusión (en línea o en papel) usan su propio formato.
     */
    public static function aplicaA(ConsentimientoAceptacion $aceptacion): bool
    {
        return in_array($aceptacion->medio, [ConsentimientoAceptacion::MEDIO_FIRMA_EXPEDIENTE, ConsentimientoAceptacion::MEDIO_FIRMA_ENLACE], true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function datos(ConsentimientoAceptacion $aceptacion): array
    {
        $aceptacion->loadMissing(['caso.solicitud.persona', 'caso.profesional.persona', 'version.modelo', 'profesional.persona', 'firmaProfesional.profesional.persona']);

        $caso = $aceptacion->caso;
        $persona = $caso->solicitud?->persona;
        $contrafirma = $aceptacion->firmaProfesional;
        $menores = (bool) $aceptacion->version?->modelo?->esDeMenores();
        $texto = (string) $aceptacion->texto_presentado;

        return self::base($menores ? self::FORMATO_MENORES : self::FORMATO_GENERAL) + [
            'caso' => $caso,
            'aceptacion' => $aceptacion,
            'persona' => $persona,
            'edad' => $persona?->edadEn($aceptacion->fecha_hora),
            'profesional' => $contrafirma?->profesional?->persona ?? $aceptacion->profesional?->persona,
            'contrafirma' => $contrafirma,
            'parrafos' => PdfInclusion::parrafos($texto),
            'textoIntegro' => hash_equals((string) $aceptacion->texto_sha256, hash('sha256', $texto)),
            'firmaOtorgante' => $aceptacion->firma_ruta ? PdfInclusion::firma($aceptacion->firma_ruta, $aceptacion->firma_sha256) : null,
            // Menor de edad: la firma del propio estudiante, además de la del acudiente.
            'firmaEstudiante' => $aceptacion->firma_estudiante_ruta ? PdfInclusion::firma($aceptacion->firma_estudiante_ruta, $aceptacion->firma_estudiante_sha256) : null,
            'firmaProfesional' => $contrafirma ? PdfInclusion::firma($contrafirma->firma_ruta, $contrafirma->firma_sha256) : null,
            'medio' => $aceptacion->medio === ConsentimientoAceptacion::MEDIO_FIRMA_ENLACE
                ? 'Firma táctil del estudiante desde su cuenta en SIGABU'
                : 'Firma táctil en el expediente, en presencia del profesional',
        ];
    }

    /**
     * Nombre del archivo: expediente y fecha de la firma, para distinguir varias aceptaciones.
     */
    public static function nombreArchivo(ConsentimientoAceptacion $aceptacion): string
    {
        return 'Consentimiento-'.$aceptacion->caso->codigo_expediente.'-'.$aceptacion->fecha_hora->format('d-m-y-Hi').'.pdf';
    }

    /**
     * Encabezado de Calidad del formato, logo, ciudad y fecha de generación. Si la fila del
     * formato no existe todavía, el PDF sale igual con los datos del formato en papel.
     *
     * @return array<string, mixed>
     */
    private static function base(string $clave): array
    {
        $formato = FormatoInstitucional::where('clave', $clave)->first();
        $menores = $clave === self::FORMATO_MENORES;

        return [
            'formato' => [
                'proceso' => $formato?->proceso ?? 'GESTIÓN APOYO AL ESTUDIANTE',
                'nombre' => $formato?->nombre ?? ($menores ? 'CONSENTIMIENTO INFORMADO MENORES DE EDAD' : 'CONSENTIMIENTO INFORMADO ORIENTACIÓN PSICOLÓGICA'),
                'codigo' => $formato?->codigo ?? ($menores ? 'GBU-GPGR-F-019' : 'GBU-GPGR-F-032'),
                'version' => $formato?->version ?? '1',
                'fecha' => $formato?->fecha_formato ? FormatoInstitucional::fechaComoFormato($formato->fecha_formato) : '',
            ],
            'logo' => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-uceva.jpg'))),
            'ciudad' => config('sigabu.ciudad'),
            'generado' => now(Actuacion::ZONA_HORARIA),
        ];
    }
}

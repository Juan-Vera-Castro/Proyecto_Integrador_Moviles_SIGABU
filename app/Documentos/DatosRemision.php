<?php

namespace App\Documentos;

use App\Models\FormatoInstitucional;
use App\Models\RemisionExterna;
use Carbon\CarbonInterface;

/**
 * Datos del formato GBU-GPGR-F-016 "Remisión psicológica" (RQ-3) ya formateados, a partir
 * de la copia inmutable de la remisión externa (RN-09). El PDF y el Word usan esta misma
 * fuente, así que muestran exactamente lo mismo.
 */
class DatosRemision
{
    public const MARCA = 'X';

    public const TIPOS_DOCUMENTO = ['TI', 'CC', 'CE'];

    public const SEXOS = ['F', 'M'];

    public const CATEGORIAS = [RemisionExterna::URGENTE, RemisionExterna::PRIORITARIO];

    /**
     * @param  array{0: string, 1: string, 2: string}  $fechaAtencion  día, mes y año (dd, mm, aa)
     * @param  array<string, string>  $tipoDocumento  'X' en la casilla que corresponde
     * @param  array<string, string>  $sexo
     * @param  array{0: string, 1: string, 2: string}  $fechaNacimiento
     * @param  array<string, string>  $categoria
     * @param  array<string, string>  $acompanante  vacío si es PRIORITARIO
     */
    public function __construct(
        public readonly string $proceso,
        public readonly string $nombreFormato,
        public readonly string $codigo,
        public readonly string $version,
        public readonly string $fecha,
        public readonly array $fechaAtencion,
        public readonly string $hora,
        public readonly string $nombre,
        public readonly array $tipoDocumento,
        public readonly string $numeroDocumento,
        public readonly array $sexo,
        public readonly string $edad,
        public readonly array $fechaNacimiento,
        public readonly array $categoria,
        public readonly string $remitidoA,
        public readonly string $justificacion,
        public readonly array $acompanante,
        public readonly string $psicologo,
        public readonly string $nombreArchivo,
    ) {}

    public static function desde(RemisionExterna $detalle): self
    {
        $detalle->loadMissing('remision.caso');
        $remision = $detalle->remision;
        $formato = FormatoInstitucional::where('clave', config('sigabu.formato_remision'))->first();
        $urgente = $detalle->esUrgente();
        $atencion = $detalle->fecha_hora_atencion;

        return new self(
            proceso: $formato?->proceso ?? 'GESTIÓN APOYO AL ESTUDIANTE',
            nombreFormato: $formato?->nombre ?? 'REMISIÓN PSICOLÓGICA',
            codigo: 'CÓDIGO: '.$detalle->formato_codigo,
            version: 'VERSIÓN: '.$detalle->formato_version,
            fecha: 'FECHA: '.FormatoInstitucional::fechaComoFormato($detalle->formato_fecha),
            fechaAtencion: self::casillasFecha($atencion),
            hora: $atencion->format('g:i a'),
            nombre: $detalle->estudiante_nombre,
            tipoDocumento: self::marcas(self::TIPOS_DOCUMENTO, $detalle->estudiante_tipo_documento),
            numeroDocumento: $detalle->estudiante_numero_documento,
            sexo: self::marcas(self::SEXOS, $detalle->estudiante_sexo),
            edad: (string) $detalle->estudiante_edad,
            fechaNacimiento: self::casillasFecha($detalle->estudiante_fecha_nacimiento),
            categoria: self::marcas(self::CATEGORIAS, $detalle->categoria),
            remitidoA: (string) $remision?->entidad_destino,
            justificacion: (string) $remision?->descripcion,
            acompanante: [
                'nombre' => $urgente ? (string) $detalle->acompanante_nombre : '',
                'parentesco' => $urgente ? (string) $detalle->acompanante_parentesco : '',
                'identificacion' => $urgente ? self::tipoDocumentoLegible($detalle->acompanante_tipo_documento).' '.$detalle->acompanante_numero_documento : '',
                'telefono' => $urgente ? (string) $detalle->acompanante_telefono : '',
            ],
            psicologo: $detalle->profesional_nombre,
            nombreArchivo: 'Remision-'.($remision?->caso?->codigo_expediente ?? 'caso').'-'.$atencion->format('d-m-y'),
        );
    }

    /**
     * Fecha en tres casillas, como en el formato (dd-mm-aa).
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function casillasFecha(CarbonInterface $fecha): array
    {
        return [$fecha->format('d'), $fecha->format('m'), $fecha->format('y')];
    }

    /**
     * "X" en la casilla de $valor y vacío en las demás.
     *
     * @param  list<string>  $opciones
     * @return array<string, string>
     */
    public static function marcas(array $opciones, ?string $valor): array
    {
        return collect($opciones)->mapWithKeys(fn (string $opcion) => [$opcion => $opcion === trim((string) $valor) ? self::MARCA : ''])->all();
    }

    private static function tipoDocumentoLegible(?string $tipo): string
    {
        return $tipo === 'PA' ? 'Pasaporte' : (string) $tipo;
    }
}

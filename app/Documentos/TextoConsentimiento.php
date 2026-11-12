<?php

namespace App\Documentos;

use App\Models\Caso;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Marcadores del texto de un consentimiento (D-24): el texto publicado lleva, p. ej.,
 * "{nombre_estudiante}" y la pantalla de firma lo muestra con el dato real.
 */
class TextoConsentimiento
{
    /**
     * Se muestra en lugar de un dato que todavía no se ha escrito.
     */
    public const VACIO = '____';

    /**
     * Marcadores permitidos y qué representan (design.md, 4.3).
     */
    public const MARCADORES = [
        'ciudad' => 'Ciudad de la institución',
        'fecha' => 'Fecha de la firma',
        'nombre_estudiante' => 'Nombre del estudiante',
        'tipo_documento_estudiante' => 'Tipo de documento del estudiante',
        'documento_estudiante' => 'Número de documento del estudiante',
        'lugar_expedicion' => 'Lugar de expedición del documento del estudiante',
        'eps_ips' => 'EPS o IPS del estudiante',
        'acudiente_notificacion' => 'Acudiente o representante legal a notificar en una urgencia',
        'profesional' => 'Nombre del profesional que atiende',
        'documento_profesional' => 'Documento del profesional que atiende',
        'nombre_acudiente' => 'Nombre del acudiente (menores)',
        'tipo_documento_acudiente' => 'Tipo de documento del acudiente (menores)',
        'documento_acudiente' => 'Número de documento del acudiente (menores)',
        'lugar_expedicion_acudiente' => 'Lugar de expedición del documento del acudiente (menores)',
        'parentesco_acudiente' => 'Parentesco del acudiente con el menor',
        'decisiones' => 'Casillas de decisión (formato de Inclusión)',
    ];

    /**
     * Marcadores que se llenan con lo que se escribe en la pantalla de firma (D-25).
     */
    public const CAMPOS_DE_FIRMA = [
        'lugar_expedicion' => 'lugar_expedicion',
        'eps_ips' => 'eps_ips',
        'acudiente_notificacion' => 'contacto_urgencia',
        'nombre_acudiente' => 'acudiente_nombre',
        'tipo_documento_acudiente' => 'acudiente_tipo_documento',
        'documento_acudiente' => 'acudiente_numero_documento',
        'lugar_expedicion_acudiente' => 'acudiente_lugar_expedicion',
        'parentesco_acudiente' => 'acudiente_parentesco',
    ];

    private const PATRON = '/\{([a-z_]+)\}/';

    /**
     * Datos de los marcadores que ya conoce el sistema: institución, fecha, estudiante y profesional.
     *
     * @return array<string, string|null>
     */
    public static function datosBase(Caso $caso, User $profesional, CarbonInterface $fecha): array
    {
        $estudiante = $caso->solicitud?->persona;

        return [
            'ciudad' => config('sigabu.ciudad'),
            'fecha' => $fecha->format('d/m/Y'),
            'nombre_estudiante' => $estudiante?->nombre_completo,
            'tipo_documento_estudiante' => $estudiante?->tipo_documento,
            'documento_estudiante' => $estudiante?->numero_documento,
            'profesional' => $profesional->persona?->nombre_completo,
            'documento_profesional' => $profesional->persona?->numero_documento,
        ];
    }

    /**
     * Datos de los marcadores a partir de los campos del formulario de firma.
     *
     * @param  array<string, mixed>  $campos
     * @return array<string, string|null>
     */
    public static function datosDeFirma(array $campos): array
    {
        return array_map(fn (string $campo) => $campos[$campo] ?? null, self::CAMPOS_DE_FIRMA);
    }

    /**
     * Marcadores del texto que no están en la lista, sin repetir y en orden de aparición.
     *
     * @return list<string>
     */
    public static function marcadoresDesconocidos(string $texto): array
    {
        preg_match_all(self::PATRON, $texto, $coincidencias);

        return array_values(array_unique(array_diff($coincidencias[1], array_keys(self::MARCADORES))));
    }

    /**
     * Reemplaza los marcadores conocidos por sus datos; los que no tienen dato quedan como
     * "____" y los desconocidos se dejan tal cual para que se note el error.
     *
     * @param  array<string, string|null>  $datos
     */
    public static function renderizar(string $texto, array $datos): string
    {
        return preg_replace_callback(self::PATRON, function (array $m) use ($datos) {
            if (! array_key_exists($m[1], self::MARCADORES)) {
                return $m[0];
            }

            $valor = trim((string) ($datos[$m[1]] ?? ''));

            return $valor === '' ? self::VACIO : $valor;
        }, $texto);
    }
}

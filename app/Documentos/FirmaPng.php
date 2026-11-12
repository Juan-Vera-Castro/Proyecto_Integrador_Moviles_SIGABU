<?php

namespace App\Documentos;

/**
 * Lee el PNG de la firma táctil sin depender de la extensión GD (no viene en todos los PHP,
 * p. ej. Herd Lite): solo usa zlib. Admite PNG de 8 bits sin entrelazar, que es lo que
 * exporta canvas.toDataURL('image/png'); cualquier otro formato se trata como sin trazo.
 */
class FirmaPng
{
    private const FIRMA = "\x89PNG\r\n\x1a\n";

    /**
     * Bytes por píxel según el tipo de color del PNG (8 bits por canal).
     */
    private const BYTES_POR_PIXEL = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4];

    /**
     * Opacidad mínima (0-255) para contar un píxel como trazo. Equivale al alfa < 100 de GD
     * (0 opaco, 127 transparente) que se usaba antes.
     */
    private const OPACIDAD_MIN = 56;

    /**
     * Píxeles con trazo, muestreando cada 2 px, para descartar firmas vacías o de un punto.
     */
    public static function pixelesConTrazo(string $png): int
    {
        $imagen = self::leer($png);

        if ($imagen === null) {
            return 0;
        }

        ['ancho' => $ancho, 'alto' => $alto, 'color' => $tipo] = $imagen;
        $muestras = intdiv($ancho + 1, 2) * intdiv($alto + 1, 2);

        // Sin canal alfa ni paleta, todo píxel es opaco: no hace falta descomprimir nada.
        if ($tipo === 0 || $tipo === 2) {
            return $muestras;
        }

        $canal = self::reconstruirCanal($imagen);

        if ($canal === null) {
            return 0;
        }

        $con = 0;

        for ($y = 0; $y < $alto; $y += 2) {
            for ($x = 0; $x < $ancho; $x += 2) {
                $valor = $canal[$y][$x];
                $opacidad = $tipo === 3 ? ($imagen['transparencia'][$valor] ?? 255) : $valor;

                if ($opacidad >= self::OPACIDAD_MIN) {
                    $con++;
                }
            }
        }

        return $con;
    }

    /**
     * Cabecera, datos comprimidos y transparencia de la paleta, o null si no es un PNG que se sepa leer.
     *
     * @return array{ancho: int, alto: int, color: int, datos: string, transparencia: array<int, int>}|null
     */
    private static function leer(string $png): ?array
    {
        if (! str_starts_with($png, self::FIRMA)) {
            return null;
        }

        $pos = strlen(self::FIRMA);
        $total = strlen($png);
        $cabecera = null;
        $datos = '';
        $transparencia = [];

        while ($pos + 8 <= $total) {
            ['largo' => $largo, 'tipo' => $bloque] = unpack('Nlargo/a4tipo', $png, $pos);
            $contenido = substr($png, $pos + 8, $largo);
            $pos += 12 + $largo;

            if ($bloque === 'IHDR' && strlen($contenido) === 13) {
                $cabecera = unpack('Nancho/Nalto/Cprofundidad/Ccolor/Ccompresion/Cfiltro/Centrelazado', $contenido);
            } elseif ($bloque === 'tRNS') {
                $transparencia = array_values(unpack('C*', $contenido) ?: []);
            } elseif ($bloque === 'IDAT') {
                $datos .= $contenido;
            } elseif ($bloque === 'IEND') {
                break;
            }
        }

        if (! $cabecera || $cabecera['profundidad'] !== 8 || $cabecera['entrelazado'] !== 0
            || ! isset(self::BYTES_POR_PIXEL[$cabecera['color']]) || $cabecera['ancho'] < 1 || $cabecera['alto'] < 1) {
            return null;
        }

        return [
            'ancho' => $cabecera['ancho'],
            'alto' => $cabecera['alto'],
            'color' => $cabecera['color'],
            'datos' => $datos,
            'transparencia' => $transparencia,
        ];
    }

    /**
     * Valores del canal que decide la opacidad (alfa, o el índice de la paleta) de cada píxel.
     * Los filtros PNG solo combinan bytes del mismo canal, así que basta reconstruir ese.
     *
     * @return list<list<int>>|null
     */
    private static function reconstruirCanal(array $imagen): ?array
    {
        $crudo = self::descomprimir($imagen['datos']);
        $bpp = self::BYTES_POR_PIXEL[$imagen['color']];
        $desfase = $imagen['color'] === 3 ? 0 : $bpp - 1;
        $ancho = $imagen['ancho'];
        $bytesFila = $ancho * $bpp;

        if ($crudo === null || strlen($crudo) < $imagen['alto'] * ($bytesFila + 1)) {
            return null;
        }

        $filas = [];
        $anterior = array_fill(0, $ancho, 0);

        for ($y = 0; $y < $imagen['alto']; $y++) {
            $inicio = $y * ($bytesFila + 1);
            $filtro = ord($crudo[$inicio]);
            $base = $inicio + 1 + $desfase;
            $fila = [];

            for ($x = 0; $x < $ancho; $x++) {
                $valor = ord($crudo[$base + $x * $bpp]);
                $a = $x > 0 ? $fila[$x - 1] : 0;
                $b = $anterior[$x];

                $fila[$x] = match ($filtro) {
                    0 => $valor,
                    1 => ($valor + $a) & 0xFF,
                    2 => ($valor + $b) & 0xFF,
                    3 => ($valor + (($a + $b) >> 1)) & 0xFF,
                    4 => ($valor + self::paeth($a, $b, $x > 0 ? $anterior[$x - 1] : 0)) & 0xFF,
                    default => null,
                };

                if ($fila[$x] === null) {
                    return null;
                }
            }

            $filas[] = $anterior = $fila;
        }

        return $filas;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
    }

    /**
     * Descomprime los datos sin dejar avisos de PHP si vienen corruptos.
     */
    private static function descomprimir(string $datos): ?string
    {
        set_error_handler(fn () => true);

        try {
            $crudo = gzuncompress($datos);
        } finally {
            restore_error_handler();
        }

        return $crudo === false ? null : $crudo;
    }
}

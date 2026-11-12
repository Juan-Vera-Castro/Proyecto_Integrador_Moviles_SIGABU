<?php

namespace Tests\Concerns;

/**
 * PNG RGBA de prueba como los que exporta el lienzo de firma, armado sin la extensión GD.
 */
trait GeneraFirmaPng
{
    /**
     * PNG de $ancho×$alto transparente; con $trazo dibuja dos líneas de 4 px como firma.
     * $filtro es el filtro PNG (0 a 4) de cada fila, para probar el lector con todos.
     */
    protected function firmaPng(bool $trazo = true, int $filtro = 0, int $ancho = 800, int $alto = 300): string
    {
        $pixeles = array_fill(0, $alto, array_fill(0, $ancho * 4, 0));

        if ($trazo) {
            $this->trazarLinea($pixeles, 50, 150, 750, 120);
            $this->trazarLinea($pixeles, 100, 80, 300, 250);
        }

        $crudo = '';
        $anterior = array_fill(0, $ancho * 4, 0);

        foreach ($pixeles as $fila) {
            $crudo .= chr($filtro).pack('C*', ...$this->aplicarFiltro($filtro, $fila, $anterior));
            $anterior = $fila;
        }

        return "\x89PNG\r\n\x1a\n"
            .$this->bloquePng('IHDR', pack('NNCCCCC', $ancho, $alto, 8, 6, 0, 0, 0))
            .$this->bloquePng('IDAT', gzcompress($crudo))
            .$this->bloquePng('IEND', '');
    }

    private function trazarLinea(array &$pixeles, int $x0, int $y0, int $x1, int $y1): void
    {
        $pasos = max(abs($x1 - $x0), abs($y1 - $y0));

        for ($i = 0; $i <= $pasos; $i++) {
            $cx = (int) round($x0 + ($x1 - $x0) * $i / $pasos);
            $cy = (int) round($y0 + ($y1 - $y0) * $i / $pasos);

            for ($dy = -2; $dy < 2; $dy++) {
                for ($dx = -2; $dx < 2; $dx++) {
                    if (isset($pixeles[$cy + $dy][($cx + $dx) * 4 + 3])) {
                        array_splice($pixeles[$cy + $dy], ($cx + $dx) * 4, 4, [17, 24, 39, 255]);
                    }
                }
            }
        }
    }

    private function aplicarFiltro(int $filtro, array $fila, array $anterior): array
    {
        $salida = [];

        foreach ($fila as $i => $valor) {
            $a = $i >= 4 ? $fila[$i - 4] : 0;
            $b = $anterior[$i];
            $c = $i >= 4 ? $anterior[$i - 4] : 0;
            $p = $a + $b - $c;
            $paeth = (abs($p - $a) <= abs($p - $b) && abs($p - $a) <= abs($p - $c)) ? $a : (abs($p - $b) <= abs($p - $c) ? $b : $c);

            $salida[] = ($valor - match ($filtro) {
                1 => $a,
                2 => $b,
                3 => ($a + $b) >> 1,
                4 => $paeth,
                default => 0,
            }) & 0xFF;
        }

        return $salida;
    }

    private function bloquePng(string $tipo, string $datos): string
    {
        return pack('N', strlen($datos)).$tipo.$datos.pack('N', crc32($tipo.$datos));
    }
}

<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Código QR en SVG, generado en el servidor (sin servicios externos).
 */
class CodigoQr
{
    public static function svg(string $texto, int $tamano = 180): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($tamano, 1), new SvgImageBackEnd)))->writeString($texto);

        // Sin la declaración XML, para poder incrustarlo en la página.
        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}

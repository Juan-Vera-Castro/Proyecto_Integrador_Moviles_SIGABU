<?php

namespace Tests\Unit;

use App\Documentos\FirmaPng;
use App\Http\Requests\RegistrarConsentimientoRequest;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\GeneraFirmaPng;

/**
 * Lectura del PNG de la firma sin la extensión GD.
 */
class FirmaPngTest extends TestCase
{
    use GeneraFirmaPng;

    public function test_cuenta_el_trazo_igual_con_cualquier_filtro_png(): void
    {
        $sinFiltro = FirmaPng::pixelesConTrazo($this->firmaPng(filtro: 0));

        $this->assertGreaterThanOrEqual(RegistrarConsentimientoRequest::FIRMA_MIN_TRAZO, $sinFiltro);

        foreach ([1, 2, 3, 4] as $filtro) {
            $this->assertSame($sinFiltro, FirmaPng::pixelesConTrazo($this->firmaPng(filtro: $filtro)), "Filtro $filtro");
        }
    }

    public function test_una_firma_transparente_no_tiene_trazo(): void
    {
        $this->assertSame(0, FirmaPng::pixelesConTrazo($this->firmaPng(trazo: false, filtro: 4)));
    }

    public function test_lo_que_no_es_un_png_valido_no_tiene_trazo(): void
    {
        $this->assertSame(0, FirmaPng::pixelesConTrazo('no es una imagen'));
        $this->assertSame(0, FirmaPng::pixelesConTrazo(substr($this->firmaPng(), 0, 60)));
    }
}

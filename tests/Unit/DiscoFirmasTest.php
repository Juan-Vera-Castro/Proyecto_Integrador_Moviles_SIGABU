<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * El entorno E2E guarda las firmas en disco local. Eso no puede alterar el disco del servidor:
 * en S3, "root" es un prefijo de las rutas, y las firmas ya guardadas dejarían de encontrarse.
 */
class DiscoFirmasTest extends TestCase
{
    public function test_por_defecto_las_firmas_van_a_s3_sin_prefijo_de_ruta(): void
    {
        $disco = config('filesystems.disks.firmas');

        $this->assertSame('s3', $disco['driver']);
        $this->assertSame('', $disco['root']);
    }
}

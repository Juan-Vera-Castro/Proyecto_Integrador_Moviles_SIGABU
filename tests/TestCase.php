<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase aísla cada prueba en una transacción en estas conexiones. "vbg" es la base
     * propia de Violencias de Género (SQLite en memoria en las pruebas): sin declararla aquí, su
     * base en memoria se perdería entre pruebas.
     *
     * @var array<int, string|null>
     */
    protected $connectionsToTransact = [null, 'vbg'];
}

<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Registros de evidencia que solo se insertan (RN-06). En la base, impedir_cambios()
 * también rechaza UPDATE y DELETE; aquí el error aparece antes, con un mensaje claro.
 */
trait EsInmutable
{
    protected static function bootEsInmutable(): void
    {
        static::updating(function () {
            throw new LogicException('Los registros de '.static::class.' no se pueden modificar: registra una decisión nueva.');
        });

        static::deleting(function () {
            throw new LogicException('Los registros de '.static::class.' no se pueden borrar.');
        });
    }
}

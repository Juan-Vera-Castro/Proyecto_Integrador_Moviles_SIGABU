<?php

namespace App\Models\Vbg;

/**
 * Modelos de la base propia de VBG (conexión "vbg"). Sus relaciones con modelos de la base
 * principal (Reporte, User) deben consultarse en la principal: por defecto Laravel le daría al
 * modelo relacionado la conexión de quien pregunta, y buscaría vbg_reportes o usuarios en la
 * base de VBG, donde no existen.
 */
trait EnBaseVbg
{
    protected function newRelatedInstance($class)
    {
        return tap(new $class, function ($instance) {
            if (! $instance->getConnectionName()) {
                $instance->setConnection(config('database.default'));
            }
        });
    }
}

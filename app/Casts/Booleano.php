<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Booleano para la conexión pgsql, que usa
 * ATTR_EMULATE_PREPARES, así que un true de PHP llega como 1 y PostgreSQL lo
 * rechaza en una columna boolean ("expression is of type integer"). En pgsql se
 * guarda como 'true'/'false'; en SQLite (pruebas) como 1/0.
 */
class Booleano implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?bool
    {
        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string|int|null
    {
        return $value === null ? null : self::valor((bool) $value, $model->getConnection()->getDriverName());
    }

    /**
     * Valor que se debe enviar a la base para un booleano, también para usarlo en un where.
     */
    public static function valor(bool $valor, string $driver): string|int
    {
        return $driver === 'pgsql' ? ($valor ? 'true' : 'false') : (int) $valor;
    }
}

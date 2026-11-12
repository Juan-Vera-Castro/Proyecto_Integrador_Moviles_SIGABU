<?php

namespace App\Models\Inclusion;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Recurso pedagógico del catálogo, por condición. Su contenido son bloques de datos.
 */
class Recurso extends Model
{
    protected $table = 'inclusion_recursos';

    protected $primaryKey = 'id_recurso';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'condicion',
        'titulo',
        'version',
        'contenido',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'contenido' => 'array',
            'activo' => Booleano::class,
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', Booleano::valor(true, $query->getConnection()->getDriverName()));
    }
}

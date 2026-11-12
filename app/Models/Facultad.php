<?php

namespace App\Models;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Facultad de la universidad. Con la jornada, decide a qué psicólogo llega una solicitud.
 */
class Facultad extends Model
{
    protected $table = 'facultades';

    protected $primaryKey = 'id_facultad';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => Booleano::class,
        ];
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionProfesional::class, 'id_facultad', 'id_facultad');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', Booleano::valor(true, $query->getConnection()->getDriverName()))->orderBy('nombre');
    }

    /**
     * La facultad registrada en la vinculación del estudiante (texto libre), si coincide con una del catálogo.
     */
    public static function deTexto(?string $nombre): ?self
    {
        $nombre = trim((string) $nombre);

        return $nombre === '' ? null : static::activas()->get()
            ->first(fn (self $facultad) => mb_strtolower($facultad->nombre) === mb_strtolower($nombre)
                || mb_strtolower($facultad->nombre) === mb_strtolower('Facultad de '.$nombre));
    }
}

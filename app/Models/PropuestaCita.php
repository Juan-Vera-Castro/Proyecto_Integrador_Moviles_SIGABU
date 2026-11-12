<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horario que el psicólogo propone para una cita. El estudiante elige uno de los propuestos:
 * ese queda "Elegida" (con la cita que se creó) y los demás "Descartada".
 */
class PropuestaCita extends Model
{
    use HasUuids;

    public const PROPUESTA = 'Propuesta';

    public const ELEGIDA = 'Elegida';

    public const DESCARTADA = 'Descartada';

    /**
     * El estudiante dijo que ninguno de los horarios le sirve.
     */
    public const RECHAZADA = 'Rechazada';

    /**
     * Cuántos horarios puede proponer el profesional a la vez.
     */
    public const MAXIMO = 5;

    protected $table = 'propuestas_cita';

    protected $primaryKey = 'id_propuesta';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_profesional',
        'fecha_hora',
        'estado',
        'id_cita',
        'fecha_propuesta',
        'fecha_respuesta',
    ];

    protected $attributes = [
        'estado' => self::PROPUESTA,
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'fecha_propuesta' => 'datetime',
            'fecha_respuesta' => 'datetime',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Propuestas que el estudiante todavía puede elegir: vigentes y en el futuro.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', self::PROPUESTA)
            ->where('fecha_hora', '>', now(Actuacion::ZONA_HORARIA)->toDateTimeString())
            ->orderBy('fecha_hora');
    }

    public function etiqueta(): string
    {
        return ucfirst($this->fecha_hora->locale('es')->translatedFormat('l j \d\e F')).' · '.$this->fecha_hora->format('g:i a');
    }
}

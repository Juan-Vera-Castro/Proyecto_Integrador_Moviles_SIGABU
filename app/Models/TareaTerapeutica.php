<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Actividad que el profesional asigna al estudiante entre sesiones (HU-013).
 * Nace "Pendiente de evaluación" hasta que el profesional la revisa.
 */
class TareaTerapeutica extends Model
{
    use HasUuids;

    public const ESTADO_PENDIENTE = 'Pendiente de evaluación';

    public const ESTADO_CUMPLIDA = 'Cumplida';

    public const ESTADO_NO_CUMPLIDA = 'No cumplida';

    /**
     * Tarea pendiente que la remisión del caso dejó sin efecto (HU-007): no se completó.
     */
    public const ESTADO_CANCELADA = 'Cancelada';

    public const TITULO_MAX = 150;

    public const DESCRIPCION_MAX = 1000;

    protected $table = 'tareas_terapeuticas';

    protected $primaryKey = 'id_tarea';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_profesional',
        'titulo',
        'descripcion',
        'fecha_limite',
        'fecha_asignacion',
        'estado',
        'id_remision',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_PENDIENTE,
    ];

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'date',
            'fecha_asignacion' => 'datetime',
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
     * Remisión que canceló la tarea.
     */
    public function remision(): BelongsTo
    {
        return $this->belongsTo(Actuacion::class, 'id_remision', 'id_actuacion');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atención registrada en el expediente por el profesional a cargo del caso: tipo, asistencia,
 * fecha, observaciones y acuerdos. Es evidencia del proceso: no se modifica ni se borra.
 */
class Atencion extends Model
{
    use EsInmutable, HasUuids;

    public const TIPOS = [
        'Sesión psicológica individual',
        'Evaluación de necesidades de inclusión',
        'Seguimiento terapéutico',
        'Acompañamiento académico',
        'Orientación psicosocial',
    ];

    /**
     * Los mismos valores de asistencia que las citas (Actuacion).
     */
    public const ASISTENCIAS = [Actuacion::ASISTIO, Actuacion::NO_ASISTIO, Actuacion::CANCELADA];

    public const OBSERVACIONES_MIN = 10;

    public const OBSERVACIONES_MAX = 3000;

    public const ACUERDOS_MAX = 1000;

    protected $table = 'atenciones';

    protected $primaryKey = 'id_atencion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_profesional',
        'tipo_atencion',
        'asistencia',
        'fecha_atencion',
        'observaciones',
        'acuerdos_compromisos',
        'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'fecha_atencion' => 'datetime',
            'fecha_registro' => 'datetime',
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
}

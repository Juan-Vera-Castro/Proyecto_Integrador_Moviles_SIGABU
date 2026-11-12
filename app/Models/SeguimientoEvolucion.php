<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota de evolución de una cita a la que el estudiante asistió (HU-015): nivel de riesgo,
 * valoración del avance y, en Salud Mental, el puntaje de una escala. Una por cita. Es la
 * fuente de las métricas de progreso del caso y no se modifica: las correcciones van como
 * anotación de la historia clínica. La registra siempre el profesional; si más adelante una
 * IA sugiere el texto, el profesional lo revisa antes de guardarlo.
 */
class SeguimientoEvolucion extends Model
{
    use EsInmutable, HasUuids;

    public const MEJORA = 'Mejora';

    public const ESTABLE = 'Estable';

    public const RETROCESO = 'Retroceso';

    public const VALORACIONES = [self::MEJORA, self::ESTABLE, self::RETROCESO];

    public const GAD7 = 'GAD-7';

    public const PHQ9 = 'PHQ-9';

    /**
     * Escalas que se pueden aplicar, con su puntaje máximo. Solo en casos de Salud Mental.
     */
    public const INSTRUMENTOS = [self::GAD7 => 21, self::PHQ9 => 27];

    /**
     * Bandas de severidad de cada escala (RN-06): puntaje mínimo de la banda => nombre.
     */
    public const SEVERIDADES = [
        self::GAD7 => [0 => 'Mínima', 5 => 'Leve', 10 => 'Moderada', 15 => 'Severa'],
        self::PHQ9 => [0 => 'Mínima', 5 => 'Leve', 10 => 'Moderada', 15 => 'Moderadamente severa', 20 => 'Severa'],
    ];

    public const NOTA_MAX = 2000;

    protected $table = 'seguimientos_evolucion';

    protected $primaryKey = 'id_seguimiento';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_actuacion',
        'id_profesional',
        'fecha_hora',
        'nivel_riesgo',
        'valoracion_avance',
        'instrumento',
        'puntaje',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'puntaje' => 'integer',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }

    /**
     * Cita a la que corresponde el seguimiento.
     */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Actuacion::class, 'id_actuacion', 'id_actuacion');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Severidad del puntaje según la escala aplicada, o null si no se aplicó ninguna.
     */
    public function severidad(): ?string
    {
        return $this->instrumento ? self::severidadDe($this->instrumento, $this->puntaje) : null;
    }

    public static function severidadDe(string $instrumento, int $puntaje): ?string
    {
        $nombre = null;

        foreach (self::SEVERIDADES[$instrumento] ?? [] as $desde => $banda) {
            if ($puntaje >= $desde) {
                $nombre = $banda;
            }
        }

        return $nombre;
    }
}

<?php

namespace App\Models\Vbg;

use App\Casts\Booleano;
use App\Models\Actuacion;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de una situación de violencia basada en género. Lo hace la persona afectada, un
 * tercero o alguien anónimo; si es anónimo no queda quién lo hizo. Cuando un profesional VBG
 * lo toma, el reporte es el caso: lleva su estado, nivel de riesgo y acciones.
 */
class Reporte extends Model
{
    use HasUuids;

    public const AFECTADA = 'Afectada';

    public const TERCERO = 'Tercero';

    public const ANONIMO = 'Anónimo';

    public const TIPOS_REPORTANTE = [self::AFECTADA, self::TERCERO, self::ANONIMO];

    public const TIPOS_VIOLENCIA = ['Física', 'Psicológica', 'Sexual', 'Económica', 'Digital', 'Otra'];

    public const RECIBIDO = 'Recibido';

    public const EN_ATENCION = 'En atención';

    public const CERRADO = 'Cerrado';

    public const ESTADOS = [self::RECIBIDO, self::EN_ATENCION, self::CERRADO];

    public const NIVELES_RIESGO = ['Bajo', 'Medio', 'Alto'];

    public const RELATO_MIN = 20;

    public const RELATO_MAX = 5000;

    protected $table = 'vbg_reportes';

    protected $primaryKey = 'id_reporte';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'tipo_reportante',
        'id_persona_reporta',
        'afectado_nombre',
        'afectado_vinculo',
        'relato',
        'tipo_violencia',
        'lugar',
        'fecha_hechos',
        'desea_contacto',
        'medio_contacto',
        'acepta_datos',
        'fecha_reporte',
        'estado',
        'nivel_riesgo',
        'id_profesional',
        'fecha_cierre',
    ];

    protected $attributes = [
        'estado' => self::RECIBIDO,
    ];

    protected function casts(): array
    {
        return [
            'fecha_hechos' => 'date',
            'fecha_reporte' => 'datetime',
            'fecha_cierre' => 'datetime',
            'desea_contacto' => Booleano::class,
            'acepta_datos' => Booleano::class,
        ];
    }

    public function reportante(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona_reporta', 'id_persona');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Citas del caso, en la base propia de VBG (sigabu_vbg).
     */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'id_reporte', 'id_reporte')->orderBy('fecha')->orderBy('hora_inicio');
    }

    /**
     * Informes de análisis del caso, del más reciente al más antiguo (base sigabu_vbg).
     */
    public function informes(): HasMany
    {
        return $this->hasMany(Informe::class, 'id_reporte', 'id_reporte')->orderByDesc('creado_en');
    }

    public function acciones(): HasMany
    {
        return $this->hasMany(Accion::class, 'id_reporte', 'id_reporte')->orderBy('fecha_hora');
    }

    public function esAnonimo(): bool
    {
        return $this->tipo_reportante === self::ANONIMO;
    }

    public function estaCerrado(): bool
    {
        return $this->estado === self::CERRADO;
    }

    /**
     * Persona a quien le ocurrió la situación, en palabras del equipo VBG.
     */
    public function etiquetaAfectado(): string
    {
        return match ($this->tipo_reportante) {
            self::AFECTADA => $this->reportante?->nombre_completo ?? 'Persona que reporta',
            self::TERCERO => $this->afectado_nombre.($this->afectado_vinculo ? " ({$this->afectado_vinculo})" : ''),
            default => 'Reporte anónimo',
        };
    }

    public function scopeDeEstado(Builder $query, ?string $estado): Builder
    {
        return $estado && in_array($estado, self::ESTADOS, true) ? $query->where('estado', $estado) : $query;
    }

    /**
     * Registra el reporte con su código VG-AAAA-NNNN. Si dos reportes toman el mismo número
     * al tiempo, la restricción UNIQUE rechaza uno y se reintenta con el siguiente.
     */
    public static function registrar(array $datos): self
    {
        $anio = now(Actuacion::ZONA_HORARIA)->format('Y');

        for ($intento = 0; ; $intento++) {
            $ultimo = static::where('codigo', 'like', "VG-{$anio}-%")->max('codigo');
            $numero = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

            try {
                return DB::transaction(fn () => static::create($datos + ['codigo' => sprintf('VG-%s-%04d', $anio, $numero)]));
            } catch (QueryException $e) {
                if ($intento >= 4 || ! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }
    }
}

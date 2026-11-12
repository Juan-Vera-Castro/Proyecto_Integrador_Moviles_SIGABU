<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Alerta temprana de un caso (HU-026): se genera sola cuando el caso pasa a riesgo alto o se le
 * marca un factor de riesgo crítico, y queda pendiente hasta que el profesional a cargo la
 * atiende. Hay a lo sumo una pendiente por caso. Generarla y atenderla quedan en la auditoría.
 */
class AlertaTemprana extends Model
{
    use HasUuids;

    public const PENDIENTE = 'Pendiente';

    public const ATENDIDA = 'Atendida';

    public const RIESGO_ALTO = 'Nivel de riesgo alto';

    public const FACTOR_CRITICO = 'Factor de riesgo crítico';

    protected $table = 'alertas_tempranas';

    protected $primaryKey = 'id_alerta';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'tipo_alerta',
        'descripcion',
        'estado',
        'generada_por',
        'fecha_generacion',
        'atendida_por',
        'fecha_atencion',
    ];

    protected $attributes = [
        'estado' => self::PENDIENTE,
    ];

    protected function casts(): array
    {
        return [
            'fecha_generacion' => 'datetime',
            'fecha_atencion' => 'datetime',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }

    public function usuarioAtendio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por', 'id_usuario');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::PENDIENTE);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    /**
     * Genera la alerta del caso si su situación lo amerita y no tiene ya una pendiente.
     * Devuelve la alerta creada, o null si no hizo falta.
     */
    public static function evaluar(Caso $caso): ?self
    {
        $criticos = array_values(array_intersect($caso->factores_riesgo ?? [], Caso::FACTORES_CRITICOS));

        [$tipo, $descripcion] = match (true) {
            $caso->nivel_riesgo === 'Alto' => [self::RIESGO_ALTO, 'El caso está en nivel de riesgo alto.'],
            $criticos !== [] => [self::FACTOR_CRITICO, 'Factor de riesgo crítico: '.implode('; ', $criticos).'.'],
            default => [null, null],
        };

        if ($tipo === null || $caso->alertas()->pendientes()->exists()) {
            return null;
        }

        try {
            // Punto de guardado: si otra petición la creó al tiempo, la transacción de quien llama sigue viva.
            return DB::transaction(function () use ($caso, $tipo, $descripcion) {
                $alerta = static::create([
                    'id_caso' => $caso->id_caso,
                    'tipo_alerta' => $tipo,
                    'descripcion' => $descripcion.' Requiere atención oportuna.',
                    'generada_por' => auth()->id(),
                    'fecha_generacion' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
                ]);

                AuditoriaExpediente::registrar(AuditoriaExpediente::ALERTA_GENERADA, $caso, [
                    'id_alerta' => $alerta->id_alerta,
                    'tipo_alerta' => $tipo,
                ]);

                return $alerta;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}

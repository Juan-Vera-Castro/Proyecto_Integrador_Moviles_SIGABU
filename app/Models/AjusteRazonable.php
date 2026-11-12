<?php

namespace App\Models;

use App\Models\Inclusion\Evidencia;
use App\Models\Inclusion\Recurso;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ajuste razonable de un caso de Inclusión Universitaria (HU-018): qué barrera atiende, en qué
 * consiste y quién lo aplica. Su avance se registra con seguimientos (HU-019).
 */
class AjusteRazonable extends Model
{
    use HasUuids;

    public const TIPOS = ['Académico o de evaluación', 'Metodológico', 'Tecnológico o de acceso', 'De infraestructura', 'De tiempo', 'De comunicación', 'Otro'];

    public const VIGENTE = 'Vigente';

    public const CUMPLIDO = 'Cumplido';

    public const SUSPENDIDO = 'Suspendido';

    public const ESTADOS = [self::VIGENTE, self::CUMPLIDO, self::SUSPENDIDO];

    public const TEXTO_MAX = 2000;

    /**
     * Áreas de la plantilla 5.8 del recurso pedagógico.
     */
    public const AREAS = ['Instrucciones y metodología', 'Evaluación', 'Comunicación', 'Organización', 'Trabajo en grupo', 'Regulación emocional'];

    protected $table = 'ajustes_razonables';

    protected $primaryKey = 'id_ajuste';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_profesional',
        'tipo',
        'barrera',
        'descripcion',
        'responsable',
        'area',
        'observaciones',
        'id_recurso',
        'fecha_inicio',
        'estado',
        'fecha_registro',
    ];

    protected $attributes = [
        'estado' => self::VIGENTE,
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
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

    /**
     * Historial de seguimiento, en orden cronológico.
     */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoAjuste::class, 'id_ajuste', 'id_ajuste')->orderBy('fecha')->orderBy('fecha_registro');
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class, 'id_recurso', 'id_recurso');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class, 'id_destino', 'id_ajuste')
            ->where('destino', Evidencia::DESTINO_AJUSTE)
            ->where('estado', Evidencia::VINCULADA);
    }

    /**
     * Cómo se clasifica el ajuste: los de la plantilla tienen área, los anteriores tipo.
     */
    public function etiqueta(): string
    {
        return (string) ($this->area ?? $this->tipo);
    }

    public function estaVigente(): bool
    {
        return $this->estado === self::VIGENTE;
    }
}

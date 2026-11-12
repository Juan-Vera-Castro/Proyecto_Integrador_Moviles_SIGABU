<?php

namespace App\Models\Alimentos;

use App\Models\Actuacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ingreso a bodega: un lote de mercados o elementos de una donación. Las entregas descuentan
 * de su cantidad disponible; la base impide que quede negativa.
 */
class Lote extends Model
{
    public const TIPOS = ['Mercado Tipo A', 'Mercado Tipo B', 'Elemento suelto'];

    /**
     * Mercados que se entregan a una solicitud aprobada.
     */
    public const TIPOS_MERCADO = ['Mercado Tipo A', 'Mercado Tipo B'];

    /**
     * Días antes del vencimiento en que un lote se marca "próximo a vencer".
     */
    public const DIAS_ALERTA_VENCIMIENTO = 20;

    protected $table = 'alim_lotes';

    protected $primaryKey = 'id_lote';

    public $timestamps = false;

    protected $fillable = [
        'codigo_lote',
        'tipo',
        'descripcion',
        'cantidad_inicial',
        'cantidad_disponible',
        'fecha_ingreso',
        'fecha_vencimiento',
        'id_donante',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_inicial' => 'integer',
            'cantidad_disponible' => 'integer',
            'fecha_ingreso' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function donante(): BelongsTo
    {
        return $this->belongsTo(Donante::class, 'id_donante', 'id_donante');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por', 'id_usuario');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'id_lote', 'id_lote');
    }

    /**
     * Lotes de mercado con existencias y sin vencer, el que vence primero adelante.
     */
    public function scopeDespachables(Builder $query): Builder
    {
        return $query->whereIn('tipo', self::TIPOS_MERCADO)
            ->where('cantidad_disponible', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('fecha_vencimiento')->orWhere('fecha_vencimiento', '>=', now(Actuacion::ZONA_HORARIA)->toDateString()))
            ->orderByRaw('fecha_vencimiento IS NULL')
            ->orderBy('fecha_vencimiento')
            ->orderBy('fecha_ingreso');
    }

    /**
     * Lote con existencias que vence dentro de los próximos días de alerta.
     */
    public function venceProximamente(): bool
    {
        return $this->fecha_vencimiento !== null && $this->cantidad_disponible > 0
            && $this->fecha_vencimiento->lte(now(Actuacion::ZONA_HORARIA)->startOfDay()->addDays(self::DIAS_ALERTA_VENCIMIENTO));
    }

    public function estaVencido(): bool
    {
        return $this->fecha_vencimiento !== null && $this->fecha_vencimiento->lt(now(Actuacion::ZONA_HORARIA)->startOfDay());
    }
}

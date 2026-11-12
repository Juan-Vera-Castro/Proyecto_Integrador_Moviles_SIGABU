<?php

namespace App\Models\Inclusion;

use App\Models\Caso;
use App\Models\Concerns\EsInmutable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Entrada de la matriz de seguimiento. No se edita ni se borra: se corrige con anotaciones.
 */
class EntradaBitacora extends Model
{
    use EsInmutable, HasUuids;

    public const ATENCION_INICIAL = 'Atención inicial';

    public const SEGUIMIENTO = 'Seguimiento';

    public const CIERRE = 'Cierre';

    public const TIPOS = [self::ATENCION_INICIAL, self::SEGUIMIENTO, self::CIERRE];

    public const TEXTO_MAX = 10000;

    protected $table = 'inclusion_bitacora_entradas';

    protected $primaryKey = 'id_entrada';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_entrada',
        'id_bitacora',
        'numero',
        'fecha_hora',
        'tipo',
        'descripcion',
        'id_profesional',
        'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha_hora' => 'datetime',
            'fecha_registro' => 'datetime',
        ];
    }

    public function bitacora(): BelongsTo
    {
        return $this->belongsTo(Bitacora::class, 'id_bitacora', 'id_bitacora');
    }

    /**
     * Caso de la entrada, para el control de área de CheckAreaProfesional.
     */
    public function caso(): HasOneThrough
    {
        return $this->hasOneThrough(Caso::class, Bitacora::class, 'id_bitacora', 'id_caso', 'id_bitacora', 'id_caso');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    public function anotaciones(): HasMany
    {
        return $this->hasMany(AnotacionBitacora::class, 'id_entrada', 'id_entrada')->orderBy('fecha_hora');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class, 'id_destino', 'id_entrada')
            ->where('destino', Evidencia::DESTINO_ENTRADA)
            ->where('estado', Evidencia::VINCULADA);
    }
}

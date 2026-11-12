<?php

namespace App\Models\Inclusion;

use App\Models\Concerns\EsInmutable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quién cambió qué y cuándo en la caracterización. Guarda los valores, que son datos sensibles:
 * por eso va aquí y no en auditoria_expedientes, que ve el administrador.
 */
class BitacoraCambio extends Model
{
    use EsInmutable, HasUuids;

    protected $table = 'inclusion_bitacora_cambios';

    protected $primaryKey = 'id_cambio';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_bitacora',
        'campo',
        'valor_anterior',
        'valor_nuevo',
        'id_profesional',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }
}

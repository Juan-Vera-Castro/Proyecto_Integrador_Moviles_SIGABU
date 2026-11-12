<?php

namespace App\Models\Inclusion;

use App\Models\Concerns\EsInmutable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reapertura de un caso cerrado con constancia: solo el administrador, con motivo obligatorio.
 */
class Reapertura extends Model
{
    use EsInmutable, HasUuids;

    public const MOTIVO_MAX = 1000;

    protected $table = 'inclusion_reaperturas';

    protected $primaryKey = 'id_reapertura';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_bitacora',
        'id_constancia',
        'id_administrador',
        'motivo',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_administrador', 'id_usuario');
    }
}

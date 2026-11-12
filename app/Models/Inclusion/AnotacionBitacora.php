<?php

namespace App\Models\Inclusion;

use App\Models\Concerns\EsInmutable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Corrección o complemento de una entrada, con su autor y fecha. Solo se insertan.
 */
class AnotacionBitacora extends Model
{
    use EsInmutable, HasUuids;

    public const TEXTO_MAX = 5000;

    protected $table = 'inclusion_bitacora_anotaciones';

    protected $primaryKey = 'id_anotacion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_anotacion',
        'id_entrada',
        'texto',
        'id_profesional',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function entrada(): BelongsTo
    {
        return $this->belongsTo(EntradaBitacora::class, 'id_entrada', 'id_entrada');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class, 'id_destino', 'id_anotacion')
            ->where('destino', Evidencia::DESTINO_ANOTACION)
            ->where('estado', Evidencia::VINCULADA);
    }
}

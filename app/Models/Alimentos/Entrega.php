<?php

namespace App\Models\Alimentos;

use App\Models\Concerns\EsInmutable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrega de un mercado a una solicitud aprobada, verificando el carné o la cédula de quien
 * lo recibe. Es la trazabilidad de la bodega: nunca se edita ni se borra.
 */
class Entrega extends Model
{
    use EsInmutable, HasUuids;

    public const METODOS_VALIDACION = ['Carné estudiantil', 'Cédula'];

    protected $table = 'alim_entregas';

    protected $primaryKey = 'id_entrega';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_lote',
        'cantidad',
        'metodo_validacion',
        'documento_verificado',
        'entregado_por',
        'fecha_hora',
        'firma_ruta',
        'firma_sha256',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'fecha_hora' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud', 'id_solicitud');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'id_lote', 'id_lote');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por', 'id_usuario');
    }
}

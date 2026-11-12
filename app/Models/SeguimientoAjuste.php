<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seguimiento de un ajuste razonable (HU-019): fecha, nivel de cumplimiento y observaciones
 * del avance. Forma el historial del proceso de inclusión; nunca se edita ni se borra.
 */
class SeguimientoAjuste extends Model
{
    use EsInmutable, HasUuids;

    public const CUMPLIMIENTOS = ['Se cumple', 'Se cumple parcialmente', 'No se cumple'];

    protected $table = 'seguimientos_ajuste';

    protected $primaryKey = 'id_seguimiento';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_ajuste',
        'id_profesional',
        'fecha',
        'cumplimiento',
        'observaciones',
        'estado_resultante',
        'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_registro' => 'datetime',
        ];
    }

    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(AjusteRazonable::class, 'id_ajuste', 'id_ajuste');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }
}

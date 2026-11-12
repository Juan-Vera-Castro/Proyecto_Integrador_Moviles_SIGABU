<?php

namespace App\Models\Inclusion;

use App\Models\Concerns\EsInmutable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia de gestión firmada al cerrar el caso. Cada cierre tiene la suya y se conservan
 * todas, aunque el caso se reabra.
 */
class Constancia extends Model
{
    use EsInmutable, HasUuids;

    /**
     * Texto fijo de la sección IV del formato.
     */
    public const TEXTO = 'Se entrega la presente bitácora como registro de la orientación técnica, acompañamiento psicosocial y asesoría normativa brindada en el marco de este caso, conforme a los lineamientos de la Política de Educación Superior Inclusiva. La información aquí consignada es fiel reflejo de las acciones realizadas por la Unidad de Inclusión y cuenta con los respectivos soportes y registros de asistencia que validan el acompañamiento institucional, este documento puede utilizarse como constancia de la gestión adelantada y para los fines pertinentes de seguimiento institucional o requerimientos externos.';

    protected $table = 'inclusion_constancias';

    protected $primaryKey = 'id_constancia';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_constancia',
        'id_bitacora',
        'id_entrada_cierre',
        'id_profesional',
        'nombre_profesional',
        'texto_presentado',
        'firma_ruta',
        'firma_sha256',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function bitacora(): BelongsTo
    {
        return $this->belongsTo(Bitacora::class, 'id_bitacora', 'id_bitacora');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }
}

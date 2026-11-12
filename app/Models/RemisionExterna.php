<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detalle de una remisión externa con el formato GBU-GPGR-F-016: categoría, acompañante y
 * una copia de los datos del estudiante, de la atención y del formato al momento de remitir,
 * para que el documento sea siempre el mismo aunque el registro cambie después (RN-09).
 */
class RemisionExterna extends Model
{
    use EsInmutable;

    public const URGENTE = 'URGENTE';

    public const PRIORITARIO = 'PRIORITARIO';

    public const CATEGORIAS = [self::URGENTE, self::PRIORITARIO];

    public const TIPOS_DOCUMENTO_ACOMPANANTE = ['CC', 'CE', 'TI', 'PA'];

    protected $table = 'remisiones_externas';

    protected $primaryKey = 'id_actuacion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_actuacion',
        'categoria',
        'estudiante_nombre',
        'estudiante_tipo_documento',
        'estudiante_numero_documento',
        'estudiante_sexo',
        'estudiante_fecha_nacimiento',
        'estudiante_edad',
        'id_cita',
        'fecha_hora_atencion',
        'profesional_nombre',
        'acompanante_nombre',
        'acompanante_parentesco',
        'acompanante_tipo_documento',
        'acompanante_numero_documento',
        'acompanante_telefono',
        'formato_codigo',
        'formato_version',
        'formato_fecha',
    ];

    protected function casts(): array
    {
        return [
            'estudiante_fecha_nacimiento' => 'date',
            'estudiante_edad' => 'integer',
            'fecha_hora_atencion' => 'datetime',
            'formato_fecha' => 'date',
        ];
    }

    /**
     * La actuación de tipo Remisión (destino Externa) que este registro detalla.
     */
    public function remision(): BelongsTo
    {
        return $this->belongsTo(Actuacion::class, 'id_actuacion', 'id_actuacion');
    }

    /**
     * Cita en la que se atendió al estudiante, si la hubo (D-10).
     */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Actuacion::class, 'id_cita', 'id_actuacion');
    }

    public function esUrgente(): bool
    {
        return $this->categoria === self::URGENTE;
    }
}

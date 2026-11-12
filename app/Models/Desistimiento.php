<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desistimiento de la atención: la persona decide no continuar o no contar lo sucedido. Es el
 * respaldo legal de la universidad; guarda una copia de los datos del estudiante, del
 * profesional y del formato vigente, para que el documento salga siempre igual. Inmutable.
 */
class Desistimiento extends Model
{
    use EsInmutable, HasUuids;

    public const OTRO = 'Otro';

    public const MOTIVOS = ['No desea continuar la atención', 'No desea contar lo sucedido', 'Prefiere atención externa', self::OTRO];

    public const OBSERVACIONES_MAX = 2000;

    protected $table = 'desistimientos';

    protected $primaryKey = 'id_desistimiento';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_profesional',
        'motivo',
        'observaciones',
        'fecha_hora',
        'estudiante_nombre',
        'estudiante_tipo_documento',
        'estudiante_numero_documento',
        'profesional_nombre',
        'formato_codigo',
        'formato_version',
        'formato_fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'formato_fecha' => 'date',
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
}

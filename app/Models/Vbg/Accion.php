<?php

namespace App\Models\Vbg;

use App\Models\Concerns\EsInmutable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acción registrada en un caso de VBG: una ruta activada (remisión jurídica, denuncia ante
 * Fiscalía, etc.), una nota de seguimiento o un cambio de estado. Nunca se edita ni se borra.
 */
class Accion extends Model
{
    use EsInmutable, HasUuids;

    /**
     * Rutas y seguimiento que registra el profesional desde el caso.
     */
    public const TIPOS = [
        'Remisión jurídica',
        'Denuncia ante Fiscalía',
        'Seguimiento terapéutico',
        'Remisión a Salud Mental',
        'Medida de protección',
        'Nota de seguimiento',
    ];

    public const TOMADO = 'Caso tomado';

    public const CERRADO = 'Caso cerrado';

    public const REABIERTO = 'Caso reabierto';

    public const DESCRIPCION_MAX = 3000;

    protected $table = 'vbg_acciones';

    protected $primaryKey = 'id_accion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'tipo_accion',
        'descripcion',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'id_reporte', 'id_reporte');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}

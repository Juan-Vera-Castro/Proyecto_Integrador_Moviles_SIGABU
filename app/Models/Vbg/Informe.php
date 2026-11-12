<?php

namespace App\Models\Vbg;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Informe de análisis de un caso VBG: resumen, nivel de riesgo, tipos de violencia, marco
 * legal y ruta de atención. Inmutable: cada versión es un registro nuevo y queda el historial.
 * Los campos origen, modelo_llm y prompt quedan para cuando se active la IA (rama VIOLENCIA_GENERO).
 */
class Informe extends Model
{
    use EnBaseVbg, HasUuids;

    public const MANUAL = 'Manual';

    public const IA = 'IA';

    public const RESUMEN_MAX = 10000;

    protected $connection = 'vbg';

    protected $table = 'vbg_informes';

    protected $primaryKey = 'id_informe';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id_reporte', 'id_transcripcion', 'resumen', 'nivel_riesgo', 'tipos_violencia', 'marco_legal', 'documentos_repositorio',
        'ruta_atencion', 'origen', 'modelo_llm', 'prompt', 'editado_por_profesional', 'id_profesional', 'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'tipos_violencia' => 'array',
            'marco_legal' => 'array',
            'documentos_repositorio' => 'array',
            'ruta_atencion' => 'array',
            'editado_por_profesional' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'id_reporte', 'id_reporte');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    public function transcripcion(): BelongsTo
    {
        return $this->belongsTo(Transcripcion::class, 'id_transcripcion', 'id_transcripcion');
    }
}

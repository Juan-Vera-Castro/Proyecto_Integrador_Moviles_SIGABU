<?php

namespace App\Models\Vbg;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transcripción de un audio. Hoy la escribe el profesional (Manual); la transcripción
 * automática (IA) queda contemplada: "En cola" hasta que la procese php artisan vbg:transcribir.
 */
class Transcripcion extends Model
{
    use EnBaseVbg, HasUuids;

    public const EN_COLA = 'En cola';

    public const COMPLETADA = 'Completada';

    public const ERROR = 'Error';

    public const MANUAL = 'Manual';

    public const IA = 'IA';

    public const TEXTO_MAX = 100000;

    protected $connection = 'vbg';

    protected $table = 'vbg_transcripciones';

    protected $primaryKey = 'id_transcripcion';

    public $timestamps = false;

    protected $fillable = [
        'id_audio', 'texto', 'estado', 'origen', 'modelo', 'segundos_proceso', 'completado_en', 'id_usuario', 'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'segundos_proceso' => 'float',
            'completado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function audio(): BelongsTo
    {
        return $this->belongsTo(Audio::class, 'id_audio', 'id_audio');
    }

    public function estaCompleta(): bool
    {
        return $this->estado === self::COMPLETADA && filled($this->texto);
    }

    /**
     * Si la transcripción automática está configurada en el servidor.
     */
    public static function iaDisponible(): bool
    {
        return filled(config('sigabu.vbg.ia.transcripcion_comando'));
    }
}

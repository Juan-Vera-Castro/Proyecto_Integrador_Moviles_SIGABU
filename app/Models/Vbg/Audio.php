<?php

namespace App\Models\Vbg;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Audio de una sesión VBG, guardado en el bucket privado "vbg" con su huella. Inmutable.
 */
class Audio extends Model
{
    use EnBaseVbg, HasUuids;

    public const DISCO = 'vbg';

    protected $connection = 'vbg';

    protected $table = 'vbg_audios';

    protected $primaryKey = 'id_audio';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id_audio', 'id_cita', 'origen', 'formato', 'duracion_segundos', 'ruta', 'nombre_original', 'bytes', 'sha256', 'subido_por', 'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'duracion_segundos' => 'integer',
            'bytes' => 'integer',
            'creado_en' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'id_cita', 'id_cita');
    }

    public function transcripcion(): HasOne
    {
        return $this->hasOne(Transcripcion::class, 'id_audio', 'id_audio');
    }

    /**
     * "1 h 05 min", "12 min 30 s" o null si no se conoce.
     */
    public function duracionLegible(): ?string
    {
        if ($this->duracion_segundos === null) {
            return null;
        }

        $h = intdiv($this->duracion_segundos, 3600);
        $m = intdiv($this->duracion_segundos % 3600, 60);
        $s = $this->duracion_segundos % 60;

        return $h > 0 ? sprintf('%d h %02d min', $h, $m) : sprintf('%d min %02d s', $m, $s);
    }

    public function tamanoLegible(): string
    {
        return $this->bytes >= 1048576 ? number_format($this->bytes / 1048576, 1, ',', '.').' MB' : max(1, (int) round($this->bytes / 1024)).' KB';
    }
}

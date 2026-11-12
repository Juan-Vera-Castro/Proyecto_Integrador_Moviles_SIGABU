<?php

namespace App\Models\Vbg;

use App\Models\Actuacion;
use Illuminate\Database\Eloquent\Model;

/**
 * Trazabilidad del módulo VBG en su propia base: quién agendó, firmó, subió o consultó qué.
 * Inmutable.
 */
class Auditoria extends Model
{
    public const CITA_AGENDADA = 'cita_agendada';

    public const CITA_ESTADO = 'cita_estado';

    public const CONSENTIMIENTO_REGISTRADO = 'consentimiento_registrado';

    public const FIRMA_CONSULTADA = 'firma_consultada';

    public const AUDIO_SUBIDO = 'audio_subido';

    public const AUDIO_ESCUCHADO = 'audio_escuchado';

    public const TRANSCRIPCION_REGISTRADA = 'transcripcion_registrada';

    public const INFORME_REGISTRADO = 'informe_registrado';

    public const INFORME_PDF = 'informe_pdf';

    protected $connection = 'vbg';

    protected $table = 'vbg_auditoria';

    protected $primaryKey = 'id_auditoria';

    public $timestamps = false;

    protected $fillable = ['id_reporte', 'id_usuario', 'accion', 'detalle', 'fecha_hora'];

    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'fecha_hora' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    public static function registrar(string $accion, string $idReporte, array $detalle = []): self
    {
        return self::create([
            'id_reporte' => $idReporte,
            'id_usuario' => auth()->id(),
            'accion' => $accion,
            'detalle' => $detalle ?: null,
            'fecha_hora' => now(Actuacion::ZONA_HORARIA)->format('Y-m-d H:i:s.u'),
        ]);
    }
}

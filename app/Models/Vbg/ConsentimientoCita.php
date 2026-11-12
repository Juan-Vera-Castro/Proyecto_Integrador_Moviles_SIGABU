<?php

namespace App\Models\Vbg;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consentimiento informado de una cita VBG, con firma táctil. Si la persona atendida es menor
 * de edad, otorga el consentimiento su acudiente (firma) y también firma la persona (firma_menor).
 * Inmutable: un cambio de decisión es un registro nuevo.
 */
class ConsentimientoCita extends Model
{
    use EnBaseVbg, HasUuids;

    public const ACEPTADO = 'Aceptado';

    public const RECHAZADO = 'Rechazado';

    public const DISCO = 'vbg';

    protected $connection = 'vbg';

    protected $table = 'vbg_consentimientos_cita';

    protected $primaryKey = 'id_consentimiento';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id_consentimiento', 'id_cita', 'decision', 'firmante_nombre', 'firmante_documento', 'es_menor',
        'acudiente_nombre', 'acudiente_telefono', 'acudiente_documento', 'acudiente_parentesco',
        'version', 'texto_presentado', 'texto_sha256', 'firma_ruta', 'firma_sha256',
        'firma_menor_ruta', 'firma_menor_sha256', 'id_profesional', 'ip_origen', 'user_agent', 'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'es_menor' => 'boolean',
            'fecha_hora' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'id_cita', 'id_cita');
    }

    public function esAceptado(): bool
    {
        return $this->decision === self::ACEPTADO;
    }

    /**
     * Texto que se presenta para firmar: los datos de quien firma y el texto vigente.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function textoPara(array $datos): string
    {
        $config = config('sigabu.vbg.consentimiento_cita');
        $encabezado = 'Persona atendida: '.trim((string) ($datos['firmante_nombre'] ?? '')).', documento '.trim((string) ($datos['firmante_documento'] ?? '')).'.';

        if (! empty($datos['es_menor'])) {
            $encabezado .= "\nMenor de edad. Otorga el consentimiento su acudiente: ".trim((string) ($datos['acudiente_nombre'] ?? ''))
                .', C.C. '.trim((string) ($datos['acudiente_documento'] ?? ''))
                .', '.trim((string) ($datos['acudiente_parentesco'] ?? ''))
                .', teléfono '.trim((string) ($datos['acudiente_telefono'] ?? '')).'.';
        }

        return mb_strtoupper($config['titulo'])."\n\n".$encabezado."\n\n".$config['texto'];
    }
}

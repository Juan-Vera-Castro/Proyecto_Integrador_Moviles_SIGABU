<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trazabilidad de las acciones sensibles sobre un expediente (RNF-05, D-22): quién firmó,
 * quién consultó una firma, quién descargó un documento. Solo se insertan registros.
 */
class AuditoriaExpediente extends Model
{
    use EsInmutable;

    public const CONSENTIMIENTO_ACEPTADO = 'consentimiento_aceptado';

    public const CONSENTIMIENTO_RECHAZADO = 'consentimiento_rechazado';

    public const FIRMA_CONSULTADA = 'firma_consultada';

    public const REMISION_EXTERNA_REGISTRADA = 'remision_externa_registrada';

    public const DOCUMENTO_REMISION_PDF = 'documento_remision_pdf';

    public const DOCUMENTO_REMISION_WORD = 'documento_remision_word';

    public const DATOS_ESTUDIANTE_ACTUALIZADOS = 'datos_estudiante_actualizados';

    public const FORMATO_ACTUALIZADO = 'formato_actualizado';

    public const HISTORIA_CLINICA_REGISTRADA = 'historia_clinica_registrada';

    public const HISTORIA_CLINICA_ANOTADA = 'historia_clinica_anotada';

    public const CONSENTIMIENTO_FIRMADO_PROFESIONAL = 'consentimiento_firmado_profesional';

    public const DESISTIMIENTO_REGISTRADO = 'desistimiento_registrado';

    public const DOCUMENTO_DESISTIMIENTO_PDF = 'documento_desistimiento_pdf';

    public const SEGUIMIENTO_REGISTRADO = 'seguimiento_registrado';

    public const EVOLUCION_CONSULTADA = 'evolucion_consultada';

    public const EXPEDIENTE_CONSULTADO = 'expediente_consultado';

    public const REPORTE_INCLUSION_PDF = 'reporte_inclusion_pdf';

    public const CASO_TRASLADADO_ADMIN = 'caso_trasladado_admin';

    public const CONSENTIMIENTO_REVOCADO = 'consentimiento_revocado';

    public const CONSENTIMIENTO_PAPEL_REGISTRADO = 'consentimiento_papel_registrado';

    public const CONSENTIMIENTO_INCLUSION_PDF = 'consentimiento_inclusion_pdf';

    public const BITACORA_REGISTRADA = 'bitacora_registrada';

    public const BITACORA_ACTUALIZADA = 'bitacora_actualizada';

    public const BITACORA_ENTRADA_REGISTRADA = 'bitacora_entrada_registrada';

    public const BITACORA_ANOTADA = 'bitacora_anotada';

    public const BITACORA_PDF = 'bitacora_pdf';

    public const CASO_CERRADO_CONSTANCIA = 'caso_cerrado_constancia';

    public const CASO_REABIERTO = 'caso_reabierto';

    public const AJUSTE_PLANTILLA_REGISTRADO = 'ajuste_plantilla_registrado';

    public const PLANTILLA_PDF = 'plantilla_pdf';

    public const PLANTILLA_COMPARTIR_PDF = 'plantilla_compartir_pdf';

    public const EVIDENCIA_DESCARGADA = 'evidencia_descargada';

    public const CONSENTIMIENTO_PDF = 'consentimiento_pdf';

    public const ATENCION_REGISTRADA = 'atencion_registrada';

    public const FACTORES_ACTUALIZADOS = 'factores_actualizados';

    public const ALERTA_GENERADA = 'alerta_generada';

    public const ALERTA_ATENDIDA = 'alerta_atendida';

    protected $table = 'auditoria_expedientes';

    protected $primaryKey = 'id_auditoria';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_caso',
        'accion',
        'detalle',
        'fecha_hora',
        'ip_origen',
    ];

    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'fecha_hora' => 'datetime',
        ];
    }

    /**
     * Registra una acción del usuario autenticado, con la IP de la petición.
     */
    public static function registrar(string $accion, ?Caso $caso = null, array $detalle = []): self
    {
        return static::create([
            'id_usuario' => auth()->id(),
            'id_caso' => $caso?->id_caso,
            'accion' => $accion,
            'detalle' => $detalle ?: null,
            'fecha_hora' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            'ip_origen' => request()?->ip(),
        ]);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }
}

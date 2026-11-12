<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\Inclusion\EstadoDocumento;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Evidencia de la decisión sobre el consentimiento informado de un caso: aceptación con
 * firma táctil o rechazo. Apunta a la versión exacta del texto y guarda el texto que se
 * presentó con los datos ya reemplazados. Nunca se edita: una nueva decisión es otra fila.
 */
class ConsentimientoAceptacion extends Model
{
    use EsInmutable, HasUuids;

    public const ACEPTADO = 'Aceptado';

    public const RECHAZADO = 'Rechazado';

    /**
     * Medio por el que se otorgó (D-16). Las decisiones nuevas son por enlace: el estudiante
     * firma desde su cuenta; las de expediente son las que tomó antes el profesional.
     */
    public const MEDIO_FIRMA_EXPEDIENTE = 'firma_tactil_expediente';

    public const MEDIO_FIRMA_ENLACE = 'firma_tactil_enlace';

    /**
     * Formato de Inclusión: firmado en pantalla, o en papel y cargado por el profesional.
     */
    public const MEDIO_FIRMA_INCLUSION = 'firma_tactil_inclusion';

    public const MEDIO_FIRMA_PAPEL = 'firma_papel_inclusion';

    public const OTORGANTE_ESTUDIANTE = 'Estudiante';

    public const OTORGANTE_ACUDIENTE = 'Acudiente';

    public const TIPOS_DOCUMENTO_ACUDIENTE = ['CC', 'CE', 'TI', 'PA'];

    /**
     * Vinculación con la universidad, como en el encabezado del formato en papel.
     */
    public const VINCULACION_ESTUDIANTE = 'Estudiante';

    public const VINCULACIONES = [self::VINCULACION_ESTUDIANTE, 'Docente TC', 'Docente TCO', 'Docente HC', 'Funcionario', 'Otro'];

    public const SEMESTRE_MAX = 12;

    /**
     * Personas a quienes se avisa en una urgencia: nombre, teléfono y parentesco de cada una.
     */
    public const PARENTESCOS_URGENCIA = ['Mamá', 'Papá', 'Hermano', 'Hermana', 'Familiar', 'Amigo(a)'];

    public const MAX_CONTACTOS_URGENCIA = 3;

    protected $table = 'consentimiento_aceptaciones';

    protected $primaryKey = 'id_aceptacion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * Con microsegundos, para ordenar sin empates dos decisiones del mismo segundo.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * El id se asigna antes de insertar porque nombra el archivo de la firma en el bucket.
     */
    protected $fillable = [
        'id_aceptacion',
        'id_caso',
        'id_version',
        'decision',
        'fecha_hora',
        'medio',
        'id_profesional',
        'otorgante',
        'acudiente_nombre',
        'acudiente_tipo_documento',
        'acudiente_numero_documento',
        'acudiente_parentesco',
        'acudiente_lugar_expedicion',
        'acudiente_telefono',
        'lugar_expedicion',
        'eps_ips',
        'contacto_urgencia',
        'contactos_urgencia',
        'vinculacion',
        'programa_academico',
        'semestre',
        'dependencia_facultad',
        'texto_presentado',
        'texto_sha256',
        'firma_ruta',
        'firma_sha256',
        'firma_estudiante_ruta',
        'firma_estudiante_sha256',
        'ip_origen',
        'user_agent',
    ];

    protected $attributes = [
        'medio' => self::MEDIO_FIRMA_EXPEDIENTE,
        'otorgante' => self::OTORGANTE_ESTUDIANTE,
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'id_version' => 'integer',
            'semestre' => 'integer',
            'contactos_urgencia' => 'array',
        ];
    }

    /**
     * Resumen de los contactos de urgencia para el texto del consentimiento y para mostrar:
     * "María Pérez (Mamá) - Tel. 3001234567; Juan Pérez (Papá) - Tel. 3109876543".
     *
     * @param  iterable<array{nombre?: ?string, telefono?: ?string, parentesco?: ?string}>  $contactos
     */
    public static function resumirContactosUrgencia(iterable $contactos): ?string
    {
        $partes = [];

        foreach ($contactos as $contacto) {
            $nombre = trim((string) ($contacto['nombre'] ?? ''));
            $telefono = trim((string) ($contacto['telefono'] ?? ''));
            $parentesco = trim((string) ($contacto['parentesco'] ?? ''));

            if ($nombre === '' && $telefono === '' && $parentesco === '') {
                continue;
            }

            $partes[] = trim($nombre.($parentesco !== '' ? " ({$parentesco})" : '').($telefono !== '' ? " - Tel. {$telefono}" : ''));
        }

        return $partes === [] ? null : implode('; ', $partes);
    }

    /**
     * Contrafirma del profesional tratante, como en el formato en papel.
     */
    public function firmaProfesional(): HasOne
    {
        return $this->hasOne(FirmaProfesionalConsentimiento::class, 'id_aceptacion', 'id_aceptacion');
    }

    /**
     * Lo propio del formato de Inclusión: decisiones, revocación y firma en papel.
     */
    public function detalleInclusion(): HasOne
    {
        return $this->hasOne(ConsentimientoDetalle::class, 'id_aceptacion', 'id_aceptacion');
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(VersionConsentimiento::class, 'id_version', 'id_version');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Disco del bucket privado S3 donde se guardan las firmas.
     */
    public const DISCO_FIRMAS = 'firmas';

    /**
     * Si el disco de firmas tiene sus credenciales (pendiente PD-01).
     */
    public static function almacenamientoListo(): bool
    {
        $disco = config('filesystems.disks.'.self::DISCO_FIRMAS);

        return filled($disco['key'] ?? null) && filled($disco['secret'] ?? null) && filled($disco['endpoint'] ?? null);
    }

    public function esEnPapel(): bool
    {
        return $this->medio === self::MEDIO_FIRMA_PAPEL;
    }

    public function esRevocacion(): bool
    {
        return $this->medio === self::MEDIO_FIRMA_INCLUSION && (bool) $this->loadMissing('detalleInclusion')->detalleInclusion?->es_revocacion;
    }

    /**
     * La decisión como se muestra en el expediente.
     */
    public function etiquetaDecision(): string
    {
        return $this->esRevocacion() ? EstadoDocumento::REVOCADO : $this->decision;
    }

    public function esAceptado(): bool
    {
        return $this->decision === self::ACEPTADO;
    }

    public function loOtorgaAcudiente(): bool
    {
        return $this->otorgante === self::OTORGANTE_ACUDIENTE;
    }

    /**
     * Si es una aceptación que todavía no contrafirma el profesional tratante.
     */
    public function faltaFirmaProfesional(): bool
    {
        return $this->esAceptado() && ! $this->esEnPapel() && ! $this->firmaProfesional;
    }

    /**
     * Encabezado del formato en una línea: vinculación, programa, semestre y dependencia.
     * Null en los consentimientos aceptados antes de pedirlo.
     */
    public function encabezado(): ?string
    {
        if (! $this->vinculacion) {
            return null;
        }

        return collect([
            $this->vinculacion,
            $this->programa_academico,
            $this->semestre ? "semestre {$this->semestre}" : null,
            $this->dependencia_facultad,
        ])->filter()->implode(' · ');
    }
}

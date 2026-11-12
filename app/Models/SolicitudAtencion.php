<?php

namespace App\Models;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Al insertar una solicitud, el trigger trg_solicitud_crea_caso de la base
 * crea su caso (con codigo_expediente SM-/IN-AAAA-NNNN) y la marca "Procesada".
 *
 * El estudiante indica su facultad, su jornada y la franja horaria en la que puede asistir;
 * con la facultad y la jornada el caso llega a su psicólogo, que concierta la hora exacta.
 * Las solicitudes anteriores a la revisión del 06/10/2026 conservan el profesional, la fecha,
 * la hora, la modalidad y el motivo que se pedían entonces.
 */
class SolicitudAtencion extends Model
{
    use HasUuids;

    public const SERVICIOS = ['Salud Mental', 'Inclusión'];

    public const JORNADAS = ['Diurna', 'Nocturna'];

    public const FRANJAS = ['Mañana', 'Tarde', 'Noche', 'Todas'];

    /**
     * Modalidad de las solicitudes antiguas (ck_solicitudes_modalidad). Ya no se pide:
     * la atención es presencial y la virtual la autoriza el psicólogo.
     */
    public const MODALIDADES = ['Presencial', 'Virtual'];

    public const MOTIVO_RECHAZO_MAX = 300;

    protected $table = 'solicitudes_atencion';

    protected $primaryKey = 'id_solicitud';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_persona',
        'tipo_servicio',
        'descripcion',
        'fecha_preferida',
        'hora_preferida',
        'modalidad',
        'id_profesional_preferido',
        'fecha_respuesta',
        'motivo_rechazo',
        'id_facultad',
        'jornada',
        'franja_horaria',
        'es_emergencia',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'es_emergencia' => Booleano::class,
            'fecha_preferida' => 'date',
            'fecha_respuesta' => 'datetime',
        ];
    }

    public function fueRechazada(): bool
    {
        return $this->motivo_rechazo !== null;
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    /**
     * Profesional con el que el estudiante pidió la cita, si eligió alguno.
     */
    public function profesionalPreferido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional_preferido', 'id_usuario');
    }

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'id_facultad', 'id_facultad');
    }

    /**
     * Facultad, jornada y franja en una línea, p. ej. "Facultad de Ingeniería · Nocturna · franja Noche".
     */
    public function etiquetaAtencion(): ?string
    {
        return collect([
            $this->facultad?->nombre,
            $this->jornada,
            $this->franja_horaria ? ($this->franja_horaria === 'Todas' ? 'cualquier franja' : 'franja '.$this->franja_horaria) : null,
        ])->filter()->implode(' · ') ?: null;
    }

    public function caso(): HasOne
    {
        return $this->hasOne(Caso::class, 'id_solicitud_origen', 'id_solicitud');
    }
}

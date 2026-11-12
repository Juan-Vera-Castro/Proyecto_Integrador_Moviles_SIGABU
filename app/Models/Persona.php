<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class Persona extends Model
{
    use HasUuids;

    /**
     * Tipos de identificación del formato de remisión (ck_personas_tipo_documento).
     */
    public const TIPOS_DOCUMENTO = ['TI', 'CC', 'CE'];

    public const SEXOS = ['F', 'M'];

    public const MAYORIA_DE_EDAD = 18;

    protected $table = 'personas';

    protected $primaryKey = 'id_persona';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'numero_documento',
        'nombre_completo',
        'correo_inst',
        'tipo_documento',
        'sexo',
        'fecha_nacimiento',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudAtencion::class, 'id_persona', 'id_persona');
    }

    /**
     * Casos que nacieron de las solicitudes de la persona (uno por solicitud).
     */
    public function casos(): HasManyThrough
    {
        return $this->hasManyThrough(Caso::class, SolicitudAtencion::class, 'id_persona', 'id_solicitud_origen', 'id_persona', 'id_solicitud');
    }

    /**
     * Años cumplidos en $fecha, o null si no hay fecha de nacimiento (RN-11).
     */
    public function edadEn(CarbonInterface $fecha): ?int
    {
        return $this->fecha_nacimiento
            ? (int) $this->fecha_nacimiento->copy()->startOfDay()->diffInYears($fecha->copy()->startOfDay())
            : null;
    }

    /**
     * Si en $fecha es menor de edad; null si no se sabe por falta de fecha de nacimiento.
     */
    public function esMenorEn(CarbonInterface $fecha): ?bool
    {
        $edad = $this->edadEn($fecha);

        return $edad === null ? null : $edad < self::MAYORIA_DE_EDAD;
    }

    /**
     * Si tiene los datos que el formato de remisión toma del registro (D-01).
     */
    public function datosRemisionCompletos(): bool
    {
        return $this->tipo_documento && $this->sexo && $this->fecha_nacimiento;
    }

    /**
     * Datos académicos registrados en su vinculación: facultad (del catálogo), programa,
     * jornada y semestre. La solicitud de cita los toma de aquí en lugar de preguntarlos.
     *
     * @return array{vinculacion: ?string, facultad: ?Facultad, programa: ?string, jornada: ?string, semestre: ?int}
     */
    public function datosAcademicos(): array
    {
        $vinculacion = DB::table('vinculacion')->where('id_persona', $this->id_persona)->orderBy('id_vinculacion')->first();

        $facultad = isset($vinculacion->id_facultad) && $vinculacion->id_facultad
            ? Facultad::find($vinculacion->id_facultad)
            : Facultad::deTexto($vinculacion->facultad ?? null);

        return [
            'vinculacion' => $vinculacion->tipo_vinculacion ?? null,
            'facultad' => $facultad?->activa ? $facultad : null,
            'programa' => $vinculacion->programa_academico ?? null,
            'jornada' => in_array($vinculacion->jornada ?? null, SolicitudAtencion::JORNADAS, true) ? $vinculacion->jornada : null,
            'semestre' => isset($vinculacion->semestre) ? (int) $vinculacion->semestre : null,
        ];
    }
}

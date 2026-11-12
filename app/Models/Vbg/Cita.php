<?php

namespace App\Models\Vbg;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cita de un caso VBG (base sigabu_vbg). El caso (vbg_reportes) y el profesional (usuarios)
 * están en la base principal: se enlazan por id, sin llave foránea entre bases.
 */
class Cita extends Model
{
    use EnBaseVbg, HasUuids;

    public const PRESENCIAL = 'Presencial';

    public const VIRTUAL = 'Virtual';

    public const MODALIDADES = [self::PRESENCIAL, self::VIRTUAL];

    public const PROGRAMADA = 'Programada';

    public const EN_CURSO = 'En curso';

    public const COMPLETADA = 'Completada';

    public const CANCELADA = 'Cancelada';

    public const ESTADOS = [self::PROGRAMADA, self::EN_CURSO, self::COMPLETADA, self::CANCELADA];

    /**
     * Cambios de estado permitidos.
     */
    public const TRANSICIONES = [
        self::PROGRAMADA => [self::EN_CURSO, self::CANCELADA],
        self::EN_CURSO => [self::COMPLETADA, self::CANCELADA],
        self::COMPLETADA => [],
        self::CANCELADA => [],
    ];

    protected $connection = 'vbg';

    protected $table = 'vbg_citas';

    protected $primaryKey = 'id_cita';

    public $timestamps = false;

    protected $fillable = [
        'id_reporte', 'id_profesional', 'fecha', 'hora_inicio', 'hora_fin', 'modalidad', 'lugar',
        'motivo', 'notas_previas', 'estado', 'creado_en', 'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
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

    public function consentimientos(): HasMany
    {
        return $this->hasMany(ConsentimientoCita::class, 'id_cita', 'id_cita')->orderBy('fecha_hora');
    }

    public function audios(): HasMany
    {
        return $this->hasMany(Audio::class, 'id_cita', 'id_cita')->orderBy('creado_en');
    }

    /**
     * La última decisión sobre el consentimiento de esta cita.
     */
    public function consentimientoVigente(): ?ConsentimientoCita
    {
        return $this->relationLoaded('consentimientos')
            ? $this->consentimientos->last()
            : $this->consentimientos()->reorder('fecha_hora', 'desc')->first();
    }

    public function tieneConsentimientoAceptado(): bool
    {
        return $this->consentimientoVigente()?->decision === ConsentimientoCita::ACEPTADO;
    }

    public function puedePasarA(string $estado): bool
    {
        return in_array($estado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    public function estaAbierta(): bool
    {
        return in_array($this->estado, [self::PROGRAMADA, self::EN_CURSO], true);
    }

    /**
     * "08:00 – 09:00" sin segundos, venga la hora de PostgreSQL o de SQLite.
     */
    public function horario(): string
    {
        return substr((string) $this->hora_inicio, 0, 5).' – '.substr((string) $this->hora_fin, 0, 5);
    }
}

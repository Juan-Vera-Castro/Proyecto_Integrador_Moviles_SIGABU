<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Actuaciones registradas sobre un caso. Las citas de asesoría psicológica (HU-011)
 * son las actuaciones de tipo "Consulta": caso + profesional + fecha_hora. Las
 * remisiones (HU-007) son las de tipo "Remisión": id_profesional es quien remite.
 */
class Actuacion extends Model
{
    use HasUuids;

    public const TIPO_CITA = 'Consulta';

    public const TIPO_REMISION = 'Remisión';

    /**
     * Destino de la remisión, según ck_actuaciones_remision.
     */
    public const DESTINO_INTERNA = 'Interna';

    public const DESTINO_EXTERNA = 'Externa';

    public const ENTIDAD_OTRA = 'Otra';

    /**
     * Entidades externas a las que se remite; "Otra" pide escribir el nombre.
     */
    public const ENTIDADES = [
        'EPS / IPS de salud',
        'Servicio de urgencias',
        'Centro de salud mental / Hospital',
        self::ENTIDAD_OTRA,
    ];

    /**
     * Las remisiones a Comisaría, Fiscalía o ICBF por violencia las tramita la abogada con los
     * formatos de esas entidades (RN-12, D-07): ya no se eligen aquí, ni escritas en "Otra".
     */
    public const ENTIDADES_FUERA_DE_ALCANCE = ['comisaría', 'comisaria', 'fiscalía', 'fiscalia', 'icbf', 'bienestar familiar'];

    public const ENTIDAD_MAX = 150;

    public const MOTIVO_REMISION_MAX = 3000;

    /**
     * Motivo que queda en las citas pasadas sin asistencia registrada al remitir el caso.
     */
    public const MOTIVO_SIN_REGISTRO = 'Sin registro de asistencia al remitir el caso.';

    /**
     * Franjas de atención de Bienestar (las mismas del paso "Fecha y hora" del diseño).
     */
    public const HORARIOS = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];

    public const DURACION_MINUTOS = 60;

    public const OBSERVACIONES_MAX = 300;

    /**
     * Asistencia a la cita (HU-012), según ck_actuaciones_asistencia.
     */
    public const ASISTENCIA_PENDIENTE = 'Pendiente';

    public const ASISTIO = 'Asistió';

    public const NO_ASISTIO = 'No asistió';

    /**
     * Cita próxima que la remisión del caso dejó sin efecto (HU-007).
     */
    public const CANCELADA = 'Cancelada';

    public const MOTIVO_MAX = 300;

    /**
     * fecha_hora es "timestamp without time zone": guarda la hora local de la universidad.
     */
    public const ZONA_HORARIA = 'America/Bogota';

    protected $table = 'actuaciones';

    protected $primaryKey = 'id_actuacion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_profesional',
        'tipo_actuacion',
        'fecha_hora',
        'descripcion',
        'estado_asistencia',
        'motivo_inasistencia',
        'destino_remision',
        'id_profesional_destino',
        'entidad_destino',
        'id_remision',
        'autorizacion_traslado',
        'id_admin_traslado',
    ];

    protected $attributes = [
        'estado_asistencia' => self::ASISTENCIA_PENDIENTE,
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Profesional que recibe el caso en una remisión interna.
     */
    public function profesionalDestino(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional_destino', 'id_usuario');
    }

    /**
     * Administrador que hizo el traslado, si el caso no lo remitió su profesional.
     */
    public function adminTraslado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin_traslado', 'id_usuario');
    }

    /**
     * Remisión que canceló esta cita o la cerró sin asistencia.
     */
    public function remision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_remision', 'id_actuacion');
    }

    /**
     * Detalle con el formato GBU-GPGR-F-016, solo en remisiones externas registradas
     * desde que existe ese formato.
     */
    public function remisionExterna(): HasOne
    {
        return $this->hasOne(RemisionExterna::class, 'id_actuacion', 'id_actuacion');
    }

    /**
     * Nota de evolución de la cita, si el estudiante asistió y ya se registró (HU-015).
     */
    public function seguimiento(): HasOne
    {
        return $this->hasOne(SeguimientoEvolucion::class, 'id_actuacion', 'id_actuacion');
    }

    public function scopeCitas(Builder $query): Builder
    {
        return $query->where('tipo_actuacion', self::TIPO_CITA);
    }

    public function scopeRemisiones(Builder $query): Builder
    {
        return $query->where('tipo_actuacion', self::TIPO_REMISION);
    }

    /**
     * Citas del profesional que se cruzan con la franja que empieza en $inicio.
     * Las canceladas ya no ocupan la franja.
     */
    public function scopeCruzanCon(Builder $query, string $idProfesional, Carbon $inicio): Builder
    {
        return $query->citas()
            ->where('estado_asistencia', '<>', self::CANCELADA)
            ->where('id_profesional', $idProfesional)
            ->where('fecha_hora', '>', $inicio->copy()->subMinutes(self::DURACION_MINUTOS)->toDateTimeString())
            ->where('fecha_hora', '<', $inicio->copy()->addMinutes(self::DURACION_MINUTOS)->toDateTimeString());
    }

    /**
     * Arma la fecha y hora local de una cita a partir de los valores del formulario.
     */
    public static function inicioDeFranja(string $fecha, string $hora): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "$fecha $hora", self::ZONA_HORARIA)->startOfMinute();
    }

    public static function horarioOcupado(string $idProfesional, Carbon $inicio): bool
    {
        return static::query()->cruzanCon($idProfesional, $inicio)->exists();
    }

    /**
     * Franjas del día que ya no se pueden elegir: ocupadas por otra cita del profesional
     * o que ya pasaron.
     *
     * @return array{ocupadas: list<string>, pasadas: list<string>}
     */
    public static function disponibilidadDelDia(string $idProfesional, string $fecha): array
    {
        $ahora = now(self::ZONA_HORARIA);
        $ocupadas = [];
        $pasadas = [];

        $citas = static::query()->citas()
            ->where('estado_asistencia', '<>', self::CANCELADA)
            ->where('id_profesional', $idProfesional)
            ->where('fecha_hora', '>', self::inicioDeFranja($fecha, '00:00')->subMinutes(self::DURACION_MINUTOS)->toDateTimeString())
            ->where('fecha_hora', '<', self::inicioDeFranja($fecha, '23:59')->addMinutes(self::DURACION_MINUTOS)->toDateTimeString())
            ->pluck('fecha_hora');

        foreach (self::HORARIOS as $hora) {
            $inicio = self::inicioDeFranja($fecha, $hora);

            if ($inicio->lte($ahora)) {
                $pasadas[] = $hora;
            }

            $seCruza = $citas->contains(fn (Carbon $fechaHora) => abs($fechaHora->shiftTimezone(self::ZONA_HORARIA)->diffInMinutes($inicio)) < self::DURACION_MINUTOS);

            if ($seCruza) {
                $ocupadas[] = $hora;
            }
        }

        return ['ocupadas' => $ocupadas, 'pasadas' => $pasadas];
    }

    /**
     * Etiqueta de una franja, p. ej. "08:00" → "8:00 am".
     */
    public static function etiquetaHora(string $hora): string
    {
        return Carbon::createFromFormat('H:i', $hora)->format('g:i a');
    }

    public function esProxima(): bool
    {
        return $this->fecha_hora->shiftTimezone(self::ZONA_HORARIA)->isFuture();
    }

    public function asistenciaPendiente(): bool
    {
        return $this->estado_asistencia === self::ASISTENCIA_PENDIENTE;
    }

    public function estaCancelada(): bool
    {
        return $this->estado_asistencia === self::CANCELADA;
    }

    public function esRemisionInterna(): bool
    {
        return $this->destino_remision === self::DESTINO_INTERNA;
    }

    /**
     * Traslado de último recurso que hizo el administrador, no el profesional del caso.
     */
    public function esTrasladoAdministrativo(): bool
    {
        return $this->id_admin_traslado !== null;
    }

    public function etiquetaAutorizacion(): string
    {
        return $this->esTrasladoAdministrativo() ? 'Autorizado por' : 'Traslado a otra facultad autorizado por';
    }

    /**
     * Al salir el caso de manos del profesional, esta remisión cierra lo que quedó pendiente: las
     * citas próximas se cancelan, las pasadas quedan sin asistencia y las tareas se cancelan.
     *
     * @return array{citas_canceladas: int, citas_sin_asistencia: int, tareas_canceladas: int}
     */
    public function liberarPendientesDelCaso(): array
    {
        $ahora = $this->fecha_hora->toDateTimeString();
        $citasPendientes = self::citas()
            ->where('id_caso', $this->id_caso)
            ->where('estado_asistencia', self::ASISTENCIA_PENDIENTE);

        return [
            'citas_canceladas' => (clone $citasPendientes)->where('fecha_hora', '>', $ahora)->update([
                'estado_asistencia' => self::CANCELADA,
                'id_remision' => $this->id_actuacion,
            ]),
            'citas_sin_asistencia' => (clone $citasPendientes)->where('fecha_hora', '<=', $ahora)->update([
                'estado_asistencia' => self::NO_ASISTIO,
                'motivo_inasistencia' => self::MOTIVO_SIN_REGISTRO,
                'id_remision' => $this->id_actuacion,
            ]),
            'tareas_canceladas' => TareaTerapeutica::where('id_caso', $this->id_caso)
                ->where('estado', TareaTerapeutica::ESTADO_PENDIENTE)
                ->update([
                    'estado' => TareaTerapeutica::ESTADO_CANCELADA,
                    'id_remision' => $this->id_actuacion,
                ]),
        ];
    }

    /**
     * A quién se remitió: el nombre del profesional o la entidad externa.
     */
    public function etiquetaDestino(): string
    {
        return $this->esRemisionInterna()
            ? ($this->profesionalDestino?->persona?->nombre_completo ?? 'otro profesional')
            : (string) $this->entidad_destino;
    }

    /**
     * La asistencia se registra una sola vez y solo cuando la cita ya empezó.
     */
    public function admiteRegistroDeAsistencia(): bool
    {
        return $this->asistenciaPendiente() && ! $this->esProxima();
    }
}

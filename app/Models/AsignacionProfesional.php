<?php

namespace App\Models;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qué profesional atiende un servicio en una facultad y jornada. Las solicitudes llegan
 * directo a él (revisión del prototipo, 06/10/2026). No se borra: se desactiva.
 */
class AsignacionProfesional extends Model
{
    protected $table = 'asignaciones_profesional';

    protected $primaryKey = 'id_asignacion';

    public $timestamps = false;

    protected $fillable = [
        'id_profesional',
        'tipo_servicio',
        'id_facultad',
        'jornada',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => Booleano::class,
        ];
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'id_facultad', 'id_facultad');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', Booleano::valor(true, $query->getConnection()->getDriverName()));
    }

    /**
     * Profesionales activos asignados a un servicio en una facultad (y, si se indica, una jornada).
     */
    public static function profesionalesDe(string $servicio, ?int $idFacultad, ?string $jornada = null): Builder
    {
        return User::profesionalesActivos()->whereIn('id_usuario', static::activas()
            ->where('tipo_servicio', $servicio)
            ->where('id_facultad', $idFacultad)
            ->when($jornada, fn (Builder $query) => $query->where('jornada', $jornada))
            ->select('id_profesional'));
    }

    /**
     * Profesional al que llega una solicitud, en cascada: el asignado a ese servicio, facultad
     * y jornada; si no hay, uno de la misma facultad en otra jornada; si tampoco, cualquiera
     * que atienda ese servicio. Entre los candidatos, el de menos casos activos (y, si empatan,
     * por nombre). Null solo si nadie atiende el servicio: el caso queda sin asignar.
     */
    public static function profesionalPara(string $servicio, ?int $idFacultad, ?string $jornada): ?User
    {
        $niveles = [
            fn () => $idFacultad && $jornada ? static::profesionalesDe($servicio, $idFacultad, $jornada) : null,
            fn () => $idFacultad ? static::profesionalesDe($servicio, $idFacultad) : null,
            fn () => User::profesionalesActivos()->whereIn('id_usuario', static::activas()->where('tipo_servicio', $servicio)->select('id_profesional')),
        ];

        foreach ($niveles as $nivel) {
            $consulta = $nivel();

            if ($consulta && ($profesional = static::elMenosCargado($consulta))) {
                return $profesional;
            }
        }

        return null;
    }

    private static function elMenosCargado(Builder $candidatos): ?User
    {
        return $candidatos
            ->with('persona')
            ->withCount(['casosAsignados as casos_activos' => fn (Builder $casos) => $casos->activos()])
            ->get()
            ->sortBy([['casos_activos', 'asc'], [fn (User $a, User $b) => strcmp((string) $a->persona?->nombre_completo, (string) $b->persona?->nombre_completo)]])
            ->first();
    }

    /**
     * Asigna las solicitudes que quedaron sin profesional, con la misma cascada. Se llama al
     * crear o activar una asignación, para que no queden esperando. Devuelve cuántas asignó.
     */
    public static function asignarPendientes(): int
    {
        $asignados = 0;

        foreach (Caso::sinAsignar()->with('solicitud')->orderBy('fecha_apertura')->get() as $caso) {
            $solicitud = $caso->solicitud;
            $profesional = $solicitud ? static::profesionalPara($solicitud->tipo_servicio, $solicitud->id_facultad, $solicitud->jornada) : null;

            if ($profesional && Caso::whereKey($caso->id_caso)->sinAsignar()->update(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $profesional->id_usuario])) {
                $asignados++;
            }
        }

        return $asignados;
    }
}

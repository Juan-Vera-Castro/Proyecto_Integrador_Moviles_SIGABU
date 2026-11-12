<?php

namespace App\Support;

use App\Casts\Booleano;
use App\Models\Caso;
use App\Models\User;
use App\Notifications\NuevaSolicitudCita;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Avisos que salen en el momento en que ocurre algo (los programados están en Recordatorios).
 *
 * Un aviso nunca debe tumbar la acción que lo originó: si falla, se reporta en el log y el
 * usuario sigue su flujo normal.
 */
class Avisos
{
    /**
     * Nueva solicitud de cita: al profesional asignado o, si no hay, a los profesionales activos
     * del área. Si nadie atiende el área, a los administradores, para que no quede sin ver.
     */
    public function nuevaSolicitud(Caso $caso): void
    {
        rescue(function () use ($caso) {
            $caso->loadMissing(['solicitud', 'profesional.persona']);

            Notification::send($this->destinatariosDeSolicitud($caso), new NuevaSolicitudCita($caso));
        }, report: true);
    }

    /**
     * @return Collection<int, User>
     */
    private function destinatariosDeSolicitud(Caso $caso): Collection
    {
        if ($caso->profesional) {
            return new Collection([$caso->profesional]);
        }

        $profesionales = User::profesionalesActivos()
            ->queAtienden($caso->solicitud?->tipo_servicio)
            ->with('persona')
            ->get();

        return $profesionales->isNotEmpty() ? $profesionales : $this->administradoresActivos();
    }

    /**
     * @return Collection<int, User>
     */
    private function administradoresActivos(): Collection
    {
        return User::query()
            ->where('estado_activo', Booleano::valor(true, User::query()->getConnection()->getDriverName()))
            ->whereHas('rolReal', fn (Builder $rol) => $rol->where('nombre_rol', 'Admin'))
            ->with('persona')
            ->get();
    }
}

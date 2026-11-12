<?php

namespace App\Notifications;

use App\Models\Actuacion;
use App\Models\User;

/**
 * Recordatorio de una cita próxima, al estudiante y al profesional.
 */
class RecordatorioCita extends AvisoSigabu
{
    public function __construct(public Actuacion $cita, public int $horasAntes)
    {
        parent::__construct();
    }

    protected function contenido(User $notifiable): ContenidoAviso
    {
        $cita = $this->cita->loadMissing(['caso', 'profesional.persona']);
        $cuando = $this->cuando();

        if ($cita->id_profesional === $notifiable->id_usuario) {
            return new ContenidoAviso(
                titulo: 'Recordatorio de cita',
                mensaje: "Tienes una cita del expediente {$cita->caso->codigo_expediente} {$cuando}.",
                url: route('citas.agenda', absolute: false),
                textoAccion: 'Ver mi agenda',
            );
        }

        $profesional = $cita->profesional?->name;

        return new ContenidoAviso(
            titulo: 'Recordatorio de tu cita',
            mensaje: 'Tienes una cita en Bienestar Universitario '.$cuando.($profesional ? " con {$profesional}" : '').'. Si no puedes asistir, avísale a tu profesional.',
            url: route('citas.miscitas', absolute: false),
            textoAccion: 'Ver mis citas',
        );
    }

    /**
     * Sale solo si la cita sigue pendiente y todavía no empieza.
     */
    protected function sigueVigente(User $notifiable): bool
    {
        $cita = $this->cita->fresh();

        return $cita !== null && $cita->asistenciaPendiente() && $cita->esProxima();
    }

    protected function profesionalRemitente(User $notifiable): ?User
    {
        return $this->cita->id_profesional === $notifiable->id_usuario ? null : $this->cita->profesional;
    }

    /**
     * "el martes 10 de noviembre a las 9:00 am".
     */
    private function cuando(): string
    {
        $fecha = $this->cita->fecha_hora;

        return 'el '.$fecha->locale('es')->translatedFormat('l j \d\e F').' a las '.$fecha->format('g:i a');
    }
}

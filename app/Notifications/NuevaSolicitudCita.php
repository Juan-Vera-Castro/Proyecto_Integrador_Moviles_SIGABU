<?php

namespace App\Notifications;

use App\Models\Caso;
use App\Models\User;

/**
 * Al profesional: le llegó una solicitud de cita (a él, o sin asignar en su área).
 * Las de emergencia salen con prioridad alta.
 */
class NuevaSolicitudCita extends AvisoSigabu
{
    public function __construct(public Caso $caso)
    {
        parent::__construct();
    }

    protected function contenido(User $notifiable): ContenidoAviso
    {
        $caso = $this->caso->loadMissing('solicitud');
        $servicio = $caso->solicitud?->tipo_servicio ?? 'Bienestar';
        $asignada = $caso->id_profesional === $notifiable->id_usuario;

        $mensaje = $asignada
            ? "Te asignaron la solicitud de {$servicio} del expediente {$caso->codigo_expediente}. Acuerda con el estudiante la fecha de la primera cita."
            : "Llegó la solicitud de {$servicio} del expediente {$caso->codigo_expediente} y todavía no tiene profesional asignado.";

        return $caso->esEmergencia()
            ? new ContenidoAviso(
                titulo: 'Solicitud de emergencia',
                mensaje: $mensaje.' El estudiante la marcó como emergencia: atiéndela con prioridad.',
                url: route('solicitudes.index', absolute: false),
                textoAccion: 'Ver la solicitud',
                prioridad: ContenidoAviso::PRIORIDAD_ALTA,
            )
            : new ContenidoAviso(
                titulo: 'Nueva solicitud de cita',
                mensaje: $mensaje,
                url: route('solicitudes.index', absolute: false),
                textoAccion: 'Ver la solicitud',
            );
    }

    /**
     * Ya no aplica si el caso se cerró o lo tomó otro profesional mientras el aviso esperaba.
     */
    protected function sigueVigente(User $notifiable): bool
    {
        $caso = $this->caso->fresh();

        return $caso !== null
            && $caso->estado_caso !== Caso::ESTADO_CERRADO
            && in_array($caso->id_profesional, [null, $notifiable->id_usuario], true);
    }
}

<?php

namespace App\Notifications;

use App\Models\TareaTerapeutica;
use App\Models\User;

/**
 * La fecha límite de una tarea terapéutica pasó y sigue sin evaluar: al estudiante se le
 * recuerda y al profesional se le pide registrar si la cumplió.
 */
class TareaVencida extends AvisoSigabu
{
    public function __construct(public TareaTerapeutica $tarea)
    {
        parent::__construct();
    }

    protected function contenido(User $notifiable): ContenidoAviso
    {
        $tarea = $this->tarea->loadMissing('caso');
        $fecha = $tarea->fecha_limite->locale('es')->translatedFormat('j \d\e F');

        if ($tarea->id_profesional === $notifiable->id_usuario) {
            return new ContenidoAviso(
                titulo: 'Tarea por evaluar',
                mensaje: "La tarea «{$tarea->titulo}» del expediente {$tarea->caso->codigo_expediente} venció el {$fecha}. Registra si el estudiante la cumplió.",
                url: route('casos.show', $tarea->caso, absolute: false),
                textoAccion: 'Ir al expediente',
            );
        }

        return new ContenidoAviso(
            titulo: 'Tu tarea venció',
            mensaje: "La fecha límite de tu tarea «{$tarea->titulo}» fue el {$fecha}. Si ya la hiciste, coméntaselo a tu profesional en la próxima cita.",
            url: route('estudiante.home', absolute: false),
            textoAccion: 'Ver mis tareas',
        );
    }

    protected function sigueVigente(User $notifiable): bool
    {
        return (bool) $this->tarea->fresh()?->estaPendiente();
    }

    protected function profesionalRemitente(User $notifiable): ?User
    {
        return $this->tarea->id_profesional === $notifiable->id_usuario ? null : $this->tarea->profesional;
    }
}

<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base de los avisos de SIGABU: la campana (canal database) y el correo institucional.
 *
 * - La campana se guarda en el momento (conexión sync): se ve aunque el worker de colas no
 *   esté corriendo. El correo va por la cola, con reintentos, para no demorar la página.
 * - afterCommit: si el aviso nace dentro de una transacción, solo sale cuando se confirma;
 *   nunca se avisa de algo que al final no se guardó.
 * - shouldSend() vuelve a comprobar el pendiente justo antes de enviar: si la cita se canceló
 *   o el consentimiento ya se firmó mientras el correo esperaba en la cola, no sale.
 */
abstract class AvisoSigabu extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Intentos del correo si el servidor SMTP falla, y segundos de espera entre ellos.
     */
    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * Texto del aviso para quien lo recibe (el estudiante y el profesional leen cosas distintas).
     */
    abstract protected function contenido(User $notifiable): ContenidoAviso;

    /**
     * Si el aviso todavía aplica. Por defecto, siempre.
     */
    protected function sigueVigente(User $notifiable): bool
    {
        return true;
    }

    /**
     * Profesional que firma el correo dirigido al estudiante: el estudiante ve su nombre como
     * remitente y, si responde, la respuesta le llega a ese profesional.
     */
    protected function profesionalRemitente(User $notifiable): ?User
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        if (! $notifiable->estado_activo) {
            return [];
        }

        $canales = ['database'];

        if (config('sigabu.notificaciones.correo') && filled($notifiable->email)) {
            $canales[] = 'mail';
        }

        return $canales;
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $this->sigueVigente($notifiable);
    }

    /**
     * Lo que guarda la campana.
     *
     * @return array{titulo: string, mensaje: string, url: string, prioridad: string}
     */
    public function toArray(User $notifiable): array
    {
        $contenido = $this->contenido($notifiable);

        return [
            'titulo' => $contenido->titulo,
            'mensaje' => $contenido->mensaje,
            'url' => $contenido->url,
            'prioridad' => $contenido->prioridad,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $contenido = $this->contenido($notifiable);
        $asunto = ($contenido->esPrioritario() ? '[URGENTE] ' : '').$contenido->titulo.' · SIGABU';

        $correo = (new MailMessage)
            ->subject($asunto)
            ->greeting('Hola, '.($notifiable->name ?? 'usuario').'.')
            ->line($contenido->mensaje)
            ->action($contenido->textoAccion, url($contenido->url))
            ->line('Por confidencialidad, los detalles están solo dentro de SIGABU.')
            ->salutation('Vicerrectoría de Bienestar Universitario · UCEVA');

        if ($contenido->esPrioritario()) {
            $correo->priority(1);
        }

        $profesional = $this->profesionalRemitente($notifiable);

        if ($profesional && filled($profesional->email)) {
            // La cuenta del sistema envía (SPF/DMARC); el nombre y la respuesta son del profesional.
            $correo->from(config('mail.from.address'), $profesional->name.' · SIGABU')
                ->replyTo($profesional->email, $profesional->name);
        }

        return $correo;
    }
}

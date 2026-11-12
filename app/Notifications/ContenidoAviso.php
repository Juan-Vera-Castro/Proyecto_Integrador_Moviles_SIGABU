<?php

namespace App\Notifications;

/**
 * Lo que dice un aviso, igual en la campana y en el correo. La url es relativa (p. ej.
 * "/estudiante/mis-citas"): la campana la usa tal cual y el correo la completa con APP_URL.
 *
 * Los textos no llevan información clínica (motivos, diagnósticos ni notas): el correo sale
 * del sistema y puede leerse fuera de SIGABU.
 */
final readonly class ContenidoAviso
{
    public const PRIORIDAD_NORMAL = 'normal';

    public const PRIORIDAD_ALTA = 'alta';

    public function __construct(
        public string $titulo,
        public string $mensaje,
        public string $url,
        public string $textoAccion,
        public string $prioridad = self::PRIORIDAD_NORMAL,
    ) {}

    public function esPrioritario(): bool
    {
        return $this->prioridad === self::PRIORIDAD_ALTA;
    }
}

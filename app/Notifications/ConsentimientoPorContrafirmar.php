<?php

namespace App\Notifications;

use App\Models\ConsentimientoAceptacion;
use App\Models\User;

/**
 * Al profesional: el estudiante ya firmó el consentimiento y falta su contrafirma.
 */
class ConsentimientoPorContrafirmar extends AvisoSigabu
{
    public function __construct(public ConsentimientoAceptacion $aceptacion)
    {
        parent::__construct();
    }

    protected function contenido(User $notifiable): ContenidoAviso
    {
        $caso = $this->aceptacion->loadMissing('caso')->caso;

        return new ContenidoAviso(
            titulo: 'Consentimiento pendiente de tu firma',
            mensaje: "El consentimiento informado del expediente {$caso->codigo_expediente} ya lo firmó el estudiante. Falta tu firma como profesional tratante.",
            url: route('casos.show', $caso, absolute: false),
            textoAccion: 'Firmar en el expediente',
        );
    }

    protected function sigueVigente(User $notifiable): bool
    {
        $aceptacion = $this->aceptacion->fresh();

        return $aceptacion !== null && $aceptacion->faltaFirmaProfesional();
    }
}

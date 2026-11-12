<?php

namespace App\Notifications;

use App\Models\Caso;
use App\Models\User;

/**
 * Al estudiante: su caso ya tiene profesional y le falta firmar el consentimiento informado.
 */
class ConsentimientoPorFirmar extends AvisoSigabu
{
    public function __construct(public Caso $caso)
    {
        parent::__construct();
    }

    protected function contenido(User $notifiable): ContenidoAviso
    {
        return new ContenidoAviso(
            titulo: 'Firma tu consentimiento informado',
            mensaje: "Para continuar con tu atención (expediente {$this->caso->codigo_expediente}) necesitas leer y firmar el consentimiento informado.",
            url: route('consentimientos.firmar', $this->caso, absolute: false),
            textoAccion: 'Firmar el consentimiento',
        );
    }

    /**
     * Ya no aplica si el caso dejó de estar activo o ya tiene una decisión registrada.
     */
    protected function sigueVigente(User $notifiable): bool
    {
        $caso = $this->caso->fresh();

        return $caso !== null && $caso->estaActivo() && ! $caso->aceptacionesConsentimiento()->exists();
    }

    protected function profesionalRemitente(User $notifiable): ?User
    {
        return $this->caso->profesional;
    }
}

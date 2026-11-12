<?php

namespace App\Policies;

use App\Models\Caso;
use App\Models\User;

/**
 * Permisos de los formatos de Inclusión (sección 2 de la spec). Se aplican en el servidor en
 * cada petición, incluidas las descargas: ocultar un botón no basta (RN-05).
 */
class CasoInclusionPolicy
{
    private const SERVICIO = 'Inclusión';

    /**
     * Lo gestiona solo el profesional asignado y de área Inclusión o Ambas: bitácora, plantilla,
     * consentimiento en papel, cierre y sus PDF.
     */
    public function gestionar(User $usuario, Caso $caso): bool
    {
        return $caso->esDeInclusion()
            && $usuario->role === 'profesional'
            && $caso->id_profesional === $usuario->id_usuario
            && $usuario->atiendeServicio(self::SERVICIO);
    }

    /**
     * La persona ve solo su propio consentimiento; el profesional asignado y el administrador
     * siguen viéndolo, como la evidencia actual.
     */
    public function verConsentimiento(User $usuario, Caso $caso): bool
    {
        return $caso->esDeInclusion()
            && ($this->esDueno($usuario, $caso) || $usuario->role === 'admin' || $this->gestionar($usuario, $caso));
    }

    /**
     * Firmar, revocar y guardar el borrador: solo la persona dueña del caso.
     */
    public function diligenciarConsentimiento(User $usuario, Caso $caso): bool
    {
        return $caso->esDeInclusion() && $this->esDueno($usuario, $caso);
    }

    public function consultarRecursos(User $usuario): bool
    {
        return $usuario->role === 'profesional' && $usuario->atiendeServicio(self::SERVICIO);
    }

    public function reabrir(User $usuario, Caso $caso): bool
    {
        return $caso->esDeInclusion() && $usuario->role === 'admin';
    }

    private function esDueno(User $usuario, Caso $caso): bool
    {
        return $usuario->role === 'estudiante' && $caso->esDelEstudiante($usuario);
    }
}

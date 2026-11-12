<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La app móvil reutiliza las acciones de los controladores web (solicitar cita, elegir horario,
 * revocar, reportar...), así las reglas de negocio viven en un solo lugar. Esas acciones
 * terminan en una redirección con un mensaje en la sesión; aquí se convierte en JSON:
 *
 * - 'error' en la sesión => 409 {"message": "..."}
 * - si no, 200 {"mensaje": "...", "datos": ...}
 *
 * Los errores de validación ya salen en JSON (bootstrap/app.php: api/* responde JSON).
 * Las rutas de la API no inician sesión: los mensajes quedan en el almacén de sesión en
 * memoria solo durante esta petición.
 */
class RespuestaJsonMovil
{
    /**
     * Códigos de estado que algunas acciones guardan en lugar de un texto.
     */
    private const MENSAJES = [
        'profile-updated' => 'Tus datos quedaron actualizados.',
        'password-updated' => 'Tu contraseña quedó actualizada.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if (! $respuesta instanceof RedirectResponse) {
            return $respuesta;
        }

        $sesion = app('session.store');

        if ($error = $sesion->get('error')) {
            return response()->json(['message' => $error], 409);
        }

        $estado = $sesion->get('status');

        return response()->json(array_filter([
            'mensaje' => self::MENSAJES[$estado] ?? $estado,
            'datos' => $sesion->get('reporte_enviado'),
        ], fn ($valor) => $valor !== null));
    }
}

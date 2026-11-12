<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el administrador desactiva una cuenta, su sesión abierta se cierra en el siguiente clic
 * en lugar de seguir viva hasta que expire.
 */
class CerrarSesionInactiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->estado_activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => 'La cuenta está inactiva.'], 401)
                : redirect()->route('login')->withErrors(['email' => 'Tu cuenta está inactiva. Comunícate con Bienestar Universitario.']);
        }

        return $next($request);
    }
}

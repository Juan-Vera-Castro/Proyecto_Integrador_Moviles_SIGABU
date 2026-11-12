<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API de la app móvil es solo para el rol estudiante. A diferencia de CheckRole, no
 * redirige al panel del rol: responde 403 en JSON.
 */
class SoloEstudianteMovil
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->role !== 'estudiante' || ! $usuario->estado_activo) {
            return response()->json(['message' => 'La app móvil de SIGABU es solo para estudiantes con la cuenta activa.'], 403);
        }

        return $next($request);
    }
}

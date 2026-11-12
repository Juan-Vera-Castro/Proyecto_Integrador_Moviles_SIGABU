<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Si no hay usuario o su rol no está en la lista de permitidos para esta ruta
        if (! $user || ! in_array($user->role, $roles)) {
            // Lo devuelve a su propio panel o al login
            return redirect($user ? $user->getDashboardUrl() : route('login'));
        }

        return $next($request);
    }
}
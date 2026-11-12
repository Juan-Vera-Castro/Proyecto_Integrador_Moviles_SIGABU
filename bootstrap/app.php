<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\CerrarSesionInactiva;
use App\Http\Middleware\CheckAreaProfesional;
use App\Http\Middleware\CheckRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // En el servidor la aplicación va detrás del Apache que termina HTTPS (red de Docker)
        $middleware->trustProxies(at: ['127.0.0.1', '172.16.0.0/12']);
        $middleware->alias([
            'role' => CheckRole::class,
            'area' => CheckAreaProfesional::class,
        ]);
        $middleware->web(append: [
            CerrarSesionInactiva::class,
            CabecerasSeguridad::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // La API de lectura (api/v1) responde los errores en JSON (401, 403, 404, 422), sin
        // redirigir al login, aunque el cliente no envíe "Accept: application/json".
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();

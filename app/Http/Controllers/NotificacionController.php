<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja de avisos (la campana) de cualquier usuario. Cada quien ve y marca solo los suyos:
 * las consultas parten siempre de $request->user()->notifications().
 */
class NotificacionController extends Controller
{
    public const POR_PAGINA = 20;

    public function index(Request $request): View
    {
        return view('notificaciones.index', [
            'notificaciones' => $request->user()->notifications()->paginate(self::POR_PAGINA),
            'sinLeer' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marca el aviso como leído y lleva a la pantalla donde se atiende.
     */
    public function abrir(Request $request, string $notificacion): RedirectResponse
    {
        $aviso = $request->user()->notifications()->findOrFail($notificacion);
        $aviso->markAsRead();

        return redirect()->to(self::destinoSeguro($aviso->data['url'] ?? null));
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notificaciones.index')->with('status', 'Marcaste todos los avisos como leídos.');
    }

    /**
     * Solo rutas internas ("/algo"): nunca otro sitio, aunque el dato guardado se alterara.
     */
    public static function destinoSeguro(mixed $url): string
    {
        $esInterna = is_string($url)
            && str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
            && ! str_contains($url, '\\');

        return $esInterna ? $url : route('notificaciones.index', absolute: false);
    }
}

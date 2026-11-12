<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificacionController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Avisos de la campana del estudiante (NotificacionController). Cada quien ve y marca solo los suyos.
 */
class NotificacionesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pagina = $request->user()->notifications()->paginate(NotificacionController::POR_PAGINA);

        return response()->json([
            'avisos' => collect($pagina->items())->map(fn (DatabaseNotification $aviso) => [
                'id' => $aviso->id,
                'titulo' => $aviso->data['titulo'] ?? 'Aviso',
                'mensaje' => $aviso->data['mensaje'] ?? '',
                'url' => NotificacionController::destinoSeguro($aviso->data['url'] ?? null),
                'prioridad' => $aviso->data['prioridad'] ?? 'normal',
                'leido' => $aviso->read_at !== null,
                'fecha' => $aviso->created_at?->format('Y-m-d\TH:i:s'),
            ])->values(),
            'sin_leer' => $request->user()->unreadNotifications()->count(),
            'pagina' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
        ]);
    }

    /**
     * Marca el aviso como leído y devuelve a dónde lleva, para que la app abra esa pantalla.
     */
    public function leer(Request $request, string $notificacion): JsonResponse
    {
        $aviso = $request->user()->notifications()->findOrFail($notificacion);
        $aviso->markAsRead();

        return response()->json(['url' => NotificacionController::destinoSeguro($aviso->data['url'] ?? null)]);
    }

    public function leerTodas(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['mensaje' => 'Marcaste todos los avisos como leídos.']);
    }
}

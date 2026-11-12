<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EstudianteController;
use App\Models\Actuacion;
use App\Models\TareaTerapeutica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inicio del estudiante en la app: lo mismo que EstudianteController::home (HU-013).
 */
class InicioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = EstudianteController::FILTROS_TAREAS;
        $filtro = array_key_exists((string) $request->query('tareas'), $filtros) ? (string) $request->query('tareas') : 'todas';

        $idsCasos = Formato::idsCasos($request);
        $hoy = now(Actuacion::ZONA_HORARIA)->startOfDay();

        $tareas = TareaTerapeutica::whereIn('id_caso', $idsCasos)
            ->where('estado', TareaTerapeutica::ESTADO_PENDIENTE)
            ->when($filtro !== 'todas', fn ($query) => $query
                ->whereNotNull('fecha_limite')
                ->where('fecha_limite', '<=', $hoy->copy()->addDays((int) $filtro)->toDateString()))
            ->with('caso')
            ->orderByRaw('fecha_limite IS NULL')
            ->orderBy('fecha_limite')
            ->orderByDesc('fecha_asignacion')
            ->get();

        $proximaCita = Actuacion::citas()
            ->whereIn('id_caso', $idsCasos)
            ->where('fecha_hora', '>=', now(Actuacion::ZONA_HORARIA)->toDateTimeString())
            ->with('profesional.persona', 'caso.solicitud')
            ->orderBy('fecha_hora')
            ->first();

        return response()->json([
            'usuario' => AuthController::usuario($request->user()),
            'hoy' => $hoy->toDateString(),
            'filtro' => $filtro,
            'filtros' => $filtros,
            'proxima_cita' => $proximaCita ? Formato::cita($proximaCita) : null,
            'tareas' => $tareas->map(fn (TareaTerapeutica $tarea) => [
                'id' => $tarea->id_tarea,
                'titulo' => $tarea->titulo,
                'descripcion' => $tarea->descripcion,
                'fecha_limite' => $tarea->fecha_limite?->toDateString(),
                'fecha_asignacion' => $tarea->fecha_asignacion?->toDateString(),
                'codigo_expediente' => $tarea->caso?->codigo_expediente,
            ])->values(),
            'avisos_sin_leer' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}

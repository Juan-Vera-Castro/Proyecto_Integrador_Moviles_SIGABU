<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Vbg\Reporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Violencias Basadas en Género: opciones del formulario y "Mis reportes" (Vbg\ReporteController::create).
 * El reporte se envía a Vbg\ReporteController::store.
 */
class ReportesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'mis_reportes' => Reporte::where('id_persona_reporta', $request->user()->persona?->id_persona)
                ->orderByDesc('fecha_reporte')
                ->get()
                ->map(fn (Reporte $reporte) => [
                    'codigo' => $reporte->codigo,
                    'fecha' => $reporte->fecha_reporte?->format('Y-m-d'),
                    'estado' => $reporte->estado,
                ])->values(),
            'hoy' => now(Actuacion::ZONA_HORARIA)->toDateString(),
            'tipos_reportante' => [
                ['valor' => Reporte::AFECTADA, 'icono' => '🙋', 'titulo' => 'Soy la persona afectada', 'texto' => 'Quiero reportar una situación que estoy viviendo.'],
                ['valor' => Reporte::TERCERO, 'icono' => '🤝', 'titulo' => 'Reporto en nombre de otra persona', 'texto' => 'Conozco a alguien que necesita apoyo y acompañamiento.'],
                ['valor' => Reporte::ANONIMO, 'icono' => '🕶️', 'titulo' => 'Prefiero reportar de forma anónima', 'texto' => 'No compartiré mi identidad ni la de la persona afectada.'],
            ],
            'tipos_violencia' => Reporte::TIPOS_VIOLENCIA,
            'relato_min' => Reporte::RELATO_MIN,
            'relato_max' => Reporte::RELATO_MAX,
        ]);
    }
}

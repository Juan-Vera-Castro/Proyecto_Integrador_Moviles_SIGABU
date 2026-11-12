<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\Caso;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inicio del profesional: indicadores de sus casos y de su día.
 */
class ProfesionalController extends Controller
{
    public function home(Request $request): View
    {
        $profesional = $request->user();
        $hoy = now(Actuacion::ZONA_HORARIA);
        $activos = Caso::activos()->deAreaDe($profesional)->where('id_profesional', $profesional->id_usuario);

        return view('profesional.home', [
            'indicadores' => [
                'activos' => (clone $activos)->count(),
                'riesgoAlto' => (clone $activos)->where('nivel_riesgo', 'Alto')->count(),
                'enSeguimiento' => (clone $activos)->where('estado_caso', 'En Seguimiento')->count(),
                'cerradosMes' => Caso::deAreaDe($profesional)->where('id_profesional', $profesional->id_usuario)
                    ->where('estado_caso', Caso::ESTADO_CERRADO)
                    ->where('fecha_cierre', '>=', $hoy->copy()->startOfMonth()->toDateTimeString())
                    ->count(),
            ],
            'citasHoy' => Actuacion::citas()
                ->where('id_profesional', $profesional->id_usuario)
                ->whereHas('caso', fn ($caso) => $caso->deAreaDe($profesional))
                ->where('estado_asistencia', '!=', Actuacion::CANCELADA)
                ->whereBetween('fecha_hora', [$hoy->copy()->startOfDay()->toDateTimeString(), $hoy->copy()->endOfDay()->toDateTimeString()])
                ->count(),
            'porAtender' => Caso::porAtenderDe($profesional),
            'trasladados' => Caso::trasladadosSinCitaDe($profesional)->with('solicitud.persona')->orderBy('fecha_apertura')->get(),
            // Emergencias que esperan su primera cita: las suyas y las que aún no tienen profesional.
            'emergencias' => Caso::emergencias()->where(fn ($q) => $q->whereIn('id_caso', Caso::nuevosDe($profesional)->select('id_caso'))->orWhereIn('id_caso', Caso::sinAsignar()->deAreaDe($profesional)->select('id_caso')))
                ->with('solicitud.persona')->orderBy('fecha_apertura')->get(),
        ]);
    }
}

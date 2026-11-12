<?php

namespace App\Http\Controllers;

use App\Support\IndicadoresInstitucionales;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * HU-032: tablero de indicadores institucionales, agregados y anonimizados, para el
 * administrador (directivo) y los profesionales que él autorice.
 */
class IndicadoresController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->puedeVerIndicadores(), 403);

        $meses = in_array((int) $request->query('meses'), IndicadoresInstitucionales::PERIODOS, true) ? (int) $request->query('meses') : 6;
        $servicio = in_array($request->query('servicio'), IndicadoresInstitucionales::SERVICIOS, true) ? $request->query('servicio') : 'Todos';
        $indicadores = new IndicadoresInstitucionales($meses, $servicio);

        return view('indicadores.index', [
            'datos' => $indicadores->todo(),
            'meses' => $meses,
            'servicio' => $servicio,
            'desde' => $indicadores->desde(),
            'minimo' => IndicadoresInstitucionales::minimo(),
        ]);
    }
}

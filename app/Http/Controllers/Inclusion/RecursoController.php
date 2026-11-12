<?php

namespace App\Http\Controllers\Inclusion;

use App\Http\Controllers\Controller;
use App\Models\Inclusion\Recurso;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Catálogo de recursos pedagógicos: lo consultan los profesionales de Inclusión o de ambas áreas.
 */
class RecursoController extends Controller
{
    public function index(): View
    {
        Gate::authorize('inclusion.consultarRecursos');

        return view('inclusion.recursos.index', [
            'recursos' => Recurso::activos()->orderBy('titulo')->get(),
        ]);
    }

    public function show(Recurso $recurso): View
    {
        Gate::authorize('inclusion.consultarRecursos');

        return view('inclusion.recursos.show', ['recurso' => $recurso]);
    }
}

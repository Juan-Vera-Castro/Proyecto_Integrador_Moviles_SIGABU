<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\TareaTerapeutica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TareaTerapeuticaController extends Controller
{
    /**
     * Asigna una tarea terapéutica al estudiante del caso (HU-013). Solo el profesional
     * del caso puede hacerlo y solo mientras el caso está activo.
     */
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        abort_unless($caso->id_profesional === $request->user()->id_usuario, 403);

        $request->merge([
            'titulo' => trim((string) $request->input('titulo')),
            'descripcion' => trim((string) $request->input('descripcion')),
        ]);

        $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();

        $datos = $request->validateWithBag('tarea', [
            'titulo' => ['required', 'string', 'max:'.TareaTerapeutica::TITULO_MAX],
            'descripcion' => ['required', 'string', 'max:'.TareaTerapeutica::DESCRIPCION_MAX],
            'fecha_limite' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$hoy],
        ], [
            'titulo.required' => 'El título de la actividad es obligatorio.',
            'titulo.max' => 'El título no puede superar los '.TareaTerapeutica::TITULO_MAX.' caracteres.',
            'descripcion.required' => 'La descripción de la actividad es obligatoria.',
            'descripcion.max' => 'La descripción no puede superar los '.TareaTerapeutica::DESCRIPCION_MAX.' caracteres.',
            'fecha_limite.date_format' => 'Escribe una fecha válida, por ejemplo 2026-10-05.',
            'fecha_limite.after_or_equal' => 'La fecha límite no puede ser anterior a hoy.',
        ]);

        $volver = redirect()->to(route('casos.show', $caso).'#tareas');

        if ($caso->estaActivo() && $motivo = $caso->motivoSinAtencion()) {
            return $volver->withInput()->with('error', $motivo);
        }

        if (! $caso->estaActivo()) {
            return $volver->withInput()->with('error', "Solo se pueden asignar tareas en casos activos. El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\".");
        }

        $caso->tareas()->create([
            'id_profesional' => $request->user()->id_usuario,
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'fecha_limite' => $datos['fecha_limite'] ?? null,
            'fecha_asignacion' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
        ]);

        return $volver->with('status', "Tarea \"{$datos['titulo']}\" asignada. Queda pendiente de evaluación.");
    }
}

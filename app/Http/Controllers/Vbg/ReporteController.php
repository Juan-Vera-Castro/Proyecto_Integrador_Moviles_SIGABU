<?php

namespace App\Http\Controllers\Vbg;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Vbg\Reporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Reporte de una situación de violencia basada en género desde el panel del estudiante.
 * Puede reportar la persona afectada, alguien en nombre de otra persona o alguien anónimo:
 * en el anónimo no se guarda quién reporta ni un medio de contacto.
 */
class ReporteController extends Controller
{
    public function create(Request $request): View
    {
        return view('reportes.crear', [
            'misReportes' => Reporte::where('id_persona_reporta', $request->user()->persona?->id_persona)
                ->orderByDesc('fecha_reporte')
                ->get(),
            'hoy' => now(Actuacion::ZONA_HORARIA)->toDateString(),
        ]);
    }

    /**
     * Espacio de VBG del profesional: activa la ruta reportando en nombre de un estudiante
     * que atiende, y sigue el estado de los reportes que hizo.
     */
    public function profesional(Request $request): View
    {
        return view('vbg.profesional', [
            'misReportes' => Reporte::with('profesional.persona')
                ->where('id_persona_reporta', $request->user()->persona?->id_persona)
                ->orderByDesc('fecha_reporte')
                ->get(),
            'hoy' => now(Actuacion::ZONA_HORARIA)->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $persona = $request->user()->persona;
        abort_unless($persona, 403);

        // El profesional siempre reporta en nombre de otra persona.
        $esProfesional = $request->user()->role === 'profesional';
        if ($esProfesional) {
            $request->merge(['tipo_reportante' => Reporte::TERCERO]);
        }

        $request->merge(collect(['afectado_nombre', 'afectado_vinculo', 'relato', 'lugar', 'medio_contacto'])
            ->mapWithKeys(fn (string $campo) => [$campo => trim((string) $request->input($campo)) ?: null])
            ->all());

        $anonimo = $request->input('tipo_reportante') === Reporte::ANONIMO;
        $tercero = $request->input('tipo_reportante') === Reporte::TERCERO;

        $datos = $request->validate([
            'tipo_reportante' => ['required', Rule::in(Reporte::TIPOS_REPORTANTE)],
            'afectado_nombre' => [Rule::excludeIf(! $tercero), 'required', 'string', 'max:150'],
            'afectado_vinculo' => [Rule::excludeIf(! $tercero), 'nullable', 'string', 'max:100'],
            'relato' => ['required', 'string', 'min:'.Reporte::RELATO_MIN, 'max:'.Reporte::RELATO_MAX],
            'tipo_violencia' => ['nullable', Rule::in(Reporte::TIPOS_VIOLENCIA)],
            'lugar' => ['nullable', 'string', 'max:150'],
            'fecha_hechos' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'desea_contacto' => [Rule::excludeIf($anonimo), 'nullable', 'boolean'],
            'medio_contacto' => [Rule::excludeIf($anonimo || ! $request->boolean('desea_contacto')), 'required', 'string', 'max:150'],
            'acepta_datos' => ['accepted'],
        ], [
            'tipo_reportante.required' => 'Indica quién está reportando.',
            'tipo_reportante.in' => 'Indica quién está reportando.',
            'afectado_nombre.required' => 'Escribe el nombre de la persona afectada.',
            'relato.required' => 'Cuéntanos qué ocurrió.',
            'relato.min' => 'Cuéntanos un poco más: al menos '.Reporte::RELATO_MIN.' caracteres.',
            'relato.max' => 'El relato no puede superar los '.Reporte::RELATO_MAX.' caracteres.',
            'tipo_violencia.in' => 'Selecciona un tipo de la lista o déjalo en blanco.',
            'fecha_hechos.before_or_equal' => 'La fecha de los hechos no puede ser futura.',
            'medio_contacto.required' => 'Indica cómo podemos contactarte de forma segura.',
            'acepta_datos.accepted' => 'Para enviar el reporte debes autorizar el tratamiento confidencial de los datos.',
        ]);

        try {
            $reporte = Reporte::registrar([
                'tipo_reportante' => $datos['tipo_reportante'],
                'id_persona_reporta' => $anonimo ? null : $persona->id_persona,
                'afectado_nombre' => $datos['afectado_nombre'] ?? null,
                'afectado_vinculo' => $datos['afectado_vinculo'] ?? null,
                'relato' => $datos['relato'],
                'tipo_violencia' => $datos['tipo_violencia'] ?? null,
                'lugar' => $datos['lugar'] ?? null,
                'fecha_hechos' => $datos['fecha_hechos'] ?? null,
                'desea_contacto' => ! $anonimo && $request->boolean('desea_contacto'),
                'medio_contacto' => $datos['medio_contacto'] ?? null,
                'acepta_datos' => true,
                'fecha_reporte' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No fue posible enviar tu reporte en este momento. No se guardó nada; intenta de nuevo en unos minutos.');
        }

        if ($esProfesional) {
            return redirect()->route('profesional.vbg')->with('status', "Reporte {$reporte->codigo} enviado al equipo de Violencias de Género. Aquí verás su estado.");
        }

        return redirect()->route('reportes.crear')->with('reporte_enviado', [
            'codigo' => $reporte->codigo,
            'anonimo' => $anonimo,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Vbg;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Vbg\Accion;
use App\Models\Vbg\Cita;
use App\Models\Vbg\Reporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Espacio del equipo de Violencias Basadas en Género: bandeja de reportes y gestión de cada
 * caso (nivel de riesgo, tipo de violencia, rutas activadas y seguimiento). Un reporte lo toma
 * un profesional VBG; desde ahí solo él registra acciones, y cada acción queda inmutable.
 */
class CasoController extends Controller
{
    public function index(Request $request): View
    {
        $estado = $request->query('estado');

        return view('vbg.home', [
            'reportes' => Reporte::with(['profesional.persona', 'reportante'])
                ->deEstado($estado)
                ->orderByRaw("CASE estado WHEN 'Recibido' THEN 1 WHEN 'En atención' THEN 2 ELSE 3 END")
                ->orderByRaw("CASE nivel_riesgo WHEN 'Alto' THEN 1 WHEN 'Medio' THEN 2 WHEN 'Bajo' THEN 3 ELSE 4 END")
                ->orderByDesc('fecha_reporte')
                ->get(),
            'estado' => in_array($estado, Reporte::ESTADOS, true) ? $estado : null,
            'resumen' => [
                'recibidos' => Reporte::where('estado', Reporte::RECIBIDO)->count(),
                'enAtencion' => Reporte::where('estado', Reporte::EN_ATENCION)->count(),
                'riesgoAlto' => Reporte::where('estado', '!=', Reporte::CERRADO)->where('nivel_riesgo', 'Alto')->count(),
                'mios' => Reporte::where('estado', Reporte::EN_ATENCION)->where('id_profesional', $request->user()->id_usuario)->count(),
            ],
            'citasHoy' => $this->citasDeHoy($request),
        ]);
    }

    public function show(Reporte $reporte): View
    {
        $reporte->load(['reportante.usuario', 'profesional.persona', 'acciones.usuario.persona']);

        // Citas e informes viven en la base propia de VBG: si no responde, el caso se ve igual.
        $vbg = rescue(fn () => [
            'citas' => $reporte->citas()->with(['consentimientos', 'audios.transcripcion'])->get(),
            'informes' => $reporte->informes()->with('profesional.persona')->get(),
        ], null, report: true);

        return view('vbg.show', [
            'reporte' => $reporte,
            'vbgDisponible' => $vbg !== null,
            'citas' => $vbg['citas'] ?? collect(),
            'informes' => $vbg['informes'] ?? collect(),
        ]);
    }

    /**
     * El profesional toma el reporte: el caso queda a su cargo "En atención".
     */
    public function tomar(Request $request, Reporte $reporte): RedirectResponse
    {
        DB::transaction(function () use ($request, $reporte) {
            $reporte = Reporte::whereKey($reporte->id_reporte)->lockForUpdate()->firstOrFail();

            if ($reporte->estado !== Reporte::RECIBIDO) {
                throw ValidationException::withMessages(['reporte' => "El reporte {$reporte->codigo} ya fue tomado por otro profesional. Actualiza la página."])
                    ->redirectTo(route('vbg.reportes.show', $reporte));
            }

            $reporte->update(['estado' => Reporte::EN_ATENCION, 'id_profesional' => $request->user()->id_usuario]);
            $this->registrar($reporte, $request, Accion::TOMADO, 'El caso quedó a cargo de '.($request->user()->persona?->nombre_completo ?? 'el profesional').'.');
        });

        return redirect()->route('vbg.reportes.show', $reporte)->with('status', "El caso {$reporte->codigo} quedó a tu cargo.");
    }

    /**
     * Valoración del caso: nivel de riesgo y tipo de violencia.
     */
    public function valorar(Request $request, Reporte $reporte): RedirectResponse
    {
        $this->exigirACargo($request, $reporte);

        $datos = $request->validateWithBag('valoracion', [
            'nivel_riesgo' => ['required', Rule::in(Reporte::NIVELES_RIESGO)],
            'tipo_violencia' => ['required', Rule::in(Reporte::TIPOS_VIOLENCIA)],
        ], [
            'nivel_riesgo.required' => 'Selecciona el nivel de riesgo.',
            'tipo_violencia.required' => 'Selecciona el tipo de violencia.',
        ]);

        $reporte->update($datos);

        return redirect()->route('vbg.reportes.show', $reporte)->with('status', 'Valoración del caso actualizada.');
    }

    /**
     * Ruta activada o nota de seguimiento, con fecha y autor.
     */
    public function accion(Request $request, Reporte $reporte): RedirectResponse
    {
        $this->exigirACargo($request, $reporte);
        $request->merge(['descripcion' => trim((string) $request->input('descripcion'))]);

        $datos = $request->validateWithBag('accion', [
            'tipo_accion' => ['required', Rule::in(Accion::TIPOS)],
            'descripcion' => ['required', 'string', 'max:'.Accion::DESCRIPCION_MAX],
        ], [
            'tipo_accion.required' => 'Selecciona la acción.',
            'tipo_accion.in' => 'Selecciona una acción de la lista.',
            'descripcion.required' => 'Describe lo que se hizo.',
            'descripcion.max' => 'La descripción no puede superar los '.Accion::DESCRIPCION_MAX.' caracteres.',
        ]);

        $this->registrar($reporte, $request, $datos['tipo_accion'], $datos['descripcion']);

        return redirect()->to(route('vbg.reportes.show', $reporte).'#acciones')->with('status', "Acción \"{$datos['tipo_accion']}\" registrada.");
    }

    public function cerrar(Request $request, Reporte $reporte): RedirectResponse
    {
        $this->exigirACargo($request, $reporte);
        $request->merge(['motivo' => trim((string) $request->input('motivo'))]);

        $datos = $request->validateWithBag('cierre', [
            'motivo' => ['required', 'string', 'max:'.Accion::DESCRIPCION_MAX],
        ], [
            'motivo.required' => 'Escribe por qué se cierra el caso.',
        ]);

        DB::transaction(function () use ($request, $reporte, $datos) {
            $reporte->update(['estado' => Reporte::CERRADO, 'fecha_cierre' => now(Actuacion::ZONA_HORARIA)->toDateTimeString()]);
            $this->registrar($reporte, $request, Accion::CERRADO, $datos['motivo']);
        });

        return redirect()->route('vbg.reportes.show', $reporte)->with('status', "Caso {$reporte->codigo} cerrado.");
    }

    public function reabrir(Request $request, Reporte $reporte): RedirectResponse
    {
        abort_unless($reporte->estaCerrado() && $reporte->id_profesional === $request->user()->id_usuario, 403);

        DB::transaction(function () use ($request, $reporte) {
            $reporte->update(['estado' => Reporte::EN_ATENCION, 'fecha_cierre' => null]);
            $this->registrar($reporte, $request, Accion::REABIERTO, 'El caso se reabrió para continuar la atención.');
        });

        return redirect()->route('vbg.reportes.show', $reporte)->with('status', "Caso {$reporte->codigo} reabierto.");
    }

    /**
     * Citas de hoy del profesional (base propia de VBG); null si no está disponible.
     */
    private function citasDeHoy(Request $request): ?\Illuminate\Support\Collection
    {
        return rescue(fn () => Cita::where('id_profesional', $request->user()->id_usuario)
            ->whereDate('fecha', now(Actuacion::ZONA_HORARIA)->toDateString())
            ->whereIn('estado', [Cita::PROGRAMADA, Cita::EN_CURSO])
            ->orderBy('hora_inicio')
            ->get(), null, report: true);
    }

    /**
     * Solo el profesional a cargo gestiona el caso, y mientras esté en atención.
     */
    private function exigirACargo(Request $request, Reporte $reporte): void
    {
        abort_unless($reporte->id_profesional === $request->user()->id_usuario, 403);

        if ($reporte->estado !== Reporte::EN_ATENCION) {
            throw ValidationException::withMessages(['reporte' => "El caso {$reporte->codigo} está \"{$reporte->estado}\": no admite cambios."])
                ->redirectTo(route('vbg.reportes.show', $reporte));
        }
    }

    private function registrar(Reporte $reporte, Request $request, string $tipo, string $descripcion): void
    {
        Accion::create([
            'id_reporte' => $reporte->id_reporte,
            'id_usuario' => $request->user()->id_usuario,
            'tipo_accion' => $tipo,
            'descripcion' => $descripcion,
            'fecha_hora' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
        ]);
    }
}

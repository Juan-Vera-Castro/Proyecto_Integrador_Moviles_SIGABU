<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\AjusteRazonable;
use App\Models\Caso;
use App\Models\SeguimientoAjuste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ajustes razonables de Inclusión Universitaria: el profesional del caso los registra (HU-018) y
 * da seguimiento a su cumplimiento (HU-019). Cada seguimiento queda en un historial inmutable
 * con su fecha y observaciones.
 */
class AjusteRazonableController extends Controller
{
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        $volver = redirect()->to(route('casos.show', $caso).'#ajustes');
        abort_unless($caso->id_profesional === $request->user()->id_usuario, 403);
        abort_unless($caso->esDeInclusion(), 404);

        $request->merge(collect(['barrera', 'descripcion', 'responsable'])->mapWithKeys(fn ($c) => [$c => trim((string) $request->input($c)) ?: null])->all());

        $datos = $request->validateWithBag('ajuste', [
            'tipo' => ['required', Rule::in(AjusteRazonable::TIPOS)],
            'barrera' => ['required', 'string', 'max:'.AjusteRazonable::TEXTO_MAX],
            'descripcion' => ['required', 'string', 'max:'.AjusteRazonable::TEXTO_MAX],
            'responsable' => ['nullable', 'string', 'max:150'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
        ], [
            'tipo.required' => 'Selecciona el tipo de ajuste.',
            'tipo.in' => 'Selecciona un tipo de la lista.',
            'barrera.required' => 'Describe la barrera que atiende el ajuste.',
            'descripcion.required' => 'Describe en qué consiste el ajuste.',
            'fecha_inicio.required' => 'Indica desde cuándo aplica el ajuste.',
            'fecha_inicio.date_format' => 'Escribe una fecha válida, por ejemplo 2026-10-05.',
        ]);

        if ($error = $this->motivoSinGestion($caso)) {
            return $volver->withInput()->with('error', $error);
        }

        $caso->ajustes()->create($datos + [
            'id_profesional' => $request->user()->id_usuario,
            'fecha_registro' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
        ]);

        return $volver->with('status', "Ajuste razonable \"{$datos['tipo']}\" registrado. Registra su seguimiento para verificar el cumplimiento.");
    }

    /**
     * Seguimiento del ajuste: queda en el historial con su fecha y observaciones. Puede cambiar
     * el estado del ajuste (cumplido o suspendido, o volver a vigente).
     */
    public function seguimiento(Request $request, AjusteRazonable $ajuste): RedirectResponse
    {
        $caso = $ajuste->caso;
        $volver = redirect()->to(route('casos.show', $caso).'#ajuste-'.$ajuste->id_ajuste);
        abort_unless($caso->id_profesional === $request->user()->id_usuario, 403);

        $request->merge(['observaciones' => trim((string) $request->input('observaciones')) ?: null]);
        $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();

        $datos = $request->validateWithBag('seguimiento-'.$ajuste->id_ajuste, [
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$hoy, 'after_or_equal:'.$ajuste->fecha_inicio->toDateString()],
            'cumplimiento' => ['required', Rule::in(SeguimientoAjuste::CUMPLIMIENTOS)],
            'observaciones' => ['required', 'string', 'max:'.AjusteRazonable::TEXTO_MAX],
            'estado_resultante' => ['nullable', Rule::in(AjusteRazonable::ESTADOS)],
        ], [
            'fecha.required' => 'Indica la fecha del seguimiento.',
            'fecha.before_or_equal' => 'La fecha del seguimiento no puede ser futura.',
            'fecha.after_or_equal' => 'La fecha no puede ser anterior al inicio del ajuste.',
            'cumplimiento.required' => 'Indica si el ajuste se está cumpliendo.',
            'observaciones.required' => 'Escribe las observaciones del avance.',
        ]);

        if ($error = $this->motivoSinGestion($caso)) {
            return $volver->withInput()->with('error', $error);
        }

        DB::transaction(function () use ($ajuste, $datos, $request) {
            $nuevoEstado = $datos['estado_resultante'] ?? null;

            SeguimientoAjuste::create([
                'id_ajuste' => $ajuste->id_ajuste,
                'id_profesional' => $request->user()->id_usuario,
                'fecha' => $datos['fecha'],
                'cumplimiento' => $datos['cumplimiento'],
                'observaciones' => $datos['observaciones'],
                'estado_resultante' => $nuevoEstado && $nuevoEstado !== $ajuste->estado ? $nuevoEstado : null,
                'fecha_registro' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            ]);

            if ($nuevoEstado && $nuevoEstado !== $ajuste->estado) {
                $ajuste->update(['estado' => $nuevoEstado]);
            }
        });

        return $volver->with('status', 'Seguimiento registrado en el historial del ajuste.');
    }

    /**
     * Mismas condiciones que la atención: caso activo y consentimiento cuando se exige (RN-01).
     */
    private function motivoSinGestion(Caso $caso): ?string
    {
        if (! $caso->estaActivo()) {
            return "El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\": solo se gestionan ajustes en casos activos.";
        }

        return $caso->motivoSinAtencion();
    }
}

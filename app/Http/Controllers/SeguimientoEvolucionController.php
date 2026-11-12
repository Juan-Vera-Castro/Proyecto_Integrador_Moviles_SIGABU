<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\AlertaTemprana;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\SeguimientoEvolucion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SeguimientoEvolucionController extends Controller
{
    /**
     * Registra la nota de evolución de una cita a la que el estudiante asistió (HU-015).
     * Solo el profesional asignado, en un caso activo que admite atención, una vez por cita.
     * El nivel de riesgo informado pasa a ser el riesgo vigente del caso (RN-03).
     */
    public function store(Request $request, Actuacion $cita): RedirectResponse
    {
        abort_unless($cita->tipo_actuacion === Actuacion::TIPO_CITA, 404);

        $caso = $cita->caso;
        abort_unless($caso?->id_profesional === $request->user()->id_usuario, 403);

        $request->merge([
            'instrumento' => $request->input('instrumento') ?: null,
            'puntaje' => $request->filled('puntaje') ? $request->input('puntaje') : null,
            'nota' => trim((string) $request->input('nota')),
        ]);

        $instrumento = $request->input('instrumento');
        $maximo = SeguimientoEvolucion::INSTRUMENTOS[$instrumento] ?? null;

        $datos = $request->validateWithBag('seguimiento', [
            'nivel_riesgo' => ['required', Rule::in(Caso::NIVELES_RIESGO)],
            'valoracion_avance' => ['required', Rule::in(SeguimientoEvolucion::VALORACIONES)],
            'instrumento' => $caso->esDeSaludMental()
                ? ['nullable', Rule::in(array_keys(SeguimientoEvolucion::INSTRUMENTOS))]
                : ['prohibited'],
            'puntaje' => $maximo !== null ? ['required', 'integer', 'min:0', "max:{$maximo}"] : ['prohibited'],
            'nota' => ['required', 'string', 'max:'.SeguimientoEvolucion::NOTA_MAX],
        ], [
            'nivel_riesgo.required' => 'Indica el nivel de riesgo del estudiante en esta sesión.',
            'nivel_riesgo.in' => 'Indica el nivel de riesgo del estudiante en esta sesión.',
            'valoracion_avance.required' => 'Indica cómo avanzó el estudiante respecto de la sesión anterior.',
            'valoracion_avance.in' => 'Indica cómo avanzó el estudiante respecto de la sesión anterior.',
            'instrumento.in' => 'Selecciona GAD-7 o PHQ-9.',
            'instrumento.prohibited' => 'Las escalas clínicas solo se aplican en casos de Salud Mental.',
            'puntaje.required' => "Escribe el puntaje de {$instrumento}.",
            'puntaje.integer' => "El puntaje de {$instrumento} debe ser un número entero.",
            'puntaje.min' => "El puntaje de {$instrumento} va de 0 a {$maximo}.",
            'puntaje.max' => "El puntaje de {$instrumento} va de 0 a {$maximo}.",
            'puntaje.prohibited' => 'Selecciona la escala aplicada antes de escribir el puntaje.',
            'nota.required' => 'Escribe la nota de la sesión.',
            'nota.max' => 'La nota no puede superar los '.SeguimientoEvolucion::NOTA_MAX.' caracteres.',
        ]);

        $volver = redirect()->to(route('casos.show', $caso).'#citas');

        if (! $caso->estaActivo()) {
            return $volver->with('error', "El seguimiento solo se registra en casos activos. El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\".");
        }

        if ($cita->estado_asistencia !== Actuacion::ASISTIO) {
            return $volver->with('error', 'El seguimiento solo se registra en citas a las que el estudiante asistió.');
        }

        if ($motivo = $caso->motivoSinAtencion()) {
            return $volver->with('error', $motivo);
        }

        $yaRegistrado = 'Esta cita ya tiene su seguimiento registrado: no se modifica.';

        if ($cita->seguimiento()->exists()) {
            return $volver->with('error', $yaRegistrado);
        }

        try {
            DB::transaction(function () use ($caso, $cita, $datos, $request) {
                $seguimiento = SeguimientoEvolucion::create($datos + [
                    'id_caso' => $caso->id_caso,
                    'id_actuacion' => $cita->id_actuacion,
                    'id_profesional' => $request->user()->id_usuario,
                    'fecha_hora' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
                ]);
                $caso->update(['nivel_riesgo' => $datos['nivel_riesgo']]);
                AuditoriaExpediente::registrar(AuditoriaExpediente::SEGUIMIENTO_REGISTRADO, $caso, [
                    'id_seguimiento' => $seguimiento->id_seguimiento,
                    'id_actuacion' => $cita->id_actuacion,
                ]);
                // Si la sesión dejó el caso en riesgo alto, alerta temprana (HU-026).
                AlertaTemprana::evaluar($caso);
            });
        } catch (UniqueConstraintViolationException) {
            return $volver->with('error', $yaRegistrado);
        }

        return $volver->with('status', "Seguimiento registrado para la cita del {$cita->fecha_hora->format('Y-m-d')} a las {$cita->fecha_hora->format('g:i a')}.");
    }
}

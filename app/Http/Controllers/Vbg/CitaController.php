<?php

namespace App\Http\Controllers\Vbg;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Vbg\Auditoria;
use App\Models\Vbg\Cita;
use App\Models\Vbg\Reporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Agenda de citas de un caso VBG (integrado desde la rama VIOLENCIA_GENERO): fecha, horario,
 * modalidad, lugar o enlace, motivo y notas previas. La cita pasa a "En curso" solo con el
 * consentimiento aceptado.
 */
class CitaController extends Controller
{
    use ExigeCasoACargo;

    public function store(Request $request, Reporte $reporte): RedirectResponse
    {
        $this->exigirGestionable($request, $reporte);
        $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();

        $datos = $request->validateWithBag('cita', [
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$hoy],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'modalidad' => ['required', Rule::in(Cita::MODALIDADES)],
            'lugar' => ['nullable', 'string', 'max:200'],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'notas_previas' => ['nullable', 'string', 'max:4000'],
        ], [
            'fecha.required' => 'Indica la fecha de la cita.',
            'fecha.after_or_equal' => 'La cita no puede quedar en una fecha pasada.',
            'hora_inicio.required' => 'Indica la hora de inicio.',
            'hora_fin.required' => 'Indica la hora de fin.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'modalidad.required' => 'Selecciona si la cita es presencial o virtual.',
            'modalidad.in' => 'Selecciona si la cita es presencial o virtual.',
        ]);

        $inicio = $datos['hora_inicio'].':00';
        $fin = $datos['hora_fin'].':00';

        $cruce = Cita::where('id_profesional', $request->user()->id_usuario)
            ->whereDate('fecha', $datos['fecha'])
            ->whereIn('estado', [Cita::PROGRAMADA, Cita::EN_CURSO])
            ->where('hora_inicio', '<', $fin)
            ->where('hora_fin', '>', $inicio)
            ->exists();

        if ($cruce) {
            throw ValidationException::withMessages(['hora_inicio' => 'Ya tienes otra cita en ese horario.'])
                ->errorBag('cita')
                ->redirectTo(route('vbg.reportes.show', $reporte).'#citas');
        }

        $cita = Cita::create([
            'id_reporte' => $reporte->id_reporte,
            'id_profesional' => $request->user()->id_usuario,
            'fecha' => $datos['fecha'],
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'modalidad' => $datos['modalidad'],
            'lugar' => $datos['lugar'] ?? null,
            'motivo' => $datos['motivo'] ?? null,
            'notas_previas' => $datos['notas_previas'] ?? null,
            'estado' => Cita::PROGRAMADA,
            'creado_en' => now(Actuacion::ZONA_HORARIA),
        ]);

        Auditoria::registrar(Auditoria::CITA_AGENDADA, $reporte->id_reporte, ['id_cita' => $cita->id_cita]);

        return $this->volverAlCaso($reporte, 'cita-'.$cita->id_cita, 'Cita agendada para el '.$cita->fecha->format('Y-m-d').' de '.$cita->horario().'.');
    }

    public function estado(Request $request, Cita $cita): RedirectResponse
    {
        $reporte = $cita->reporte;
        abort_unless($reporte, 404);
        $this->exigirGestionable($request, $reporte);

        $estado = $request->validate(['estado' => ['required', Rule::in(Cita::ESTADOS)]])['estado'];
        $volver = route('vbg.reportes.show', $reporte).'#cita-'.$cita->id_cita;

        if (! $cita->puedePasarA($estado)) {
            throw ValidationException::withMessages(['reporte' => "La cita está \"{$cita->estado}\" y no puede pasar a \"{$estado}\"."])->redirectTo($volver);
        }

        if ($estado === Cita::EN_CURSO && ! $cita->tieneConsentimientoAceptado()) {
            throw ValidationException::withMessages(['reporte' => 'Antes de iniciar la cita, registra el consentimiento informado aceptado y firmado.'])->redirectTo($volver);
        }

        $anterior = $cita->estado;
        $cita->update(['estado' => $estado, 'actualizado_en' => now(Actuacion::ZONA_HORARIA)]);
        Auditoria::registrar(Auditoria::CITA_ESTADO, $reporte->id_reporte, ['id_cita' => $cita->id_cita, 'de' => $anterior, 'a' => $estado]);

        return $this->volverAlCaso($reporte, 'cita-'.$cita->id_cita, "La cita quedó \"{$estado}\".");
    }
}

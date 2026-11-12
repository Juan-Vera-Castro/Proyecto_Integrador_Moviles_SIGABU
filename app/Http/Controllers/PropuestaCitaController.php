<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCitaRequest;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\PropuestaCita;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * El psicólogo propone varios horarios y el estudiante elige el que le sirve: ese horario se
 * vuelve la cita y los demás se descartan. Así la hora se acuerda sin llamadas de por medio.
 */
class PropuestaCitaController extends Controller
{
    private const BOLSA = 'propuestas';

    /**
     * Propone de 1 a 5 horarios. Reemplaza los que estaban vigentes para el caso.
     */
    public function proponer(Request $request, Caso $caso): RedirectResponse
    {
        $profesional = $request->user();
        abort_unless($caso->id_profesional === $profesional->id_usuario, 403);

        // Las filas vacías del formulario no cuentan.
        $request->merge(['opciones' => collect($request->input('opciones', []))
            ->filter(fn ($opcion) => is_array($opcion) && filled($opcion['fecha'] ?? null) && filled($opcion['hora'] ?? null))
            ->values()->all()]);

        $datos = $request->validateWithBag(self::BOLSA, [
            'opciones' => ['required', 'array', 'min:1', 'max:'.PropuestaCita::MAXIMO],
            'opciones.*.fecha' => ['required', 'date_format:Y-m-d'],
            'opciones.*.hora' => ['required', Rule::in(Actuacion::HORARIOS)],
        ], [
            'opciones.required' => 'Propón al menos un horario (día y hora).',
            'opciones.min' => 'Propón al menos un horario (día y hora).',
            'opciones.max' => 'Puedes proponer hasta '.PropuestaCita::MAXIMO.' horarios.',
            'opciones.*.fecha.date_format' => 'Escribe una fecha válida.',
            'opciones.*.hora.in' => 'Elige una de las horas de atención.',
        ]);

        if (! $caso->estaActivo()) {
            throw ValidationException::withMessages(['opciones' => "El caso {$caso->codigo_expediente} no está activo."])->errorBag(self::BOLSA);
        }

        $inicios = collect($datos['opciones'])->map(fn (array $opcion) => Actuacion::inicioDeFranja($opcion['fecha'], $opcion['hora']));
        $this->validarHorarios($inicios, $profesional);

        DB::transaction(function () use ($caso, $profesional, $inicios) {
            PropuestaCita::where('id_caso', $caso->id_caso)->where('estado', PropuestaCita::PROPUESTA)
                ->update(['estado' => PropuestaCita::DESCARTADA, 'fecha_respuesta' => now(Actuacion::ZONA_HORARIA)->toDateTimeString()]);

            foreach ($inicios->sort() as $inicio) {
                PropuestaCita::create([
                    'id_caso' => $caso->id_caso,
                    'id_profesional' => $profesional->id_usuario,
                    'fecha_hora' => $inicio->toDateTimeString(),
                    'fecha_propuesta' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
                ]);
            }
        });

        return back()->with('status', "Enviaste {$inicios->count()} ".($inicios->count() === 1 ? 'horario' : 'horarios')." al estudiante del caso {$caso->codigo_expediente}. Cuando elija uno, la cita quedará en tu agenda.");
    }

    public function retirar(Request $request, Caso $caso): RedirectResponse
    {
        abort_unless($caso->id_profesional === $request->user()->id_usuario, 403);

        PropuestaCita::where('id_caso', $caso->id_caso)->where('estado', PropuestaCita::PROPUESTA)
            ->update(['estado' => PropuestaCita::DESCARTADA, 'fecha_respuesta' => now(Actuacion::ZONA_HORARIA)->toDateTimeString()]);

        return back()->with('status', 'Retiraste los horarios propuestos.');
    }

    /**
     * El estudiante elige un horario: se crea la cita y los demás propuestos se descartan.
     */
    public function elegir(Request $request, PropuestaCita $propuesta): RedirectResponse
    {
        $volver = route('citas.miscitas').'#caso-'.$propuesta->id_caso;
        abort_unless($propuesta->caso?->esDelEstudiante($request->user()), 403);

        $cita = DB::transaction(function () use ($propuesta, $volver) {
            $propuesta = PropuestaCita::whereKey($propuesta->id_propuesta)->lockForUpdate()->firstOrFail();
            $caso = Caso::whereKey($propuesta->id_caso)->lockForUpdate()->with('citas')->firstOrFail();
            User::whereKey($propuesta->id_profesional)->lockForUpdate()->first();
            $inicio = Actuacion::inicioDeFranja($propuesta->fecha_hora->format('Y-m-d'), $propuesta->fecha_hora->format('H:i'));

            $error = match (true) {
                $propuesta->estado !== PropuestaCita::PROPUESTA => 'Ese horario ya no está disponible. Revisa los horarios vigentes.',
                ! $caso->estaActivo() || $caso->id_profesional !== $propuesta->id_profesional => 'Tu caso cambió de profesional o ya no está activo. Actualiza la página.',
                $caso->proximaCita() !== null => 'Ya tienes una cita agendada en este caso.',
                ! $inicio->isFuture() => 'Ese horario ya pasó. Elige otro de los propuestos.',
                Actuacion::horarioOcupado($propuesta->id_profesional, $inicio) => 'Ese horario se ocupó mientras elegías. Elige otro de los propuestos.',
                default => null,
            };

            if ($error) {
                throw ValidationException::withMessages(['propuesta' => $error])->errorBag(self::BOLSA)->redirectTo($volver);
            }

            $cita = Actuacion::create([
                'id_caso' => $caso->id_caso,
                'id_profesional' => $propuesta->id_profesional,
                'tipo_actuacion' => Actuacion::TIPO_CITA,
                'fecha_hora' => $inicio,
                'descripcion' => 'Horario elegido por el estudiante',
            ]);

            $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();
            $propuesta->update(['estado' => PropuestaCita::ELEGIDA, 'id_cita' => $cita->id_actuacion, 'fecha_respuesta' => $ahora]);
            PropuestaCita::where('id_caso', $caso->id_caso)->where('estado', PropuestaCita::PROPUESTA)
                ->update(['estado' => PropuestaCita::DESCARTADA, 'fecha_respuesta' => $ahora]);

            return $cita;
        });

        return redirect()->to($volver)->with('status', "Tu cita quedó agendada el {$cita->fecha_hora->format('Y-m-d')} a las {$cita->fecha_hora->format('g:i a')}.");
    }

    /**
     * El estudiante rechaza la solicitud: ninguno de los horarios propuestos le sirve. El
     * profesional lo ve al buscarlo y puede enviarle otra.
     */
    public function rechazar(Request $request, Caso $caso): RedirectResponse
    {
        abort_unless($caso->esDelEstudiante($request->user()), 403);

        $rechazadas = PropuestaCita::where('id_caso', $caso->id_caso)->where('estado', PropuestaCita::PROPUESTA)
            ->update(['estado' => PropuestaCita::RECHAZADA, 'fecha_respuesta' => now(Actuacion::ZONA_HORARIA)->toDateTimeString()]);

        return redirect()->to(route('citas.miscitas').'#caso-'.$caso->id_caso)->with('status', $rechazadas
            ? 'Rechazaste la solicitud de cita. Tu profesional te enviará otra.'
            : 'Esa solicitud de cita ya no estaba vigente.');
    }

    /**
     * Cada horario: día hábil, futuro, sin repetirse y libre en la agenda del profesional.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Carbon>  $inicios
     */
    private function validarHorarios($inicios, User $profesional): void
    {
        $errores = [];

        foreach ($inicios as $i => $inicio) {
            $n = $i + 1;
            $error = match (true) {
                $inicio->isWeekend() => "Horario {$n}: Bienestar atiende de lunes a viernes.",
                ! $inicio->isFuture() => "Horario {$n}: ya pasó; elige uno posterior.",
                $inicios->filter(fn ($otro) => $otro->equalTo($inicio))->count() > 1 => "Horario {$n}: está repetido.",
                Actuacion::horarioOcupado($profesional->id_usuario, $inicio) => "Horario {$n}: ".StoreCitaRequest::mensajeConflicto($inicio),
                default => null,
            };

            if ($error) {
                $errores["opciones.{$i}"] = $error;
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores)->errorBag(self::BOLSA);
        }
    }
}

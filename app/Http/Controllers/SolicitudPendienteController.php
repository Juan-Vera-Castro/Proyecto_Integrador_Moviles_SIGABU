<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCitaRequest;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Solicitudes de cita del lado del profesional. Las solicitudes llegan directo al psicólogo
 * de la facultad y jornada del estudiante (revisión del prototipo, 06/10/2026): ya no se
 * aceptan ni se rechazan. Aquí el profesional ve sus casos nuevos para acordar la primera
 * cita y las solicitudes que llegaron sin profesional, porque nadie atiende esa facultad y
 * jornada, para asignárselas.
 */
class SolicitudPendienteController extends Controller
{
    /**
     * Bolsa de errores de los formularios de la página (hay uno por caso).
     */
    private const BOLSA = 'respuesta';

    public function index(Request $request): View
    {
        $profesional = $request->user();
        $relaciones = ['solicitud.persona', 'solicitud.facultad', 'consentimientos.version'];

        return view('citas.solicitudes', [
            'nuevos' => Caso::nuevosDe($profesional)->with([...$relaciones, 'propuestasVigentes'])->orderBy('fecha_apertura')->get()->sortByDesc(fn (Caso $caso) => $caso->esEmergencia())->values(),
            // Las emergencias primero; dentro de cada grupo, la más antigua primero.
            'sinAsignar' => Caso::sinAsignar()->deAreaDe($profesional)->with($relaciones)->orderBy('fecha_apertura')->get()->sortByDesc(fn (Caso $caso) => $caso->esEmergencia())->values(),
            'horarios' => Actuacion::HORARIOS,
            'hoy' => now(Actuacion::ZONA_HORARIA)->toDateString(),
        ]);
    }

    /**
     * Agenda la primera cita de un caso que le llegó al profesional, en la fecha y hora que
     * acordó con el estudiante.
     */
    public function agendar(Request $request, Caso $caso): RedirectResponse
    {
        $profesional = $request->user();
        $volver = route('solicitudes.index').'#solicitud-'.$caso->id_caso;

        abort_unless($caso->id_profesional === $profesional->id_usuario, 403);

        $datos = $request->validateWithBag(self::BOLSA, [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', Rule::in(Actuacion::HORARIOS)],
        ], [
            'fecha.required' => 'Selecciona la fecha de la primera cita.',
            'fecha.date_format' => 'Escribe una fecha válida, por ejemplo 2026-10-05.',
            'hora.required' => 'Selecciona la hora de la primera cita.',
            'hora.in' => 'Selecciona una de las horas de atención.',
        ]);

        $inicio = Actuacion::inicioDeFranja($datos['fecha'], $datos['hora']);

        try {
            $caso = DB::transaction(function () use ($caso, $profesional, $inicio, $volver) {
                User::whereKey($profesional->id_usuario)->lockForUpdate()->first();
                $caso = Caso::whereKey($caso->id_caso)->lockForUpdate()->firstOrFail();

                if (! $caso->estaActivo() || $caso->id_profesional !== $profesional->id_usuario || $caso->citas()->exists()) {
                    throw ValidationException::withMessages([
                        'solicitud' => "El caso {$caso->codigo_expediente} ya tiene su primera cita o ya no está a tu cargo. Actualiza la página.",
                    ])->errorBag(self::BOLSA)->redirectTo($volver);
                }

                if ($error = $this->errorDeHorario($profesional, $inicio)) {
                    throw ValidationException::withMessages([$error[0] => $error[1]])->errorBag(self::BOLSA)->redirectTo($volver);
                }

                Actuacion::create([
                    'id_caso' => $caso->id_caso,
                    'id_profesional' => $profesional->id_usuario,
                    'tipo_actuacion' => Actuacion::TIPO_CITA,
                    'fecha_hora' => $inicio,
                    'descripcion' => 'Primera cita',
                ]);

                return $caso;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return redirect()->to($volver)->withInput()->with('error', 'No fue posible agendar la cita en este momento. No se guardó ningún dato; intenta de nuevo en unos minutos.');
        }

        return redirect()
            ->route('casos.show', $caso)
            ->with('status', "Primera cita del caso {$caso->codigo_expediente} agendada el {$inicio->format('Y-m-d')} a las {$inicio->format('g:i a')}.");
    }

    /**
     * El profesional se asigna una solicitud que llegó sin profesional. Después acuerda la
     * primera cita como en cualquier caso nuevo.
     */
    public function tomar(Request $request, Caso $caso): RedirectResponse
    {
        $profesional = $request->user();
        $volver = route('solicitudes.index').'#solicitud-'.$caso->id_caso;

        try {
            $caso = DB::transaction(function () use ($caso, $profesional, $volver) {
                $caso = Caso::whereKey($caso->id_caso)->lockForUpdate()->firstOrFail();

                if ($caso->estado_caso !== Caso::ESTADO_PENDIENTE || $caso->id_profesional !== null) {
                    throw ValidationException::withMessages([
                        'solicitud' => "La solicitud {$caso->codigo_expediente} ya tiene profesional asignado. Actualiza la página.",
                    ])->errorBag(self::BOLSA)->redirectTo($volver);
                }

                $caso->forceFill(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $profesional->id_usuario])->save();

                return $caso;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return redirect()->to($volver)->with('error', 'No fue posible asignarte la solicitud en este momento. Intenta de nuevo en unos minutos.');
        }

        return redirect()
            ->to(route('solicitudes.index').'#solicitud-'.$caso->id_caso)
            ->with('status', "El caso {$caso->codigo_expediente} quedó a tu cargo. Acuerda con el estudiante la primera cita y agéndala aquí.");
    }

    /**
     * Mismas reglas de la cita que agenda el profesional (HU-011): franja futura y libre en su agenda.
     *
     * @return array{0: string, 1: string}|null  Campo y mensaje del error.
     */
    private function errorDeHorario(User $profesional, Carbon $inicio): ?array
    {
        $ahora = now(Actuacion::ZONA_HORARIA);

        return match (true) {
            $inicio->toDateString() < $ahora->toDateString() => ['fecha', 'La fecha de la cita no puede ser anterior a hoy.'],
            $inicio->lte($ahora) => ['hora', 'Esa hora ya pasó. Elige una hora posterior o otra fecha.'],
            Actuacion::horarioOcupado($profesional->id_usuario, $inicio) => ['hora', StoreCitaRequest::mensajeConflicto($inicio)],
            default => null,
        };
    }
}

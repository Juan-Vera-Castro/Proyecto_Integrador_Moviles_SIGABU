<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudCitaRequest;
use App\Models\Actuacion;
use App\Models\AsignacionProfesional;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\ModeloConsentimiento;
use App\Models\SolicitudAtencion;
use App\Support\Avisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class SolicitudCitaController extends Controller
{
    /**
     * Formulario por pasos con el que el estudiante solicita una cita. La facultad, el programa
     * y la jornada se toman de su vinculación; solo se preguntan si no están registrados.
     */
    public function create(Request $request): View
    {
        $academicos = $request->user()->persona?->datosAcademicos() ?? ['facultad' => null, 'programa' => null, 'jornada' => null, 'semestre' => null];

        return view('citas.solicitar', [
            'servicios' => SolicitudAtencion::SERVICIOS,
            'consentimientos' => collect(SolicitudAtencion::SERVICIOS)
                ->mapWithKeys(fn (string $tipo) => [$tipo => ModeloConsentimiento::versionVigenteDe($tipo)])
                ->filter(),
            'facultades' => Facultad::activas()->get(),
            'facultadRegistrada' => $academicos['facultad'],
            'jornadaRegistrada' => $academicos['jornada'],
            'programa' => $academicos['programa'],
            'semestre' => $academicos['semestre'],
            'jornadas' => SolicitudAtencion::JORNADAS,
            'franjas' => SolicitudAtencion::FRANJAS,
        ]);
    }

    /**
     * Registra la solicitud; el trigger de la base crea su caso con el código de expediente.
     * El caso llega directo al psicólogo asignado a la facultad y jornada del estudiante; si
     * no hay ninguno configurado, queda "Pendiente Asignación" y lo puede tomar un profesional.
     * Si el servicio tiene consentimiento vigente, queda la versión exacta que aceptó (HU-009).
     * Al final se avisa a quien debe atenderla.
     */
    public function store(StoreSolicitudCitaRequest $request, Avisos $avisos): RedirectResponse
    {
        $datos = $request->validated();
        $idFacultad = $request->idFacultad();
        $jornada = $request->jornada();

        try {
            $caso = DB::transaction(function () use ($datos, $request, $idFacultad, $jornada) {
                $solicitud = $request->user()->persona->solicitudes()->create([
                    'tipo_servicio' => $datos['tipo_servicio'],
                    'id_facultad' => $idFacultad,
                    'jornada' => $jornada,
                    'franja_horaria' => $datos['franja_horaria'],
                    'es_emergencia' => $request->boolean('es_emergencia'),
                ]);

                $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();

                if ($profesional = AsignacionProfesional::profesionalPara($datos['tipo_servicio'], $idFacultad, $jornada)) {
                    $caso->forceFill(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $profesional->id_usuario])->save();
                }

                if ($version = $request->versionConsentimiento()) {
                    $caso->consentimientos()->create([
                        'aceptado' => true,
                        'fecha_hora_aceptacion' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
                        'id_version' => $version->id_version,
                        'version_formato' => $version->etiqueta(),
                    ]);
                }

                return $caso->load('profesional.persona');
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No fue posible enviar tu solicitud en este momento. No se guardó ningún dato; intenta de nuevo en unos minutos.');
        }

        // Al profesional asignado (o a los del área, si quedó sin asignar). Prioritario si es emergencia.
        $avisos->nuevaSolicitud($caso);

        $mensaje = $caso->profesional
            ? "Recibimos tu solicitud {$caso->codigo_expediente}. Tu caso quedó a cargo de {$caso->profesional->persona?->nombre_completo}, que te contactará para acordar la fecha y la hora de tu cita."
            : "Recibimos tu solicitud {$caso->codigo_expediente}. Bienestar Universitario te asignará un profesional y te contactará para acordar la fecha y la hora de tu cita.";

        if ($request->boolean('es_emergencia')) {
            $mensaje .= ' La marcaste como emergencia: aparecerá como prioritaria para el psicólogo.';
        }

        return redirect()->route('citas.miscitas')->with('status', $mensaje);
    }
}

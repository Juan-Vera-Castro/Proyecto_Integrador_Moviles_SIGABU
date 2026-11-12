<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRemisionRequest;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\FormatoInstitucional;
use App\Models\RemisionExterna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RemisionController extends Controller
{
    /**
     * Registra la remisión del caso (HU-007) a otro profesional o a una entidad externa.
     *
     * En una transacción, con el caso bloqueado: las citas próximas pendientes se cancelan
     * (si el profesional lo confirmó), las pasadas sin asistencia quedan como "No asistió",
     * las tareas pendientes se cancelan, y el caso pasa al profesional destino o se cierra.
     */
    public function store(StoreRemisionRequest $request, Caso $caso): RedirectResponse
    {
        $volver = route('casos.show', $caso).'#remisiones';

        if (! $caso->estaActivo()) {
            return redirect()->to($volver)->with('error', "Solo se pueden remitir casos activos. El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\".");
        }

        $datos = $request->validated();
        $idProfesional = $request->user()->id_usuario;

        try {
            [$caso, $remision, $resumen] = DB::transaction(function () use ($caso, $datos, $request, $idProfesional, $volver) {
                $caso = Caso::whereKey($caso->id_caso)->lockForUpdate()->firstOrFail();

                // El caso pudo cambiar de estado o de profesional mientras se llenaba el formulario.
                if (! $caso->estaActivo() || $caso->id_profesional !== $idProfesional) {
                    throw ValidationException::withMessages([
                        'destino_remision' => "El caso {$caso->codigo_expediente} ya no está activo o no está asignado a ti. Actualiza la página y verifica el caso.",
                    ])->errorBag('remision')->redirectTo($volver);
                }

                $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();
                $citasPendientes = Actuacion::citas()
                    ->where('id_caso', $caso->id_caso)
                    ->where('estado_asistencia', Actuacion::ASISTENCIA_PENDIENTE);

                if (! ($datos['confirmar_citas'] ?? false) && (clone $citasPendientes)->exists()) {
                    throw ValidationException::withMessages([
                        'confirmar_citas' => 'El caso tiene citas pendientes. Confirma que se cancelen las próximas y que las pasadas sin asistencia queden como "No asistió" para poder remitirlo.',
                    ])->errorBag('remision')->redirectTo($volver);
                }

                $interna = $datos['destino_remision'] === Actuacion::DESTINO_INTERNA;

                $remision = Actuacion::create([
                    'id_caso' => $caso->id_caso,
                    'id_profesional' => $idProfesional,
                    'tipo_actuacion' => Actuacion::TIPO_REMISION,
                    'fecha_hora' => $ahora,
                    'descripcion' => $datos['motivo'] ?? null,
                    'destino_remision' => $datos['destino_remision'],
                    'id_profesional_destino' => $interna ? $datos['id_profesional_destino'] : null,
                    'entidad_destino' => $request->entidadDestino(),
                    'autorizacion_traslado' => $datos['autorizacion_traslado'] ?? null,
                ]);

                if (! $interna) {
                    $this->registrarDetalleExterno($remision, $caso, $datos, $request);
                }

                $resumen = $remision->liberarPendientesDelCaso();

                // Interna: la atención sigue con el profesional destino. Externa: sale de la universidad.
                $caso->forceFill($interna
                    ? ['id_profesional' => $datos['id_profesional_destino'], 'estado_caso' => Caso::ESTADO_ABIERTO]
                    : ['estado_caso' => Caso::ESTADO_CERRADO, 'fecha_cierre' => $ahora]
                )->save();

                return [$caso, $remision, $resumen];
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return redirect()->to($volver)->withInput()->with('error', 'No fue posible remitir el caso en este momento. No se guardó ningún dato; intenta de nuevo en unos minutos.');
        }

        $remision->load('profesionalDestino.persona');

        return redirect()
            ->to(route('casos.show', $caso).'#remisiones')
            ->with('status', "Caso {$caso->codigo_expediente} remitido a {$remision->etiquetaDestino()}. "
                .($remision->esRemisionInterna() ? 'Ahora está a su cargo; conservas la historia clínica hasta hoy.' : 'El caso queda cerrado.')
                .$this->describirResumen($resumen)
                .($remision->esRemisionInterna() ? '' : ' Descarga el documento de remisión en PDF o Word desde el historial de remisiones para imprimirlo y firmarlo.'));
    }

    /**
     * Detalle con el formato GBU-GPGR-F-016 (RQ-2): categoría, acompañante si es urgente y una
     * copia de los datos del estudiante, de la atención y del formato vigente (RN-09, RN-13).
     * La atención es la última cita a la que asistió el estudiante o, si no hubo, la remisión (D-10).
     */
    private function registrarDetalleExterno(Actuacion $remision, Caso $caso, array $datos, StoreRemisionRequest $request): void
    {
        $persona = $caso->solicitud->persona;
        $cita = $caso->ultimaCitaAsistida();
        $atencion = $cita?->fecha_hora ?? $remision->fecha_hora;
        $formato = FormatoInstitucional::deClave(config('sigabu.formato_remision'));
        $urgente = $datos['categoria'] === RemisionExterna::URGENTE;

        RemisionExterna::create([
            'id_actuacion' => $remision->id_actuacion,
            'categoria' => $datos['categoria'],
            'estudiante_nombre' => $persona->nombre_completo,
            'estudiante_tipo_documento' => $persona->tipo_documento,
            'estudiante_numero_documento' => $persona->numero_documento,
            'estudiante_sexo' => $persona->sexo,
            'estudiante_fecha_nacimiento' => $persona->fecha_nacimiento->toDateString(),
            'estudiante_edad' => $persona->edadEn($atencion),
            'id_cita' => $cita?->id_actuacion,
            'fecha_hora_atencion' => $atencion->toDateTimeString(),
            'profesional_nombre' => $request->user()->persona?->nombre_completo ?? $request->user()->codigo_unico,
            ...collect(StoreRemisionRequest::CAMPOS_ACOMPANANTE)->mapWithKeys(fn (string $campo) => [$campo => $urgente ? $datos[$campo] : null])->all(),
            'formato_codigo' => $formato->codigo,
            'formato_version' => $formato->version,
            'formato_fecha' => $formato->fecha_formato->toDateString(),
        ]);

        AuditoriaExpediente::registrar(AuditoriaExpediente::REMISION_EXTERNA_REGISTRADA, $caso, [
            'id_actuacion' => $remision->id_actuacion,
            'categoria' => $datos['categoria'],
        ]);
    }

    /**
     * Lo que la remisión dejó sin efecto, p. ej. " Citas próximas canceladas: 2. Tareas pendientes canceladas: 1."
     *
     * @param  array{citas_canceladas: int, citas_sin_asistencia: int, tareas_canceladas: int}  $resumen
     */
    private function describirResumen(array $resumen): string
    {
        $etiquetas = [
            'citas_canceladas' => 'Citas próximas canceladas',
            'citas_sin_asistencia' => 'Citas pasadas registradas como "No asistió"',
            'tareas_canceladas' => 'Tareas pendientes canceladas',
        ];

        return collect($etiquetas)
            ->filter(fn (string $etiqueta, string $clave) => $resumen[$clave] > 0)
            ->map(fn (string $etiqueta, string $clave) => " {$etiqueta}: {$resumen[$clave]}.")
            ->implode('');
    }
}

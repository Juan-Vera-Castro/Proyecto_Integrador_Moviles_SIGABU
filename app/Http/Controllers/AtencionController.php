<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\Atencion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AtencionController extends Controller
{
    /**
     * Registra una atención en el expediente. Solo el profesional a cargo, en un caso activo
     * que admite atención (con el consentimiento al día). Queda en la auditoría y no se modifica.
     */
    public function store(Request $request, string $caso): RedirectResponse
    {
        $caso = $this->casoACargo($request, $caso);

        $request->merge([
            'observaciones' => trim((string) $request->input('observaciones')),
            'acuerdos_compromisos' => trim((string) $request->input('acuerdos_compromisos')) ?: null,
        ]);

        $ahora = now(Actuacion::ZONA_HORARIA);

        $datos = $request->validateWithBag('atencion', [
            'tipo_atencion' => ['required', Rule::in(Atencion::TIPOS)],
            'asistencia' => ['required', Rule::in(Atencion::ASISTENCIAS)],
            'fecha_atencion' => ['required', 'date', 'before_or_equal:'.$ahora->format('Y-m-d H:i:s')],
            'observaciones' => ['required', 'string', 'min:'.Atencion::OBSERVACIONES_MIN, 'max:'.Atencion::OBSERVACIONES_MAX],
            'acuerdos_compromisos' => ['nullable', 'string', 'max:'.Atencion::ACUERDOS_MAX],
        ], [
            'tipo_atencion.required' => 'Selecciona el tipo de atención.',
            'tipo_atencion.in' => 'Selecciona el tipo de atención.',
            'asistencia.required' => 'Indica la asistencia del estudiante.',
            'asistencia.in' => 'Indica la asistencia del estudiante.',
            'fecha_atencion.required' => 'Indica la fecha y la hora de la atención.',
            'fecha_atencion.date' => 'Indica la fecha y la hora de la atención.',
            'fecha_atencion.before_or_equal' => 'La atención no puede tener una fecha futura.',
            'observaciones.required' => 'Escribe las observaciones de la atención.',
            'observaciones.min' => 'Las observaciones deben tener al menos '.Atencion::OBSERVACIONES_MIN.' caracteres.',
            'observaciones.max' => 'Las observaciones no pueden superar los '.Atencion::OBSERVACIONES_MAX.' caracteres.',
            'acuerdos_compromisos.max' => 'Los acuerdos no pueden superar los '.Atencion::ACUERDOS_MAX.' caracteres.',
        ]);

        $volver = redirect()->to(route('profesional.expediente.show', $caso).'#registro-atenciones');

        if (! $caso->estaActivo()) {
            return $volver->with('error', "Las atenciones solo se registran en casos activos. El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\".");
        }

        if ($motivo = $caso->motivoSinAtencion()) {
            return $volver->with('error', $motivo);
        }

        DB::transaction(function () use ($caso, $datos, $request, $ahora) {
            $atencion = Atencion::create($datos + [
                'id_caso' => $caso->id_caso,
                'id_profesional' => $request->user()->id_usuario,
                'fecha_registro' => $ahora->toDateTimeString(),
            ]);

            AuditoriaExpediente::registrar(AuditoriaExpediente::ATENCION_REGISTRADA, $caso, [
                'id_atencion' => $atencion->id_atencion,
                'tipo_atencion' => $atencion->tipo_atencion,
            ]);
        });

        return $volver->with('status', 'Atención registrada en el expediente.');
    }

    /**
     * Solo el profesional a cargo; a los demás, 404 para no revelar que el caso existe
     * (igual que el expediente).
     */
    private function casoACargo(Request $request, string $caso): Caso
    {
        return Caso::where('id_profesional', $request->user()->id_usuario)->whereKey($caso)->first()
            ?? abort(404, 'Expediente no encontrado.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\AlertaTemprana;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Expediente digital único (HU-005): reúne en una sola vista todo lo registrado en el caso
 * (consentimientos, atenciones, seguimientos, documentos) a partir de las tablas que ya usa
 * cada módulo. Aquí también se registran las atenciones, los factores de riesgo y protectores
 * y se atienden las alertas tempranas (HU-026). La gestión del caso sigue en casos.show.
 */
class ExpedienteController extends Controller
{
    public function show(Request $request, string $caso): View
    {
        $caso = $this->casoACargo($request, $caso);

        $caso->load([
            'solicitud.persona', 'solicitud.facultad', 'profesional.persona',
            'aceptacionesConsentimiento.version.modelo', 'aceptacionesConsentimiento.firmaProfesional', 'aceptacionesConsentimiento.detalleInclusion', 'consentimientos.version.modelo',
            'citas.profesional.persona', 'citas.seguimiento',
            'seguimientos.profesional.persona', 'seguimientos.cita',
            'historiaClinica.profesional.persona',
            'remisiones.profesional.persona', 'remisiones.profesionalDestino.persona', 'remisiones.remisionExterna',
            'desistimientos.profesional.persona', 'atenciones.profesional.persona', 'alertas.usuarioAtendio.persona',
        ]);

        if ($caso->esDeInclusion()) {
            $caso->load('ajustes.seguimientos.profesional.persona', 'ajustes.profesional.persona', 'bitacora.profesional.persona', 'bitacora.constancias', 'bitacora.reaperturas');
        }

        AuditoriaExpediente::registrar(AuditoriaExpediente::EXPEDIENTE_CONSULTADO, $caso);

        return view('profesional.expediente.show', [
            'caso' => $caso,
            'estado' => $caso->estado_caso,
        ]);
    }

    /**
     * Guarda los factores de riesgo y protectores del caso (solo los de la lista). Si con ellos
     * el caso tiene un factor crítico o está en riesgo alto, se genera una alerta temprana.
     */
    public function actualizarFactores(Request $request, string $caso): RedirectResponse
    {
        $caso = $this->casoACargo($request, $caso);

        $datos = $request->validateWithBag('factores', [
            'factores_riesgo' => ['nullable', 'array'],
            'factores_riesgo.*' => ['string', Rule::in(Caso::FACTORES_RIESGO)],
            'factores_protectores' => ['nullable', 'array'],
            'factores_protectores.*' => ['string', Rule::in(Caso::FACTORES_PROTECTORES)],
        ], [
            'factores_riesgo.*.in' => 'Uno de los factores de riesgo no es válido.',
            'factores_protectores.*.in' => 'Uno de los factores protectores no es válido.',
        ]);

        $volver = redirect()->to(route('profesional.expediente.show', $caso).'#factores');

        if (! $caso->estaActivo()) {
            return $volver->with('error', "Los factores solo se actualizan en casos activos. El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\".");
        }

        // En el orden de la lista y sin repetidos.
        $riesgo = array_values(array_intersect(Caso::FACTORES_RIESGO, $datos['factores_riesgo'] ?? []));
        $protectores = array_values(array_intersect(Caso::FACTORES_PROTECTORES, $datos['factores_protectores'] ?? []));

        $alerta = DB::transaction(function () use ($caso, $riesgo, $protectores) {
            $caso->update(['factores_riesgo' => $riesgo, 'factores_protectores' => $protectores]);

            AuditoriaExpediente::registrar(AuditoriaExpediente::FACTORES_ACTUALIZADOS, $caso, [
                'factores_riesgo' => $riesgo,
                'factores_protectores' => $protectores,
            ]);

            return AlertaTemprana::evaluar($caso);
        });

        return $volver->with('status', $alerta
            ? 'Factores guardados. Se generó una alerta temprana: '.mb_strtolower($alerta->tipo_alerta).'.'
            : 'Factores de riesgo y protectores guardados.');
    }

    /**
     * Marca como atendida la alerta pendiente de un caso a cargo del profesional.
     */
    public function atenderAlerta(Request $request, string $alerta): RedirectResponse
    {
        $alerta = AlertaTemprana::whereKey($alerta)
            ->whereHas('caso', fn ($caso) => $caso->where('id_profesional', $request->user()->id_usuario))
            ->first() ?? abort(404, 'Alerta no encontrada.');

        $volver = redirect()->to(route('profesional.expediente.show', $alerta->id_caso).'#alertas');

        // Condición en el UPDATE: si dos personas la atienden al tiempo, solo cuenta la primera.
        $atendida = DB::transaction(function () use ($alerta, $request) {
            $filas = AlertaTemprana::whereKey($alerta->id_alerta)->pendientes()->update([
                'estado' => AlertaTemprana::ATENDIDA,
                'atendida_por' => $request->user()->id_usuario,
                'fecha_atencion' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            ]);

            if ($filas === 1) {
                AuditoriaExpediente::registrar(AuditoriaExpediente::ALERTA_ATENDIDA, $alerta->caso, [
                    'id_alerta' => $alerta->id_alerta,
                    'tipo_alerta' => $alerta->tipo_alerta,
                ]);
            }

            return $filas === 1;
        });

        return $atendida
            ? $volver->with('status', 'Alerta temprana marcada como atendida y registrada en la auditoría del expediente.')
            : $volver->with('error', 'Esta alerta ya estaba atendida.');
    }

    /**
     * Solo el profesional asignado; a los demás (también a quien remitió el caso), 404 para no
     * revelar que existe.
     */
    private function casoACargo(Request $request, string $caso): Caso
    {
        return Caso::where('id_profesional', $request->user()->id_usuario)->whereKey($caso)->first()
            ?? abort(404, 'Expediente no encontrado.');
    }
}

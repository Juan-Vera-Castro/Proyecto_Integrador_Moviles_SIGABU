<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Desistimiento;
use App\Models\FormatoInstitucional;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Desistimiento de la atención: el profesional del caso registra que la persona decidió no
 * continuar o no contar lo sucedido. El caso se cierra, lo pendiente se cancela y queda el
 * formato para imprimir y firmar, que es el respaldo legal de la universidad.
 */
class DesistimientoController extends Controller
{
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        $volver = route('casos.show', $caso).'#desistimiento';
        abort_unless($caso->id_profesional === $request->user()->id_usuario, 403);

        $request->merge(['observaciones' => trim((string) $request->input('observaciones')) ?: null]);

        $datos = $request->validateWithBag('desistimiento', [
            'motivo' => ['required', Rule::in(Desistimiento::MOTIVOS)],
            'observaciones' => [Rule::requiredIf($request->input('motivo') === Desistimiento::OTRO), 'nullable', 'string', 'max:'.Desistimiento::OBSERVACIONES_MAX],
            'confirmacion' => ['accepted'],
        ], [
            'motivo.required' => 'Selecciona el motivo del desistimiento.',
            'motivo.in' => 'Selecciona un motivo de la lista.',
            'observaciones.required' => 'Describe el motivo.',
            'confirmacion.accepted' => 'Confirma que la persona manifestó su decisión de desistir.',
        ]);

        DB::transaction(function () use ($request, $caso, $datos, $volver) {
            $caso = Caso::whereKey($caso->id_caso)->lockForUpdate()->with('solicitud.persona')->firstOrFail();

            if (! $caso->estaActivo() || $caso->id_profesional !== $request->user()->id_usuario) {
                throw ValidationException::withMessages(['motivo' => "El caso {$caso->codigo_expediente} ya no está activo o no está a tu cargo."])
                    ->errorBag('desistimiento')->redirectTo($volver);
            }

            $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();
            $persona = $caso->solicitud?->persona;
            $formato = FormatoInstitucional::deClave(config('sigabu.formato_desistimiento'));

            $desistimiento = Desistimiento::create([
                'id_caso' => $caso->id_caso,
                'id_profesional' => $request->user()->id_usuario,
                'motivo' => $datos['motivo'],
                'observaciones' => $datos['observaciones'] ?? null,
                'fecha_hora' => $ahora,
                'estudiante_nombre' => $persona?->nombre_completo ?? 'Sin registro',
                'estudiante_tipo_documento' => $persona?->tipo_documento,
                'estudiante_numero_documento' => $persona?->numero_documento ?? 'Sin registro',
                'profesional_nombre' => $request->user()->persona?->nombre_completo ?? $request->user()->codigo_unico,
                'formato_codigo' => $formato->codigo,
                'formato_version' => $formato->version,
                'formato_fecha' => $formato->fecha_formato->toDateString(),
            ]);

            $caso->cancelarPendientes($ahora, 'Sin registro de asistencia al registrar el desistimiento.');

            $caso->forceFill(['estado_caso' => Caso::ESTADO_CERRADO, 'fecha_cierre' => $ahora])->save();

            AuditoriaExpediente::registrar(AuditoriaExpediente::DESISTIMIENTO_REGISTRADO, $caso, ['id_desistimiento' => $desistimiento->id_desistimiento, 'motivo' => $datos['motivo']]);
        });

        return redirect()->to($volver)->with('status', "Desistimiento registrado: el caso {$caso->codigo_expediente} quedó cerrado. Descarga el formato para imprimirlo y firmarlo.");
    }

    /**
     * Formato de desistimiento en PDF, para imprimir y firmar. Lo descarga el profesional que
     * lo registró o el profesional actual del caso.
     */
    public function pdf(Request $request, Desistimiento $desistimiento): Response
    {
        $usuario = $request->user()->id_usuario;
        abort_unless($desistimiento->id_profesional === $usuario || $desistimiento->caso?->id_profesional === $usuario, 403);

        $formato = FormatoInstitucional::deClave(config('sigabu.formato_desistimiento'));

        $pdf = Pdf::loadView('documentos.desistimiento', [
            'd' => $desistimiento->load('caso'),
            'proceso' => $formato->proceso,
            'nombreFormato' => $formato->nombre,
            'fechaFormato' => FormatoInstitucional::fechaComoFormato($desistimiento->formato_fecha),
            'ciudad' => config('sigabu.ciudad'),
            'logo' => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-uceva.jpg'))),
        ])->setPaper('letter');

        AuditoriaExpediente::registrar(AuditoriaExpediente::DOCUMENTO_DESISTIMIENTO_PDF, $desistimiento->caso, ['id_desistimiento' => $desistimiento->id_desistimiento]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Desistimiento-'.$desistimiento->caso?->codigo_expediente.'-'.$desistimiento->fecha_hora->format('d-m-y').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}

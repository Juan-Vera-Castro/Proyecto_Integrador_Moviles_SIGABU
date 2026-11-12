<?php

namespace App\Http\Controllers\Inclusion;

use App\Documentos\PdfInclusion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inclusion\GuardarCaracterizacionRequest;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Inclusion\Bitacora;
use App\Models\Inclusion\BitacoraCambio;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\Evidencia;
use App\Support\AlmacenEvidencias;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Bitácora de orientación y acompañamiento: los datos generales salen del caso, la
 * caracterización se corrige con el caso abierto y cada cambio queda registrado (RN-19).
 */
class BitacoraController extends Controller
{
    public function __construct(private AlmacenEvidencias $almacen) {}

    public function show(Request $request, Caso $caso): View
    {
        Gate::authorize('inclusion.gestionar', $caso);

        $caso->load([
            'solicitud.persona',
            'aceptacionesConsentimiento.detalleInclusion',
            'bitacora.soportes',
            'bitacora.cambios.profesional.persona',
            'bitacora.entradas.profesional.persona',
            'bitacora.entradas.anotaciones.profesional.persona',
            'bitacora.entradas.evidencias',
            'bitacora.constancias',
            'bitacora.reaperturas.administrador.persona',
        ]);

        $usuario = $request->user();
        $bitacora = $caso->bitacora;
        $borrador = Borrador::de($caso, $usuario, Borrador::BITACORA);
        $vigente = $caso->consentimientoVigente();
        $detalle = $vigente?->detalleInclusion;
        $datos = $borrador?->datos ?? [];

        return view('inclusion.bitacora.show', [
            'caso' => $caso,
            'bitacora' => $bitacora,
            'borrador' => $borrador,
            'detalle' => $detalle,
            'sinDatosSensibles' => $detalle !== null && ! $detalle->decision_datos_sensibles,
            'motivoBloqueo' => $caso->motivoSinRegistroInclusion(),
            'iniciales' => array_merge([
                'rol_institucional' => $bitacora?->rol_institucional ?? $detalle?->vinculacion_detalle,
                'programa_dependencia' => $bitacora?->programa_dependencia ?? $vigente?->programa_academico ?? $vigente?->dependencia_facultad,
                'situacion' => $bitacora?->situacion,
                'ruta_activada' => $bitacora?->ruta_activada,
                'soportes_texto' => $bitacora?->soportes_texto,
                'orientacion' => $bitacora?->orientacion,
            ], $datos),
            'soportesTemporales' => $this->almacen->temporalesParaFormulario($caso, $usuario, old('soportes', $datos['soportes'] ?? [])),
            'evidenciasTemporales' => $this->almacen->temporalesParaFormulario($caso, $usuario, old('evidencias', [])),
        ]);
    }

    public function guardar(GuardarCaracterizacionRequest $request, Caso $caso): RedirectResponse
    {
        if ($motivo = $caso->motivoSinRegistroInclusion()) {
            return back()->withInput()->with('error', $motivo);
        }

        $datos = $request->caracterizacion();
        $usuario = $request->user();
        $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();
        $nueva = ! $caso->bitacora;

        try {
            DB::transaction(function () use ($request, $caso, $datos, $usuario, $ahora) {
                $bitacora = $caso->bitacora ?? Bitacora::create($datos + [
                    'id_caso' => $caso->id_caso,
                    'id_profesional' => $usuario->id_usuario,
                    'fecha_registro' => $ahora,
                    'fecha_actualizacion' => $ahora,
                ]);

                $cambios = $bitacora->wasRecentlyCreated ? [] : $this->registrarCambios($bitacora, $datos, $usuario->id_usuario, $ahora);

                if ($cambios) {
                    $bitacora->update($datos + ['fecha_actualizacion' => $ahora]);
                }

                $this->almacen->vincular($caso, $request->validated('soportes') ?? [], Evidencia::DESTINO_SOPORTE, $bitacora->id_bitacora, $usuario);
                Borrador::terminar($caso, $usuario, Borrador::BITACORA);

                // Solo los nombres de los campos: los valores son sensibles y el administrador ve la auditoría.
                AuditoriaExpediente::registrar(
                    $bitacora->wasRecentlyCreated ? AuditoriaExpediente::BITACORA_REGISTRADA : AuditoriaExpediente::BITACORA_ACTUALIZADA,
                    $caso,
                    ['id_bitacora' => $bitacora->id_bitacora, 'campos' => $cambios],
                );
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No fue posible guardar la bitácora. No se guardó nada; intenta de nuevo.');
        }

        return redirect()->route('inclusion.bitacora', $caso)->with('status', $nueva ? 'La bitácora quedó registrada.' : 'La caracterización quedó actualizada.');
    }

    /**
     * @param  array<string, string|null>  $datos
     * @return list<string> campos que cambiaron
     */
    private function registrarCambios(Bitacora $bitacora, array $datos, string $idProfesional, string $ahora): array
    {
        $cambios = [];

        foreach ($datos as $campo => $valor) {
            if ((string) $bitacora->{$campo} === (string) $valor) {
                continue;
            }

            BitacoraCambio::create([
                'id_bitacora' => $bitacora->id_bitacora,
                'campo' => $campo,
                'valor_anterior' => $bitacora->{$campo},
                'valor_nuevo' => $valor,
                'id_profesional' => $idProfesional,
                'fecha_hora' => $ahora,
            ]);

            $cambios[] = $campo;
        }

        return $cambios;
    }

    /**
     * Secciones I a IV con la constancia firmada. Solo el profesional asignado (RN-05).
     */
    public function pdf(Caso $caso): Response
    {
        Gate::authorize('inclusion.gestionar', $caso);

        $datos = PdfInclusion::bitacora($caso);
        $respuesta = PdfInclusion::descargar('documentos.bitacora-inclusion', $datos, PdfInclusion::nombreArchivo('Bitacora', $caso), 'landscape');

        AuditoriaExpediente::registrar(AuditoriaExpediente::BITACORA_PDF, $caso, ['entradas' => $datos['bitacora']?->entradas->count() ?? 0]);

        return $respuesta;
    }
}

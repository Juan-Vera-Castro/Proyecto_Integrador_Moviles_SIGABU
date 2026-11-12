<?php

namespace App\Http\Controllers\Inclusion;

use App\Documentos\PdfInclusion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inclusion\RegistrarAjustePlantillaRequest;
use App\Models\Actuacion;
use App\Models\AjusteRazonable;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\Evidencia;
use App\Models\Inclusion\Recurso;
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
 * Plantilla 5.8 de ajustes pedagógicos. Cada fila es un ajuste razonable del caso: una vez
 * guardada no se edita, y su avance va por el seguimiento existente (RN-24, RN-25).
 */
class PlantillaAjustesController extends Controller
{
    public function __construct(private AlmacenEvidencias $almacen) {}

    public function show(Request $request, Caso $caso): View
    {
        Gate::authorize('inclusion.gestionar', $caso);

        $caso->load(['ajustes.evidencias', 'ajustes.recurso', 'aceptacionesConsentimiento.detalleInclusion']);

        $usuario = $request->user();
        $borrador = Borrador::de($caso, $usuario, Borrador::PLANTILLA);
        $datos = $borrador?->datos ?? [];
        $recursos = Recurso::activos()->orderBy('titulo')->get();
        $idRecurso = old('id_recurso', $datos['id_recurso'] ?? $recursos->first()?->id_recurso);

        return view('inclusion.plantilla.show', [
            'caso' => $caso,
            'borrador' => $borrador,
            'iniciales' => $datos,
            'recursos' => $recursos,
            'recurso' => $recursos->firstWhere('id_recurso', (int) $idRecurso),
            'motivoBloqueo' => $caso->motivoSinRegistroInclusion(),
            'evidenciasTemporales' => $this->almacen->temporalesParaFormulario($caso, $usuario, old('evidencias', $datos['evidencias'] ?? [])),
        ]);
    }

    public function store(RegistrarAjustePlantillaRequest $request, Caso $caso): RedirectResponse
    {
        if ($motivo = $caso->motivoSinRegistroInclusion()) {
            return back()->withInput()->with('error', $motivo);
        }

        $datos = $request->validated();
        $usuario = $request->user();

        try {
            DB::transaction(function () use ($caso, $datos, $usuario) {
                $ajuste = AjusteRazonable::create([
                    'id_caso' => $caso->id_caso,
                    'id_profesional' => $usuario->id_usuario,
                    'area' => $datos['area'],
                    'barrera' => $datos['barrera'],
                    'descripcion' => $datos['descripcion'],
                    'responsable' => $datos['responsable'],
                    'fecha_inicio' => $datos['fecha_inicio'],
                    'observaciones' => $datos['observaciones'] ?? null,
                    'id_recurso' => $datos['id_recurso'] ?? null,
                    'fecha_registro' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
                ]);

                $this->almacen->vincular($caso, $datos['evidencias'] ?? [], Evidencia::DESTINO_AJUSTE, $ajuste->id_ajuste, $usuario);
                Borrador::terminar($caso, $usuario, Borrador::PLANTILLA);

                AuditoriaExpediente::registrar(AuditoriaExpediente::AJUSTE_PLANTILLA_REGISTRADO, $caso, ['id_ajuste' => $ajuste->id_ajuste, 'area' => $datos['area']]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No fue posible guardar la fila. No se guardó nada; intenta de nuevo.');
        }

        return redirect()->route('inclusion.plantilla', $caso)->with('status', 'La fila quedó registrada en la plantilla.');
    }

    /**
     * Recurso y plantilla, solo para el expediente (CA-07.5).
     */
    public function pdf(Caso $caso): Response
    {
        Gate::authorize('inclusion.gestionar', $caso);

        $datos = PdfInclusion::plantilla($caso, false);
        $respuesta = PdfInclusion::descargar('documentos.recurso-plantilla-inclusion', $datos, PdfInclusion::nombreArchivo('Recurso-y-plantilla', $caso));

        AuditoriaExpediente::registrar(AuditoriaExpediente::PLANTILLA_PDF, $caso, ['filas' => $datos['ajustes']->count()]);

        return $respuesta;
    }

    /**
     * Solo la plantilla, para docentes y dependencias: sin el recurso ni la condición (RN-38).
     * El aviso de RN-39 se muestra antes en la pantalla y no bloquea la descarga.
     */
    public function compartir(Caso $caso): Response
    {
        Gate::authorize('inclusion.gestionar', $caso);

        $datos = PdfInclusion::plantilla($caso, true);
        $respuesta = PdfInclusion::descargar('documentos.plantilla-compartir-inclusion', $datos, PdfInclusion::nombreArchivo('Plantilla-ajustes', $caso));

        AuditoriaExpediente::registrar(AuditoriaExpediente::PLANTILLA_COMPARTIR_PDF, $caso, [
            'filas' => $datos['ajustes']->count(),
            'con_aviso' => PdfInclusion::avisoCompartir($caso) !== null,
        ]);

        return $respuesta;
    }
}

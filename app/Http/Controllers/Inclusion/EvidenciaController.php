<?php

namespace App\Http\Controllers\Inclusion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inclusion\SubirEvidenciaRequest;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Inclusion\Evidencia;
use App\Support\AlmacenEvidencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Archivos y links de los formatos de Inclusión: subir y quitar mientras se llena el formulario,
 * y descargar a través de la aplicación, porque el almacenamiento es privado (RN-31).
 */
class EvidenciaController extends Controller
{
    public function __construct(private AlmacenEvidencias $almacen) {}

    public function store(SubirEvidenciaRequest $request, Caso $caso): JsonResponse
    {
        if (! $caso->estaActivo()) {
            return response()->json(['message' => 'El caso está cerrado: no admite evidencias nuevas.'], 409);
        }

        try {
            $evidencia = $request->file('archivo')
                ? $this->almacen->subirArchivo($caso, $request->file('archivo'), $request->user())
                : $this->almacen->agregarEnlace($caso, $request->input('url'), $request->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo guardar la evidencia. Intenta de nuevo y, si sigue fallando, avisa al administrador.'], 503);
        }

        return response()->json(AlmacenEvidencias::paraFormulario($evidencia), 201);
    }

    /**
     * Quita una evidencia que aún no se guardó con su registro. Después es parte del proceso (RN-30).
     */
    public function quitar(Request $request, Evidencia $evidencia): JsonResponse
    {
        Gate::authorize('inclusion.gestionar', $evidencia->caso);
        abort_unless($evidencia->estado === Evidencia::TEMPORAL && $evidencia->subido_por === $request->user()->id_usuario, 404);

        $this->almacen->descartar($evidencia);

        return response()->json(['quitada' => true]);
    }

    public function archivo(Evidencia $evidencia): StreamedResponse
    {
        Gate::authorize('inclusion.gestionar', $evidencia->caso);
        abort_unless($evidencia->esArchivo() && $evidencia->estado !== Evidencia::DESCARTADA, 404);
        abort_unless(Storage::disk(Evidencia::DISCO)->exists($evidencia->ruta), 404);

        // Solo el id: el nombre del archivo puede ser un dato sensible y el administrador ve la auditoría.
        AuditoriaExpediente::registrar(AuditoriaExpediente::EVIDENCIA_DESCARGADA, $evidencia->caso, ['id_evidencia' => $evidencia->id_evidencia]);

        return $this->almacen->descargar($evidencia);
    }
}

<?php

namespace App\Http\Controllers\Inclusion;

use App\Http\Controllers\Controller;
use App\Models\Caso;
use App\Models\Inclusion\Borrador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Borradores de los formularios de Inclusión: se guardan en el servidor para retomarlos desde
 * cualquier dispositivo. Cada usuario ve solo el suyo.
 */
class BorradorController extends Controller
{
    /**
     * Tamaño máximo del borrador en JSON: cubre la matriz y la plantilla con holgura.
     */
    private const TAMANO_MAX = 200000;

    /**
     * Nunca se guardan en un borrador (CA-10.3).
     */
    private const CAMPOS_EXCLUIDOS = ['firma', '_token', '_method'];

    public function guardar(Request $request, Caso $caso, string $documento): JsonResponse
    {
        Gate::authorize($documento === Borrador::CONSENTIMIENTO ? 'inclusion.diligenciarConsentimiento' : 'inclusion.gestionar', $caso);

        if (! $caso->estaActivo()) {
            return response()->json(['message' => 'El caso ya no está activo: no se guardó el borrador.'], 409);
        }

        $request->validate(['datos' => ['present', 'array']]);
        $datos = Arr::except($request->input('datos'), self::CAMPOS_EXCLUIDOS);

        if (strlen((string) json_encode($datos)) > self::TAMANO_MAX) {
            throw ValidationException::withMessages(['datos' => 'El borrador es demasiado grande para guardarlo.']);
        }

        $borrador = Borrador::guardar($caso, $request->user(), $documento, $datos);

        return response()->json(['guardado' => $borrador->fecha_actualizacion->format('H:i')]);
    }
}

<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Models\RepositorioDocumento;
use Illuminate\Http\JsonResponse;

/**
 * Recursos de bienestar: lo publicado para todos en el repositorio (como la ruta recursos.index).
 * Los archivos se descargan por RepositorioController::archivo.
 */
class RecursosController extends Controller
{
    public function index(): JsonResponse
    {
        $documentos = RepositorioDocumento::paraTodos()->orderByDesc('fecha_publicacion')->get();

        return response()->json([
            'recursos' => $documentos->map(function (RepositorioDocumento $documento) {
                $archivo = $documento->tieneArchivo();
                [$icono, $tipo] = $archivo
                    ? match (strtolower(pathinfo((string) $documento->archivo_nombre, PATHINFO_EXTENSION))) {
                        'pdf' => ['📄', 'PDF'],
                        'jpg', 'jpeg', 'png' => ['🖼️', 'Imagen'],
                        'xls', 'xlsx' => ['📊', 'Hoja de cálculo'],
                        'ppt', 'pptx' => ['📑', 'Presentación'],
                        default => ['📝', 'Documento'],
                    }
                    : ['🔗', 'Enlace'];

                return [
                    'id' => $documento->id_documento,
                    'titulo' => $documento->titulo,
                    'categoria' => $documento->categoria,
                    'descripcion' => $documento->descripcion,
                    'icono' => $icono,
                    'tipo' => $tipo,
                    'tiene_archivo' => $archivo,
                    'archivo_nombre' => $archivo ? $documento->archivo_nombre : null,
                    'tamano' => $archivo ? $documento->tamanoLegible() : 'Sitio externo',
                    'url' => $archivo ? null : $documento->url,
                ];
            })->values(),
            'categorias' => $documentos->pluck('categoria')->filter()->unique()->values(),
        ]);
    }
}

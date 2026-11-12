<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\RepositorioDocumento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Repositorio de documentos de Bienestar. Lo consultan y alimentan el administrador y los
 * equipos (profesionales, VBG y Alimentos): cada documento es un archivo subido o un enlace.
 * Los archivos se guardan en un disco privado y solo se descargan a través de la aplicación.
 */
class RepositorioController extends Controller
{
    public function index(Request $request): View
    {
        $esAdmin = $request->user()->role === 'admin';
        $categoria = in_array($request->query('categoria'), RepositorioDocumento::CATEGORIAS, true) ? $request->query('categoria') : null;
        $busqueda = trim((string) $request->query('q'));

        $documentos = RepositorioDocumento::with('autor.persona')
            ->when(! $esAdmin, fn ($q) => $q->where(fn ($q) => $q->activos()->orWhere('publicado_por', $request->user()->id_usuario)))
            ->when($categoria, fn ($q) => $q->where('categoria', $categoria))
            ->orderByDesc('fecha_publicacion')
            ->get()
            ->when($busqueda !== '', fn ($coleccion) => $coleccion->filter(
                fn (RepositorioDocumento $documento) => str_contains(mb_strtolower($documento->titulo.' '.$documento->descripcion.' '.$documento->archivo_nombre), mb_strtolower($busqueda))
            ));

        return view('repositorio.index', [
            'documentos' => $documentos,
            'categoria' => $categoria,
            'busqueda' => $busqueda,
        ]);
    }

    /**
     * Publica un documento con un archivo, un enlace o ambos.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(collect(['titulo', 'descripcion', 'url'])->mapWithKeys(fn ($c) => [$c => trim((string) $request->input($c)) ?: null])->all());

        $datos = $request->validateWithBag('documento', [
            'titulo' => ['required', 'string', 'max:150'],
            'categoria' => ['required', Rule::in(RepositorioDocumento::CATEGORIAS)],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'archivo' => ['nullable', 'required_without:url', 'file', 'max:'.RepositorioDocumento::ARCHIVO_MAX_KB, 'extensions:'.implode(',', RepositorioDocumento::EXTENSIONES)],
            'url' => ['nullable', 'required_without:archivo', 'url:https', 'max:500'],
            'audiencia' => ['required', Rule::in(RepositorioDocumento::AUDIENCIAS)],
        ], [
            'titulo.required' => 'Escribe el título del documento.',
            'categoria.required' => 'Selecciona la categoría.',
            'archivo.required_without' => 'Sube un archivo o pega un enlace.',
            'archivo.uploaded' => 'No se pudo subir el archivo: es más grande de lo que permite el servidor.',
            'archivo.max' => 'El archivo no puede superar los '.(RepositorioDocumento::ARCHIVO_MAX_KB / 1024).' MB.',
            'archivo.extensions' => 'Formatos permitidos: '.strtoupper(implode(', ', RepositorioDocumento::EXTENSIONES)).'.',
            'url.required_without' => 'Sube un archivo o pega un enlace.',
            'url.url' => 'El enlace debe ser una dirección https:// válida.',
            'audiencia.required' => 'Indica quién puede verlo.',
        ]);

        $archivo = $request->file('archivo');
        $ruta = null;

        if ($archivo) {
            $ruta = now(Actuacion::ZONA_HORARIA)->format('Y/m').'/'.Str::uuid().'.'.strtolower($archivo->getClientOriginalExtension());

            try {
                Storage::disk(RepositorioDocumento::DISCO)->putFileAs(dirname($ruta), $archivo, basename($ruta));
            } catch (Throwable $e) {
                report($e);

                return back()->withInput()->with('error', 'No se pudo guardar el archivo. No se publicó nada; intenta de nuevo.');
            }
        }

        try {
            DB::transaction(fn () => RepositorioDocumento::create([
                'titulo' => $datos['titulo'],
                'categoria' => $datos['categoria'],
                'descripcion' => $datos['descripcion'] ?? null,
                'url' => $datos['url'] ?? null,
                'audiencia' => $datos['audiencia'],
                'activo' => true,
                'publicado_por' => $request->user()->id_usuario,
                'fecha_publicacion' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
                'archivo_ruta' => $ruta,
                'archivo_nombre' => $archivo ? Str::limit($archivo->getClientOriginalName(), 195, '') : null,
                'archivo_mime' => $archivo?->getMimeType(),
                'archivo_bytes' => $archivo?->getSize(),
                'archivo_sha256' => $archivo ? hash_file('sha256', $archivo->getRealPath()) : null,
            ]));
        } catch (Throwable $e) {
            report($e);
            // Si falla la base, el archivo no queda huérfano.
            if ($ruta) {
                rescue(fn () => Storage::disk(RepositorioDocumento::DISCO)->delete($ruta));
            }

            return back()->withInput()->with('error', 'No se pudo publicar el documento. Intenta de nuevo.');
        }

        return redirect()->route('repositorio.index')->with('status', "Documento \"{$datos['titulo']}\" publicado.");
    }

    /**
     * Descarga el archivo con su nombre original. Pasa por la aplicación para validar quién lo pide.
     */
    public function archivo(Request $request, RepositorioDocumento $documento): StreamedResponse
    {
        abort_unless($documento->tieneArchivo() && $documento->puedeVerlo($request->user()), 404);
        abort_unless(Storage::disk(RepositorioDocumento::DISCO)->exists($documento->archivo_ruta), 404);

        return Storage::disk(RepositorioDocumento::DISCO)->download($documento->archivo_ruta, $documento->archivo_nombre, [
            'Content-Type' => $documento->archivo_mime ?? 'application/octet-stream',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Retira o vuelve a publicar un documento. No se borra ni el registro ni el archivo.
     */
    public function cambiarEstado(Request $request, RepositorioDocumento $documento): RedirectResponse
    {
        abort_unless($documento->puedeRetirarlo($request->user()), 403);

        $documento->update(['activo' => ! $documento->activo]);

        return redirect()->route('repositorio.index')->with('status', $documento->activo ? 'Documento publicado de nuevo.' : 'Documento retirado: ya no lo ven los demás.');
    }
}

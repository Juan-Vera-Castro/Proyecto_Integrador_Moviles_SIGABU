<?php

namespace App\Support;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\Inclusion\Evidencia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Servicio único de archivos y links de los formatos de Inclusión. Cada archivo se sube solo y
 * queda temporal mientras se llena el formulario; al guardar pasa a vinculado, en la misma
 * transacción del registro. Nunca queda un archivo sin su fila ni una fila sin su archivo.
 */
class AlmacenEvidencias
{
    public function subirArchivo(Caso $caso, UploadedFile $archivo, User $usuario): Evidencia
    {
        $id = (string) Str::uuid();
        $extension = strtolower($archivo->getClientOriginalExtension());
        $carpeta = "casos/{$caso->id_caso}";
        $disco = Storage::disk(Evidencia::DISCO);

        $disco->putFileAs($carpeta, $archivo, "{$id}.{$extension}");
        $ruta = "{$carpeta}/{$id}.{$extension}";

        try {
            return Evidencia::create([
                'id_evidencia' => $id,
                'id_caso' => $caso->id_caso,
                'tipo' => Evidencia::TIPO_ARCHIVO,
                'ruta' => $ruta,
                'nombre' => Str::limit($archivo->getClientOriginalName(), 195, ''),
                'mime' => Str::limit((string) $archivo->getMimeType(), 115, ''),
                'bytes' => $archivo->getSize(),
                'sha256' => hash_file('sha256', $archivo->getRealPath()),
                'subido_por' => $usuario->id_usuario,
                'fecha_registro' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            ]);
        } catch (Throwable $e) {
            rescue(fn () => $disco->delete($ruta));

            throw $e;
        }
    }

    public function agregarEnlace(Caso $caso, string $url, User $usuario): Evidencia
    {
        return Evidencia::create([
            'id_caso' => $caso->id_caso,
            'tipo' => Evidencia::TIPO_ENLACE,
            'url' => $url,
            'subido_por' => $usuario->id_usuario,
            'fecha_registro' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
        ]);
    }

    /**
     * Quita una evidencia antes de guardar: la fila queda descartada, porque sigabu_app no
     * borra, y el objeto sí se borra del almacenamiento.
     */
    public function descartar(Evidencia $evidencia): void
    {
        $evidencia->update(['estado' => Evidencia::DESCARTADA]);

        if ($evidencia->esArchivo()) {
            rescue(fn () => Storage::disk(Evidencia::DISCO)->delete($evidencia->ruta));
        }
    }

    /**
     * Vincula al registro las evidencias temporales que subió $usuario en este caso. Debe
     * llamarse dentro de la transacción que guarda el registro.
     *
     * @param  list<string>  $ids
     *
     * @throws ValidationException si alguna ya no está disponible o superan el máximo
     */
    public function vincular(Caso $caso, array $ids, string $destino, string $idDestino, User $usuario): int
    {
        $ids = array_values(array_unique($ids));

        if (count($ids) > Evidencia::MAX_POR_REGISTRO) {
            throw ValidationException::withMessages(['evidencias' => 'Puedes adjuntar hasta '.Evidencia::MAX_POR_REGISTRO.' evidencias por registro.']);
        }

        $evidencias = Evidencia::whereIn('id_evidencia', $ids)
            ->where('id_caso', $caso->id_caso)
            ->where('subido_por', $usuario->id_usuario)
            ->where('estado', Evidencia::TEMPORAL)
            ->get();

        if ($evidencias->count() !== count($ids)) {
            throw ValidationException::withMessages(['evidencias' => 'Alguna evidencia ya no está disponible. Revisa la lista y vuelve a adjuntarla.']);
        }

        $evidencias->each(fn (Evidencia $evidencia) => $evidencia->update([
            'estado' => Evidencia::VINCULADA,
            'destino' => $destino,
            'id_destino' => $idDestino,
        ]));

        return $evidencias->count();
    }

    public function descargar(Evidencia $evidencia): StreamedResponse
    {
        return Storage::disk(Evidencia::DISCO)->download($evidencia->ruta, $evidencia->nombre, [
            'Content-Type' => $evidencia->mime ?? 'application/octet-stream',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Descarta las temporales que nadie guardó, p. ej. porque se cerró el formulario.
     */
    public function limpiarTemporales(int $horas = 24): int
    {
        $limite = now(Actuacion::ZONA_HORARIA)->subHours($horas)->toDateTimeString();
        $temporales = Evidencia::where('estado', Evidencia::TEMPORAL)->where('fecha_registro', '<', $limite)->get();

        $temporales->each(fn (Evidencia $evidencia) => $this->descartar($evidencia));

        return $temporales->count();
    }

    /**
     * Evidencias temporales de $usuario, para volver a mostrarlas si el formulario se recarga.
     *
     * @param  array<int, mixed>  $ids
     * @return list<array<string, string|null>>
     */
    public function temporalesParaFormulario(Caso $caso, User $usuario, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Evidencia::whereIn('id_evidencia', $ids)
            ->where('id_caso', $caso->id_caso)
            ->where('subido_por', $usuario->id_usuario)
            ->where('estado', Evidencia::TEMPORAL)
            ->get()
            ->map(fn (Evidencia $evidencia) => self::paraFormulario($evidencia))
            ->all();
    }

    /**
     * Datos que necesita la lista de evidencias del formulario.
     *
     * @return array<string, string|null>
     */
    public static function paraFormulario(Evidencia $evidencia): array
    {
        return [
            'id' => $evidencia->id_evidencia,
            'nombre' => $evidencia->esArchivo() ? $evidencia->nombre : $evidencia->url,
            'tamano' => $evidencia->tamanoLegible(),
            'quitar' => route('inclusion.evidencias.quitar', $evidencia),
        ];
    }
}

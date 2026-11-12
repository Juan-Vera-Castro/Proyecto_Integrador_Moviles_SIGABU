<?php

namespace App\Http\Controllers\Vbg;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Vbg\Audio;
use App\Models\Vbg\Auditoria;
use App\Models\Vbg\Cita;
use App\Models\Vbg\Transcripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Audios de las sesiones VBG (integrado desde la rama VIOLENCIA_GENERO): MP3, WAV o M4A de
 * hasta 2 horas, en el bucket privado. Se suben solo con el consentimiento de la cita aceptado.
 * La transcripción la escribe el profesional; si la transcripción automática está configurada,
 * el audio queda "En cola" para php artisan vbg:transcribir.
 */
class AudioController extends Controller
{
    use ExigeCasoACargo;

    private const TIPOS = [
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'm4a' => 'audio/mp4',
    ];

    public function store(Request $request, Cita $cita): RedirectResponse
    {
        $reporte = $cita->reporte;
        abort_unless($reporte, 404);
        $this->exigirGestionable($request, $reporte);
        $ancla = route('vbg.reportes.show', $reporte).'#cita-'.$cita->id_cita;
        $config = config('sigabu.vbg.audio');

        if ($cita->estado === Cita::CANCELADA || ! $cita->tieneConsentimientoAceptado()) {
            throw ValidationException::withMessages(['audio' => 'Para subir el audio, la cita debe tener el consentimiento informado aceptado y firmado.'])
                ->errorBag('audio-'.$cita->id_cita)->redirectTo($ancla);
        }

        $datos = $request->validateWithBag('audio-'.$cita->id_cita, [
            'audio' => ['required', 'file', 'extensions:'.implode(',', $config['extensiones']),
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/wave,audio/vnd.wave,audio/mp4,audio/x-m4a,audio/m4a,audio/aac,video/mp4',
                'max:'.$config['max_kb']],
            'duracion_segundos' => ['nullable', 'integer', 'min:0', 'max:'.$config['max_segundos']],
        ], [
            'audio.required' => 'Selecciona el archivo de audio.',
            'audio.extensions' => 'Formatos permitidos: MP3, WAV o M4A.',
            'audio.mimetypes' => 'El archivo no parece un audio MP3, WAV o M4A.',
            'audio.max' => 'El audio supera el tamaño máximo de '.intdiv($config['max_kb'], 1024).' MB.',
            'duracion_segundos.max' => 'El audio supera el límite de 2 horas.',
        ]);

        $archivo = $datos['audio'];
        $formato = strtolower($archivo->getClientOriginalExtension());
        $id = (string) Str::uuid();
        $ruta = "audios/{$cita->id_cita}/{$id}.{$formato}";
        $sha = hash_file('sha256', $archivo->getRealPath());

        try {
            Storage::disk(Audio::DISCO)->putFileAs("audios/{$cita->id_cita}", $archivo, "{$id}.{$formato}");
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['audio' => 'No se pudo guardar el audio. No se registró nada; intenta de nuevo.'])
                ->errorBag('audio-'.$cita->id_cita)->redirectTo($ancla);
        }

        try {
            DB::connection('vbg')->transaction(function () use ($request, $cita, $reporte, $archivo, $formato, $id, $ruta, $sha, $datos) {
                Audio::create([
                    'id_audio' => $id,
                    'id_cita' => $cita->id_cita,
                    'origen' => 'Archivo',
                    'formato' => $formato,
                    'duracion_segundos' => $datos['duracion_segundos'] ?? null,
                    'ruta' => $ruta,
                    'nombre_original' => Str::limit($archivo->getClientOriginalName(), 195, ''),
                    'bytes' => $archivo->getSize(),
                    'sha256' => $sha,
                    'subido_por' => $request->user()->id_usuario,
                    'creado_en' => now(Actuacion::ZONA_HORARIA)->format('Y-m-d H:i:s.u'),
                ]);

                // Transcripción automática contemplada (rama VIOLENCIA_GENERO): solo si está configurada.
                if (Transcripcion::iaDisponible()) {
                    Transcripcion::create([
                        'id_audio' => $id,
                        'estado' => Transcripcion::EN_COLA,
                        'origen' => Transcripcion::IA,
                        'modelo' => config('sigabu.vbg.ia.transcripcion_modelo'),
                    ]);
                }

                Auditoria::registrar(Auditoria::AUDIO_SUBIDO, $reporte->id_reporte, ['id_cita' => $cita->id_cita, 'id_audio' => $id, 'sha256' => $sha]);
            });
        } catch (Throwable $e) {
            report($e);
            rescue(fn () => Storage::disk(Audio::DISCO)->delete($ruta));
            throw ValidationException::withMessages(['audio' => 'No fue posible registrar el audio. No se guardó nada; intenta de nuevo.'])
                ->errorBag('audio-'.$cita->id_cita)->redirectTo($ancla);
        }

        return $this->volverAlCaso($reporte, 'cita-'.$cita->id_cita, Transcripcion::iaDisponible()
            ? 'Audio guardado. La transcripción automática quedó en cola.'
            : 'Audio guardado. Puedes escribir su transcripción.');
    }

    /**
     * Reproduce el audio. Solo el profesional a cargo; no se cachea y queda auditado.
     */
    public function show(Request $request, Audio $audio): StreamedResponse
    {
        $reporte = $audio->cita?->reporte;
        abort_unless($reporte, 404);
        $this->exigirACargo($request, $reporte);
        abort_unless(Storage::disk(Audio::DISCO)->exists($audio->ruta), 404);

        Auditoria::registrar(Auditoria::AUDIO_ESCUCHADO, $reporte->id_reporte, ['id_audio' => $audio->id_audio]);

        return Storage::disk(Audio::DISCO)->response($audio->ruta, $audio->nombre_original, [
            'Content-Type' => self::TIPOS[$audio->formato] ?? 'application/octet-stream',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * El profesional escribe (o corrige) la transcripción del audio.
     */
    public function transcripcion(Request $request, Audio $audio): RedirectResponse
    {
        $reporte = $audio->cita?->reporte;
        abort_unless($reporte, 404);
        $this->exigirGestionable($request, $reporte);

        $texto = $request->validateWithBag('transcripcion-'.$audio->id_audio, [
            'texto' => ['required', 'string', 'max:'.Transcripcion::TEXTO_MAX],
        ], [
            'texto.required' => 'Escribe la transcripción.',
            'texto.max' => 'La transcripción es demasiado larga.',
        ])['texto'];

        $ahora = now(Actuacion::ZONA_HORARIA);
        Transcripcion::updateOrCreate(['id_audio' => $audio->id_audio], [
            'texto' => trim($texto),
            'estado' => Transcripcion::COMPLETADA,
            'origen' => Transcripcion::MANUAL,
            'id_usuario' => $request->user()->id_usuario,
            'completado_en' => $ahora,
            'actualizado_en' => $ahora,
        ]);

        Auditoria::registrar(Auditoria::TRANSCRIPCION_REGISTRADA, $reporte->id_reporte, ['id_audio' => $audio->id_audio]);

        return $this->volverAlCaso($reporte, 'audio-'.$audio->id_audio, 'Transcripción guardada.');
    }
}

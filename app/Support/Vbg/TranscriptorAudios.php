<?php

namespace App\Support\Vbg;

use App\Models\Actuacion;
use App\Models\Vbg\Audio;
use App\Models\Vbg\Auditoria;
use App\Models\Vbg\Transcripcion;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Transcripción automática de los audios VBG, contemplada en la rama VIOLENCIA_GENERO con
 * faster-whisper (docs/vbg-ia). Solo actúa si VBG_TRANSCRIPCION_COMANDO está configurado: el
 * comando recibe la ruta de un archivo de audio temporal y devuelve el texto por la salida
 * estándar. Si el profesional ya escribió la transcripción, no se reemplaza.
 */
class TranscriptorAudios
{
    /**
     * @return array{completadas: int, errores: int}
     */
    public function procesarPendientes(int $limite = 5): array
    {
        $comando = (string) config('sigabu.vbg.ia.transcripcion_comando');
        $resultado = ['completadas' => 0, 'errores' => 0];

        if ($comando === '') {
            return $resultado;
        }

        $pendientes = Transcripcion::with('audio.cita')
            ->where('estado', Transcripcion::EN_COLA)
            ->orderBy('id_transcripcion')
            ->limit($limite)
            ->get();

        foreach ($pendientes as $transcripcion) {
            $this->procesar($transcripcion, $comando) ? $resultado['completadas']++ : $resultado['errores']++;
        }

        return $resultado;
    }

    private function procesar(Transcripcion $transcripcion, string $comando): bool
    {
        $audio = $transcripcion->audio;
        $temporal = tempnam(sys_get_temp_dir(), 'vbg-audio-').'.'.$audio->formato;
        $inicio = microtime(true);

        try {
            file_put_contents($temporal, Storage::disk(Audio::DISCO)->get($audio->ruta));

            $proceso = Process::timeout((int) config('sigabu.vbg.ia.transcripcion_timeout'))
                ->run($comando.' '.escapeshellarg($temporal));
            $texto = trim($proceso->output());

            if (! $proceso->successful() || $texto === '') {
                throw new \RuntimeException('El comando de transcripción no devolvió texto: '.mb_substr($proceso->errorOutput(), 0, 300));
            }

            // Entre tanto el profesional pudo escribirla: se respeta la suya.
            if ($transcripcion->fresh()?->estado !== Transcripcion::EN_COLA) {
                return true;
            }

            $ahora = now(Actuacion::ZONA_HORARIA);
            $transcripcion->update([
                'texto' => mb_substr($texto, 0, Transcripcion::TEXTO_MAX),
                'estado' => Transcripcion::COMPLETADA,
                'segundos_proceso' => round(microtime(true) - $inicio, 2),
                'completado_en' => $ahora,
                'actualizado_en' => $ahora,
            ]);

            Auditoria::create([
                'id_reporte' => $audio->cita->id_reporte,
                'id_usuario' => $audio->subido_por,
                'accion' => Auditoria::TRANSCRIPCION_REGISTRADA,
                'detalle' => ['id_audio' => $audio->id_audio, 'origen' => Transcripcion::IA],
                'fecha_hora' => $ahora->format('Y-m-d H:i:s.u'),
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);
            $transcripcion->update(['estado' => Transcripcion::ERROR, 'actualizado_en' => now(Actuacion::ZONA_HORARIA)]);

            return false;
        } finally {
            @unlink($temporal);
        }
    }
}

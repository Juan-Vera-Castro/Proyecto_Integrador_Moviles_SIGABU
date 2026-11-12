"""
Transcripción de audios de las sesiones VBG con faster-whisper.

Viene de la rama VIOLENCIA_GENERO (docs/ai-models/transcripcion/transcribe.py), adaptado para
SIGABU: recibe la ruta del audio y escribe solo el texto en la salida estándar, que es lo que
lee php artisan vbg:transcribir. No guarda archivos ni manda el audio fuera del servidor.

Uso:  python transcribir.py /ruta/al/audio.mp3
"""
import os
import sys

from faster_whisper import WhisperModel


def main() -> int:
    if len(sys.argv) < 2:
        print("Falta la ruta del audio.", file=sys.stderr)
        return 2

    modelo = WhisperModel(
        os.environ.get("VBG_WHISPER_MODELO", "medium"),
        device=os.environ.get("VBG_WHISPER_DISPOSITIVO", "cpu"),
        compute_type=os.environ.get("VBG_WHISPER_COMPUTO", "int8"),
    )
    segmentos, _ = modelo.transcribe(sys.argv[1], language="es")
    print(" ".join(segmento.text.strip() for segmento in segmentos))

    return 0


if __name__ == "__main__":
    sys.exit(main())

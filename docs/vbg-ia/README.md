# Transcripción automática de audios VBG (contemplada, apagada)

La rama `VIOLENCIA_GENERO` contemplaba transcribir con IA los audios de las sesiones
(faster-whisper) y generar el informe de análisis con un modelo de lenguaje. En esa rama
ninguna de las dos estaba conectada: el script de transcripción no lo llamaba nadie y el
"reporte IA" eran datos de ejemplo.

En SIGABU queda así:

- **Transcripción:** hoy la escribe el profesional. La transcripción automática está
  contemplada y **apagada**: se activa solo si se configura `VBG_TRANSCRIPCION_COMANDO`.
- **Informe de análisis:** lo elabora el profesional. La base guarda `origen`, `modelo_llm`
  y `prompt` para cuando se use IA, pero hoy siempre es `Manual`.

## Activar la transcripción automática

Antes de activarla hay que decidir dónde corre: el audio de una víctima no debe salir de la
universidad. Este script corre en el propio servidor; no usa servicios externos.

1. En el servidor (o en un contenedor con acceso al audio), instalar Python 3.10+ y
   `pip install -r docs/vbg-ia/requirements.txt`. Con GPU NVIDIA hacen falta las CUDA tools;
   sin GPU funciona en CPU, más lento.
2. En el `.env`:

   ```dotenv
   VBG_TRANSCRIPCION_COMANDO="python3 /ruta/docs/vbg-ia/transcribir.py"
   VBG_TRANSCRIPCION_MODELO="faster-whisper medium"
   VBG_TRANSCRIPCION_TIMEOUT=3600
   ```

3. Programar `php artisan vbg:transcribir` (por ejemplo, cada 5 minutos con cron). Toma los
   audios en cola, guarda el texto y, si falla, los marca con error para que el profesional
   escriba la transcripción.

El comando recibe la ruta de un audio temporal y debe escribir el texto en la salida estándar.
Si el profesional escribió la transcripción mientras tanto, la suya no se reemplaza.

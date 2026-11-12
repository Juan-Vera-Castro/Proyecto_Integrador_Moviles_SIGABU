<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos fijos de la institución
    |--------------------------------------------------------------------------
    |
    | Ciudad que llena el marcador {ciudad} de los consentimientos informados.
    |
    */

    'ciudad' => env('SIGABU_CIUDAD', 'Tuluá'),

    /*
    |--------------------------------------------------------------------------
    | Formatos institucionales
    |--------------------------------------------------------------------------
    |
    | Clave del formato de remisión psicológica en la tabla formatos_institucionales.
    | El código, la versión y la fecha se editan desde Administración.
    |
    */

    'formato_remision' => 'REMISION_PSICOLOGICA',

    // Formato de desistimiento de la atención: respaldo legal cuando la persona decide no
    // continuar o no contar lo sucedido. Su código lo asigna Calidad desde Administración.
    'formato_desistimiento' => 'DESISTIMIENTO',

    /*
    |--------------------------------------------------------------------------
    | Exigencia del consentimiento informado firmado (RN-01)
    |--------------------------------------------------------------------------
    |
    | "exigir": interruptor. Apagado, la falta de firma no bloquea la atención (un rechazo
    | registrado sí). Se enciende cuando estén las claves S3 y los textos publicados.
    | Aunque esté encendido, no bloquea un caso si su servicio no tiene texto publicado o
    | si faltan las claves: así ningún caso queda trabado sin forma de firmar.
    |
    | "desde": fecha (AAAA-MM-DD) desde la que se exige. Los casos abiertos antes quedan
    | eximidos de firmar. Vacía, se exige a todos los casos.
    |
    */

    'consentimiento' => [
        'exigir' => (bool) env('SIGABU_EXIGIR_CONSENTIMIENTO', false),
        'desde' => env('SIGABU_CONSENTIMIENTO_DESDE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Evolución del caso (HU-015)
    |--------------------------------------------------------------------------
    |
    | Cambio mínimo de puntaje, entre la primera y la última medición de una escala, para
    | considerar que el estudiante mejoró o empeoró de forma clínicamente relevante.
    | Valores de referencia de la literatura: debe confirmarlos la coordinación de psicología.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Indicadores institucionales (HU-032)
    |--------------------------------------------------------------------------
    |
    | Los desgloses por grupo (facultad, riesgo, tipo de violencia...) con menos de este
    | número de casos se muestran como "<N", para que nadie pueda deducir de quién se trata.
    |
    */

    'indicadores' => [
        'minimo_por_grupo' => (int) env('SIGABU_INDICADORES_MINIMO', 5),
    ],

    'evolucion' => [
        'umbrales' => [
            'GAD-7' => (int) env('SIGABU_UMBRAL_GAD7', 4),
            'PHQ-9' => (int) env('SIGABU_UMBRAL_PHQ9', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificaciones y recordatorios
    |--------------------------------------------------------------------------
    |
    | Los avisos siempre quedan en la campana de SIGABU. "correo": además se envían al correo
    | institucional, con la cuenta de MAIL_* (con MAIL_MAILER=log solo quedan en el log).
    |
    | "recordatorios_cita": horas de anticipación de cada recordatorio de cita, separadas
    | por coma (p. ej. "24,2" = un día antes y dos horas antes).
    |
    | "ventana_dias": cuántos días hacia atrás se revisan las tareas vencidas y los
    | consentimientos sin firmar. Evita avisar de pendientes muy antiguos.
    |
    */

    'notificaciones' => [
        'correo' => (bool) env('SIGABU_NOTIFICAR_POR_CORREO', true),
        'recordatorios_cita' => array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) env('SIGABU_RECORDATORIOS_CITA', '24,2'))),
            fn (int $horas) => $horas > 0,
        ))),
        'ventana_dias' => (int) env('SIGABU_AVISOS_VENTANA_DIAS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Violencias Basadas en Género: citas, consentimiento, audios e informes
    |--------------------------------------------------------------------------
    |
    | Integrado desde la rama VIOLENCIA_GENERO. El texto del consentimiento por cita y los
    | catálogos de marco legal y ruta de atención vienen de esa rama; el equipo VBG debe
    | revisarlos antes de usarlos en atenciones reales. Al cambiar el texto, sube la versión:
    | los consentimientos ya firmados conservan el texto que se firmó.
    |
    */

    'vbg' => [
        'consentimiento_cita' => [
            'version' => 'VBG-CITA-1',
            'titulo' => 'Consentimiento informado para la atención',
            'texto' => "1. NATURALEZA DE LA INTERVENCIÓN: El servicio de orientación y bienestar psicológico ofrecido por la UCEVA tiene como objetivo principal brindar acompañamiento psicoterapéutico breve de carácter académico, emocional y personal a la comunidad universitaria.\n\n"
                ."2. CONFIDENCIALIDAD: Toda información compartida durante las sesiones se mantendrá en estricta reserva clínica. No obstante, según la Ley de Salud Mental de Colombia (Ley 1616 de 2013), la reserva será suspendida únicamente ante situaciones inminentes de riesgo comprobado para la vida del usuario o de terceros.\n\n"
                ."3. VOLUNTARIEDAD: El estudiante participa de forma voluntaria en este proceso de apoyo, manteniendo la potestad de suspenderlo cuando lo considere pertinente.\n\n"
                ."4. PROTECCIÓN DE DATOS: En cumplimiento de la Ley Estatutaria 1581 de 2012 (Habeas Data) y el Decreto 1377 de 2013, los datos registrados serán tratados con confidencialidad estricta y uso asistencial exclusivo institucional.",
        ],

        // Audios de las sesiones: hasta 2 horas (regla de la rama VIOLENCIA_GENERO).
        'audio' => [
            'max_kb' => (int) env('SIGABU_VBG_AUDIO_MAX_KB', 102400),
            'max_segundos' => 2 * 60 * 60,
            'extensiones' => ['mp3', 'wav', 'm4a'],
        ],

        // Transcripción automática (contemplada en la rama VIOLENCIA_GENERO con faster-whisper).
        // Apagada mientras VBG_TRANSCRIPCION_COMANDO esté vacío: el profesional escribe la
        // transcripción. El comando recibe la ruta del audio y devuelve el texto por la salida
        // estándar; ver docs/vbg-ia/README.md.
        'ia' => [
            'transcripcion_comando' => env('VBG_TRANSCRIPCION_COMANDO'),
            'transcripcion_modelo' => env('VBG_TRANSCRIPCION_MODELO', 'faster-whisper medium'),
            'transcripcion_timeout' => (int) env('VBG_TRANSCRIPCION_TIMEOUT', 3600),
        ],

        // Opciones sugeridas del informe de análisis (de la rama VIOLENCIA_GENERO).
        'marco_legal' => [
            ['titulo' => 'Ley 1257 de 2008', 'descripcion' => 'Sensibilización, prevención y sanción de formas de violencia y discriminación contra las mujeres.'],
            ['titulo' => 'Ley 1761 de 2015 (Rosa Elvira Cely)', 'descripcion' => 'Tipificación del feminicidio como delito autónomo.'],
            ['titulo' => 'Ley 294 de 1996', 'descripcion' => 'Normas para prevenir, remediar y sancionar la violencia intrafamiliar.'],
            ['titulo' => 'Resolución 459 de 2012', 'descripcion' => 'Protocolo y modelo de atención integral en salud para víctimas de violencia sexual.'],
        ],
        'ruta_atencion' => [
            ['titulo' => 'Activar medidas de protección inmediata', 'descripcion' => 'Notificar a la Comisaría de Familia para medida de protección.'],
            ['titulo' => 'Remisión a asesoría jurídica', 'descripcion' => 'Contactar el consultorio jurídico de la UCEVA para orientación legal gratuita.'],
            ['titulo' => 'Canalización interinstitucional', 'descripcion' => 'Derivar el caso a la Fiscalía General de la Nación si la persona decide denunciar.'],
            ['titulo' => 'Plan de seguridad personalizado', 'descripcion' => 'Elaborar con la persona un plan de seguridad y su red de apoyo.'],
            ['titulo' => 'Seguimiento psicológico continuo', 'descripcion' => 'Programar sesiones de acompañamiento.'],
        ],
    ],

];

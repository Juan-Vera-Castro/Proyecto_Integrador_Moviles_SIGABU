<?php

namespace App\Documentos;

use App\Models\Inclusion\Recurso;

/**
 * Recursos pedagógicos de la Unidad de Inclusión, transcritos literalmente (RN-36): las posibles
 * erratas se conservan hasta que la Unidad confirme las correcciones. El contenido son bloques
 * de datos, no HTML, y se muestra igual en la aplicación y en el PDF.
 */
class RecursosInclusion
{
    public const CODIGO_TEA_N1 = 'TEA-N1';

    /**
     * Bloques del recurso. Tipos: titulo, subtitulo, parrafo, lista, tabla, destacado.
     *
     * @return list<array<string, mixed>>
     */
    public static function teaNivel1(): array
    {
        return [
            ['tipo' => 'destacado', 'titulo' => 'PROPÓSITO', 'texto' => 'Orientar a los docentes en la implementación de estrategias de accesibilidad académica, manteniendo los estándares académicos y los resultados de aprendizaje.'],

            ['tipo' => 'titulo', 'texto' => '1. Introducción'],
            ['tipo' => 'parrafo', 'texto' => 'En el marco del Acuerdo Directivo 024 de 11 de diciembre de 2025 – Política de Educación Superior Inclusiva, la Institución adopta acciones orientadas a garantizar el derecho a la educación en condiciones de equidad, calidad y no discriminación.'],
            ['tipo' => 'parrafo', 'texto' => 'Este Lineamiento Operativo desarrolla las líneas de acción:'],
            ['tipo' => 'lista', 'items' => ['Eliminación de barreras', 'Docencia con enfoque inclusivo']],
            ['tipo' => 'parrafo', 'texto' => 'Su propósito es orientar a los docentes en la implementación de estrategias de accesibilidad académica para estudiantes con Discapacidad Psicosocial, manteniendo los estándares académicos y los resultados de aprendizaje.'],

            ['tipo' => 'titulo', 'texto' => '2. Fundamentación normativa'],
            ['tipo' => 'parrafo', 'texto' => 'Este documento se sustenta en:'],
            ['tipo' => 'lista', 'items' => [
                'Acuerdo Directivo 024 de 2025 – Política de Educación Superior Inclusiva (UCEVA).',
                'Convención sobre los Derechos de las Personas con Discapacidad de las Naciones Unidas.',
                'Ley 1346 de 2009 (incorporación de la Convención al ordenamiento jurídico colombiano).',
                'Ley 2460 del 16 de junio de 2025, la cual modifica y actualiza la anterior Ley de Salud Mental (Ley 1616 de 2013)',
            ]],
            ['tipo' => 'parrafo', 'texto' => 'En este marco, los ajustes razonables se entienden como modificaciones necesarias y adecuadas que no implican carga desproporcionada y que permiten eliminar barreras para la participación.'],
            ['tipo' => 'parrafo', 'texto' => 'En el contexto universitario, estos se concretan como estrategias de accesibilidad académica, sin alterar:'],
            ['tipo' => 'lista', 'items' => ['Resultados de aprendizaje.', 'Perfil de egreso.', 'Créditos académicos.', 'Nivel de exigencia disciplinar.']],

            ['tipo' => 'titulo', 'texto' => '3. Marco conceptual y operativo de TEA'],
            ['tipo' => 'parrafo', 'texto' => 'El Trastorno del Espectro Autista (TEA) Nivel 1, es una condición del neurodesarrollo caracterizada por formas distintas de procesar la información social, cognitiva y sensorial, así como de organizar el pensamiento.'],
            ['tipo' => 'parrafo', 'texto' => 'El diagnóstico de TEA se realiza siguiendo criterios clínicos establecidos en el DSM-5-TR (Manual Diagnóstico y Estadístico de los Trastornos Mentales, quinta edición, texto revisado, American Psychiatric Association, 2022). Dichos criterios incluyen:'],
            ['tipo' => 'lista', 'items' => [
                'Diferencias persistentes en la comunicación e interacción social.',
                'Patrones restrictivos o repetitivos de comportamiento o pensamiento.',
                'Síntomas presentes desde la primera infancia y que afectan el funcionamiento diario.',
            ]],
            ['tipo' => 'parrafo', 'texto' => 'El nivel de apoyo se determina según la capacidad del estudiante para desenvolverse en contextos académicos y sociales:'],
            ['tipo' => 'parrafo', 'texto' => 'Nivel 1 de apoyo: requiere apoyos leves pero consistentes, principalmente en situaciones de interacción social y flexibilidad cognitiva, pero mantiene competencia académica conservada.'],
            ['tipo' => 'parrafo', 'texto' => 'Estas particularidades no implican menor capacidad intelectual, sino diferencias en la interpretación de contextos sociales, patrones de pensamiento y procesamiento sensorial. Comprenderlas permite implementar estrategias pedagógicas que ajusten la forma de enseñanza y evaluación, sin modificar la exigencia académica ni los resultados de aprendizaje esperados.'],

            ['tipo' => 'titulo', 'texto' => '4. Barreras frecuentes y ajustes razonables'],
            ['tipo' => 'parrafo', 'texto' => 'Como docente, es importante que reconozcas que algunos de tus estudiantes con TEA pueden enfrentar dificultades que no se deben a falta de interés o capacidad, sino a la manera en que su cerebro procesa la información social, cognitiva y sensorial.'],
            ['tipo' => 'parrafo', 'texto' => 'Pregúntate:'],
            ['tipo' => 'lista', 'items' => [
                '¿Estoy observando conductas como ausentismo recurrente, fatiga, desorganización o bloqueo evaluativo, y puedo comprender su origen?',
                '¿Mis instrucciones, actividades y evaluaciones están lo suficientemente estructuradas para facilitar el aprendizaje?',
                '¿Estoy ofreciendo espacios seguros y claros que permitan que todos los estudiantes muestren su verdadero potencial?',
            ]],
            ['tipo' => 'parrafo', 'texto' => '“Este punto te ayudará a identificar las barreras más frecuentes en el aula y a implementar ajustes razonables que modifiquen la forma de acceder al aprendizaje, sin disminuir el contenido ni la exigencia académica. La idea es que cada estudiante pueda participar plenamente y demostrar sus competencias de manera equitativa”.'],
            ['tipo' => 'tabla', 'encabezados' => ['Dificultad frecuente', 'Manifestación en aula', 'Ajuste razonable posible', 'No implica'], 'filas' => [
                ['Interpretación literal', 'No comprende orientaciones ambiguas', 'Instrucciones escritas, claras y paso a paso; pedir que repita la instrucción con sus palabras', 'Bajar exigencia'],
                ['Ansiedad ante cambios', 'Desregulación si se modifica fecha o dinámica', 'Anticipar cambios con tiempo, explicar secuencias de actividades', 'Eliminar la evaluación o otorgar calificaciones sin sustento probatorio.'],
                ['Dificultad en interacción grupal', 'Conflictos en trabajos colaborativos', 'Roles definidos, evaluación individual, apoyo gradual; permitir ajustes si estudiante lo solicita', 'Eximirlo de trabajar'],
                ['Sobrecarga sensorial', 'Distracción en espacios ruidosos o iluminados', 'Permitir evaluación en espacio más tranquilo o con menor estímulo', 'Ventaja académica'],
                ['Dificultad en preguntas abiertas', 'Respuestas incompletas pese a conocer el tema', 'Preguntas estructuradas o guía de respuesta, ejemplos modelo', 'Eliminar evaluación'],
                ['Rigidez cognitiva', 'Dificultad para cambiar enfoque o adaptarse', 'Dar ejemplos modelo previos y secuenciar tareas', 'Reducir profundidad'],
            ]],
            ['tipo' => 'destacado', 'titulo' => 'Nota', 'texto' => 'Las barreras y ajustes propuestos se basan en el DSM-5-TR (APA, 2022), Ley 2460 del 16 de junio de 2025, la normativa de educación inclusiva en educación superior (Convenio ONU, Ley 1346 de 2009) y evidencia sobre programas de apoyo psicosocial universitario (Eisenberg et al., 2019; National Center for College Students with Disabilities, 2021).'],

            ['tipo' => 'titulo', 'texto' => '5. Recomendaciones concretas para docentes'],
            ['tipo' => 'parrafo', 'texto' => 'Recuerda que tu rol como docente es facilitar el acceso al aprendizaje de cada estudiante con TEA Nivel 1, sin modificar la exigencia académica ni los resultados de aprendizaje esperados. Esto implica ajustar la forma en que enseñas, comunicas, organizas y evalúas, generando entornos claros, estructurados y previsibles.'],
            ['tipo' => 'parrafo', 'texto' => 'Antes de cada actividad, pregúntate:'],
            ['tipo' => 'lista', 'items' => [
                '¿Mis instrucciones son suficientemente claras para que todos comprendan lo que se espera?',
                '¿Estoy ofreciendo alternativas que permitan demostrar lo que el estudiante realmente sabe, incluso si necesita mayor estructura o apoyo sensorial?',
                '¿He anticipado posibles dificultades y organizado recursos, tiempos y apoyos individuales?',
            ]],
            ['tipo' => 'parrafo', 'texto' => 'Recuerda que la estructuración y organización pedagógica de tus actividades garantiza los derechos de los estudiantes a un acceso equitativo al aprendizaje. Los ajustes que implementes no modifican la exigencia académica ni el contenido disciplinar; su propósito es optimizar los canales de acceso al aprendizaje, asegurando que todos los estudiantes puedan participar plenamente y demostrar sus competencias en igualdad de condiciones.'],

            ['tipo' => 'subtitulo', 'texto' => '5.1 Instrucciones y metodología'],
            ['tipo' => 'lista', 'items' => [
                'Entrega siempre las consignas por escrito.',
                'Utiliza lenguaje claro, directo y sin ambigüedades.',
                'Divide tareas largas en pasos secuenciales.',
                'Muestra un ejemplo modelo cuando solicites trabajos complejos.',
                'Verifica comprensión pidiendo al estudiante que repita la instrucción con sus propias palabras.',
            ]],
            ['tipo' => 'destacado', 'titulo' => 'Clave', 'texto' => 'claridad no significa reducir la exigencia; significa facilitar el acceso al aprendizaje.'],

            ['tipo' => 'subtitulo', 'texto' => '5.2 Evaluación'],
            ['tipo' => 'lista', 'items' => [
                'Entrega rúbricas antes de la evaluación.',
                'Estructura preguntas abiertas indicando el número de argumentos esperados.',
                'Mantén fechas estables y anticipa cambios con tiempo.',
                'Permite espacio tranquilo en caso de sobrecarga sensorial, sin modificar tiempo ni contenido.',
            ]],
            ['tipo' => 'destacado', 'titulo' => 'Clave', 'texto' => 'los ajustes modifican la forma de evaluar, no el nivel de dificultad ni los objetivos de aprendizaje.'],

            ['tipo' => 'subtitulo', 'texto' => '5.3 Trabajos en grupo'],
            ['tipo' => 'lista', 'items' => [
                'Define roles explícitos dentro del equipo.',
                'Evalúa también el desempeño individual.',
                'Intervenir si surgen conflictos derivados de malentendidos comunicativos.',
                'La exención del trabajo grupal solo procede si el estudiante lo solicita justificadamente.',
            ]],

            ['tipo' => 'subtitulo', 'texto' => '5.4 Comunicación'],
            ['tipo' => 'lista', 'items' => [
                'Sé explícito en las expectativas académicas.',
                'Evita ironías, dobles sentidos o ambigüedades.',
                'Prefiere la comunicación institucional escrita para información clave.',
                'Utiliza apoyos visuales o esquemas cuando sea necesario.',
            ]],

            ['tipo' => 'subtitulo', 'texto' => '5.5 Organización académica'],
            ['tipo' => 'lista', 'items' => [
                'Publica el cronograma completo desde el inicio del semestre.',
                'Fracciona proyectos grandes en entregas parciales.',
                'Recuerda fechas importantes mediante avisos o recordatorios.',
            ]],

            ['tipo' => 'subtitulo', 'texto' => '5.6 Regulación emocional'],
            ['tipo' => 'lista', 'items' => [
                'Mantén un trato respetuoso y neutral ante episodios de ansiedad.',
                'Permite breves pausas si el estudiante se desregula, sin penalización.',
                'Remite a Bienestar sólo si hay afectación significativa en el desempeño o bienestar del estudiante.',
            ]],

            ['tipo' => 'subtitulo', 'texto' => '5.7 Trazabilidad y registro de ajustes'],
            ['tipo' => 'lista', 'items' => [
                'Registrar todas las estrategias y ajustes implementados.',
                'Incluir la fecha de cada acción o intervención.',
                'Indicar el responsable de la implementación y seguimiento.',
                'Adjuntar evidencia asociada, como: listado de asistencia a encuentros o tutorías; evidencias fotográficas de tutorías o sesiones de apoyo; fotografías de material adaptado utilizado en el proceso.',
            ]],
            ['tipo' => 'parrafo', 'texto' => 'Envía la información a Coordinación y Decanatura (asegura el reposo institucional de la trazabilidad del enfoque diferencial en cada caso).'],
            ['tipo' => 'parrafo', 'texto' => 'La implementación exitosa de las estrategias de accesibilidad académica requiere de un principio de corresponsabilidad: el diálogo y la concertación transparente entre docente y estudiante. En este marco, el compromiso activo del estudiante es indispensable, traduciéndose en acciones concretas como el aprovechamiento de los espacios de tutoría, la adherencia a sus procesos terapéuticos o profesionales externos, y la práctica constante de sus herramientas de autorregulación emocional.'],

            ['tipo' => 'subtitulo', 'texto' => '5.8 Plantilla de ajustes pedagógicos para acceso equitativo'],
            ['tipo' => 'parrafo', 'texto' => 'Esta plantilla permite registrar cada ajuste o estrategia implementada, garantizando trazabilidad, evidencia y seguimiento institucional.'],
            ['tipo' => 'parrafo', 'texto' => 'La plantilla presentada a continuación incluye casos ilustrativos a modo de ejemplo. Las barreras y los ajustes razonables no deben aplicarse de manera estandarizada o automática; deben identificarse y concertarse de forma individualizada mediante el diálogo directo y respetuoso entre el docente y el estudiante. Cada caso exige comprender las dinámicas particulares del estudiante en el entorno del aula.'],
            ['tipo' => 'tabla', 'encabezados' => ['Área / Barrera', 'Descripción de la barrera', 'Ajuste o estrategia pedagógica implementada', 'Responsable', 'Fecha', 'Evidencia asociada', 'Observaciones'], 'filas' => [
                ['Ej. Instrucciones y metodología', 'El estudiante no comprende instrucciones ambiguas', 'Entregar consignas por escrito y mostrar un ejemplo modelo', 'Docente', '15/03/2026', 'Fotografía del material adaptado, lista de asistencia a tutoría', 'El ajuste permitió que el estudiante repitiera la instrucción correctamente; se observa participación activa'],
                ['Ej. Evaluación', 'Dificultad con preguntas abiertas', 'Estructurar preguntas indicando número de argumentos y ejemplos', 'Docente', '22/03/2026', 'Rúbrica entregada, registro de tutoría individual', 'El estudiante completó la actividad mostrando comprensión del tema'],
                ['Ej. Trabajo en grupo', 'Malentendidos en roles', 'Definir roles explícitos y supervisar interacción', 'Docente', '29/03/2026', 'Lista de asistencia a encuentro grupal', 'Se redujo la ansiedad y mejoró la participación'],
            ]],
            ['tipo' => 'parrafo', 'texto' => 'La Unidad de Inclusión realizará el seguimiento periódico a la implementación de los ajustes razonables en cada asignatura a través de espacios de encuentro y diálogo con cada estudiante activo en la ruta. Este seguimiento permitirá valorar de cerca la efectividad, oportunidad y pertinencia de los ajustes concertados, asegurando un acompañamiento continuo tanto al docente como al estudiante durante el periodo académico. Asimismo, este proceso posibilitará la identificación de acciones afirmativas, la sistematización de estrategias pedagógicas exitosas y la detección oportuna de oportunidades de mejora.'],
            ['tipo' => 'parrafo', 'texto' => 'Guía para completar la plantilla:'],
            ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                'Área / Barrera: dónde ocurre la dificultad (Instrucciones, Evaluación, Comunicación, Organización, Trabajo en grupo, Regulación emocional).',
                'Descripción de la barrera: la dificultad específica que presenta el estudiante.',
                'Ajuste o estrategia pedagógica implementada: qué se modificó o adaptó para facilitar el acceso al aprendizaje sin cambiar contenido ni exigencia.',
                'Responsable: quién implementa y supervisa el ajuste.',
                'Fecha: día en que se implementa o se revisa el ajuste.',
                'Evidencia asociada: adjuntar documentación que respalde la acción (link, correos institucionales o anexos).',
                'Observaciones: comentarios amplios que reflejen el enfoque en el estudiante y el interés de facilitar el acceso equitativo al aprendizaje, incluyendo avances, dificultades y participación activa del estudiante.',
            ]],
            ['tipo' => 'destacado', 'titulo' => 'Importante', 'texto' => 'Envía la información a Coordinación y Decanatura para que quede reposada institucionalmente, asegurando trazabilidad del enfoque diferencial en cada caso. La participación activa del estudiante también es necesaria: tutorías, acompañamiento con especialistas externos, o prácticas de autorregulación deben ser cumplidas por el estudiante para que los ajustes sean efectivos.'],
            ['tipo' => 'parrafo', 'texto' => 'Tu compromiso no solo facilita el aprendizaje de todos tus estudiantes, sino que transforma la cultura institucional hacia una educación más inclusiva.'],
            ['tipo' => 'parrafo', 'texto' => '“CONSTRUYENDO PUENTES, PROMOVIENDO INCLUSIÓN”'],
        ];
    }

    /**
     * Publica los recursos que falten. No reemplaza los que ya existan.
     *
     * @return list<string> códigos creados
     */
    public static function cargar(): array
    {
        if (Recurso::where('codigo', self::CODIGO_TEA_N1)->exists()) {
            return [];
        }

        Recurso::create([
            'codigo' => self::CODIGO_TEA_N1,
            'condicion' => 'Trastorno del Espectro Autista (TEA) – Nivel 1 de Apoyo',
            'titulo' => 'Estrategias de Accesibilidad Académica',
            'version' => 1,
            'contenido' => self::teaNivel1(),
            'activo' => true,
        ]);

        return [self::CODIGO_TEA_N1];
    }
}

<?php

namespace App\Documentos;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use App\Models\VersionConsentimiento;
use Illuminate\Support\Facades\DB;

/**
 * Formato de consentimiento informado del Proceso de Inclusión Universitaria, transcrito
 * literalmente. El marcador {decisiones} se llena con las casillas que marcó la persona.
 * Los menores usan el mismo texto: solo cambia quién declara, porque firma el acudiente.
 */
class ConsentimientoInclusion
{
    public const CODIGO_GENERAL = 'IN-INCLUSION';

    public const CODIGO_MENORES = 'IN-INCLUSION-MENORES';

    /**
     * Las 4 decisiones del formato: columna de inclusion_consentimientos => texto de la casilla.
     */
    public const DECISIONES = [
        'decision_acompanamiento' => 'ACEPTO recibir acompañamiento y seguimiento por parte del Área de Inclusión.',
        'decision_datos_generales' => 'AUTORIZO el tratamiento de mis datos personales generales.',
        'decision_datos_sensibles' => 'AUTORIZO DE MANERA EXPRESA el tratamiento de los datos sensibles que decida suministrar.',
        'decision_compartir' => 'AUTORIZO compartir con docentes, programa y dependencias competentes la información mínima necesaria para implementar y evaluar los ajustes razonables.',
    ];

    /**
     * Tipos de vinculación del formato => grupo que admite consentimiento_aceptaciones.vinculacion.
     * Son datos del formato: no crean cuentas ni roles.
     */
    public const VINCULACIONES = [
        'Estudiante Diurno' => 'Estudiante',
        'Estudiante Nocturno' => 'Estudiante',
        'Docente TC' => 'Docente TC',
        'Docente TCO' => 'Docente TCO',
        'Docente HC' => 'Docente HC',
        'Funcionario Planta' => 'Funcionario',
        'Funcionario Provisional' => 'Funcionario',
        'Contratista' => 'Otro',
        'Judicante' => 'Otro',
        'Externo' => 'Otro',
    ];

    private const APERTURA_GENERAL = 'Yo, {nombre_estudiante}, con documento de identidad número {documento_estudiante}, manifiesto';

    private const APERTURA_MENORES = 'Yo, {nombre_acudiente}, con documento de identidad número {documento_acudiente}, en calidad de {parentesco_acudiente} de {nombre_estudiante}, con documento de identidad número {documento_estudiante}, manifiesto';

    public const TEXTO = <<<'TXT'
        Yo, {nombre_estudiante}, con documento de identidad número {documento_estudiante}, manifiesto haber recibido información clara, suficiente y comprensible sobre el acompañamiento que brinda la Unidad Central del Valle del Cauca - UCEVA, a través del Área de Inclusión, para la identificación de barreras, la valoración de necesidades de apoyo, la gestión de ajustes razonables realizados por el programa y el seguimiento a su implementación.

        Entiendo que este acompañamiento está dirigido a las personas con cualquier tipo de discapacidad que requieran atención por parte del Área de Inclusión. Comprendo que la información relacionada con mi salud, discapacidad, diagnóstico, apoyos o condiciones personales tiene carácter sensible; su entrega es voluntaria y será tratada de manera confidencial, con acceso restringido y únicamente para las finalidades informadas.

        Autorizo que el Área de Inclusión comparta con el programa académico, los docentes y las dependencias competentes únicamente la información mínima necesaria para gestionar e implementar los ajustes razonables. Comprendo que, cuando sea suficiente, se comunicarán las barreras, necesidades, apoyos y recomendaciones funcionales sin divulgar diagnósticos ni detalles personales innecesarios.

        He sido informado(a) de que los ajustes razonables acordados con los docentes podrán quedar consignados en el contrato didáctico. Este instrumento establecerá de manera concertada los ajustes, apoyos, responsabilidades, compromisos y mecanismos de comunicación y seguimiento entre la persona atendida, el docente, el programa académico y el Área de Inclusión.

        Entiendo que los ajustes razonables buscan garantizar el acceso, la participación y el aprendizaje en condiciones de igualdad. No implican eliminar los resultados de aprendizaje, aprobar automáticamente actividades o asignaturas, reducir injustificadamente las exigencias académicas ni otorgar ventajas frente a otros integrantes de la comunidad universitaria. Su pertinencia y continuidad serán valoradas individualmente.

        Acepto participar en las actividades de seguimiento que resulten necesarias, las cuales podrán incluir entrevistas, reuniones, revisión del cumplimiento del contrato didáctico, comunicación con docentes y programa académico, actualización de recomendaciones y valoración de la eficacia de los ajustes. Puedo formular preguntas, solicitar modificaciones y retirar mi consentimiento en cualquier momento, sin renunciar a mis derechos ni recibir sanciones.

        De manera libre y voluntaria, manifiesto las siguientes decisiones:

        {decisiones}

        He leído y comprendido el contenido de este consentimiento informado; confirmo que pude realizar preguntas y que recibí explicación sobre el alcance del acompañamiento, la confidencialidad, el tratamiento de datos, los ajustes razonables, el contrato didáctico y el seguimiento. Firmo con pleno conocimiento de sus implicaciones y autorizo únicamente las opciones que he marcado.

        La confidencialidad podrá limitarse cuando exista autorización específica, requerimiento de autoridad competente, deber legal o un riesgo grave, cierto e inminente para la vida o integridad. Siempre que sea posible y no aumente el riesgo, se me informará previamente sobre la activación de la ruta correspondiente.

        Declaro que conozco mi derecho a acceder, conocer, actualizar y rectificar mis datos; solicitar prueba de la autorización; conocer el uso dado a la información; revocar la autorización o solicitar la supresión cuando proceda; y presentar consultas o reclamos conforme a la Política de Tratamiento de Datos Personales de la UCEVA.
        TXT;

    /**
     * Marcadores que se llenan con lo que se escribe en el formulario de Inclusión.
     */
    public const CAMPOS_DE_FIRMA = [
        'nombre_estudiante' => 'nombre_persona',
        'documento_estudiante' => 'documento_persona',
        'nombre_acudiente' => 'acudiente_nombre',
        'documento_acudiente' => 'acudiente_numero_documento',
        'parentesco_acudiente' => 'acudiente_parentesco',
    ];

    /**
     * Versión vigente de este formato para la población de la persona, o null si el caso sigue
     * con otro modelo, por ejemplo porque no es de Inclusión.
     */
    public static function versionDe(Caso $caso): ?VersionConsentimiento
    {
        if (! $caso->esDeInclusion()) {
            return null;
        }

        $menor = $caso->solicitud?->persona?->esMenorEn(now(Actuacion::ZONA_HORARIA)) === true;
        $version = ModeloConsentimiento::versionVigenteDe('Inclusión', $menor ? ModeloConsentimiento::POBLACION_MENORES : ModeloConsentimiento::POBLACION_GENERAL);

        return in_array($version?->modelo?->codigo, [self::CODIGO_GENERAL, self::CODIGO_MENORES], true) ? $version : null;
    }

    public static function aplicaA(Caso $caso): bool
    {
        return self::versionDe($caso) !== null;
    }

    public static function esEstudiante(?string $vinculacion): bool
    {
        return (self::VINCULACIONES[$vinculacion] ?? null) === ConsentimientoAceptacion::VINCULACION_ESTUDIANTE;
    }

    /**
     * Las decisiones como texto, con la casilla marcada o vacía.
     *
     * @param  array<string, mixed>  $marcadas  columna => si se marcó
     */
    public static function textoDecisiones(array $marcadas): string
    {
        return collect(self::DECISIONES)
            ->map(fn (string $texto, string $campo) => (empty($marcadas[$campo]) ? '☐' : '☑').' '.$texto)
            ->implode("\n");
    }

    /**
     * @return list<array{codigo: string, nombre: string, poblacion: string, descripcion: string, texto: string}>
     */
    public static function modelos(): array
    {
        return [
            [
                'codigo' => self::CODIGO_GENERAL,
                'nombre' => 'Consentimiento informado - Proceso de Inclusión Universitaria',
                'poblacion' => ModeloConsentimiento::POBLACION_GENERAL,
                'descripcion' => 'Formato de la Unidad de Inclusión. Lo firma la persona atendida.',
                'texto' => self::TEXTO,
            ],
            [
                'codigo' => self::CODIGO_MENORES,
                'nombre' => 'Consentimiento informado - Proceso de Inclusión Universitaria (menores de edad)',
                'poblacion' => ModeloConsentimiento::POBLACION_MENORES,
                'descripcion' => 'Formato de la Unidad de Inclusión. Lo firma el acudiente.',
                'texto' => str_replace(self::APERTURA_GENERAL, self::APERTURA_MENORES, self::TEXTO),
            ],
        ];
    }

    /**
     * Publica los dos modelos y desactiva los anteriores de Inclusión, para que solo uno quede
     * vigente por población. Las versiones y los consentimientos firmados no cambian. Si ya
     * existen, no hace nada: no reemplaza lo que el administrador haya cambiado.
     *
     * @return list<string> códigos creados
     */
    public static function cargar(): array
    {
        $creados = [];

        DB::transaction(function () use (&$creados) {
            foreach (self::modelos() as $datos) {
                if (ModeloConsentimiento::where('codigo', $datos['codigo'])->exists()) {
                    continue;
                }

                ModeloConsentimiento::activos()
                    ->where('tipo_servicio', 'Inclusión')
                    ->where('poblacion', $datos['poblacion'])
                    ->get()
                    ->each(fn (ModeloConsentimiento $anterior) => $anterior->update(['activo' => false]));

                $texto = $datos['texto'];
                unset($datos['texto']);

                $modelo = ModeloConsentimiento::create([...$datos, 'tipo_servicio' => 'Inclusión', 'activo' => true]);

                $modelo->versiones()->create([
                    'numero_version' => 1,
                    'texto' => $texto,
                    'estado' => VersionConsentimiento::PUBLICADA,
                    'fecha_publicacion' => now(),
                ]);

                $creados[] = $datos['codigo'];
            }
        });

        return $creados;
    }
}

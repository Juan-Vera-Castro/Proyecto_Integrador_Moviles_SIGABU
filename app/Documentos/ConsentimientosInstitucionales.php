<?php

namespace App\Documentos;

use App\Models\Caso;
use App\Models\ModeloConsentimiento;
use App\Models\VersionConsentimiento;
use Illuminate\Support\Facades\DB;

/**
 * Textos oficiales de consentimiento informado de la UCEVA, cargados como versión 1 de
 * los modelos de Salud Mental e Inclusión (HU-009). Después el administrador los cambia
 * desde Consentimientos publicando versiones nuevas, sin tocar el código.
 */
class ConsentimientosInstitucionales
{
    /**
     * Formato GBU-GPGR-F-032, versión 1 (13/SEP/2023): estudiante mayor de edad.
     */
    public const TEXTO_GENERAL = <<<'TXT'
        Ciudad: {ciudad}. Fecha: {fecha}.

        Yo, {nombre_estudiante}, con documento {tipo_documento_estudiante} número {documento_estudiante} de {lugar_expedicion}, declaro que he sido informado(a) sobre los procedimientos de orientación psicológica que se realizarán y de su importancia.

        Comprendo que los resultados se utilizarán como parte del proceso que se realice y que, en caso de ser una urgencia, se me remitirá a la EPS o IPS correspondiente, {eps_ips}, y se notificará al acudiente o representante legal: {acudiente_notificacion}. Se guardará la confidencialidad de los datos obtenidos, salvo en el caso de existir un riesgo para mi salud física y/o mental.

        Igualmente manifiesto que otorgo de forma libre mi consentimiento para la orientación psicológica por parte del profesional {profesional}, con documento de identificación {documento_profesional}.

        En conformidad con lo anteriormente expresado y con pleno conocimiento de sus consecuencias legales, consiento la realización de la orientación psicológica y, como aceptación, presento mi firma.
        TXT;

    /**
     * Formato GBU-GPGR-F-019, versión 1 (07/SEP/2021): lo firma la madre, el padre o el acudiente.
     */
    public const TEXTO_MENORES = <<<'TXT'
        Ciudad: {ciudad}. Fecha: {fecha}.

        Yo, {nombre_acudiente}, con documento {tipo_documento_acudiente} número {documento_acudiente} de {lugar_expedicion_acudiente}, en calidad de {parentesco_acudiente} (madre, padre y/o acudiente) del menor {nombre_estudiante}, identificado con {tipo_documento_estudiante} número {documento_estudiante}, otorgo de forma libre mi consentimiento para la orientación psicológica por parte del profesional {profesional}, con documento de identificación {documento_profesional}.

        Comprendo que los resultados se utilizarán como parte del proceso que se realice y que, en caso de ser una urgencia, se remitirá al menor a la EPS o IPS correspondiente, {eps_ips}.

        En conformidad con lo anteriormente expresado y con pleno conocimiento de sus consecuencias legales, consiento la realización de la orientación psicológica y, como aceptación, presento mi firma.
        TXT;

    /**
     * Prefijo del código de cada servicio que exige consentimiento.
     */
    private const PREFIJOS = ['Salud Mental' => 'SM', 'Inclusión' => 'IN'];

    /**
     * Los cuatro modelos: cada servicio con consentimiento, en versión general y para menores.
     *
     * @return list<array{codigo: string, nombre: string, tipo_servicio: string, poblacion: string, descripcion: string, texto: string}>
     */
    public static function modelos(): array
    {
        $modelos = [];

        foreach (Caso::SERVICIOS_CON_CONSENTIMIENTO as $servicio) {
            $prefijo = self::PREFIJOS[$servicio];

            $modelos[] = [
                'codigo' => "{$prefijo}-ORIENTACION",
                'nombre' => 'Consentimiento informado orientación psicológica',
                'tipo_servicio' => $servicio,
                'poblacion' => ModeloConsentimiento::POBLACION_GENERAL,
                'descripcion' => 'Formato GBU-GPGR-F-032, versión 1 (13/SEP/2023). Lo firma el estudiante.',
                'texto' => self::TEXTO_GENERAL,
            ];

            $modelos[] = [
                'codigo' => "{$prefijo}-MENORES",
                'nombre' => 'Consentimiento informado menores de edad',
                'tipo_servicio' => $servicio,
                'poblacion' => ModeloConsentimiento::POBLACION_MENORES,
                'descripcion' => 'Formato GBU-GPGR-F-019, versión 1 (07/SEP/2021). Lo firma la madre, el padre o el acudiente.',
                'texto' => self::TEXTO_MENORES,
            ];
        }

        return $modelos;
    }

    /**
     * Crea y publica los modelos que falten. No toca nada que ya exista: si el código está
     * usado o el servicio ya tiene un modelo activo para esa población, ese modelo se omite.
     *
     * @return list<string> códigos creados
     */
    public static function cargar(): array
    {
        $creados = [];

        foreach (self::modelos() as $datos) {
            $ocupado = ModeloConsentimiento::where('codigo', $datos['codigo'])->exists()
                || ModeloConsentimiento::activos()
                    ->where('tipo_servicio', $datos['tipo_servicio'])
                    ->where('poblacion', $datos['poblacion'])
                    ->exists();

            if ($ocupado) {
                continue;
            }

            DB::transaction(function () use ($datos) {
                $texto = $datos['texto'];
                unset($datos['texto']);

                $modelo = ModeloConsentimiento::create([...$datos, 'activo' => true]);

                $modelo->versiones()->create([
                    'numero_version' => 1,
                    'texto' => $texto,
                    'estado' => VersionConsentimiento::PUBLICADA,
                    'fecha_publicacion' => now(),
                ]);
            });

            $creados[] = $datos['codigo'];
        }

        return $creados;
    }
}

<?php

namespace App\Documentos;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Formato GBU-GPGR-F-016 "Remisión psicológica" en Word (.docx), con la misma estructura y
 * los mismos datos que el PDF (D-11). Las firmas quedan en blanco: se firman a mano.
 */
class RemisionWord
{
    /** Ancho útil de la hoja carta con márgenes de 1,8 cm, en twips. */
    private const ANCHO = 10150;

    private const TABLA = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 70];

    private const NEGRITA = ['bold' => true];

    private const CENTRO = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];

    private const PARRAFO = ['spaceAfter' => 0];

    /**
     * Contenido binario del .docx.
     */
    public static function generar(DatosRemision $d): string
    {
        Settings::setOutputEscapingEnabled(true);

        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(9);
        $word->getDocInfo()->setTitle($d->nombreArchivo)->setCreator('SIGABU - UCEVA');

        $seccion = $word->addSection([
            'paperSize' => 'Letter',
            'marginTop' => 900, 'marginBottom' => 900, 'marginLeft' => 1020, 'marginRight' => 1020,
        ]);

        self::encabezado($seccion, $d);
        self::paginaDatos($seccion, $d);
        $seccion->addPageBreak();
        self::encabezado($seccion, $d);
        self::paginaAcompanante($seccion, $d);

        $archivo = tempnam(sys_get_temp_dir(), 'remision');

        try {
            IOFactory::createWriter($word, 'Word2007')->save($archivo);

            return (string) file_get_contents($archivo);
        } finally {
            @unlink($archivo);
        }
    }

    private static function encabezado(Section $seccion, DatosRemision $d): void
    {
        $tabla = $seccion->addTable(self::TABLA);
        $filas = [[$d->proceso, $d->codigo], ['FORMATO', $d->version], [$d->nombreFormato, $d->fecha]];

        foreach ($filas as $i => [$centro, $derecha]) {
            $tabla->addRow(340);
            $logo = $tabla->addCell(3000, ['vMerge' => $i === 0 ? 'restart' : 'continue', 'valign' => 'center']);

            if ($i === 0) {
                $logo->addImage(public_path('images/logo-uceva.png'), ['width' => 125, 'alignment' => Jc::CENTER]);
            }

            $tabla->addCell(4300, ['valign' => 'center'])->addText($centro, self::NEGRITA, self::CENTRO);
            $tabla->addCell(2850, ['valign' => 'center'])->addText($derecha, self::NEGRITA, self::PARRAFO);
        }

        $seccion->addTextBreak();
    }

    private static function paginaDatos(Section $seccion, DatosRemision $d): void
    {
        $fecha = $seccion->addTable(self::TABLA);
        $fecha->addRow(340);
        $fecha->addCell(3000)->addText('Fecha de atención (dd-mm-aa):', self::NEGRITA, self::PARRAFO);
        foreach ($d->fechaAtencion as $parte) {
            $fecha->addCell(900)->addText($parte, null, self::CENTRO);
        }
        self::rotuloYValor($fecha->addCell(4450), 'Hora: ', $d->hora);
        $seccion->addTextBreak();

        $persona = $seccion->addTable(self::TABLA);
        $persona->addRow(340);
        self::rotuloYValor($persona->addCell(self::ANCHO, ['gridSpan' => 16]), 'NOMBRE (S) Y APELLIDO (S): ', $d->nombre);

        $persona->addRow(340);
        $persona->addCell(1500)->addText('IDENTIFICACIÓN:', self::NEGRITA, self::PARRAFO);
        foreach ($d->tipoDocumento as $tipo => $marca) {
            self::casilla($persona, $tipo, $marca);
        }
        $persona->addCell(1100)->addText('# De Documento', self::NEGRITA, self::PARRAFO);
        $persona->addCell(1870, ['gridSpan' => 3])->addText($d->numeroDocumento, null, self::PARRAFO);
        $persona->addCell(700)->addText('SEXO', self::NEGRITA, self::PARRAFO);
        foreach ($d->sexo as $sexo => $marca) {
            self::casilla($persona, $sexo, $marca, negrita: false);
        }

        $persona->addRow(340);
        $persona->addCell(1500)->addText('EDAD', self::NEGRITA, self::PARRAFO);
        $persona->addCell(2880, ['gridSpan' => 6])->addText($d->edad, null, self::PARRAFO);
        $persona->addCell(3670, ['gridSpan' => 4])->addText('FECHA DE NACIMIENTO (dd-mm-aa)', self::NEGRITA, self::PARRAFO);
        foreach ($d->fechaNacimiento as $i => $parte) {
            $persona->addCell($i === 2 ? 1060 : 520, $i === 2 ? ['gridSpan' => 3] : [])->addText($parte, null, self::CENTRO);
        }
        $seccion->addTextBreak();

        $categoria = $seccion->addTable(self::TABLA);
        $categoria->addRow(340);
        $categoria->addCell(5000, ['gridSpan' => 4])->addText('CATEGORIA', self::NEGRITA, self::CENTRO);
        $categoria->addRow(340);
        foreach ($d->categoria as $nombre => $marca) {
            $categoria->addCell(1900)->addText($nombre, null, self::PARRAFO);
            $categoria->addCell(600)->addText($marca, self::NEGRITA, self::CENTRO);
        }
        $seccion->addTextBreak();

        $remitido = $seccion->addTable(self::TABLA);
        $remitido->addRow(340);
        $remitido->addCell(1800)->addText('REMITIDO A:', self::NEGRITA, self::PARRAFO);
        $remitido->addCell(self::ANCHO - 1800)->addText($d->remitidoA, null, self::PARRAFO);
        $seccion->addTextBreak();

        $justificacion = $seccion->addTable(self::TABLA);
        $justificacion->addRow(6600);
        $celda = $justificacion->addCell(self::ANCHO, ['valign' => 'top']);
        $celda->addText('JUSTIFICACIÓN:', self::NEGRITA, self::PARRAFO);
        foreach (preg_split('/\R/', $d->justificacion) as $linea) {
            $celda->addText($linea, null, self::PARRAFO);
        }
    }

    private static function paginaAcompanante(Section $seccion, DatosRemision $d): void
    {
        $tabla = $seccion->addTable(self::TABLA);
        foreach (['EN CASO DE SER REMITIDO POR URGENCIAS', 'DATOS DEL ACOMPAÑANTE'] as $titulo) {
            $tabla->addRow(420);
            $tabla->addCell(self::ANCHO, ['gridSpan' => 2, 'valign' => 'center'])->addText($titulo, self::NEGRITA, self::CENTRO);
        }

        $filas = [
            'Nombre de la persona responsable del estudiante:' => $d->acompanante['nombre'],
            'Parentesco:' => $d->acompanante['parentesco'],
            'Tipo y Numero de Identificación:' => $d->acompanante['identificacion'],
            'Numero de contacto:' => $d->acompanante['telefono'],
            'Firma:' => '',
        ];

        foreach ($filas as $rotulo => $valor) {
            $tabla->addRow($rotulo === 'Firma:' ? 700 : 420);
            $tabla->addCell(3300, ['valign' => 'bottom'])->addText($rotulo, self::NEGRITA, self::PARRAFO);
            $tabla->addCell(self::ANCHO - 3300, ['valign' => 'center'])->addText(trim($valor), null, self::PARRAFO);
        }
        $seccion->addTextBreak();

        $psicologo = $seccion->addTable(self::TABLA);
        $psicologo->addRow(700);
        $psicologo->addCell(1700, ['valign' => 'center'])->addText('NOMBRE PSICOLOGO (A)', self::NEGRITA, self::PARRAFO);
        $psicologo->addCell(4100, ['valign' => 'center'])->addText($d->psicologo, null, self::PARRAFO);
        $psicologo->addCell(1150, ['valign' => 'center'])->addText('FIRMA', self::NEGRITA, self::CENTRO);
        $psicologo->addCell(3200);
    }

    private static function rotuloYValor($celda, string $rotulo, string $valor): void
    {
        $linea = $celda->addTextRun(self::PARRAFO);
        $linea->addText($rotulo, self::NEGRITA);
        $linea->addText($valor);
    }

    private static function casilla(Table $tabla, string $etiqueta, string $marca, bool $negrita = true): void
    {
        $tabla->addCell(520)->addText($etiqueta, $negrita ? self::NEGRITA : null, self::CENTRO);
        $tabla->addCell(440)->addText($marca, self::NEGRITA, self::CENTRO);
    }
}

{{-- Formato GBU-GPGR-F-016 "Remisión psicológica" (RQ-3). Recibe $d (App\Documentos\DatosRemision) y $logo (data URI). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $d->nombreArchivo }}</title>
    <style>
        @page { size: letter; margin: 1.6cm 1.8cm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
        .encabezado td { font-size: 8.5pt; font-weight: bold; text-align: center; height: 20px; }
        .encabezado .logo { width: 30%; text-align: center; }
        .encabezado .codigo { width: 28%; text-align: left; }
        .encabezado img { width: 170px; }
        .bloque { margin-top: 16px; }
        .rotulo { font-weight: bold; }
        .casilla { width: 26px; text-align: center; font-weight: bold; }
        .centro { text-align: center; }
        .justificacion { height: 330px; vertical-align: top; white-space: pre-line; }
        .titulo { text-align: center; font-weight: bold; padding: 8px; }
        .firma { height: 38px; }
        .salto { page-break-before: always; }
        .compacta td { padding: 4px 5px; font-size: 9pt; }
        .pegada { margin-top: -1px; }
    </style>
</head>
<body>
    @foreach ([1, 2] as $pagina)
        <div @class(['salto' => $pagina === 2])>
            <table class="encabezado">
                <tr>
                    <td class="logo" rowspan="3"><img src="{{ $logo }}" alt="UCEVA"></td>
                    <td>{{ $d->proceso }}</td>
                    <td class="codigo">{{ $d->codigo }}</td>
                </tr>
                <tr>
                    <td>FORMATO</td>
                    <td class="codigo">{{ $d->version }}</td>
                </tr>
                <tr>
                    <td>{{ $d->nombreFormato }}</td>
                    <td class="codigo">{{ $d->fecha }}</td>
                </tr>
            </table>

            @if ($pagina === 1)
                <table class="bloque">
                    <tr>
                        <td class="rotulo" style="width: 30%;">Fecha de atención (dd-mm-aa):</td>
                        @foreach ($d->fechaAtencion as $parte)
                            <td class="centro" style="width: 9%;">{{ $parte }}</td>
                        @endforeach
                        <td><span class="rotulo">Hora:</span> {{ $d->hora }}</td>
                    </tr>
                </table>

                {{-- Cada fila es su propia tabla: dompdf toma los anchos de la primera fila y
                     las celdas combinadas le hacen ignorarlos. Se apilan para verse como una sola. --}}
                <table class="bloque compacta">
                    <tr><td><span class="rotulo">NOMBRE (S) Y APELLIDO (S):</span> {{ $d->nombre }}</td></tr>
                </table>
                <table class="compacta pegada">
                    <tr>
                        <td class="rotulo" style="width: 18%;">IDENTIFICACIÓN:</td>
                        @foreach ($d->tipoDocumento as $tipo => $marca)
                            <td class="rotulo centro" style="width: 4%;">{{ $tipo }}</td>
                            <td class="casilla" style="width: 4%;">{{ $marca }}</td>
                        @endforeach
                        <td class="rotulo" style="width: 11%;">#&nbsp;De Documento</td>
                        <td style="width: 15%;">{{ $d->numeroDocumento }}</td>
                        <td class="rotulo" style="width: 7%;">SEXO</td>
                        @foreach ($d->sexo as $sexo => $marca)
                            <td class="centro" style="width: 6%;">{{ $sexo }}</td>
                            <td class="casilla" style="width: 6.5%;">{{ $marca }}</td>
                        @endforeach
                    </tr>
                </table>
                <table class="compacta pegada">
                    <tr>
                        <td class="rotulo" style="width: 18%;">EDAD</td>
                        <td style="width: 24%;">{{ $d->edad }}</td>
                        <td class="rotulo" style="width: 26%;">FECHA DE NACIMIENTO (dd-mm-aa)</td>
                        @foreach ($d->fechaNacimiento as $parte)
                            <td class="centro" style="width: {{ $loop->last ? 18 : 7 }}%;">{{ $parte }}</td>
                        @endforeach
                    </tr>
                </table>

                <table class="bloque" style="width: 50%;">
                    <tr><td class="titulo" colspan="4">CATEGORIA</td></tr>
                    <tr>
                        @foreach ($d->categoria as $categoria => $marca)
                            <td>{{ $categoria }}</td>
                            <td class="casilla">{{ $marca }}</td>
                        @endforeach
                    </tr>
                </table>

                <table class="bloque">
                    <tr>
                        <td class="rotulo" style="width: 18%;">REMITIDO A:</td>
                        <td>{{ $d->remitidoA }}</td>
                    </tr>
                </table>

                <table class="bloque">
                    <tr><td class="justificacion"><span class="rotulo">JUSTIFICACIÓN:</span>
{{ $d->justificacion }}</td></tr>
                </table>
            @else
                <table class="bloque">
                    <tr><td class="titulo" colspan="2">EN CASO DE SER REMITIDO POR URGENCIAS</td></tr>
                    <tr><td class="titulo" colspan="2">DATOS DEL ACOMPAÑANTE</td></tr>
                    <tr>
                        <td class="rotulo" style="width: 32%;">Nombre de la persona responsable del estudiante:</td>
                        <td>{{ $d->acompanante['nombre'] }}</td>
                    </tr>
                    <tr><td class="rotulo">Parentesco:</td><td>{{ $d->acompanante['parentesco'] }}</td></tr>
                    <tr><td class="rotulo">Tipo y Numero de Identificación:</td><td>{{ $d->acompanante['identificacion'] }}</td></tr>
                    <tr><td class="rotulo">Numero de contacto:</td><td>{{ $d->acompanante['telefono'] }}</td></tr>
                    <tr><td class="rotulo firma">Firma:</td><td class="firma"></td></tr>
                </table>

                <table class="bloque">
                    <tr>
                        <td class="rotulo firma" style="width: 22%;">NOMBRE PSICOLOGO (A)</td>
                        <td style="width: 36%;">{{ $d->psicologo }}</td>
                        <td class="rotulo centro" style="width: 12%;">FIRMA</td>
                        <td></td>
                    </tr>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>

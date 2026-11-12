{{-- Formato de consentimiento para imprimir y firmar a mano (P6). Datos de App\Documentos\PdfInclusion::imprimible(). --}}
@php
    use App\Documentos\ConsentimientoInclusion;

    $profesional = $caso->profesional?->persona;
    $vacio = '______________';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimiento para imprimir {{ $caso->codigo_expediente }}</title>
    @include('documentos.partials.estilos-inclusion')
</head>
<body>
    @include('documentos.partials.encabezado-inclusion')

    <h1>FORMATO DE CONSENTIMIENTO INFORMADO</h1>
    <p class="subtitulo">Proceso de Inclusión Universitaria</p>

    <table>
        <tr><td class="rotulo">Fecha (día/mes/año)</td><td>&nbsp;</td></tr>
        <tr><td class="rotulo">Nombre de la persona</td><td>{{ $iniciales['nombre_persona'] }}</td></tr>
        <tr><td class="rotulo">D.I.</td><td>{{ $iniciales['documento_persona'] }}</td></tr>
        <tr><td class="rotulo">Número de contacto</td><td>&nbsp;</td></tr>
    </table>
    <table>
        <tr>
            <th style="width: 24%">Facultad</th><th style="width: 30%">Programa</th><th style="width: 12%">Semestre</th><th>Dependencia</th><th style="width: 8%">N/A</th>
        </tr>
        <tr>
            <td>{{ $iniciales['facultad'] ?: ' ' }}</td>
            <td>{{ $iniciales['programa_academico'] ?: ' ' }}</td>
            <td>{{ $iniciales['semestre'] ?: ' ' }}</td>
            <td>&nbsp;</td>
            <td><span class="casilla"></span></td>
        </tr>
    </table>
    <table>
        @foreach (array_chunk(array_keys(ConsentimientoInclusion::VINCULACIONES), 5) as $grupo)
            <tr>
                @foreach ($grupo as $vinculacion)
                    <td style="width: 20%; font-size: 9pt"><span class="casilla"></span>{{ $vinculacion }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @if ($menor)
        <table class="bloque">
            <tr><td class="rotulo">Acudiente</td><td>&nbsp;</td></tr>
            <tr><td class="rotulo">Documento del acudiente</td><td>&nbsp;</td></tr>
            <tr><td class="rotulo">Parentesco</td><td>&nbsp;</td></tr>
        </table>
    @endif

    <div class="bloque">
        @foreach ($parrafos as $parrafo)
            @if ($parrafo['casilla'] === null)
                <p>{{ $parrafo['texto'] }}</p>
            @else
                <p class="decision"><span class="casilla"></span>{{ $parrafo['texto'] }}</p>
            @endif
        @endforeach
    </div>

    <p>Atentamente,</p>

    <table class="firmas">
        <tr>
            <td style="width: 50%">
                <div class="espacio-firma"></div>
                <div class="linea">{{ $menor ? 'Acudiente' : 'Persona atendida' }}<br>D.I. {{ $menor ? $vacio : $iniciales['documento_persona'] }}</div>
            </td>
            <td></td>
        </tr>
        <tr>
            <td>
                <div class="espacio-firma"></div>
                <div class="linea">Profesional Área de Inclusión<br>{{ $profesional?->nombre_completo }}<br>C.C. {{ $profesional?->numero_documento }}</div>
            </td>
            <td>
                <div class="espacio-firma"></div>
                <div class="linea">Docente o responsable académico (cuando aplique)<br><br>C.C.</div>
            </td>
        </tr>
    </table>

    <p class="pie">
        Formato para firmar a mano. Expediente {{ $caso->codigo_expediente }} · versión {{ $version?->numero_version }} del texto · generado el {{ $generado->format('d/m/Y H:i') }}.
        Después de firmarlo, el profesional lo carga en SIGABU con las decisiones marcadas.
    </p>
</body>
</html>

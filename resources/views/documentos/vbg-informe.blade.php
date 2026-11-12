{{-- Informe de análisis de un caso VBG. Datos de App\Http\Controllers\Vbg\InformeController::pdf(). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de análisis {{ $reporte->codigo }}</title>
    @include('documentos.partials.estilos-inclusion')
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="logo" rowspan="2"><img src="{{ $logo }}" alt="UCEVA"></td>
            <td>VICERRECTORÍA DE BIENESTAR UNIVERSITARIO</td>
        </tr>
        <tr>
            <td>VIOLENCIAS BASADAS EN GÉNERO · INFORME DE ANÁLISIS DEL CASO</td>
        </tr>
    </table>

    <h1>Informe de análisis — Caso {{ $reporte->codigo }}</h1>
    <p class="subtitulo">Versión del {{ $informe->creado_en->format('d/m/Y H:i') }} · elaborado por {{ $informe->profesional?->persona?->nombre_completo }}{{ $informe->origen === 'IA' ? ' con apoyo de '.$informe->modelo_llm : '' }}</p>

    <div class="aviso">Documento confidencial. Contiene información sensible de una persona víctima o presunta víctima de violencia.</div>

    <h2>Datos del caso</h2>
    <table>
        <tr><td class="rotulo">Caso</td><td>{{ $reporte->codigo }}</td></tr>
        <tr><td class="rotulo">Reportado el</td><td>{{ $reporte->fecha_reporte?->format('d/m/Y') }}</td></tr>
        <tr><td class="rotulo">Estado</td><td>{{ $reporte->estado }}</td></tr>
        <tr><td class="rotulo">Nivel de riesgo</td><td><strong>{{ mb_strtoupper($informe->nivel_riesgo) }}</strong></td></tr>
        <tr><td class="rotulo">Tipos de violencia</td><td>{{ implode(', ', $informe->tipos_violencia) }}</td></tr>
        @if ($informe->transcripcion)
            <tr><td class="rotulo">Fuente</td><td>Transcripción del audio de la cita del {{ $informe->transcripcion->audio?->cita?->fecha?->format('d/m/Y') }}</td></tr>
        @endif
    </table>

    <h2>Resumen del análisis</h2>
    <div class="bloque">
        @foreach (preg_split('/\R{2,}/', trim($informe->resumen)) as $parrafo)
            <p>{!! nl2br(e($parrafo)) !!}</p>
        @endforeach
    </div>

    <h2>Marco legal aplicable</h2>
    @if (empty($informe->marco_legal) && $documentos->isEmpty())
        <p>No se citó marco legal.</p>
    @else
        <table>
            @foreach ($informe->marco_legal as $norma)
                <tr><td class="rotulo">{{ $norma['titulo'] }}</td><td>{{ $norma['descripcion'] }}</td></tr>
            @endforeach
            @foreach ($documentos as $documento)
                <tr><td class="rotulo">{{ $documento->titulo }}</td><td>{{ $documento->categoria }} · Repositorio de Bienestar{{ $documento->descripcion ? ' · '.$documento->descripcion : '' }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Ruta de atención recomendada</h2>
    <table>
        @foreach ($informe->ruta_atencion as $i => $paso)
            <tr><td class="rotulo">{{ $i + 1 }}. {{ $paso['titulo'] }}</td><td>{{ $paso['descripcion'] ?: '—' }}</td></tr>
        @endforeach
    </table>

    <p class="pie">
        Documento generado por SIGABU · Identificador {{ $informe->id_informe }} · {{ $ciudad }} · descargado el {{ $generado->format('d/m/Y H:i') }}.
    </p>
</body>
</html>

{{-- Recurso pedagógico con la plantilla de ajustes del caso: solo para el expediente. Datos de App\Documentos\PdfInclusion::plantilla($caso, false). --}}
@php
    $persona = $caso->solicitud?->persona;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recurso y plantilla de ajustes {{ $caso->codigo_expediente }}</title>
    @include('documentos.partials.estilos-inclusion')
</head>
<body>
    @include('documentos.partials.encabezado-inclusion')

    <table class="bloque">
        <tr><td class="rotulo">Expediente</td><td>{{ $caso->codigo_expediente }}</td></tr>
        <tr><td class="rotulo">Persona</td><td>{{ $persona?->nombre_completo }} · {{ $persona?->tipo_documento }} {{ $persona?->numero_documento }}</td></tr>
        <tr><td class="rotulo">Programa</td><td>{{ $programa ?? 'Sin registrar' }}</td></tr>
        <tr><td class="rotulo">Profesional a cargo</td><td>{{ $caso->profesional?->persona?->nombre_completo ?? 'Sin asignar' }}</td></tr>
    </table>

    @foreach ($recursos as $recurso)
        <h1>{{ $recurso->titulo }}</h1>
        <p class="subtitulo">{{ $recurso->condicion }} · versión {{ $recurso->version }}</p>
        @include('documentos.partials.bloques-recurso', ['bloques' => $recurso->contenido])
    @endforeach

    <div style="page-break-before: always"></div>
    <h1>5.8 PLANTILLA DE AJUSTES PEDAGÓGICOS PARA ACCESO EQUITATIVO</h1>
    <p class="subtitulo">Filas registradas en el caso</p>
    @include('documentos.partials.filas-plantilla', ['ajustes' => $ajustes, 'compartir' => false])

    <p class="pie">Documento con datos sensibles, solo para el expediente. Para compartir con docentes o dependencias usa el PDF para compartir. Descargado el {{ $generado->format('d/m/Y H:i') }}.</p>
</body>
</html>

{{--
    Plantilla de ajustes para compartir con docentes y dependencias (RN-38): sin el recurso, sin
    nombrar la condición, sin la caracterización ni soportes, y sin documento de identidad.
    Datos de App\Documentos\PdfInclusion::plantilla($caso, true).
--}}
@php
    $persona = $caso->solicitud?->persona;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Plantilla de ajustes pedagógicos</title>
    @include('documentos.partials.estilos-inclusion')
</head>
<body>
    @include('documentos.partials.encabezado-inclusion')

    <h1>PLANTILLA DE AJUSTES PEDAGÓGICOS PARA ACCESO EQUITATIVO</h1>

    <table class="bloque">
        <tr><td class="rotulo">Ciudad y fecha</td><td>{{ $ciudad }}, {{ $generado->format('d/m/Y') }}</td></tr>
        <tr><td class="rotulo">Estudiante</td><td>{{ $persona?->nombre_completo }}</td></tr>
        <tr><td class="rotulo">Programa</td><td>{{ $programa ?? 'Sin registrar' }}</td></tr>
        <tr><td class="rotulo">Profesional que acompaña</td><td>{{ $caso->profesional?->persona?->nombre_completo ?? 'Sin asignar' }}</td></tr>
    </table>

    <div class="bloque">
        @include('documentos.partials.filas-plantilla', ['ajustes' => $ajustes, 'compartir' => true])
    </div>

    <p class="nota">Información mínima necesaria para implementar y evaluar los ajustes razonables. Es confidencial: no la reenvíes ni la uses para otros fines.</p>
</body>
</html>

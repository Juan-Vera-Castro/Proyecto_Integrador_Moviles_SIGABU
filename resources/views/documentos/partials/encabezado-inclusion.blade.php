{{-- Encabezado de Calidad de los formatos de Inclusión. Recibe $formato (proceso, nombre, codigo, version, fecha) y $logo (data URI). --}}
<table class="encabezado">
    <tr>
        <td class="logo" rowspan="3"><img src="{{ $logo }}" alt="UCEVA"></td>
        <td>{{ $formato['proceso'] }}</td>
        <td class="codigo">CÓDIGO: {{ $formato['codigo'] }}</td>
    </tr>
    <tr>
        <td>FORMATO</td>
        <td class="codigo">VERSIÓN: {{ $formato['version'] }}</td>
    </tr>
    <tr>
        <td>{{ $formato['nombre'] }}</td>
        <td class="codigo">FECHA: {{ $formato['fecha'] }}</td>
    </tr>
</table>

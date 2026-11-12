{{-- Formato "Desistimiento de la atención". Recibe $d (App\Models\Desistimiento), $proceso, $nombreFormato, $fechaFormato, $ciudad y $logo (data URI). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Desistimiento {{ $d->caso?->codigo_expediente }}</title>
    <style>
        @page { size: letter; margin: 1.6cm 1.8cm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #000; padding: 5px 7px; vertical-align: middle; }
        .encabezado td { font-size: 8.5pt; font-weight: bold; text-align: center; height: 20px; }
        .encabezado .logo { width: 30%; }
        .encabezado .codigo { width: 28%; text-align: left; }
        .encabezado img { width: 170px; }
        .bloque { margin-top: 18px; }
        .rotulo { font-weight: bold; width: 32%; }
        p { line-height: 1.55; text-align: justify; margin: 14px 0; }
        .observaciones { min-height: 90px; white-space: pre-line; vertical-align: top; }
        .firmas td { border: none; padding-top: 55px; text-align: center; width: 50%; }
        .linea { border-top: 1px solid #000; padding-top: 4px; margin: 0 18px; font-size: 9.5pt; }
        .pie { margin-top: 26px; font-size: 8.5pt; color: #333; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="logo" rowspan="3"><img src="{{ $logo }}" alt="UCEVA"></td>
            <td>{{ $proceso }}</td>
            <td class="codigo">CÓDIGO: {{ $d->formato_codigo }}</td>
        </tr>
        <tr>
            <td>FORMATO</td>
            <td class="codigo">VERSIÓN: {{ $d->formato_version }}</td>
        </tr>
        <tr>
            <td>{{ $nombreFormato }}</td>
            <td class="codigo">FECHA: {{ $fechaFormato }}</td>
        </tr>
    </table>

    <table class="bloque">
        <tr><td class="rotulo">Ciudad y fecha</td><td>{{ $ciudad }}, {{ $d->fecha_hora->format('d/m/Y') }}</td></tr>
        <tr><td class="rotulo">Expediente</td><td>{{ $d->caso?->codigo_expediente }}</td></tr>
        <tr><td class="rotulo">Nombre</td><td>{{ $d->estudiante_nombre }}</td></tr>
        <tr><td class="rotulo">Documento</td><td>{{ $d->estudiante_tipo_documento }} {{ $d->estudiante_numero_documento }}</td></tr>
        <tr><td class="rotulo">Profesional</td><td>{{ $d->profesional_nombre }}</td></tr>
    </table>

    <p>
        Yo, <strong>{{ $d->estudiante_nombre }}</strong>, identificado(a) con {{ $d->estudiante_tipo_documento ?: 'documento' }} número
        <strong>{{ $d->estudiante_numero_documento }}</strong>, manifiesto de manera libre, consciente y voluntaria que desisto de continuar
        con el proceso de atención que adelanta la Vicerrectoría de Bienestar Universitario de la Unidad Central del Valle del Cauca
        (UCEVA) en el expediente {{ $d->caso?->codigo_expediente }}.
    </p>
    <p>
        Declaro que se me informó sobre la importancia del acompañamiento, las rutas de atención disponibles y que puedo solicitar
        nuevamente la atención en cualquier momento. Entiendo que esta decisión es mía y que libera a la universidad de la
        responsabilidad por la atención que decido no recibir.
    </p>

    <table class="bloque">
        <tr><td class="rotulo">Motivo</td><td>{{ $d->motivo }}</td></tr>
        <tr><td class="rotulo">Observaciones</td><td class="observaciones">{{ $d->observaciones }}</td></tr>
    </table>

    <table class="firmas">
        <tr>
            <td><div class="linea">Firma de quien desiste<br>{{ $d->estudiante_tipo_documento }} {{ $d->estudiante_numero_documento }}</div></td>
            <td><div class="linea">Firma del profesional<br>{{ $d->profesional_nombre }}</div></td>
        </tr>
    </table>

    <p class="pie">Registrado en SIGABU el {{ $d->fecha_hora->format('d/m/Y H:i') }}. El registro no se puede modificar ni borrar.</p>
</body>
</html>

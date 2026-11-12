{{-- Bitácora de orientación y acompañamiento, secciones I a IV. Datos de App\Documentos\PdfInclusion::bitacora(). --}}
@php
    use App\Models\Inclusion\Bitacora;
    use App\Models\Inclusion\Constancia;

    $persona = $caso->solicitud?->persona;
    $orientacion = 'landscape';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bitácora {{ $caso->codigo_expediente }}</title>
    @include('documentos.partials.estilos-inclusion', ['orientacion' => $orientacion])
</head>
<body>
    @include('documentos.partials.encabezado-inclusion')

    <h1>BITÁCORA DE ORIENTACIÓN Y ACOMPAÑAMIENTO - UNIDAD DE INCLUSIÓN</h1>

    <h2>I. DATOS GENERALES DEL CASO</h2>
    <p class="ayuda">Registro de datos básicos para la activación de la ruta de acompañamiento y el seguimiento sistemático del proceso de inclusión.</p>
    <table>
        <tr><th>ID de caso</th><th>Estado del proceso (Abierto, cerrado)</th><th>Fecha de apertura</th></tr>
        <tr>
            <td>{{ $caso->codigo_expediente }}</td>
            <td>{{ $caso->estado_caso }}{{ $caso->fecha_cierre ? ' (cerrado el '.$caso->fecha_cierre->format('d/m/Y').')' : '' }}</td>
            <td>{{ $caso->fecha_apertura?->format('d/m/Y') ?? 'No registrada' }}</td>
        </tr>
    </table>

    <h2>II. CARACTERIZACIÓN Y REGISTRO DE CASO</h2>
    <p class="ayuda">Identificación inicial de la persona y contexto institucional para la activación de la ruta de orientación.</p>
    @if (! $bitacora)
        <p class="vacio">La caracterización todavía no se ha guardado.</p>
    @else
        <table>
            <tr><td class="rotulo">Nombre completo</td><td>{{ $persona?->nombre_completo }}</td></tr>
            <tr><td class="rotulo">Identificación / Código</td><td>{{ $persona?->tipo_documento }} {{ $persona?->numero_documento }}</td></tr>
            @foreach (Bitacora::CARACTERIZACION as $campo => [$etiqueta])
                <tr>
                    <td class="rotulo">{{ $etiqueta }}</td>
                    <td class="texto">{{ $bitacora->{$campo} ?: '—' }}@if ($campo === 'soportes_texto' && $bitacora->soportes->isNotEmpty())<div style="margin-top: 4px">@include('documentos.partials.evidencias-inclusion', ['evidencias' => $bitacora->soportes])</div>@endif</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2 style="page-break-before: always; margin-top: 0">III. MATRIZ DE SEGUIMIENTO E INTERVENCIONES INTEGRALES CRONOLÓGICAS</h2>
    <p class="ayuda">Registro unificado de acciones, orientaciones y monitoreo de ajustes (formato acumulativo).</p>
    <table>
        <thead>
            <tr>
                <th style="width: 6%">Acción N°</th>
                <th style="width: 12%">Fecha y hora</th>
                <th style="width: 13%">Tipo de acción (Atención inicial, seguimiento, cierre)</th>
                <th>Descripción detallada de la orientación</th>
                <th style="width: 20%">Evidencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bitacora?->entradas ?? [] as $entrada)
                <tr>
                    <td>{{ $entrada->numero }}</td>
                    <td>{{ $entrada->fecha_hora->format('d/m/Y h:i a') }}</td>
                    <td>{{ $entrada->tipo }}</td>
                    <td>
                        <div class="texto">{{ $entrada->descripcion }}</div>
                        <div class="ayuda" style="margin-top: 3px">Registró {{ $entrada->profesional?->persona?->nombre_completo }}</div>
                        @foreach ($entrada->anotaciones as $anotacion)
                            <div style="margin-top: 5px; padding-left: 6px; border-left: 2px solid #007F2F">
                                <div class="ayuda" style="margin: 0">Anotación del {{ $anotacion->fecha_hora->format('d/m/Y H:i') }} · {{ $anotacion->profesional?->persona?->nombre_completo }}</div>
                                <div class="texto">{{ $anotacion->texto }}</div>
                            </div>
                        @endforeach
                    </td>
                    <td>
                        @include('documentos.partials.evidencias-inclusion', ['evidencias' => $entrada->evidencias])
                        @foreach ($entrada->anotaciones as $anotacion)
                            @include('documentos.partials.evidencias-inclusion', ['evidencias' => $anotacion->evidencias])
                        @endforeach
                        @if ($entrada->evidencias->isEmpty() && $entrada->anotaciones->every(fn ($a) => $a->evidencias->isEmpty()))
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Aún no hay acciones registradas.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="nota">*Nota: {{ Bitacora::NOTA }}</p>

    <div class="fila">
        <h2>IV. CONSTANCIA DE GESTIÓN</h2>
        <p class="ayuda">Resumen consolidado para la atención de requerimientos legales o institucionales al finalizar el periodo.</p>
        <p>{{ $constancia?->texto_presentado ?? Constancia::TEXTO }}</p>

        @if ($reabierta)
            <div class="aviso">El caso se reabrió después de esta constancia; la firma corresponde al cierre del {{ $constancia->fecha_hora->format('d/m/Y') }}.</div>
        @endif

        <table class="firmas">
            <tr>
                <td style="width: 50%; text-align: left">
                    @if ($firmaConstancia['src'] ?? null)
                        <img src="{{ $firmaConstancia['src'] }}" alt="Firma">
                    @else
                        <div class="espacio-firma"></div>
                    @endif
                    <div class="linea" style="margin-left: 0">Firma del profesional</div>
                </td>
                <td style="text-align: left">
                    <div class="linea">Nombre: {{ $constancia?->nombre_profesional }}</div>
                </td>
            </tr>
        </table>
        @if (! $constancia)
            <p class="vacio">La constancia se firma al cerrar el caso.</p>
        @elseif (! ($firmaConstancia['src'] ?? null))
            <p class="vacio">No se pudo cargar la imagen de la firma registrada.</p>
        @elseif (! $firmaConstancia['integra'])
            <p class="vacio">La imagen de la firma no coincide con la registrada.</p>
        @endif
    </div>

    <p class="pie">Expediente {{ $caso->codigo_expediente }} · documento con datos sensibles, solo para el expediente · descargado el {{ $generado->format('d/m/Y H:i') }}.</p>
</body>
</html>

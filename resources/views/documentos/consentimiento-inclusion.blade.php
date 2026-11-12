{{-- Consentimiento informado del Proceso de Inclusión ya registrado. Datos de App\Documentos\PdfInclusion::consentimiento(). --}}
@php
    use App\Models\Inclusion\ConsentimientoDetalle;

    $acudiente = $aceptacion->loOtorgaAcudiente();
    $profesional = $contrafirma?->profesional?->persona ?? $aceptacion->profesional?->persona;
    $nombre = $detalle?->nombre_persona ?? $caso->solicitud?->persona?->nombre_completo;
    $documento = $detalle?->documento_persona ?? $caso->solicitud?->persona?->numero_documento;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimiento informado {{ $caso->codigo_expediente }}</title>
    @include('documentos.partials.estilos-inclusion')
</head>
<body>
    @include('documentos.partials.encabezado-inclusion')

    <h1>FORMATO DE CONSENTIMIENTO INFORMADO</h1>
    <p class="subtitulo">Proceso de Inclusión Universitaria</p>

    @if ($revocacion)
        <div class="aviso">Consentimiento revocado el {{ $revocacion->fecha_hora->format('d/m/Y H:i') }}. La atención queda suspendida hasta que se firme uno nuevo.</div>
    @endif

    <table>
        <tr><td class="rotulo">Fecha</td><td>{{ ($detalle?->fecha_firma_papel ?? $aceptacion->fecha_hora)->format('d/m/Y') }}</td></tr>
        <tr><td class="rotulo">Nombre de la persona</td><td>{{ $nombre }}</td></tr>
        <tr><td class="rotulo">D.I.</td><td>{{ $documento }}</td></tr>
        <tr><td class="rotulo">Número de contacto</td><td>{{ $detalle?->numero_contacto ?? '—' }}</td></tr>
        <tr><td class="rotulo">Vinculación</td><td>{{ $detalle?->vinculacion_detalle ?? $aceptacion->vinculacion ?? '—' }}</td></tr>
    </table>
    <table>
        <tr>
            <th style="width: 24%">Facultad</th><th style="width: 30%">Programa</th><th style="width: 12%">Semestre</th><th>Dependencia</th>
        </tr>
        <tr>
            <td>{{ $dependencia['facultad'] ?? 'N/A' }}</td>
            <td>{{ $aceptacion->programa_academico ?? 'N/A' }}</td>
            <td>{{ $aceptacion->semestre ?? 'N/A' }}</td>
            <td>{{ $dependencia['dependencia'] ?? 'N/A' }}</td>
        </tr>
    </table>

    @if ($acudiente)
        <table class="bloque">
            <tr><td class="rotulo">Acudiente</td><td>{{ $aceptacion->acudiente_nombre }}</td></tr>
            <tr><td class="rotulo">Documento del acudiente</td><td>{{ $aceptacion->acudiente_tipo_documento }} {{ $aceptacion->acudiente_numero_documento }}</td></tr>
            <tr><td class="rotulo">Parentesco</td><td>{{ $aceptacion->acudiente_parentesco }}</td></tr>
        </table>
    @endif

    <div class="bloque">
        @foreach ($parrafos as $parrafo)
            @if ($parrafo['casilla'] === null)
                <p>{{ $parrafo['texto'] }}</p>
            @else
                <p class="decision"><span class="casilla">{{ $parrafo['casilla'] ? 'X' : '' }}</span>{{ $parrafo['texto'] }}</p>
            @endif
        @endforeach
    </div>

    @if ($aceptacion->esEnPapel())
        <div class="aviso">
            {{ ConsentimientoDetalle::LEYENDA_PAPEL }}.<br>
            <span style="font-weight: normal">
                Fecha de la firma: {{ $detalle?->fecha_firma_papel?->format('d/m/Y') }} · Registrado por {{ $aceptacion->profesional?->persona?->nombre_completo }}<br>
                Motivo: {{ $detalle?->motivo_papel }}<br>
                El formato firmado a mano está adjunto al expediente en SIGABU.
            </span>
        </div>
    @endif

    <p>Atentamente,</p>

    <table class="firmas">
        <tr>
            <td style="width: 50%">
                @if ($firmaPersona['src'] ?? null)
                    <img src="{{ $firmaPersona['src'] }}" alt="Firma">
                @else
                    <div class="espacio-firma"></div>
                @endif
                <div class="linea">
                    {{ $acudiente ? 'Acudiente' : 'Persona atendida' }}<br>
                    {{ $acudiente ? $aceptacion->acudiente_nombre : $nombre }}<br>
                    D.I. {{ $acudiente ? $aceptacion->acudiente_numero_documento : $documento }}
                </div>
            </td>
            <td></td>
        </tr>
        <tr>
            <td>
                @if ($firmaProfesional['src'] ?? null)
                    <img src="{{ $firmaProfesional['src'] }}" alt="Firma">
                @else
                    <div class="espacio-firma"></div>
                @endif
                <div class="linea">
                    Profesional Área de Inclusión<br>
                    {{ $profesional?->nombre_completo }}<br>
                    C.C. {{ $profesional?->numero_documento }}
                </div>
            </td>
            <td>
                <div class="espacio-firma"></div>
                <div class="linea">Docente o responsable académico (cuando aplique)<br><br>C.C.</div>
            </td>
        </tr>
    </table>

    <p class="pie">
        @if ($aceptacion->esEnPapel())
            Consentimiento registrado en SIGABU el {{ $aceptacion->fecha_hora->format('d/m/Y H:i') }} a partir del formato firmado en papel.
        @else
            Firmado en SIGABU el {{ $aceptacion->fecha_hora->format('d/m/Y H:i') }}{{ $contrafirma ? '; contrafirmado por el profesional el '.$contrafirma->fecha_hora->format('d/m/Y H:i') : '; pendiente la firma del profesional' }}.
            @if (($firmaPersona && ! $firmaPersona['src']) || ($contrafirma && ! $firmaProfesional['src']))
                No se pudo cargar la imagen de una firma registrada.
            @elseif (($firmaPersona && ! $firmaPersona['integra']) || ($contrafirma && ! $firmaProfesional['integra']))
                La imagen de una firma no coincide con la registrada.
            @endif
        @endif
        Expediente {{ $caso->codigo_expediente }} · versión {{ $aceptacion->version?->numero_version }} del texto · descargado el {{ $generado->format('d/m/Y H:i') }}.
    </p>
</body>
</html>

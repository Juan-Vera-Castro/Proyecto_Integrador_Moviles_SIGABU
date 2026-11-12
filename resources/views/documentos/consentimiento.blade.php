{{-- Consentimiento informado firmado de Salud Mental. Datos de App\Documentos\PdfConsentimiento::datos(). --}}
@php
    $acudiente = $aceptacion->loOtorgaAcudiente();
    $aceptado = $aceptacion->esAceptado();
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

    <h1>{{ $formato['nombre'] }}</h1>
    <p class="subtitulo">{{ $aceptacion->version?->modelo?->nombre }} · versión {{ $aceptacion->version?->numero_version }} del texto</p>

    <div class="aviso">
        Decisión: {{ $aceptado ? 'ACEPTÓ' : 'NO ACEPTÓ' }} el consentimiento informado · {{ $aceptacion->fecha_hora->format('d/m/Y H:i') }}
        @unless ($aceptado)
            <br><span style="font-weight: normal">Sin este consentimiento no se puede prestar la atención.</span>
        @endunless
    </div>

    <h2>Datos del caso</h2>
    <table>
        <tr><td class="rotulo">Expediente</td><td>{{ $caso->codigo_expediente }}</td></tr>
        <tr><td class="rotulo">Servicio</td><td>{{ $caso->solicitud?->tipo_servicio }}</td></tr>
        <tr><td class="rotulo">Apertura del caso</td><td>{{ $caso->fecha_apertura?->format('d/m/Y') }}</td></tr>
        <tr><td class="rotulo">Profesional tratante</td><td>{{ $profesional?->nombre_completo ?? '—' }}</td></tr>
    </table>

    <h2>Datos del estudiante</h2>
    <table>
        <tr><td class="rotulo">Nombre</td><td>{{ $persona?->nombre_completo }}</td></tr>
        <tr><td class="rotulo">Documento</td><td>{{ $persona?->tipo_documento }} {{ $persona?->numero_documento }}{{ $aceptacion->lugar_expedicion ? ' de '.$aceptacion->lugar_expedicion : '' }}</td></tr>
        <tr><td class="rotulo">Edad al firmar</td><td>{{ $edad !== null ? $edad.' años' : '—' }}</td></tr>
        <tr><td class="rotulo">Correo institucional</td><td>{{ $persona?->correo_inst }}</td></tr>
        <tr><td class="rotulo">EPS / IPS</td><td>{{ $aceptacion->eps_ips ?? '—' }}</td></tr>
        <tr>
            <td class="rotulo">Contacto en caso de urgencia</td>
            <td>
                @forelse ($aceptacion->contactos_urgencia ?? [] as $contacto)
                    {{ $contacto['nombre'] ?? '' }} · {{ $contacto['parentesco'] ?? '' }} · Tel. {{ $contacto['telefono'] ?? '' }}@if (! $loop->last)<br>@endif
                @empty
                    {{ $aceptacion->contacto_urgencia ?? '—' }}
                @endforelse
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <th style="width: 22%">Vinculación</th><th style="width: 30%">Programa</th><th style="width: 12%">Semestre</th><th>Facultad o dependencia</th>
        </tr>
        <tr>
            <td>{{ $aceptacion->vinculacion ?? 'N/A' }}</td>
            <td>{{ $aceptacion->programa_academico ?? 'N/A' }}</td>
            <td>{{ $aceptacion->semestre ?? 'N/A' }}</td>
            <td>{{ $aceptacion->dependencia_facultad ?? 'N/A' }}</td>
        </tr>
    </table>

    @if ($acudiente)
        <h2>Lo otorga el acudiente</h2>
        <table>
            <tr><td class="rotulo">Nombre</td><td>{{ $aceptacion->acudiente_nombre }}</td></tr>
            <tr><td class="rotulo">Documento</td><td>{{ $aceptacion->acudiente_tipo_documento }} {{ $aceptacion->acudiente_numero_documento }}{{ $aceptacion->acudiente_lugar_expedicion ? ' de '.$aceptacion->acudiente_lugar_expedicion : '' }}</td></tr>
            <tr><td class="rotulo">Parentesco</td><td>{{ $aceptacion->acudiente_parentesco }}</td></tr>
            @if ($aceptacion->acudiente_telefono)
                <tr><td class="rotulo">Teléfono</td><td>{{ $aceptacion->acudiente_telefono }}</td></tr>
            @endif
        </table>
    @endif

    <h2>Texto presentado y {{ $aceptado ? 'aceptado' : 'no aceptado' }}</h2>
    <div class="bloque">
        @foreach ($parrafos as $parrafo)
            @if ($parrafo['casilla'] === null)
                <p>{{ $parrafo['texto'] }}</p>
            @else
                <p class="decision"><span class="casilla">{{ $parrafo['casilla'] ? 'X' : '' }}</span>{{ $parrafo['texto'] }}</p>
            @endif
        @endforeach
    </div>

    <table class="firmas">
        <tr>
            @if ($firmaEstudiante)
                <td style="width: 33%">
                    @if ($firmaEstudiante['src'] ?? null)
                        <img src="{{ $firmaEstudiante['src'] }}" alt="Firma">
                    @else
                        <div class="espacio-firma"></div>
                    @endif
                    <div class="linea">
                        Estudiante (menor de edad)<br>
                        {{ $persona?->nombre_completo }}<br>
                        {{ $persona?->tipo_documento }} {{ $persona?->numero_documento }}
                    </div>
                </td>
            @endif
            <td style="width: {{ $firmaEstudiante ? 33 : 50 }}%">
                @if ($firmaOtorgante['src'] ?? null)
                    <img src="{{ $firmaOtorgante['src'] }}" alt="Firma">
                @else
                    <div class="espacio-firma"></div>
                @endif
                <div class="linea">
                    {{ $acudiente ? 'Acudiente' : 'Estudiante' }}<br>
                    {{ $acudiente ? $aceptacion->acudiente_nombre : $persona?->nombre_completo }}<br>
                    {{ $acudiente ? $aceptacion->acudiente_tipo_documento.' '.$aceptacion->acudiente_numero_documento : $persona?->tipo_documento.' '.$persona?->numero_documento }}
                </div>
            </td>
            <td>
                @if ($firmaProfesional['src'] ?? null)
                    <img src="{{ $firmaProfesional['src'] }}" alt="Firma">
                @else
                    <div class="espacio-firma"></div>
                @endif
                <div class="linea">
                    Profesional tratante<br>
                    {{ $profesional?->nombre_completo }}<br>
                    C.C. {{ $profesional?->numero_documento }}
                </div>
            </td>
        </tr>
    </table>

    <h2>Constancia de la firma electrónica</h2>
    <table>
        <tr><td class="rotulo">Medio</td><td>{{ $medio }}</td></tr>
        <tr><td class="rotulo">Fecha y hora</td><td>{{ $aceptacion->fecha_hora->format('d/m/Y H:i:s') }} (hora de Colombia)</td></tr>
        <tr><td class="rotulo">Firma del profesional</td><td>
            @if ($contrafirma)
                {{ $contrafirma->fecha_hora->format('d/m/Y H:i:s') }}
            @elseif ($aceptado)
                Pendiente
            @else
                No aplica
            @endif
        </td></tr>
        <tr><td class="rotulo">Identificador</td><td>{{ $aceptacion->id_aceptacion }}</td></tr>
        <tr><td class="rotulo">Huella del texto (SHA-256)</td><td style="font-size: 8pt">{{ $aceptacion->texto_sha256 }}<br>{{ $textoIntegro ? 'Verificada: el texto no ha cambiado desde la firma.' : 'NO COINCIDE: el texto no corresponde al que se firmó.' }}</td></tr>
        @if ($firmaOtorgante)
            <tr><td class="rotulo">Huella de la firma (SHA-256)</td><td style="font-size: 8pt">{{ $aceptacion->firma_sha256 }}<br>{{ $firmaOtorgante['src'] === null ? 'No se pudo cargar la imagen de la firma.' : ($firmaOtorgante['integra'] ? 'Verificada: la imagen coincide con la registrada.' : 'NO COINCIDE: la imagen no corresponde a la registrada.') }}</td></tr>
        @endif
        @if ($firmaEstudiante)
            <tr><td class="rotulo">Huella firma del estudiante</td><td style="font-size: 8pt">{{ $aceptacion->firma_estudiante_sha256 }}<br>{{ $firmaEstudiante['src'] === null ? 'No se pudo cargar la imagen de la firma.' : ($firmaEstudiante['integra'] ? 'Verificada: la imagen coincide con la registrada.' : 'NO COINCIDE: la imagen no corresponde a la registrada.') }}</td></tr>
        @endif
        @if ($contrafirma)
            <tr><td class="rotulo">Huella firma profesional</td><td style="font-size: 8pt">{{ $contrafirma->firma_sha256 }}<br>{{ $firmaProfesional['src'] === null ? 'No se pudo cargar la imagen de la firma.' : ($firmaProfesional['integra'] ? 'Verificada: la imagen coincide con la registrada.' : 'NO COINCIDE: la imagen no corresponde a la registrada.') }}</td></tr>
        @endif
        <tr><td class="rotulo">Dirección IP</td><td>{{ $aceptacion->ip_origen ?? '—' }}</td></tr>
        <tr><td class="rotulo">Navegador</td><td style="font-size: 8pt">{{ $aceptacion->user_agent ?? '—' }}</td></tr>
    </table>

    <p class="pie">
        Documento generado por SIGABU a partir del registro inmutable del consentimiento. Expediente {{ $caso->codigo_expediente }} · {{ $ciudad }} · descargado el {{ $generado->format('d/m/Y H:i') }}.
    </p>
</body>
</html>

{{-- Reporte de seguimiento de un caso de Inclusión (HU-020). Recibe $caso, $resumen, $generado, $generadoPor, $ciudad y $logo (data URI). --}}
@php
    use App\Models\AjusteRazonable;
    use App\Models\SeguimientoAjuste;

    $persona = $caso->solicitud?->persona;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de seguimiento {{ $caso->codigo_expediente }}</title>
    <style>
        @page { size: letter; margin: 1.6cm 1.8cm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        th { background: #e6f2ea; font-size: 9pt; text-align: left; }
        .encabezado td { font-size: 9pt; font-weight: bold; text-align: center; vertical-align: middle; height: 22px; }
        .encabezado .logo { width: 30%; }
        .encabezado img { width: 170px; }
        h2 { font-size: 11pt; margin: 18px 0 6px; color: #007F2F; }
        h3 { font-size: 10pt; margin: 14px 0 4px; }
        .bloque { margin-top: 12px; }
        .rotulo { font-weight: bold; width: 30%; }
        .texto { white-space: pre-line; }
        .ajuste { page-break-inside: avoid; }
        .vacio { font-style: italic; color: #444; }
        .pie { margin-top: 24px; font-size: 8.5pt; color: #333; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="logo" rowspan="2"><img src="{{ $logo }}" alt="UCEVA"></td>
            <td>VICERRECTORÍA DE BIENESTAR UNIVERSITARIO · INCLUSIÓN UNIVERSITARIA</td>
        </tr>
        <tr>
            <td>REPORTE DE SEGUIMIENTO DEL PROCESO DE INCLUSIÓN</td>
        </tr>
    </table>

    <table class="bloque">
        <tr><td class="rotulo">Ciudad y fecha</td><td>{{ $ciudad }}, {{ $generado->format('d/m/Y') }}</td></tr>
        <tr><td class="rotulo">Expediente</td><td>{{ $caso->codigo_expediente }}</td></tr>
        <tr><td class="rotulo">Estudiante</td><td>{{ $persona?->nombre_completo ?? 'Sin registrar' }}</td></tr>
        <tr><td class="rotulo">Documento</td><td>{{ $persona?->tipo_documento }} {{ $persona?->numero_documento }}</td></tr>
        <tr><td class="rotulo">Facultad</td><td>{{ $caso->solicitud?->facultad?->nombre ?? 'Sin facultad registrada' }}</td></tr>
        <tr><td class="rotulo">Profesional a cargo</td><td>{{ $caso->profesional?->persona?->nombre_completo ?? 'Sin asignar' }}</td></tr>
        <tr><td class="rotulo">Apertura del caso</td><td>{{ $caso->fecha_apertura?->format('d/m/Y') ?? 'No registrada' }}</td></tr>
        <tr><td class="rotulo">Estado del caso</td><td>{{ $caso->estado_caso }}{{ $caso->fecha_cierre ? ' (cerrado el '.$caso->fecha_cierre->format('d/m/Y').')' : '' }}</td></tr>
    </table>

    <h2>1. Resumen del proceso</h2>
    <table>
        <tr>
            <td class="rotulo">Ajustes razonables</td>
            <td>
                {{ $resumen['ajustes'] }} en total
                @if ($resumen['ajustes'] > 0)
                    ({{ collect(AjusteRazonable::ESTADOS)->map(fn ($estado) => mb_strtolower($estado).': '.($resumen['porEstado'][$estado] ?? 0))->implode(', ') }})
                @endif
            </td>
        </tr>
        <tr>
            <td class="rotulo">Seguimientos registrados</td>
            <td>
                {{ $resumen['seguimientos'] }}
                @if ($resumen['seguimientos'] > 0)
                    ({{ collect(SeguimientoAjuste::CUMPLIMIENTOS)->map(fn ($c) => mb_strtolower($c).': '.($resumen['porCumplimiento'][$c] ?? 0))->implode(', ') }})
                @endif
            </td>
        </tr>
        <tr>
            <td class="rotulo">Último seguimiento</td>
            <td>{{ $resumen['ultimo'] ? $resumen['ultimo']->fecha->format('d/m/Y').' · '.$resumen['ultimo']->cumplimiento : 'Sin seguimientos' }}</td>
        </tr>
        <tr><td class="rotulo">Sesiones realizadas</td><td>{{ $resumen['sesiones'] }}</td></tr>
    </table>

    <h2>2. Ajustes razonables y su seguimiento</h2>
    @forelse ($caso->ajustes->sortBy('fecha_inicio') as $i => $ajuste)
        <div class="ajuste">
            <h3>2.{{ $loop->iteration }} {{ $ajuste->etiqueta() }} · {{ $ajuste->estado }}</h3>
            <table>
                <tr><td class="rotulo">Barrera que atiende</td><td class="texto">{{ $ajuste->barrera }}</td></tr>
                <tr><td class="rotulo">En qué consiste</td><td class="texto">{{ $ajuste->descripcion }}</td></tr>
                <tr><td class="rotulo">Quién lo aplica</td><td>{{ $ajuste->responsable ?? 'No indicado' }}</td></tr>
                <tr><td class="rotulo">Aplica desde</td><td>{{ $ajuste->fecha_inicio->format('d/m/Y') }}</td></tr>
            </table>

            @if ($ajuste->seguimientos->isEmpty())
                <p class="vacio">Este ajuste todavía no tiene seguimientos.</p>
            @else
                <table class="bloque">
                    <tr>
                        <th style="width: 14%">Fecha</th>
                        <th style="width: 20%">Cumplimiento</th>
                        <th>Observaciones</th>
                        <th style="width: 20%">Profesional</th>
                    </tr>
                    @foreach ($ajuste->seguimientos as $seguimiento)
                        <tr>
                            <td>{{ $seguimiento->fecha->format('d/m/Y') }}</td>
                            <td>{{ $seguimiento->cumplimiento }}</td>
                            <td class="texto">{{ $seguimiento->observaciones }}@if ($seguimiento->estado_resultante)

(El ajuste pasó a "{{ $seguimiento->estado_resultante }}")@endif</td>
                            <td>{{ $seguimiento->profesional?->persona?->nombre_completo }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    @empty
        <p class="vacio">El caso no tiene ajustes razonables registrados.</p>
    @endforelse

    <p class="pie">
        Generado en SIGABU el {{ $generado->format('d/m/Y H:i') }}{{ $generadoPor ? ' por '.$generadoPor : '' }}.
        Documento con información confidencial del proceso de inclusión: su uso se limita a la gestión del caso.
    </p>
</body>
</html>

{{-- Informe Oficial de Transparencia y Auditoría - Módulo de Donaciones Alimentarias "Ucevistas de Corazón" --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de Transparencia - Donaciones Alimentarias UCEVA</title>
    <style>
        @page { size: letter; margin: 1.5cm 1.8cm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 8.5pt; color: #1f2937; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 8px; vertical-align: top; }
        th { background-color: #007F2F; color: #ffffff; font-weight: bold; text-align: left; font-size: 8pt; text-transform: uppercase; }

        .encabezado td { border: 1px solid #007F2F; padding: 6px 10px; text-align: center; vertical-align: middle; }
        .logo { width: 25%; font-weight: bold; font-size: 13pt; color: #007F2F; }
        .logo img { max-width: 140px; max-height: 45px; }
        .titulo-doc { font-size: 9pt; font-weight: bold; color: #004d1c; text-transform: uppercase; }
        .subtitulo-doc { font-size: 7.5pt; color: #4b5563; margin-top: 2px; }

        .seccion-titulo {
            font-size: 9.5pt;
            font-weight: bold;
            color: #007F2F;
            border-bottom: 1.5px solid #007F2F;
            padding-bottom: 3px;
            margin: 14px 0 6px 0;
            text-transform: uppercase;
        }

        .kpi-table { margin-bottom: 14px; }
        .kpi-table td { background-color: #f9fafb; text-align: center; padding: 8px 4px; border: 1px solid #e5e7eb; width: 25%; }
        .kpi-valor { font-size: 14pt; font-weight: bold; color: #007F2F; display: block; }
        .kpi-label { font-size: 7pt; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-top: 2px; }

        .aviso-habeas {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            font-size: 7.5pt;
            padding: 6px 10px;
            border-radius: 4px;
            margin-bottom: 12px;
        }

        .fila-alerta td { background-color: #fffbeb !important; }
        .pie-pagina {
            margin-top: 25px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 7.5pt;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>

    <table class="encabezado">
        <tr>
            <td class="logo" rowspan="2">
                @if ($logo)
                    <img src="{{ $logo }}" alt="UCEVA">
                @else
                    <span style="font-size: 15pt; font-weight: bold; color: #007F2F;">UCEVA</span>
                @endif
            </td>
            <td class="titulo-doc">
                UNIDAD CENTRAL DEL VALLE DEL CAUCA — UCEVA<br>
                VICERRECTORÍA DE BIENESTAR UNIVERSITARIO
            </td>
        </tr>
        <tr>
            <td class="subtitulo-doc">
                SISTEMA INTEGRADO DE GESTIÓN Y ATENCIÓN (SIGABU)<br>
                <strong>INFORME DE TRANSPARENCIA Y AUDITORÍA DE DONACIONES ALIMENTARIAS</strong>
            </td>
        </tr>
    </table>

    <table style="margin-top: 8px; font-size: 8pt;">
        <tr>
            <td style="width: 25%; background-color: #f3f4f6; font-weight: bold;">Fecha de Emisión:</td>
            <td style="width: 25%;">{{ $generado->format('d/m/Y H:i') }}</td>
            <td style="width: 25%; background-color: #f3f4f6; font-weight: bold;">Generado por:</td>
            <td style="width: 25%;">{{ $generadoPor ?? 'Responsable de Bodega' }}</td>
        </tr>
        <tr>
            <td style="background-color: #f3f4f6; font-weight: bold;">Lugar / Jurisdicción:</td>
            <td>{{ $ciudad }} (Valle del Cauca)</td>
            <td style="background-color: #f3f4f6; font-weight: bold;">Marco Normativo:</td>
            <td>Ley 1581/2012 · Estatuto de Bienestar</td>
        </tr>
    </table>

    <div class="seccion-titulo">1. Consolidado de Indicadores Clave de Gestión (KPIs)</div>
    <table class="kpi-table">
        <tr>
            <td>
                <span class="kpi-valor">{{ $resumen['mercadosDisponibles'] }}</span>
                <span class="kpi-label">Mercados en Bodega</span>
            </td>
            <td>
                <span class="kpi-valor">{{ $resumen['mercadosEntregados'] }}</span>
                <span class="kpi-label">Mercados Entregados</span>
            </td>
            <td>
                <span class="kpi-valor">{{ $resumen['coberturaEstudiantil'] }}</span>
                <span class="kpi-label">Estudiantes Beneficiados</span>
            </td>
            <td>
                <span class="kpi-valor">{{ $resumen['donantesRegistrados'] }}</span>
                <span class="kpi-label">Donantes Vinculados</span>
            </td>
        </tr>
    </table>

    <div class="seccion-titulo">2. Trazabilidad de Ingresos a Bodega (Inventario por Lotes)</div>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Código Lote</th>
                <th style="width: 20%;">Tipo</th>
                <th style="width: 30%;">Descripción de Insumos</th>
                <th style="width: 12%; text-align: center;">Inicial</th>
                <th style="width: 13%; text-align: center;">Disponible</th>
                <th style="width: 10%;">Vencimiento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lotes as $lote)
                <tr @if($lote->venceProximamente()) class="fila-alerta" @endif>
                    <td><strong>{{ $lote->codigo_lote }}</strong></td>
                    <td>{{ $lote->tipo }}</td>
                    <td>{{ $lote->descripcion }}</td>
                    <td style="text-align: center;">{{ $lote->cantidad_inicial }}</td>
                    <td style="text-align: center;"><strong>{{ $lote->cantidad_disponible }}</strong></td>
                    <td>{{ $lote->fecha_vencimiento ? $lote->fecha_vencimiento->format('d/m/Y') : 'N/A' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align: center; color: #6b7280;">No se registran lotes en el inventario.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion-titulo">3. Fuentes de Aporte y Solidaridad (Donantes)</div>
    <table>
        <thead>
            <tr>
                <th style="width: 35%;">Donante o Institución</th>
                <th style="width: 20%;">Tipo</th>
                <th style="width: 25%;">Categoría</th>
                <th style="width: 20%; text-align: center;">Lotes Aportados</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($donantes as $donante)
                <tr>
                    <td>
                        @if ($donante->anonimo)
                            <em>Donante Anónimo (Protegido por Habeas Data)</em>
                        @else
                            <strong>{{ $donante->nombre }}</strong>
                        @endif
                    </td>
                    <td>{{ $donante->tipo }}</td>
                    <td>{{ $donante->categoria }}</td>
                    <td style="text-align: center;">{{ $donante->lotes_count }} lote(s)</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align: center; color: #6b7280;">No hay donantes registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion-titulo">4. Despachos y Salidas a Estudiantes Beneficiarios</div>
    <div class="aviso-habeas">
        <strong>Protección de Datos Personales (Habeas Data):</strong> En cumplimiento del Artículo 15 de la Constitución y la Ley 1581 de 2012, los nombres y documentos de los estudiantes beneficiarios se presentan salvaguardando su reserva socioeconómica y dignidad humana.
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 18%;">Fecha y Hora</th>
                <th style="width: 30%;">Beneficiario (Iniciales / Doc)</th>
                <th style="width: 22%;">Lote Despachado</th>
                <th style="width: 15%;">Verificación</th>
                <th style="width: 15%;">Responsable</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entregas as $entrega)
                @php
                    $nombre = $entrega->solicitud?->persona?->nombre_completo ?? 'Estudiante';
                    $partes = explode(' ', trim($nombre));
                    $iniciales = '';
                    foreach ($partes as $p) { if (!empty($p)) $iniciales .= mb_substr($p, 0, 1) . '. '; }
                    $doc = $entrega->documento_verificado;
                    $docProtegido = strlen($doc) > 4 ? substr($doc, 0, 2) . '***' . substr($doc, -2) : $doc;
                @endphp
                <tr>
                    <td>{{ $entrega->fecha_hora->format('d/m/Y H:i') }}</td>
                    <td><strong>{{ trim($iniciales) }}</strong> ({{ $docProtegido }})</td>
                    <td>{{ $entrega->lote?->codigo_lote }} · {{ $entrega->lote?->tipo }}</td>
                    <td>{{ $entrega->metodo_validacion }}</td>
                    <td>{{ $entrega->responsable?->persona?->nombre_completo ?? 'Funcionario Almacén' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center; color: #6b7280;">No se registran entregas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pie-pagina">
        Informe generado de manera automática por el Módulo de Donaciones Alimentarias — SIGABU UCEVA.<br>
        Validez Institucional · Vicerrectoría de Bienestar Universitario · {{ $generado->format('Y') }}
    </div>

</body>
</html>

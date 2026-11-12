{{-- Certificado de Donación "Ucevistas de Corazón" - Vicerrectoría de Bienestar Universitario UCEVA --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado de Donación - {{ $donante->nombre }}</title>
    <style>
        @page { size: letter; margin: 1.8cm 2cm; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        .encabezado td { border: 1px solid #007F2F; padding: 8px 12px; vertical-align: middle; text-align: center; }
        .logo { width: 28%; font-weight: bold; font-size: 14pt; color: #007F2F; text-align: center; }
        .logo img { max-width: 150px; max-height: 50px; }
        .titulo-doc { font-size: 9.5pt; font-weight: bold; color: #004d1c; text-transform: uppercase; }
        .subtitulo-doc { font-size: 8.5pt; color: #4b5563; font-weight: normal; margin-top: 3px; }

        .cuerpo { margin-top: 28px; text-align: justify; }
        .certificado-destacado {
            margin: 22px 0;
            padding: 16px 20px;
            background-color: #f0fdf4;
            border-left: 4px solid #007F2F;
            border-radius: 4px;
        }
        .nombre-donante {
            font-size: 13pt;
            font-weight: bold;
            color: #004d1c;
            display: block;
            margin-bottom: 4px;
        }
        .categoria-donante {
            font-size: 9pt;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tabla-aportes { margin-top: 18px; width: 100%; font-size: 9pt; }
        .tabla-aportes th { background-color: #007F2F; color: #ffffff; padding: 6px 10px; text-align: left; font-weight: 600; border: 1px solid #007F2F; }
        .tabla-aportes td { padding: 6px 10px; border: 1px solid #e5e7eb; }
        .tabla-aportes tr:nth-child(even) td { background-color: #f9fafb; }
        .total-fila td { font-weight: bold; background-color: #ecfdf5 !important; color: #065f46; }

        .firmas-container { margin-top: 45px; width: 100%; }
        .firmas-container td { width: 50%; text-align: center; vertical-align: top; padding: 0 20px; }
        .linea-firma { width: 80%; margin: 0 auto 8px auto; border-top: 1px solid #374151; }
        .cargo-firma { font-size: 8.5pt; color: #4b5563; }
        .nombre-firmante { font-size: 9.5pt; font-weight: bold; color: #111827; }

        .pie-pagina {
            margin-top: 35px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt;
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
                    <span style="font-size: 16pt; font-weight: bold; color: #007F2F;">UCEVA</span>
                @endif
            </td>
            <td class="titulo-doc">
                UNIDAD CENTRAL DEL VALLE DEL CAUCA — UCEVA<br>
                VICERRECTORÍA DE BIENESTAR UNIVERSITARIO
            </td>
        </tr>
        <tr>
            <td class="subtitulo-doc">
                PROGRAMA DE APOYO ALIMENTARIO "UCEVISTAS DE CORAZÓN"<br>
                <strong>CONSTANCIA Y CERTIFICADO OFICIAL DE DONACIÓN</strong>
            </td>
        </tr>
    </table>

    <div class="cuerpo">
        <p>
            La <strong>Vicerrectoría de Bienestar Universitario</strong> de la Unidad Central del Valle del Cauca (UCEVA),
            a través de su Programa de Seguridad Alimentaria y Permanencia Estudiantil <em>"Ucevistas de Corazón"</em>,
            hace constar que:
        </p>

        <div class="certificado-destacado">
            <span class="nombre-donante">{{ $donante->nombre }}</span>
            <span class="categoria-donante">Donante {{ $donante->tipo }} · Categoría: {{ $donante->categoria }}</span>
            @if($donante->contacto)
                <div style="margin-top: 6px; font-size: 8.5pt; color: #374151;">
                    <strong>Contacto registrado:</strong> {{ $donante->contacto }}
                </div>
            @endif
        </div>

        <p>
            Ha realizado una valiosa y solidaria contribución al banco de alimentos institucional,
            destinada de forma íntegra, transparente y con estricta trazabilidad a garantizar el sustento alimentario
            de los estudiantes en condición de vulnerabilidad socioeconómica matriculados en la institución.
        </p>

        <h4 style="font-size: 10pt; color: #004d1c; margin-top: 16px; margin-bottom: 6px; text-transform: uppercase;">
            Relación de Aportes e Insumos Entregados
        </h4>

        <table class="tabla-aportes">
            <thead>
                <tr>
                    <th style="width: 22%;">Código de Lote</th>
                    <th style="width: 25%;">Tipo de Aporte</th>
                    <th style="width: 38%;">Descripción de Contenido</th>
                    <th style="width: 15%; text-align: right;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lotes as $lote)
                    <tr>
                        <td><strong>{{ $lote->codigo_lote }}</strong></td>
                        <td>{{ $lote->tipo }}</td>
                        <td>{{ $lote->descripcion }}</td>
                        <td style="text-align: right;">{{ number_format($lote->cantidad_inicial) }} kits</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #6b7280; padding: 12px;">
                            Donante registrado y vinculado activamente al programa solidario institucional.
                        </td>
                    </tr>
                @endforelse
                <tr class="total-fila">
                    <td colspan="3" style="text-align: right;">TOTAL CONSOLIDADO APORTADO:</td>
                    <td style="text-align: right;">{{ number_format($totalMercados) }} unidades</td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top: 18px; font-size: 9pt; color: #374151;">
            Esta constancia se expide a solicitud de la parte interesada en {{ $ciudad }}, el día
            <strong>{{ $generado->translatedFormat('d \d\e F \d\e Y') }}</strong>, en testimonio de gratitud institucional
            y cumplimiento de los principios de transparencia y responsabilidad social universitaria.
        </p>
    </div>

    <table class="firmas-container">
        <tr>
            <td>
                <div class="linea-firma"></div>
                <div class="nombre-firmante">{{ $generadoPor ?? 'Responsable de Almacén y Donaciones' }}</div>
                <div class="cargo-firma">Gestión de Bodega y Control de Insumos<br>Ucevistas de Corazón</div>
            </td>
            <td>
                <div class="linea-firma"></div>
                <div class="nombre-firmante">Vicerrectoría de Bienestar Universitario</div>
                <div class="cargo-firma">Unidad Central del Valle del Cauca — UCEVA<br>Nit. 891.900.285-3</div>
            </td>
        </tr>
    </table>

    <div class="pie-pagina">
        Unidad Central del Valle del Cauca — Carrera 27 A No. 48-144 Km. 1 Salida a Tuluá (Valle del Cauca)<br>
        Documento oficial generado por el Sistema SIGABU · Vicerrectoría de Bienestar Universitario · Código de Verificación: CERT-DON-{{ str_pad($donante->id_donante, 5, '0', STR_PAD_LEFT) }}-{{ $generado->format('Ymd') }}
    </div>

</body>
</html>

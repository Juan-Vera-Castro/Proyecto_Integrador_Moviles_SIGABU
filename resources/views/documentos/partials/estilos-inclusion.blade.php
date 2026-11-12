{{-- Estilos comunes de los PDF de Inclusión, tomados de reporte-inclusion y desistimiento. --}}
<style>
    @page { size: letter {{ $orientacion ?? 'portrait' }}; margin: 1.5cm 1.7cm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #000; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
    th { background: #e6f2ea; font-size: 9pt; text-align: left; }
    thead { display: table-header-group; }
    .encabezado td { font-size: 8.5pt; font-weight: bold; text-align: center; vertical-align: middle; height: 20px; }
    .encabezado .logo { width: 28%; }
    .encabezado .codigo { width: 26%; text-align: left; }
    .encabezado img { width: 160px; }
    h1 { font-size: 11.5pt; text-align: center; margin: 16px 0 2px; }
    .subtitulo { text-align: center; margin: 0 0 10px; font-size: 10pt; }
    h2 { font-size: 10.5pt; margin: 16px 0 4px; color: #007F2F; }
    h3 { font-size: 10pt; margin: 12px 0 4px; }
    p { line-height: 1.45; text-align: justify; margin: 7px 0; }
    .bloque { margin-top: 10px; }
    .rotulo { font-weight: bold; width: 30%; }
    .texto { white-space: pre-line; }
    .ayuda { font-size: 8.5pt; color: #333; margin: 0 0 6px; }
    .casilla { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; text-align: center; font-size: 8pt; line-height: 10px; font-weight: bold; margin-right: 6px; }
    .decision { margin: 4px 0 4px 8px; text-align: left; }
    .sin-borde td { border: none; }
    .firmas td { border: none; text-align: center; vertical-align: bottom; padding-top: 10px; }
    .firmas img { height: 60px; }
    .espacio-firma { height: 60px; }
    .linea { border-top: 1px solid #000; padding-top: 3px; margin: 0 14px; font-size: 9pt; }
    .aviso { border: 1px solid #000; padding: 6px 8px; margin: 10px 0; font-weight: bold; }
    .vacio { font-style: italic; color: #444; }
    .nota { font-size: 9pt; font-style: italic; margin-top: 6px; }
    .fila { page-break-inside: avoid; }
    .pie { margin-top: 18px; font-size: 8pt; color: #333; }
</style>

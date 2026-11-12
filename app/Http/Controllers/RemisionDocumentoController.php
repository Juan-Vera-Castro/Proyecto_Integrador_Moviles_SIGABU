<?php

namespace App\Http\Controllers;

use App\Documentos\DatosRemision;
use App\Documentos\RemisionWord;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\RemisionExterna;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Documento "Remisión psicológica" (formato GBU-GPGR-F-016) en PDF y Word para imprimir y
 * firmar (HU-RE-02). Se genera al vuelo desde la copia guardada en remisiones_externas,
 * así que siempre sale igual aunque después cambien el registro o el formato (RN-09, RN-13).
 */
class RemisionDocumentoController extends Controller
{
    public function pdf(Request $request, Actuacion $remision): Response
    {
        $detalle = $this->detalleAutorizado($request, $remision);
        $datos = DatosRemision::desde($detalle);

        // El logo va en JPEG: dompdf incrusta los PNG con la extensión GD, que el servidor puede no tener.
        $pdf = Pdf::loadView('documentos.remision-psicologica', [
            'd' => $datos,
            'logo' => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-uceva.jpg'))),
        ])->setPaper('letter');

        AuditoriaExpediente::registrar(AuditoriaExpediente::DOCUMENTO_REMISION_PDF, $remision->caso, ['id_actuacion' => $remision->id_actuacion]);

        return $this->descarga($pdf->output(), 'application/pdf', $datos->nombreArchivo.'.pdf');
    }

    public function word(Request $request, Actuacion $remision): Response
    {
        $datos = DatosRemision::desde($this->detalleAutorizado($request, $remision));

        AuditoriaExpediente::registrar(AuditoriaExpediente::DOCUMENTO_REMISION_WORD, $remision->caso, ['id_actuacion' => $remision->id_actuacion]);

        return $this->descarga(
            RemisionWord::generar($datos),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $datos->nombreArchivo.'.docx',
        );
    }

    /**
     * Solo las remisiones externas con formato; las descargan quien remitió y el profesional
     * actual del caso.
     */
    private function detalleAutorizado(Request $request, Actuacion $remision): RemisionExterna
    {
        abort_unless($remision->tipo_actuacion === Actuacion::TIPO_REMISION && $remision->remisionExterna, 404);

        $usuario = $request->user()->id_usuario;
        abort_unless($remision->id_profesional === $usuario || $remision->caso?->id_profesional === $usuario, 403);

        return $remision->remisionExterna;
    }

    private function descarga(string $contenido, string $tipo, string $nombre): Response
    {
        return response($contenido, 200, [
            'Content-Type' => $tipo,
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Reporte de seguimiento de un caso de Inclusión (HU-020): documento PDF con la información
 * consolidada del proceso (ajustes razonables y su historial de seguimiento, HU-018/019).
 */
class ReporteInclusionController extends Controller
{
    public function pdf(Request $request, string $caso): Response
    {
        // Solo el profesional asignado; a los demás (también a quien remitió el caso), 404 para no revelar que existe.
        $caso = Caso::where('id_profesional', $request->user()->id_usuario)->whereKey($caso)->first()
            ?? abort(404, 'Caso no encontrado.');
        abort_unless($caso->esDeInclusion(), 404);

        $caso->load(['solicitud.persona', 'solicitud.facultad', 'profesional.persona', 'citas', 'ajustes.seguimientos.profesional.persona']);

        $seguimientos = $caso->ajustes->flatMap->seguimientos;
        $generado = now(Actuacion::ZONA_HORARIA);

        $pdf = Pdf::loadView('documentos.reporte-inclusion', [
            'caso' => $caso,
            'resumen' => [
                'ajustes' => $caso->ajustes->count(),
                'porEstado' => $caso->ajustes->countBy('estado'),
                'seguimientos' => $seguimientos->count(),
                'porCumplimiento' => $seguimientos->countBy('cumplimiento'),
                'ultimo' => $seguimientos->sortBy(fn ($s) => [$s->fecha, $s->fecha_registro])->last(),
                'sesiones' => $caso->sesionesRealizadas(),
            ],
            'generado' => $generado,
            'generadoPor' => $request->user()->persona?->nombre_completo,
            'ciudad' => config('sigabu.ciudad'),
            'logo' => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-uceva.jpg'))),
        ])->setPaper('letter');

        AuditoriaExpediente::registrar(AuditoriaExpediente::REPORTE_INCLUSION_PDF, $caso, ['ajustes' => $caso->ajustes->count(), 'seguimientos' => $seguimientos->count()]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Reporte-seguimiento-'.$caso->codigo_expediente.'-'.$generado->format('d-m-y').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}

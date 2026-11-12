<?php

namespace App\Http\Controllers\Vbg;

use App\Models\Vbg\Reporte;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Acceso a las citas, consentimientos, audios e informes de un caso VBG: solo el profesional a
 * cargo. Registrar algo, además, exige que el caso siga "En atención".
 */
trait ExigeCasoACargo
{
    /**
     * Para consultar evidencia (firmas, audios, PDF): solo el profesional a cargo del caso.
     */
    protected function exigirACargo(Request $request, Reporte $reporte): void
    {
        abort_unless($reporte->id_profesional !== null && $reporte->id_profesional === $request->user()->id_usuario, 403);
    }

    /**
     * Para registrar: a cargo y con el caso en atención.
     */
    protected function exigirGestionable(Request $request, Reporte $reporte): void
    {
        $this->exigirACargo($request, $reporte);

        if ($reporte->estado !== Reporte::EN_ATENCION) {
            throw ValidationException::withMessages(['reporte' => "El caso {$reporte->codigo} está \"{$reporte->estado}\": no admite cambios."])
                ->redirectTo(route('vbg.reportes.show', $reporte));
        }
    }

    protected function volverAlCaso(Reporte $reporte, string $ancla, string $mensaje)
    {
        return redirect()->to(route('vbg.reportes.show', $reporte).'#'.$ancla)->with('status', $mensaje);
    }
}

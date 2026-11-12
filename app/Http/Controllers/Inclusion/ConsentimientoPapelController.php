<?php

namespace App\Http\Controllers\Inclusion;

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\PdfInclusion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inclusion\RegistrarConsentimientoPapelRequest;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Facultad;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\Inclusion\Evidencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Consentimiento firmado en papel, para quien no puede firmar en la plataforma (HU-04). Lo
 * carga el profesional asignado y queda Firmado, con la constancia escrita de que fue en papel.
 */
class ConsentimientoPapelController extends Controller
{
    public function create(Caso $caso): View|RedirectResponse
    {
        Gate::authorize('inclusion.gestionar', $caso);

        if ($motivo = $this->motivoSinRegistro($caso)) {
            return redirect()->route('profesional.expediente.show', $caso)->with('error', $motivo);
        }

        $caso->load('solicitud.persona');
        $persona = $caso->solicitud->persona;

        return view('inclusion.consentimiento.papel', [
            'caso' => $caso,
            'menor' => $persona->esMenorEn(now(Actuacion::ZONA_HORARIA)) === true,
            'iniciales' => ConsentimientoController::datosIniciales($persona),
            'facultades' => Facultad::activas()->orderBy('nombre')->pluck('nombre'),
        ]);
    }

    public function store(RegistrarConsentimientoPapelRequest $request, Caso $caso): RedirectResponse
    {
        if ($motivo = $this->motivoSinRegistro($caso)) {
            return redirect()->route('profesional.expediente.show', $caso)->with('error', $motivo);
        }

        $archivo = $request->file('archivo');
        $id = (string) Str::uuid();
        $ruta = "casos/{$caso->id_caso}/{$id}-papel.".strtolower($archivo->getClientOriginalExtension());
        $disco = Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS);
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $volver = fn (string $mensaje) => back()->withInput($request->except('archivo'))->with('error', $mensaje);

        try {
            $disco->put($ruta, $contenido);
        } catch (Throwable $e) {
            report($e);

            return $volver('No se pudo guardar el archivo. No se registró nada; intenta de nuevo.');
        }

        try {
            DB::transaction(function () use ($request, $caso, $id, $ruta, $contenido) {
                ConsentimientoAceptacion::create($request->columnasAceptacion() + [
                    'id_aceptacion' => $id,
                    'id_caso' => $caso->id_caso,
                    'decision' => ConsentimientoAceptacion::ACEPTADO,
                    'fecha_hora' => now(Actuacion::ZONA_HORARIA),
                    'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_PAPEL,
                    'id_profesional' => $request->user()->id_usuario,
                    'firma_ruta' => $ruta,
                    'firma_sha256' => hash('sha256', $contenido),
                    'ip_origen' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 252),
                ]);

                ConsentimientoDetalle::create($request->columnasDetalle() + [
                    'id_aceptacion' => $id,
                    'motivo_papel' => $request->validated('motivo_papel'),
                    'fecha_firma_papel' => $request->validated('fecha_firma_papel'),
                ]);

                AuditoriaExpediente::registrar(AuditoriaExpediente::CONSENTIMIENTO_PAPEL_REGISTRADO, $caso, ['id_aceptacion' => $id]);
            });
        } catch (Throwable $e) {
            report($e);
            rescue(fn () => $disco->delete($ruta));

            return $volver('No fue posible registrar el consentimiento. No se guardó nada; intenta de nuevo.');
        }

        return redirect()->route('profesional.expediente.show', $caso)->with('status', 'El consentimiento en papel quedó registrado.');
    }

    /**
     * Descarga del formato escaneado. Es privado: sale por la aplicación y queda auditado.
     */
    public function archivo(ConsentimientoAceptacion $aceptacion): Response
    {
        $caso = $aceptacion->caso;
        Gate::authorize('inclusion.verConsentimiento', $caso);
        abort_unless($aceptacion->esEnPapel(), 404);

        try {
            $contenido = (string) Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->get($aceptacion->firma_ruta);
        } catch (Throwable $e) {
            report($e);
            abort(503, 'No se pudo obtener el archivo en este momento.');
        }

        abort_if($contenido === '', 404);

        $integra = hash_equals((string) $aceptacion->firma_sha256, hash('sha256', $contenido));
        AuditoriaExpediente::registrar(AuditoriaExpediente::FIRMA_CONSULTADA, $caso, ['id_aceptacion' => $aceptacion->id_aceptacion, 'integra' => $integra]);

        $extension = pathinfo($aceptacion->firma_ruta, PATHINFO_EXTENSION);

        return response($contenido, 200, [
            'Content-Type' => Evidencia::TIPOS_PERMITIDOS[$extension][0] ?? 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"consentimiento-papel-{$caso->codigo_expediente}.{$extension}\"",
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function motivoSinRegistro(Caso $caso): ?string
    {
        return match (true) {
            ! $caso->estaActivo() => "El caso {$caso->codigo_expediente} ya no está activo.",
            ! ConsentimientoInclusion::aplicaA($caso) => 'Este caso no usa el formato de consentimiento de Inclusión.',
            $caso->consentimientoVigente() !== null => 'El caso ya tiene un consentimiento vigente.',
            default => null,
        };
    }

    /**
     * Formato con el texto vigente y los datos de la persona, para firmar a mano (CA-04.1).
     */
    public function imprimible(Caso $caso): Response|RedirectResponse
    {
        Gate::authorize('inclusion.gestionar', $caso);

        if ($motivo = $this->motivoSinRegistro($caso)) {
            return redirect()->route('profesional.expediente.show', $caso)->with('error', $motivo);
        }

        $respuesta = PdfInclusion::descargar('documentos.consentimiento-inclusion-imprimible', PdfInclusion::imprimible($caso), PdfInclusion::nombreArchivo('Consentimiento-para-firmar', $caso));

        AuditoriaExpediente::registrar(AuditoriaExpediente::CONSENTIMIENTO_INCLUSION_PDF, $caso, ['formato' => 'imprimible']);

        return $respuesta;
    }
}

<?php

namespace App\Http\Controllers\Inclusion;

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\PdfInclusion;
use App\Documentos\TextoConsentimiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inclusion\EnviarConsentimientoRequest;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Facultad;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Consentimiento del Proceso de Inclusión en línea: la persona lo diligencia, lo firma, lo
 * consulta y lo revoca. Usa consentimiento_aceptaciones, así que el bloqueo de la atención y
 * la contrafirma siguen funcionando igual; las decisiones van en inclusion_consentimientos.
 */
class ConsentimientoController extends Controller
{
    public function formulario(Request $request, Caso $caso): View|RedirectResponse
    {
        Gate::authorize('inclusion.diligenciarConsentimiento', $caso);

        if (! ConsentimientoInclusion::aplicaA($caso)) {
            return redirect()->route('consentimientos.firmar', $caso);
        }

        if (! $caso->estaActivo()) {
            return redirect()->route('citas.miscitas')->with('error', "El caso {$caso->codigo_expediente} ya no está activo: no se puede diligenciar el consentimiento.");
        }

        if ($caso->consentimientoVigente()) {
            return redirect()->route('inclusion.consentimiento.ver', $caso)->with('status', 'Ya tienes un consentimiento vigente en este caso.');
        }

        $caso->load(['solicitud.persona', 'profesional.persona']);
        $persona = $caso->solicitud->persona;
        $ahora = now(Actuacion::ZONA_HORARIA);
        $borrador = Borrador::de($caso, $request->user(), Borrador::CONSENTIMIENTO);

        return view('inclusion.consentimiento.formulario', [
            'caso' => $caso,
            'persona' => $persona,
            'faltanDatos' => ! $persona->datosRemisionCompletos(),
            'menor' => $persona->esMenorEn($ahora) === true,
            'version' => ConsentimientoInclusion::versionDe($caso),
            'datosBase' => TextoConsentimiento::datosBase($caso, $caso->profesional, $ahora),
            'iniciales' => array_merge(self::datosIniciales($persona), $borrador?->datos ?? []),
            'facultades' => Facultad::activas()->orderBy('nombre')->pluck('nombre'),
            'borrador' => $borrador,
            'almacenamientoListo' => ConsentimientoAceptacion::almacenamientoListo(),
        ]);
    }

    /**
     * La firma se sube primero; si después falla la base, se borra, para que nunca quede una
     * sin la otra. La aceptación y sus decisiones se guardan juntas.
     */
    public function enviar(EnviarConsentimientoRequest $request, Caso $caso): JsonResponse
    {
        if (! $caso->estaActivo()) {
            return response()->json(['message' => "El caso {$caso->codigo_expediente} ya no está activo."], 409);
        }

        if ($caso->consentimientoVigente()) {
            return $this->exito($caso, 'El consentimiento ya estaba registrado.');
        }

        if (! ConsentimientoAceptacion::almacenamientoListo()) {
            return response()->json(['message' => 'El almacenamiento de firmas no está configurado. Avisa al administrador; mientras tanto no se puede enviar el consentimiento.'], 409);
        }

        $id = (string) Str::uuid();
        $ruta = "casos/{$caso->id_caso}/{$id}.png";
        $png = $request->firmaPng();
        $disco = Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS);

        try {
            $disco->put($ruta, $png);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo guardar la firma. No se registró nada; intenta de nuevo.'], 503);
        }

        try {
            DB::transaction(function () use ($request, $caso, $id, $ruta, $png) {
                ConsentimientoAceptacion::create($request->columnasAceptacion() + [
                    'id_aceptacion' => $id,
                    'id_caso' => $caso->id_caso,
                    'decision' => ConsentimientoAceptacion::ACEPTADO,
                    'fecha_hora' => now(Actuacion::ZONA_HORARIA),
                    'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION,
                    'id_profesional' => $caso->id_profesional,
                    'firma_ruta' => $ruta,
                    'firma_sha256' => hash('sha256', $png),
                    'ip_origen' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 252),
                ]);

                ConsentimientoDetalle::create($request->columnasDetalle() + ['id_aceptacion' => $id]);
                Borrador::terminar($caso, $request->user(), Borrador::CONSENTIMIENTO);

                AuditoriaExpediente::registrar(AuditoriaExpediente::CONSENTIMIENTO_ACEPTADO, $caso, [
                    'id_aceptacion' => $id,
                    'id_version' => $request->versionVigente()->id_version,
                ]);
            });
        } catch (Throwable $e) {
            report($e);
            rescue(fn () => $disco->delete($ruta));

            return response()->json(['message' => 'No fue posible registrar el consentimiento. No se guardó nada; intenta de nuevo.'], 503);
        }

        return $this->exito($caso, 'Tu consentimiento quedó firmado y enviado. Tu profesional ya puede verlo.');
    }

    public function ver(Caso $caso): View
    {
        Gate::authorize('inclusion.verConsentimiento', $caso);

        $caso->load([
            'solicitud.persona',
            'aceptacionesConsentimiento.version.modelo',
            'aceptacionesConsentimiento.detalleInclusion',
            'aceptacionesConsentimiento.firmaProfesional',
        ]);

        $esDueno = Gate::allows('inclusion.diligenciarConsentimiento', $caso);
        $vigente = $caso->consentimientoVigente();

        return view('inclusion.consentimiento.ver', [
            'caso' => $caso,
            'estado' => $caso->estadoConsentimiento(),
            'aceptacion' => $caso->aceptacionesConsentimiento->first(fn (ConsentimientoAceptacion $fila) => $fila->esAceptado()),
            'esDueno' => $esDueno,
            'puedeRevocar' => $esDueno && $vigente !== null,
            'puedeFirmar' => $esDueno && $vigente === null && $caso->estaActivo(),
        ]);
    }

    /**
     * La revocación es una fila nueva: no borra nada, conserva el texto aceptado y bloquea la
     * atención como un rechazo. Después la persona puede firmar de nuevo (RN-13, RN-14).
     */
    public function revocar(Request $request, Caso $caso): RedirectResponse
    {
        Gate::authorize('inclusion.diligenciarConsentimiento', $caso);

        $vigente = $caso->consentimientoVigente();
        $volver = redirect()->route('inclusion.consentimiento.ver', $caso);

        if (! $vigente) {
            return $volver->with('error', 'No hay un consentimiento vigente para revocar.');
        }

        $id = (string) Str::uuid();

        try {
            DB::transaction(function () use ($request, $caso, $vigente, $id) {
                ConsentimientoAceptacion::create([
                    'id_aceptacion' => $id,
                    'id_caso' => $caso->id_caso,
                    'id_version' => $vigente->id_version,
                    'decision' => ConsentimientoAceptacion::RECHAZADO,
                    'fecha_hora' => now(Actuacion::ZONA_HORARIA),
                    'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION,
                    'id_profesional' => $caso->id_profesional ?? $vigente->id_profesional,
                    'otorgante' => $vigente->otorgante,
                    'acudiente_nombre' => $vigente->acudiente_nombre,
                    'acudiente_tipo_documento' => $vigente->acudiente_tipo_documento,
                    'acudiente_numero_documento' => $vigente->acudiente_numero_documento,
                    'acudiente_parentesco' => $vigente->acudiente_parentesco,
                    'acudiente_lugar_expedicion' => $vigente->acudiente_lugar_expedicion,
                    'texto_presentado' => $vigente->texto_presentado,
                    'texto_sha256' => $vigente->texto_sha256,
                    'ip_origen' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 252),
                ]);

                ConsentimientoDetalle::create(['id_aceptacion' => $id, 'es_revocacion' => true]);

                AuditoriaExpediente::registrar(AuditoriaExpediente::CONSENTIMIENTO_REVOCADO, $caso, ['id_aceptacion' => $id]);
            });
        } catch (Throwable $e) {
            report($e);

            return $volver->with('error', 'No fue posible registrar la revocación. Nada cambió; intenta de nuevo.');
        }

        return $volver->with('status', 'Tu consentimiento quedó revocado. No se prestará atención hasta que firmes uno nuevo.');
    }

    /**
     * Valores con los que se abre el formato, a partir de lo que ya se sabe de la persona.
     *
     * @return array<string, mixed>
     */
    public static function datosIniciales(Persona $persona): array
    {
        $academicos = $persona->datosAcademicos();
        $esEstudiante = $academicos['vinculacion'] === 'Estudiante';

        return [
            'nombre_persona' => $persona->nombre_completo,
            'documento_persona' => $persona->numero_documento,
            'vinculacion' => match (true) {
                $esEstudiante && $academicos['jornada'] === 'Diurna' => 'Estudiante Diurno',
                $esEstudiante && $academicos['jornada'] === 'Nocturna' => 'Estudiante Nocturno',
                default => '',
            },
            'facultad' => $academicos['facultad']?->nombre,
            'programa_academico' => $academicos['programa'],
            'semestre' => $academicos['semestre'],
        ];
    }

    private function exito(Caso $caso, string $mensaje): JsonResponse
    {
        session()->flash('status', $mensaje);

        return response()->json(['redirect' => route('inclusion.consentimiento.ver', $caso), 'mensaje' => $mensaje]);
    }

    /**
     * PDF del último consentimiento aceptado, con sus firmas o la leyenda del papel. Lo
     * descargan la persona, el profesional asignado y el administrador (CA-02.2).
     */
    public function pdf(Caso $caso): Response
    {
        Gate::authorize('inclusion.verConsentimiento', $caso);

        $caso->load('aceptacionesConsentimiento.detalleInclusion');
        $aceptacion = $caso->aceptacionesConsentimiento->first(fn (ConsentimientoAceptacion $fila) => $fila->esAceptado());
        abort_unless($aceptacion, 404);

        $respuesta = PdfInclusion::descargar('documentos.consentimiento-inclusion', PdfInclusion::consentimiento($caso, $aceptacion), PdfInclusion::nombreArchivo('Consentimiento-inclusion', $caso));

        AuditoriaExpediente::registrar(AuditoriaExpediente::CONSENTIMIENTO_INCLUSION_PDF, $caso, ['id_aceptacion' => $aceptacion->id_aceptacion]);

        return $respuesta;
    }
}

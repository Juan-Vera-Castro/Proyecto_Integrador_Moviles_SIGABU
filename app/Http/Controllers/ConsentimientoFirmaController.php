<?php

namespace App\Http\Controllers;

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\PdfConsentimiento;
use App\Documentos\PdfInclusion;
use App\Documentos\TextoConsentimiento;
use App\Http\Requests\RegistrarConsentimientoRequest;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Consentimiento informado con firma táctil (HU-CI-01, HU-CI-02, HU-CI-05). Lo firma el
 * estudiante (o su acudiente, si es menor) desde su cuenta, en "Mis citas"; después el
 * profesional tratante lo contrafirma desde el expediente, como en el formato en papel. Las
 * firmas van al bucket privado S3 y la evidencia a consentimiento_aceptaciones
 * y consentimiento_firmas_profesional.
 */
class ConsentimientoFirmaController extends Controller
{
    public const DISCO = ConsentimientoAceptacion::DISCO_FIRMAS;

    /**
     * Segundos en los que una misma decisión repetida se toma como un doble envío.
     */
    private const VENTANA_DOBLE_ENVIO = 10;

    /**
     * Pantalla de firma: texto vigente para la población del estudiante, sus datos y el
     * área de firma. Si faltan sus datos, se le indica que los complete su profesional (D-20).
     */
    public function create(Request $request, Caso $caso): View|RedirectResponse
    {
        $this->exigirCasoFirmable($request, $caso);

        if (ConsentimientoInclusion::aplicaA($caso)) {
            return redirect()->route('inclusion.consentimiento.formulario', $caso);
        }

        if (! $caso->estaActivo()) {
            return $this->volverConError($caso, "El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\": solo se registra el consentimiento en casos activos.");
        }

        $caso->load(['solicitud.persona', 'profesional.persona', 'aceptacionesConsentimiento']);
        $persona = $caso->solicitud->persona;
        $ahora = now(Actuacion::ZONA_HORARIA);
        $faltanDatos = ! $persona->datosRemisionCompletos();
        $menor = $persona->esMenorEn($ahora) === true;

        return view('consentimientos.firmar', [
            'caso' => $caso,
            'persona' => $persona,
            'faltanDatos' => $faltanDatos,
            'menor' => $menor,
            'edad' => $persona->edadEn($ahora),
            'version' => $faltanDatos ? null : ModeloConsentimiento::versionVigenteDe(
                $caso->solicitud->tipo_servicio,
                $menor ? ModeloConsentimiento::POBLACION_MENORES : ModeloConsentimiento::POBLACION_GENERAL,
            ),
            'datosBase' => TextoConsentimiento::datosBase($caso, $caso->profesional, $ahora),
            'vinculacion' => DB::table('vinculacion')->where('id_persona', $persona->id_persona)->first(),
            'almacenamientoListo' => self::almacenamientoListo(),
        ]);
    }

    /**
     * Registra la aceptación (con firma) o el rechazo. La firma se sube primero; si después
     * falla la base, se borra, para que nunca quede una sin la otra (RNF-08).
     */
    public function store(RegistrarConsentimientoRequest $request, Caso $caso): JsonResponse|RedirectResponse
    {
        $this->exigirCasoFirmable($request, $caso);

        // Este formato guarda sus decisiones: no se registra por el flujo general.
        abort_if(ConsentimientoInclusion::aplicaA($caso), 409, 'Vuelve a cargar la página: este consentimiento cambió de formato.');

        if (! $caso->estaActivo()) {
            return $this->responderError($request, $caso, "El caso {$caso->codigo_expediente} ya no está activo.");
        }

        $acepta = $request->validated('decision') === ConsentimientoAceptacion::ACEPTADO;

        if ($acepta && ! self::almacenamientoListo()) {
            return $this->responderError($request, $caso, 'El almacenamiento de firmas no está configurado. Avisa al administrador; mientras tanto no se puede registrar la aceptación.');
        }

        if ($this->esDobleEnvio($caso, $request)) {
            return $this->responderExito($request, $caso, 'El consentimiento ya estaba registrado.');
        }

        $datos = $request->validated();
        $ahora = now(Actuacion::ZONA_HORARIA);
        $version = $request->versionVigente();
        $acudiente = $acepta ? $request->esMenor() : $request->otorgaAcudiente();

        $texto = TextoConsentimiento::renderizar(
            $version->texto,
            TextoConsentimiento::datosBase($caso, $caso->profesional, $ahora) + TextoConsentimiento::datosDeFirma($datos),
        );

        $idAceptacion = (string) Str::uuid();
        $ruta = null;
        $png = $request->firmaPng();
        // Menor de edad: además del acudiente (firma), firma el propio estudiante.
        $rutaEstudiante = null;
        $pngEstudiante = $acepta && $acudiente ? $request->firmaEstudiantePng() : null;
        $borrarFirmas = function () use (&$ruta, &$rutaEstudiante) {
            foreach (array_filter([$ruta, $rutaEstudiante]) as $subida) {
                rescue(fn () => Storage::disk(self::DISCO)->delete($subida));
            }
        };

        if ($acepta) {
            $ruta = "casos/{$caso->id_caso}/{$idAceptacion}.png";

            try {
                Storage::disk(self::DISCO)->put($ruta, $png);

                if ($pngEstudiante) {
                    $rutaEstudiante = "casos/{$caso->id_caso}/{$idAceptacion}-estudiante.png";
                    Storage::disk(self::DISCO)->put($rutaEstudiante, $pngEstudiante);
                }
            } catch (Throwable $e) {
                report($e);
                $borrarFirmas();

                return $this->responderError($request, $caso, 'No se pudo guardar la firma. No se registró nada; intenta de nuevo.');
            }
        }

        try {
            DB::transaction(function () use ($caso, $request, $datos, $ahora, $version, $acepta, $acudiente, $texto, $idAceptacion, $ruta, $png, $rutaEstudiante, $pngEstudiante) {
                ConsentimientoAceptacion::create([
                    'id_aceptacion' => $idAceptacion,
                    'id_caso' => $caso->id_caso,
                    'id_version' => $version->id_version,
                    'decision' => $datos['decision'],
                    'fecha_hora' => $ahora,
                    'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_ENLACE,
                    // Profesional responsable de la atención: quien firma es el estudiante.
                    'id_profesional' => $caso->id_profesional,
                    'otorgante' => $acudiente ? ConsentimientoAceptacion::OTORGANTE_ACUDIENTE : ConsentimientoAceptacion::OTORGANTE_ESTUDIANTE,
                    'acudiente_nombre' => $acudiente ? $datos['acudiente_nombre'] : null,
                    'acudiente_tipo_documento' => $acudiente ? $datos['acudiente_tipo_documento'] : null,
                    'acudiente_numero_documento' => $acudiente ? $datos['acudiente_numero_documento'] : null,
                    'acudiente_parentesco' => $acudiente ? $datos['acudiente_parentesco'] : null,
                    'acudiente_lugar_expedicion' => $acudiente ? ($datos['acudiente_lugar_expedicion'] ?? null) : null,
                    'acudiente_telefono' => $acudiente ? ($datos['acudiente_telefono'] ?? null) : null,
                    'lugar_expedicion' => $datos['lugar_expedicion'] ?? null,
                    'eps_ips' => $datos['eps_ips'] ?? null,
                    'contacto_urgencia' => $acudiente ? null : ($datos['contacto_urgencia'] ?? null),
                    'contactos_urgencia' => $acudiente ? null : (array_values($datos['contactos'] ?? []) ?: null),
                    'vinculacion' => $datos['vinculacion'] ?? null,
                    'programa_academico' => $datos['programa_academico'] ?? null,
                    'semestre' => $datos['semestre'] ?? null,
                    'dependencia_facultad' => $datos['dependencia_facultad'] ?? null,
                    'texto_presentado' => $texto,
                    'texto_sha256' => hash('sha256', $texto),
                    'firma_ruta' => $ruta,
                    'firma_sha256' => $acepta ? hash('sha256', $png) : null,
                    'firma_estudiante_ruta' => $rutaEstudiante,
                    'firma_estudiante_sha256' => $pngEstudiante ? hash('sha256', $pngEstudiante) : null,
                    'ip_origen' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 252),
                ]);

                AuditoriaExpediente::registrar(
                    $acepta ? AuditoriaExpediente::CONSENTIMIENTO_ACEPTADO : AuditoriaExpediente::CONSENTIMIENTO_RECHAZADO,
                    $caso,
                    ['id_aceptacion' => $idAceptacion, 'id_version' => $version->id_version],
                );
            });
        } catch (Throwable $e) {
            report($e);
            $borrarFirmas();

            return $this->responderError($request, $caso, 'No fue posible registrar el consentimiento. No se guardó nada; intenta de nuevo.');
        }

        return $this->responderExito($request, $caso, $acepta
            ? 'Gracias. Tu consentimiento informado quedó aceptado y firmado; tu profesional ya puede atenderte.'
            : 'Registramos que no aceptas el consentimiento informado. Sin él no se puede prestar la atención; si cambias de opinión, puedes firmarlo desde aquí.');
    }

    /**
     * Contrafirma del profesional tratante sobre un consentimiento aceptado, como en el
     * formato en papel. La da el profesional que nombra el texto, desde el expediente.
     */
    public function firmarProfesional(Request $request, ConsentimientoAceptacion $aceptacion): JsonResponse|RedirectResponse
    {
        $caso = $aceptacion->caso;
        abort_unless($aceptacion->esAceptado() && $aceptacion->id_profesional === $request->user()->id_usuario, 403);
        // En Inclusión no se contrafirma el papel ni un consentimiento que ya no está vigente.
        abort_if($aceptacion->esEnPapel() || ($caso->esDeInclusion() && ! $caso->consentimientoVigente()?->is($aceptacion)), 403);

        $volver = route('casos.show', $caso).'#consentimiento';
        $responder = function (string $mensaje, bool $exito) use ($request, $caso, $volver) {
            // Con fetch, un error se muestra junto al lienzo: no debe quedar para la página siguiente.
            if ($exito || ! $request->expectsJson()) {
                session()->flash($exito ? 'status' : 'error', $mensaje);
            }

            // Con fetch se vuelve sin el ancla: si solo cambiara el ancla, el navegador no recargaría el expediente.
            return $request->expectsJson()
                ? ($exito ? response()->json(['redirect' => route('casos.show', $caso), 'mensaje' => $mensaje]) : response()->json(['message' => $mensaje], 409))
                : redirect()->to($volver);
        };

        if ($aceptacion->firmaProfesional()->exists()) {
            return $responder('El consentimiento ya tenía tu firma.', true);
        }

        if (! self::almacenamientoListo()) {
            return $responder('El almacenamiento de firmas no está configurado. Avisa al administrador.', false);
        }

        [$png, $error] = RegistrarConsentimientoRequest::leerFirma((string) $request->input('firma'));

        if ($error) {
            throw ValidationException::withMessages(['firma' => $error])->errorBag('firmaProfesional');
        }

        $ruta = "casos/{$caso->id_caso}/{$aceptacion->id_aceptacion}-profesional.png";

        try {
            Storage::disk(self::DISCO)->put($ruta, $png);
        } catch (Throwable $e) {
            report($e);

            return $responder('No se pudo guardar la firma. No se registró nada; intenta de nuevo.', false);
        }

        try {
            DB::transaction(function () use ($aceptacion, $caso, $request, $ruta, $png) {
                $aceptacion->firmaProfesional()->create([
                    'id_profesional' => $request->user()->id_usuario,
                    'fecha_hora' => now(Actuacion::ZONA_HORARIA),
                    'firma_ruta' => $ruta,
                    'firma_sha256' => hash('sha256', $png),
                    'ip_origen' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 252),
                ]);

                AuditoriaExpediente::registrar(AuditoriaExpediente::CONSENTIMIENTO_FIRMADO_PROFESIONAL, $caso, ['id_aceptacion' => $aceptacion->id_aceptacion]);
            });
        } catch (Throwable $e) {
            report($e);
            rescue(fn () => Storage::disk(self::DISCO)->delete($ruta));

            return $responder('No fue posible registrar tu firma. No se guardó nada; intenta de nuevo.', false);
        }

        return $responder('Firmaste el consentimiento informado como profesional tratante.', true);
    }

    /**
     * Imagen de la firma del estudiante (o su acudiente), o con $quien = 'profesional' la del
     * profesional tratante. Solo para quien puede consultar la evidencia del caso; no se
     * cachea y cada consulta queda auditada, con el resultado de comparar su huella.
     */
    public function firma(Request $request, ConsentimientoAceptacion $aceptacion, ?string $quien = null): Response
    {
        $caso = $aceptacion->caso;
        abort_unless($caso && $caso->puedeConsultarEvidencia($request->user()), 403);

        // El escaneado en papel no es una imagen de firma: se abre por su propia ruta.
        abort_if($aceptacion->esEnPapel(), 404);

        // Sin $quien: quien otorga (estudiante o acudiente). 'estudiante': el menor que también firma.
        [$ruta, $huella] = match ($quien) {
            'profesional' => [$aceptacion->firmaProfesional?->firma_ruta, $aceptacion->firmaProfesional?->firma_sha256],
            'estudiante' => [$aceptacion->firma_estudiante_ruta, $aceptacion->firma_estudiante_sha256],
            default => [$aceptacion->firma_ruta, $aceptacion->firma_sha256],
        };
        abort_unless($ruta, 404);

        try {
            $png = Storage::disk(self::DISCO)->get($ruta);
        } catch (Throwable $e) {
            report($e);
            abort(503, 'No se pudo obtener la firma en este momento.');
        }

        $integra = hash_equals((string) $huella, hash('sha256', (string) $png));

        AuditoriaExpediente::registrar(AuditoriaExpediente::FIRMA_CONSULTADA, $caso, array_filter([
            'id_aceptacion' => $aceptacion->id_aceptacion,
            'firmante' => $quien,
            'integra' => $integra,
        ], fn ($valor) => $valor !== null));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="firma.png"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Firma-Integra' => $integra ? 'si' : 'no',
        ]);
    }

    /**
     * PDF del consentimiento firmado, con el texto exacto, los datos del otorgante, las firmas
     * y la constancia de la firma. Lo descargan los mismos que pueden ver la firma; no se
     * cachea y cada descarga queda auditada. Los de Inclusión tienen su propio formato.
     */
    public function pdf(Request $request, ConsentimientoAceptacion $aceptacion): Response
    {
        $caso = $aceptacion->caso;
        abort_unless($caso && $caso->puedeConsultarEvidencia($request->user()), 403);
        abort_unless(PdfConsentimiento::aplicaA($aceptacion), 404);

        $datos = PdfConsentimiento::datos($aceptacion);
        $respuesta = PdfInclusion::descargar('documentos.consentimiento', $datos, PdfConsentimiento::nombreArchivo($aceptacion));

        AuditoriaExpediente::registrar(AuditoriaExpediente::CONSENTIMIENTO_PDF, $caso, [
            'id_aceptacion' => $aceptacion->id_aceptacion,
            'integro' => $datos['textoIntegro']
                && ($datos['firmaOtorgante']['integra'] ?? true)
                && ($datos['firmaProfesional']['integra'] ?? true),
        ]);

        return $respuesta;
    }

    /**
     * Si el disco de firmas tiene sus credenciales (pendiente PD-01).
     */
    public static function almacenamientoListo(): bool
    {
        return ConsentimientoAceptacion::almacenamientoListo();
    }

    /**
     * Solo el estudiante dueño del caso firma su consentimiento.
     */
    private function exigirCasoFirmable(Request $request, Caso $caso): void
    {
        abort_unless($caso->esDelEstudiante($request->user()), 403);
        abort_unless($caso->requiereConsentimiento(), 404);
    }

    private function esDobleEnvio(Caso $caso, RegistrarConsentimientoRequest $request): bool
    {
        return ConsentimientoAceptacion::where('id_caso', $caso->id_caso)
            ->where('decision', $request->validated('decision'))
            ->where('fecha_hora', '>=', now(Actuacion::ZONA_HORARIA)->subSeconds(self::VENTANA_DOBLE_ENVIO)->format('Y-m-d H:i:s.u'))
            ->exists();
    }

    private function urlConsentimiento(Caso $caso): string
    {
        return route('citas.miscitas').'#caso-'.$caso->id_caso;
    }

    private function volverConError(Caso $caso, string $mensaje): RedirectResponse
    {
        return redirect()->to($this->urlConsentimiento($caso))->with('error', $mensaje);
    }

    private function responderExito(Request $request, Caso $caso, string $mensaje): JsonResponse|RedirectResponse
    {
        session()->flash('status', $mensaje);

        return $request->expectsJson()
            ? response()->json(['redirect' => $this->urlConsentimiento($caso), 'mensaje' => $mensaje])
            : redirect()->to($this->urlConsentimiento($caso));
    }

    private function responderError(Request $request, Caso $caso, string $mensaje): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $mensaje], 409)
            : redirect()->route('consentimientos.firmar', $caso)->withInput($request->except('firma'))->with('error', $mensaje);
    }
}

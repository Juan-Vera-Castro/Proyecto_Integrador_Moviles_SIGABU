<?php

namespace App\Http\Controllers\Vbg;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarConsentimientoRequest;
use App\Models\Actuacion;
use App\Models\Vbg\Auditoria;
use App\Models\Vbg\Cita;
use App\Models\Vbg\ConsentimientoCita;
use App\Models\Vbg\Reporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Consentimiento informado de una cita VBG con firma táctil, en presencia del profesional.
 * Si la persona atendida es menor de edad, otorga el consentimiento su acudiente (nombre,
 * teléfono, cédula, parentesco y firma) y también firma la persona.
 */
class ConsentimientoCitaController extends Controller
{
    use ExigeCasoACargo;

    public function create(Request $request, Cita $cita): View|RedirectResponse
    {
        $reporte = $this->reporteDe($cita);
        $this->exigirGestionable($request, $reporte);
        $this->exigirCitaAbierta($cita, $reporte);

        // La persona afectada que reportó con su cuenta: sus datos ya se conocen.
        $persona = $reporte->tipo_reportante === Reporte::AFECTADA ? $reporte->reportante : null;

        return view('vbg.consentimiento', [
            'cita' => $cita,
            'reporte' => $reporte,
            'config' => config('sigabu.vbg.consentimiento_cita'),
            'sugerido' => [
                'firmante_nombre' => $persona?->nombre_completo ?? '',
                'firmante_documento' => $persona?->numero_documento ?? '',
                'es_menor' => (bool) $persona?->esMenorEn(now(Actuacion::ZONA_HORARIA)),
            ],
        ]);
    }

    public function store(Request $request, Cita $cita): JsonResponse|RedirectResponse
    {
        $reporte = $this->reporteDe($cita);
        $this->exigirGestionable($request, $reporte);
        $this->exigirCitaAbierta($cita, $reporte);

        $request->merge([
            'es_menor' => $request->boolean('es_menor'),
            'acudiente_telefono' => preg_replace('/[\s().-]/', '', (string) $request->input('acudiente_telefono')) ?: null,
        ]);
        $acepta = $request->input('decision') === ConsentimientoCita::ACEPTADO;
        $menor = $request->boolean('es_menor');
        $siMenor = fn () => Rule::excludeIf(! $menor);

        $datos = $request->validate([
            'decision' => ['required', Rule::in([ConsentimientoCita::ACEPTADO, ConsentimientoCita::RECHAZADO])],
            'firmante_nombre' => ['required', 'string', 'max:150'],
            'firmante_documento' => ['required', 'string', 'max:20'],
            'es_menor' => ['boolean'],
            'acudiente_nombre' => [$siMenor(), 'required', 'string', 'max:150'],
            'acudiente_telefono' => [$siMenor(), 'required', 'regex:/^\+?\d{7,15}$/'],
            'acudiente_documento' => [$siMenor(), 'required', 'string', 'max:20'],
            'acudiente_parentesco' => [$siMenor(), 'required', 'string', 'max:50'],
            'firma' => [Rule::excludeIf(! $acepta), 'required', 'string'],
            'firma_estudiante' => [Rule::excludeIf(! $acepta || ! $menor), 'required', 'string'],
        ], [
            'decision.required' => 'Indica si la persona acepta o no el consentimiento.',
            'firmante_nombre.required' => 'Escribe el nombre de la persona atendida.',
            'firmante_documento.required' => 'Escribe el documento de la persona atendida.',
            'acudiente_nombre.required' => 'Escribe el nombre del acudiente.',
            'acudiente_telefono.required' => 'Escribe el teléfono del acudiente.',
            'acudiente_telefono.regex' => 'Escribe un teléfono válido para el acudiente: solo números, entre 7 y 15 dígitos.',
            'acudiente_documento.required' => 'Escribe la cédula del acudiente.',
            'acudiente_parentesco.required' => 'Escribe el parentesco del acudiente con la persona atendida.',
            'firma.required' => $menor ? 'Falta la firma del acudiente.' : 'Falta la firma de la persona atendida.',
            'firma_estudiante.required' => 'Falta la firma de la persona atendida (menor de edad).',
        ]);

        [$png, $pngMenor] = $acepta ? $this->leerFirmas($request, $menor) : [null, null];

        $id = (string) Str::uuid();
        $texto = ConsentimientoCita::textoPara($datos);
        $rutas = [];

        try {
            if ($png) {
                $rutas['firma'] = "consentimientos/{$cita->id_cita}/{$id}.png";
                Storage::disk(ConsentimientoCita::DISCO)->put($rutas['firma'], $png);
            }
            if ($pngMenor) {
                $rutas['menor'] = "consentimientos/{$cita->id_cita}/{$id}-menor.png";
                Storage::disk(ConsentimientoCita::DISCO)->put($rutas['menor'], $pngMenor);
            }

            DB::connection('vbg')->transaction(function () use ($cita, $reporte, $request, $datos, $menor, $texto, $id, $rutas, $png, $pngMenor) {
                ConsentimientoCita::create([
                    'id_consentimiento' => $id,
                    'id_cita' => $cita->id_cita,
                    'decision' => $datos['decision'],
                    'firmante_nombre' => trim($datos['firmante_nombre']),
                    'firmante_documento' => trim($datos['firmante_documento']),
                    'es_menor' => $menor,
                    'acudiente_nombre' => $menor ? trim($datos['acudiente_nombre']) : null,
                    'acudiente_telefono' => $menor ? $datos['acudiente_telefono'] : null,
                    'acudiente_documento' => $menor ? trim($datos['acudiente_documento']) : null,
                    'acudiente_parentesco' => $menor ? trim($datos['acudiente_parentesco']) : null,
                    'version' => config('sigabu.vbg.consentimiento_cita.version'),
                    'texto_presentado' => $texto,
                    'texto_sha256' => hash('sha256', $texto),
                    'firma_ruta' => $rutas['firma'] ?? null,
                    'firma_sha256' => $png ? hash('sha256', $png) : null,
                    'firma_menor_ruta' => $rutas['menor'] ?? null,
                    'firma_menor_sha256' => $pngMenor ? hash('sha256', $pngMenor) : null,
                    'id_profesional' => $request->user()->id_usuario,
                    'ip_origen' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 252),
                    'fecha_hora' => now(Actuacion::ZONA_HORARIA)->format('Y-m-d H:i:s.u'),
                ]);

                Auditoria::registrar(Auditoria::CONSENTIMIENTO_REGISTRADO, $reporte->id_reporte, [
                    'id_cita' => $cita->id_cita, 'id_consentimiento' => $id, 'decision' => $datos['decision'],
                ]);
            });
        } catch (Throwable $e) {
            report($e);
            foreach ($rutas as $ruta) {
                rescue(fn () => Storage::disk(ConsentimientoCita::DISCO)->delete($ruta));
            }

            return $this->responder($request, $reporte, $cita, 'No fue posible registrar el consentimiento. No se guardó nada; intenta de nuevo.', false);
        }

        return $this->responder($request, $reporte, $cita, $acepta
            ? 'Consentimiento aceptado y firmado. Ya puedes iniciar la cita.'
            : 'Quedó registrado que la persona no acepta el consentimiento. Sin él no se inicia la cita.', true);
    }

    /**
     * Imagen de una firma (quien otorga o, con $quien = 'menor', la persona menor de edad).
     * Solo para el profesional a cargo; no se cachea y cada consulta queda auditada.
     */
    public function firma(Request $request, ConsentimientoCita $consentimiento, ?string $quien = null): Response
    {
        $reporte = $this->reporteDe($consentimiento->cita);
        $this->exigirACargo($request, $reporte);

        [$ruta, $huella] = $quien === 'menor'
            ? [$consentimiento->firma_menor_ruta, $consentimiento->firma_menor_sha256]
            : [$consentimiento->firma_ruta, $consentimiento->firma_sha256];
        abort_unless($ruta, 404);

        try {
            $png = (string) Storage::disk(ConsentimientoCita::DISCO)->get($ruta);
        } catch (Throwable $e) {
            report($e);
            abort(503, 'No se pudo obtener la firma en este momento.');
        }

        $integra = hash_equals((string) $huella, hash('sha256', $png));
        Auditoria::registrar(Auditoria::FIRMA_CONSULTADA, $reporte->id_reporte, array_filter([
            'id_consentimiento' => $consentimiento->id_consentimiento, 'firmante' => $quien, 'integra' => $integra,
        ], fn ($v) => $v !== null));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="firma.png"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Firma-Integra' => $integra ? 'si' : 'no',
        ]);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function leerFirmas(Request $request, bool $menor): array
    {
        $errores = [];
        [$png, $error] = RegistrarConsentimientoRequest::leerFirma((string) $request->input('firma'));
        if ($error) {
            $errores['firma'] = $error;
        }

        $pngMenor = null;
        if ($menor) {
            [$pngMenor, $error] = RegistrarConsentimientoRequest::leerFirma((string) $request->input('firma_estudiante'));
            if ($error) {
                $errores['firma_estudiante'] = $error;
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return [$png, $pngMenor];
    }

    private function reporteDe(?Cita $cita): Reporte
    {
        $reporte = $cita?->reporte;
        abort_unless($reporte, 404);

        return $reporte;
    }

    private function exigirCitaAbierta(Cita $cita, Reporte $reporte): void
    {
        if (! $cita->estaAbierta()) {
            throw ValidationException::withMessages(['reporte' => "La cita está \"{$cita->estado}\": ya no se registran consentimientos."])
                ->redirectTo(route('vbg.reportes.show', $reporte).'#cita-'.$cita->id_cita);
        }
    }

    private function responder(Request $request, Reporte $reporte, Cita $cita, string $mensaje, bool $exito): JsonResponse|RedirectResponse
    {
        $url = route('vbg.reportes.show', $reporte).'#cita-'.$cita->id_cita;

        if ($request->expectsJson()) {
            if ($exito) {
                session()->flash('status', $mensaje);

                return response()->json(['redirect' => $url]);
            }

            return response()->json(['message' => $mensaje], 409);
        }

        return $exito
            ? redirect()->to($url)->with('status', $mensaje)
            : back()->withInput($request->except(['firma', 'firma_estudiante']))->withErrors(['reporte' => $mensaje]);
    }
}

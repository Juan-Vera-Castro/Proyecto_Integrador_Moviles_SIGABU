<?php

namespace App\Http\Controllers\Inclusion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inclusion\RegistrarEntradaRequest;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Inclusion\AnotacionBitacora;
use App\Models\Inclusion\Bitacora;
use App\Models\Inclusion\EntradaBitacora;
use App\Models\Inclusion\Evidencia;
use App\Support\AlmacenEvidencias;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Matriz de seguimiento: las entradas no se editan ni se borran; se corrigen con anotaciones
 * que conservan autor y fecha (RN-18).
 */
class EntradaBitacoraController extends Controller
{
    public function __construct(private AlmacenEvidencias $almacen) {}

    /**
     * El número se asigna con la bitácora bloqueada, para que dos entradas simultáneas no
     * repitan ni salten un número (RN-17).
     */
    public function store(RegistrarEntradaRequest $request, Caso $caso): RedirectResponse
    {
        $volver = redirect()->to(route('inclusion.bitacora', $caso).'#matriz');
        $id = $caso->bitacora?->id_bitacora;

        if (! $id) {
            return $volver->with('error', 'Primero guarda la caracterización del caso.');
        }

        if (! $caso->estaActivo()) {
            return $volver->with('error', "El caso {$caso->codigo_expediente} está cerrado: no admite registros nuevos.");
        }

        $usuario = $request->user();
        $datos = $request->validated();
        $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();

        try {
            $numero = DB::transaction(function () use ($caso, $id, $datos, $usuario, $ahora) {
                $bitacora = Bitacora::whereKey($id)->lockForUpdate()->firstOrFail();

                if ($motivo = $this->motivoContraTipo($caso, $bitacora, $datos['tipo'])) {
                    throw ValidationException::withMessages(['tipo' => $motivo]);
                }

                $idEntrada = (string) Str::uuid();
                $numero = (int) $bitacora->entradas()->reorder()->max('numero') + 1;

                EntradaBitacora::create([
                    'id_entrada' => $idEntrada,
                    'id_bitacora' => $id,
                    'numero' => $numero,
                    'fecha_hora' => $datos['fecha_hora'],
                    'tipo' => $datos['tipo'],
                    'descripcion' => $datos['descripcion'],
                    'id_profesional' => $usuario->id_usuario,
                    'fecha_registro' => $ahora,
                ]);

                $this->almacen->vincular($caso, $datos['evidencias'] ?? [], Evidencia::DESTINO_ENTRADA, $idEntrada, $usuario);

                AuditoriaExpediente::registrar(AuditoriaExpediente::BITACORA_ENTRADA_REGISTRADA, $caso, ['id_entrada' => $idEntrada, 'numero' => $numero, 'tipo' => $datos['tipo']]);

                return $numero;
            });
        } catch (ValidationException $e) {
            throw $e->errorBag('entrada');
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No fue posible guardar la entrada. No se guardó nada; intenta de nuevo.');
        }

        return $volver->with('status', "La entrada N° {$numero} quedó registrada.");
    }

    public function anotar(Request $request, EntradaBitacora $entrada): RedirectResponse
    {
        $caso = $entrada->caso;
        Gate::authorize('inclusion.gestionar', $caso);

        $volver = redirect()->to(route('inclusion.bitacora', $caso).'#matriz');

        if ($motivo = $caso->motivoSinRegistroInclusion()) {
            return $volver->with('error', $motivo);
        }

        $datos = $request->validateWithBag('anotacion', [
            'texto' => ['required', 'string', 'max:'.AnotacionBitacora::TEXTO_MAX],
        ], [
            'texto.required' => 'Escribe la anotación.',
            'texto.max' => 'La anotación no puede superar los '.AnotacionBitacora::TEXTO_MAX.' caracteres.',
        ]);

        DB::transaction(function () use ($request, $caso, $entrada, $datos) {
            $anotacion = $entrada->anotaciones()->create([
                'texto' => $datos['texto'],
                'id_profesional' => $request->user()->id_usuario,
                'fecha_hora' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            ]);

            AuditoriaExpediente::registrar(AuditoriaExpediente::BITACORA_ANOTADA, $caso, ['id_entrada' => $entrada->id_entrada, 'id_anotacion' => $anotacion->id_anotacion]);
        });

        return $volver->with('status', "La anotación quedó registrada en la entrada N° {$entrada->numero}.");
    }

    /**
     * La primera entrada es la Atención inicial y es única (RN-16). Un consentimiento revocado
     * bloquea los registros nuevos (RN-14), pero el caso debe poder cerrarse.
     */
    private function motivoContraTipo(Caso $caso, Bitacora $bitacora, string $tipo): ?string
    {
        $hayEntradas = $bitacora->entradas()->exists();

        return match (true) {
            ! $hayEntradas && $tipo !== EntradaBitacora::ATENCION_INICIAL => 'La primera entrada debe ser la Atención inicial.',
            $hayEntradas && $tipo === EntradaBitacora::ATENCION_INICIAL => 'La Atención inicial ya está registrada.',
            $tipo === EntradaBitacora::CIERRE && $bitacora->cierrePendiente() !== null => 'Ya hay una entrada de Cierre pendiente de la constancia.',
            $tipo !== EntradaBitacora::CIERRE => $caso->motivoSinAtencion(),
            default => null,
        };
    }
}

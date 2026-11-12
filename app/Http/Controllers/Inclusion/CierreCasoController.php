<?php

namespace App\Http\Controllers\Inclusion;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarConsentimientoRequest;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Inclusion\Constancia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cierre del caso con la constancia de gestión firmada (HU-08). Exige una entrada de Cierre
 * sin constancia. Los cierres por desistimiento y remisión siguen por su flujo (RN-23).
 */
class CierreCasoController extends Controller
{
    public function store(Request $request, Caso $caso): JsonResponse
    {
        Gate::authorize('inclusion.gestionar', $caso);

        $bitacora = $caso->bitacora;
        $entrada = $bitacora?->cierrePendiente();

        $motivo = match (true) {
            ! $caso->estaActivo() => "El caso {$caso->codigo_expediente} ya no está activo.",
            $entrada === null => 'Para cerrar el caso, primero agrega una entrada de tipo Cierre en la matriz.',
            ! ConsentimientoAceptacion::almacenamientoListo() => 'El almacenamiento de firmas no está configurado. Avisa al administrador.',
            default => null,
        };

        if ($motivo) {
            return response()->json(['message' => $motivo], 409);
        }

        [$png, $error] = RegistrarConsentimientoRequest::leerFirma((string) $request->input('firma'));

        if ($error) {
            return response()->json(['message' => $error, 'errors' => ['firma' => [$error]]], 422);
        }

        $id = (string) Str::uuid();
        $ruta = "casos/{$caso->id_caso}/constancias/{$id}.png";
        $disco = Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS);

        try {
            $disco->put($ruta, $png);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo guardar la firma. No se registró nada; intenta de nuevo.'], 503);
        }

        try {
            DB::transaction(function () use ($request, $caso, $bitacora, $entrada, $id, $ruta, $png) {
                $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();
                $usuario = $request->user();

                Constancia::create([
                    'id_constancia' => $id,
                    'id_bitacora' => $bitacora->id_bitacora,
                    'id_entrada_cierre' => $entrada->id_entrada,
                    'id_profesional' => $usuario->id_usuario,
                    'nombre_profesional' => $usuario->persona?->nombre_completo ?? $usuario->codigo_unico,
                    'texto_presentado' => Constancia::TEXTO,
                    'firma_ruta' => $ruta,
                    'firma_sha256' => hash('sha256', $png),
                    'fecha_hora' => $ahora,
                ]);

                $caso->cancelarPendientes($ahora, 'Sin registro de asistencia al cerrar el caso.');
                $caso->forceFill(['estado_caso' => Caso::ESTADO_CERRADO, 'fecha_cierre' => $ahora])->save();

                AuditoriaExpediente::registrar(AuditoriaExpediente::CASO_CERRADO_CONSTANCIA, $caso, ['id_constancia' => $id]);
            });
        } catch (Throwable $e) {
            report($e);
            rescue(fn () => $disco->delete($ruta));

            return response()->json(['message' => 'No fue posible cerrar el caso. No se guardó nada; intenta de nuevo.'], 503);
        }

        $mensaje = "El caso {$caso->codigo_expediente} quedó cerrado con su constancia.";
        session()->flash('status', $mensaje);

        return response()->json(['redirect' => route('inclusion.bitacora', $caso), 'mensaje' => $mensaje]);
    }
}

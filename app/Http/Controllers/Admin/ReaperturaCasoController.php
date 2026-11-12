<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Inclusion\Reapertura;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Reapertura de casos de Inclusión cerrados con constancia (RN-22, RN-37). El administrador
 * solo ve el código, el estado y las fechas: nunca el contenido de la bitácora.
 */
class ReaperturaCasoController extends Controller
{
    public function index(Request $request): View
    {
        $codigo = strtoupper(trim((string) $request->query('codigo')));
        $caso = $codigo === '' ? null : Caso::where('codigo_expediente', $codigo)
            ->whereHas('solicitud', fn (Builder $solicitud) => $solicitud->where('tipo_servicio', 'Inclusión'))
            ->first();

        return view('admin.casos.reapertura', [
            'codigo' => $codigo,
            'caso' => $caso,
            'motivoSinReapertura' => $caso ? $this->motivoSinReapertura($caso) : null,
        ]);
    }

    public function store(Request $request, Caso $caso): RedirectResponse
    {
        Gate::authorize('inclusion.reabrir', $caso);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:'.Reapertura::MOTIVO_MAX],
        ], [
            'motivo.required' => 'Escribe el motivo de la reapertura.',
            'motivo.max' => 'El motivo no puede superar los '.Reapertura::MOTIVO_MAX.' caracteres.',
        ]);

        $volver = redirect()->route('admin.casos.reapertura', ['codigo' => $caso->codigo_expediente]);

        if ($motivo = $this->motivoSinReapertura($caso)) {
            return $volver->with('error', $motivo);
        }

        DB::transaction(function () use ($request, $caso, $datos) {
            $bitacora = $caso->bitacora;

            $reapertura = Reapertura::create([
                'id_bitacora' => $bitacora->id_bitacora,
                'id_constancia' => $bitacora->constancias->first()->id_constancia,
                'id_administrador' => $request->user()->id_usuario,
                'motivo' => $datos['motivo'],
                'fecha_hora' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            ]);

            $caso->forceFill(['estado_caso' => 'En Seguimiento', 'fecha_cierre' => null])->save();

            AuditoriaExpediente::registrar(AuditoriaExpediente::CASO_REABIERTO, $caso, ['id_reapertura' => $reapertura->id_reapertura]);
        });

        return $volver->with('status', "El caso {$caso->codigo_expediente} quedó reabierto.");
    }

    /**
     * Por qué no se puede reabrir, o null si se puede.
     */
    private function motivoSinReapertura(Caso $caso): ?string
    {
        if ($caso->estado_caso !== Caso::ESTADO_CERRADO) {
            return 'El caso no está cerrado.';
        }

        $bitacora = $caso->bitacora;
        $ultima = $bitacora?->constancias->first();

        if (! $ultima || $bitacora->reaperturas->contains('id_constancia', $ultima->id_constancia)) {
            return 'Este caso no se cerró con constancia: los cierres por desistimiento o remisión no se reabren desde aquí.';
        }

        $otroSinCerrar = Caso::where('id_caso', '<>', $caso->id_caso)
            ->where('estado_caso', '<>', Caso::ESTADO_CERRADO)
            ->whereHas('solicitud', fn (Builder $solicitud) => $solicitud
                ->where('tipo_servicio', 'Inclusión')
                ->where('id_persona', $caso->solicitud->id_persona))
            ->exists();

        return $otroSinCerrar ? 'La persona ya tiene otro caso de Inclusión sin cerrar: solo puede haber uno por servicio.' : null;
    }
}

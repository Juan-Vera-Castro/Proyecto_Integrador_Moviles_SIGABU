<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\Caso;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AnaliticaController extends Controller
{
    /**
     * Rangos de antigüedad de los casos activos, en días desde su apertura.
     */
    public const RANGOS_ANTIGUEDAD = [
        'Menos de 30 días' => [0, 29],
        '30 a 90 días' => [30, 90],
        'Más de 90 días' => [91, PHP_INT_MAX],
    ];

    /**
     * Estadística de los casos activos a cargo del profesional y de los que ya finalizó.
     */
    public function index(Request $request): View
    {
        $casos = Caso::deAreaDe($request->user())->where('id_profesional', $request->user()->id_usuario)
            ->with(['solicitud.persona', 'citas'])
            ->orderByDesc('fecha_apertura')
            ->get();

        $activos = $casos->filter(fn (Caso $caso) => $caso->estaActivo())->values();
        $finalizados = $casos->where('estado_caso', Caso::ESTADO_CERRADO)->values();
        $hoy = now(Actuacion::ZONA_HORARIA)->startOfDay();
        $diasAbierto = fn (Caso $caso) => (int) $caso->fecha_apertura?->copy()->startOfDay()->diffInDays($hoy);

        $citas = $activos->flatMap->citas;

        return view('profesional.analitica', [
            'activos' => $activos,
            'diasAbierto' => $diasAbierto,
            'resumen' => [
                'activos' => $activos->count(),
                'riesgoAlto' => $activos->where('nivel_riesgo', 'Alto')->count(),
                'finalizados' => $finalizados->count(),
                'promedioDias' => $activos->isEmpty() ? 0 : (int) round($activos->avg($diasAbierto)),
                'sesiones' => $activos->sum(fn (Caso $caso) => $caso->sesionesRealizadas()),
            ],
            'porEstado' => $this->contar($activos, Caso::ESTADOS_ACTIVOS, fn (Caso $caso) => $caso->estado_caso),
            'porRiesgo' => $this->contar($activos, [...array_reverse(Caso::NIVELES_RIESGO), 'Sin definir'], fn (Caso $caso) => $caso->nivel_riesgo ?? 'Sin definir'),
            'porServicio' => $this->contar($activos, $request->user()->serviciosQueAtiende(), fn (Caso $caso) => $caso->solicitud?->tipo_servicio),
            'porAntiguedad' => $this->contar($activos, array_keys(self::RANGOS_ANTIGUEDAD), function (Caso $caso) use ($diasAbierto) {
                $dias = $diasAbierto($caso);

                return collect(self::RANGOS_ANTIGUEDAD)->search(fn (array $rango) => $dias >= $rango[0] && $dias <= $rango[1]);
            }),
            'asistencia' => $this->contar($citas, [Actuacion::ASISTIO, Actuacion::NO_ASISTIO, Actuacion::ASISTENCIA_PENDIENTE, Actuacion::CANCELADA], fn (Actuacion $cita) => $cita->estado_asistencia),
            'finalizados' => $finalizados,
        ]);
    }

    /**
     * Cuántos elementos caen en cada categoría, en el orden dado (incluye las que quedan en cero).
     *
     * @return array<string, int>
     */
    private function contar(Collection $elementos, array $categorias, callable $categoria): array
    {
        $conteo = $elementos->countBy($categoria);

        return collect($categorias)->mapWithKeys(fn (string $nombre) => [$nombre => $conteo->get($nombre, 0)])->all();
    }
}

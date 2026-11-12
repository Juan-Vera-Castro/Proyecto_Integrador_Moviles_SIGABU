<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\TareaTerapeutica;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EstudianteController extends Controller
{
    /**
     * Filtros de la caja de tareas del inicio: días hacia adelante de la fecha límite.
     */
    public const FILTROS_TAREAS = [
        '7' => 'Próximos 7 días',
        '30' => 'Próximos 30 días',
        'todas' => 'Todas las tareas',
    ];

    /**
     * Inicio del estudiante: su próxima cita y las tareas terapéuticas pendientes (HU-013).
     */
    public function home(Request $request): View
    {
        $filtro = array_key_exists((string) $request->query('tareas'), self::FILTROS_TAREAS)
            ? (string) $request->query('tareas')
            : 'todas';

        $idsCasos = $this->idsCasos($request);
        $hoy = now(Actuacion::ZONA_HORARIA)->startOfDay();

        $tareas = TareaTerapeutica::whereIn('id_caso', $idsCasos)
            ->where('estado', TareaTerapeutica::ESTADO_PENDIENTE)
            // Las vencidas siguen pendientes, así que también entran en los filtros por días.
            ->when($filtro !== 'todas', fn ($query) => $query
                ->whereNotNull('fecha_limite')
                ->where('fecha_limite', '<=', $hoy->copy()->addDays((int) $filtro)->toDateString()))
            ->with('caso.solicitud')
            ->orderByRaw('fecha_limite IS NULL')
            ->orderBy('fecha_limite')
            ->orderByDesc('fecha_asignacion')
            ->get();

        $proximaCita = Actuacion::citas()
            ->whereIn('id_caso', $idsCasos)
            ->where('fecha_hora', '>=', now(Actuacion::ZONA_HORARIA)->toDateTimeString())
            ->with('profesional.persona', 'caso.solicitud')
            ->orderBy('fecha_hora')
            ->first();

        return view('estudiante.home', [
            'tareas' => $tareas,
            'proximaCita' => $proximaCita,
            'filtro' => $filtro,
            'filtros' => self::FILTROS_TAREAS,
            'hoy' => $hoy->toDateString(),
        ]);
    }

    /**
     * Solicitudes y casos del estudiante con sus citas y el consentimiento que aceptó.
     */
    public function misCitas(Request $request): View
    {
        $casos = Caso::whereIn('id_caso', $this->idsCasos($request))
            ->with(['solicitud.profesionalPreferido.persona', 'solicitud.facultad', 'profesional.persona', 'citas.profesional.persona', 'propuestasVigentes', 'consentimientos.version.modelo', 'aceptacionesConsentimiento.version', 'aceptacionesConsentimiento.detalleInclusion'])
            ->orderByDesc('fecha_apertura')
            ->get();

        $ahora = now(Actuacion::ZONA_HORARIA);

        $proximas = $casos
            ->flatMap(fn (Caso $caso) => $caso->citas->each(fn (Actuacion $cita) => $cita->setRelation('caso', $caso)))
            ->filter(fn (Actuacion $cita) => $cita->esProxima())
            ->sortBy('fecha_hora')
            ->values();

        return view('citas.miscitas', [
            'casos' => $casos,
            'proximas' => $proximas,
            'ahora' => $ahora,
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function idsCasos(Request $request): Collection
    {
        return $request->user()->persona?->casos()->pluck('casos.id_caso') ?? collect();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Casts\Booleano;
use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Alimentos\Entrega;
use App\Models\Alimentos\Lote;
use App\Models\Alimentos\Solicitud as SolicitudAlimentaria;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\SolicitudAtencion;
use App\Models\User;
use App\Models\Vbg\Reporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Panel del administrador con datos reales de los cuatro módulos: inicio, dashboard global y
 * consulta de la auditoría. La analítica institucional es el tablero de indicadores (HU-032).
 */
class PanelController extends Controller
{
    public function inicio(): View
    {
        $hoy = now(Actuacion::ZONA_HORARIA);

        return view('admin.dashboard', [
            'indicadores' => [
                'usuarios' => User::where('estado_activo', Booleano::valor(true, DB::getDriverName()))->count(),
                'casos' => Caso::count(),
                'modulos' => 4,
                'auditoriaHoy' => AuditoriaExpediente::where('fecha_hora', '>=', $hoy->copy()->startOfDay()->toDateTimeString())->count(),
            ],
            'saludo' => match (true) {
                $hoy->hour < 12 => 'Buenos días',
                $hoy->hour < 19 => 'Buenas tardes',
                default => 'Buenas noches',
            },
        ]);
    }

    /**
     * Estado de cada módulo en un vistazo.
     */
    public function global(): View
    {
        $hoy = now(Actuacion::ZONA_HORARIA);
        $inicioMes = $hoy->copy()->startOfMonth()->toDateTimeString();
        $casosPorServicio = fn (string $servicio) => Caso::whereHas('solicitud', fn ($q) => $q->where('tipo_servicio', $servicio));

        $servicios = collect(SolicitudAtencion::SERVICIOS)->mapWithKeys(fn (string $servicio) => [$servicio => [
            'activos' => $casosPorServicio($servicio)->activos()->count(),
            'sinAsignar' => $casosPorServicio($servicio)->sinAsignar()->count(),
            'riesgoAlto' => $casosPorServicio($servicio)->activos()->where('nivel_riesgo', 'Alto')->count(),
            'cerradosMes' => $casosPorServicio($servicio)->where('estado_caso', Caso::ESTADO_CERRADO)->where('fecha_cierre', '>=', $inicioMes)->count(),
            'citasMes' => Actuacion::citas()->where('fecha_hora', '>=', $inicioMes)
                ->whereHas('caso.solicitud', fn ($q) => $q->where('tipo_servicio', $servicio))->count(),
        ]]);

        return view('admin.global', [
            'servicios' => $servicios,
            'vbg' => [
                'recibidos' => Reporte::where('estado', Reporte::RECIBIDO)->count(),
                'enAtencion' => Reporte::where('estado', Reporte::EN_ATENCION)->count(),
                'riesgoAlto' => Reporte::where('estado', '!=', Reporte::CERRADO)->where('nivel_riesgo', 'Alto')->count(),
                'cerradosMes' => Reporte::where('estado', Reporte::CERRADO)->where('fecha_cierre', '>=', $inicioMes)->count(),
            ],
            'alimentos' => [
                'pendientes' => SolicitudAlimentaria::where('estado', SolicitudAlimentaria::PENDIENTE)->count(),
                'aprobadas' => SolicitudAlimentaria::where('estado', SolicitudAlimentaria::APROBADA)->count(),
                'mercados' => Lote::despachables()->get()->sum('cantidad_disponible'),
                'entregasMes' => Entrega::where('fecha_hora', '>=', $inicioMes)->count(),
            ],
            'usuariosPorRol' => User::with('rolReal')->get()->groupBy(fn (User $u) => $u->rolReal?->nombre_rol ?? 'Sin rol')->map->count()->sortKeys(),
        ]);
    }

    public function auditoria(Request $request): View
    {
        $accion = $request->query('accion');
        $acciones = AuditoriaExpediente::query()->distinct()->orderBy('accion')->pluck('accion');

        return view('admin.auditoria', [
            'registros' => AuditoriaExpediente::with(['usuario.persona', 'caso'])
                ->when($accion && $acciones->contains($accion), fn ($q) => $q->where('accion', $accion))
                ->orderByDesc('fecha_hora')
                ->limit(300)
                ->get(),
            'acciones' => $acciones,
            'accion' => $acciones->contains($accion) ? $accion : null,
        ]);
    }
}

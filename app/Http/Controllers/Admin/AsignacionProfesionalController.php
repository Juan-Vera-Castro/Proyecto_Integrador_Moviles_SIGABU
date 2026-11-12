<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsignacionProfesional;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\SolicitudAtencion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Qué profesional atiende cada servicio en cada facultad y jornada (revisión del prototipo,
 * 06/10/2026): con esto las solicitudes llegan directo a su profesional. Las asignaciones
 * y las facultades no se borran; se desactivan, para no perder el rastro de quién atendía qué.
 */
class AsignacionProfesionalController extends Controller
{
    public function index(): View
    {
        return view('admin.asignaciones.index', [
            'facultades' => Facultad::with(['asignaciones' => fn ($q) => $q->with('profesional.persona')->orderBy('tipo_servicio')->orderBy('jornada')])
                ->orderByDesc('activa')->orderBy('nombre')->get(),
            'profesionales' => User::profesionalesActivos()->with('persona')->get()
                ->sortBy(fn (User $profesional) => $profesional->persona?->nombre_completo)->values(),
            'servicios' => SolicitudAtencion::SERVICIOS,
            'jornadas' => SolicitudAtencion::JORNADAS,
            'sinAsignar' => Caso::sinAsignar()->count(),
        ]);
    }

    /**
     * Asigna un profesional a un servicio en una facultad y jornada. Si esa asignación ya
     * existía desactivada, la vuelve a activar.
     */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validateWithBag('asignacion', [
            'id_profesional' => ['required', 'uuid', Rule::in(User::profesionalesActivos()->pluck('id_usuario')->all())],
            'tipo_servicio' => ['required', Rule::in(SolicitudAtencion::SERVICIOS)],
            'id_facultad' => ['required', 'integer', Rule::in(Facultad::activas()->pluck('id_facultad')->all())],
            'jornadas' => ['required', 'array', 'min:1'],
            'jornadas.*' => [Rule::in(SolicitudAtencion::JORNADAS)],
        ], [
            'id_profesional.required' => 'Selecciona el profesional.',
            'id_profesional.in' => 'Selecciona un profesional activo de la lista.',
            'tipo_servicio.required' => 'Selecciona el servicio.',
            'id_facultad.required' => 'Selecciona la facultad.',
            'id_facultad.in' => 'Selecciona una facultad activa de la lista.',
            'jornadas.required' => 'Marca al menos una jornada.',
        ]);

        if (! User::find($datos['id_profesional'])->atiendeServicio($datos['tipo_servicio'])) {
            return back()->withInput()->withErrors(['tipo_servicio' => 'Ese profesional no atiende este servicio. Cámbiale el área en Administración o elige otro.'], 'asignacion');
        }

        foreach (array_unique($datos['jornadas']) as $jornada) {
            AsignacionProfesional::updateOrCreate(
                ['id_profesional' => $datos['id_profesional'], 'tipo_servicio' => $datos['tipo_servicio'], 'id_facultad' => (int) $datos['id_facultad'], 'jornada' => $jornada],
                ['activa' => true],
            );
        }

        $asignados = AsignacionProfesional::asignarPendientes();

        return back()->with('status', 'Asignación guardada. Las solicitudes nuevas de esa facultad y jornada llegarán a ese profesional.'
            .($asignados ? " Además se asignaron {$asignados} ".($asignados === 1 ? 'solicitud que estaba' : 'solicitudes que estaban').' sin profesional.' : ''));
    }

    /**
     * Activa o desactiva una asignación. Los casos que ya tiene el profesional no cambian.
     */
    public function cambiarEstado(AsignacionProfesional $asignacion): RedirectResponse
    {
        if (! $asignacion->activa && ! $asignacion->profesional?->atiendeServicio($asignacion->tipo_servicio)) {
            return back()->with('error', 'No se activó: ese profesional ya no atiende este servicio. Cámbiale el área en Administración.');
        }

        $asignacion->update(['activa' => ! $asignacion->activa]);
        $asignados = $asignacion->activa ? AsignacionProfesional::asignarPendientes() : 0;

        return back()->with('status', $asignacion->activa
            ? 'Asignación activada.'.($asignados ? " Se asignaron {$asignados} solicitudes que estaban sin profesional." : '')
            : 'Asignación desactivada. Los casos que ya tiene el profesional siguen a su cargo.');
    }

    public function storeFacultad(Request $request): RedirectResponse
    {
        $request->merge(['nombre' => trim((string) $request->input('nombre'))]);

        $datos = $request->validateWithBag('facultad', [
            'nombre' => ['required', 'string', 'max:150', Rule::unique('facultades', 'nombre')],
        ], [
            'nombre.required' => 'Escribe el nombre de la facultad.',
            'nombre.unique' => 'Esa facultad ya está registrada.',
        ]);

        Facultad::create(['nombre' => $datos['nombre'], 'activa' => true]);

        return back()->with('status', "Facultad {$datos['nombre']} agregada.");
    }

    /**
     * Una facultad inactiva no aparece en el formulario de solicitud; sus datos se conservan.
     */
    public function cambiarEstadoFacultad(Facultad $facultad): RedirectResponse
    {
        $facultad->update(['activa' => ! $facultad->activa]);

        return back()->with('status', $facultad->activa ? "{$facultad->nombre} activada." : "{$facultad->nombre} desactivada: ya no aparece al solicitar cita.");
    }
}

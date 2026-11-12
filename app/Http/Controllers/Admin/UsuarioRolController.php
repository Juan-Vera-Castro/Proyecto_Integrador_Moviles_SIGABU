<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsuarioRolController extends Controller
{
    /**
     * Panel de administración: personal con un rol distinto a "Persona"
     * (estudiantes/comunidad) y catálogo de roles para el modal.
     */
    public function index(): View
    {
        $personal = User::with(['persona', 'rolReal'])
            ->whereHas('rolReal', fn ($q) => $q->where('nombre_rol', '!=', 'Persona'))
            ->get()
            ->sortBy(fn (User $u) => $u->persona?->nombre_completo ?? $u->codigo_unico)
            ->values();

        $roles = Role::orderBy('id_rol')->get();

        return view('admin.administracion', compact('personal', 'roles'));
    }

    /**
     * Búsqueda de usuarios por nombre, documento, correo o código único
     * para el modal "Designar Rol".
     */
    public function buscar(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('q', ''));

        if (mb_strlen($termino) < 2) {
            return response()->json([]);
        }

        $like = '%'.$termino.'%';

        $usuarios = User::with(['persona', 'rolReal'])
            ->where(function ($q) use ($like) {
                $q->where('codigo_unico', 'ilike', $like)
                    ->orWhereHas('persona', function ($p) use ($like) {
                        $p->where('nombre_completo', 'ilike', $like)
                            ->orWhere('numero_documento', 'ilike', $like)
                            ->orWhere('correo_inst', 'ilike', $like);
                    });
            })
            ->limit(10)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id_usuario,
                'nombre' => $u->persona?->nombre_completo ?? $u->codigo_unico,
                'documento' => $u->persona?->numero_documento,
                'correo' => $u->persona?->correo_inst,
                'codigo' => $u->codigo_unico,
                'id_rol' => $u->id_rol,
                'rol' => $u->rolReal?->nombre_rol,
                'area' => $u->area_profesional,
            ]);

        return response()->json($usuarios);
    }

    /**
     * Asigna un rol a un usuario. El rol Profesional siempre va con su área. Ni el área ni el rol
     * se pueden cambiar si dejan por fuera casos activos del profesional, para que ningún
     * estudiante quede sin atención; sus asignaciones de otras áreas se desactivan.
     */
    public function asignarRol(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'id_rol' => ['required', 'integer', 'exists:roles,id_rol'],
        ], [
            'id_rol.required' => 'Selecciona un rol.',
            'id_rol.exists' => 'El rol seleccionado no existe.',
        ]);

        $rol = Role::findOrFail($datos['id_rol']);

        // Evita que el administrador se quite a sí mismo el acceso al panel.
        if ($usuario->is($request->user()) && $rol->nombre_rol !== 'Admin') {
            return back()->withErrors(['id_rol' => 'No puedes quitarte tu propio rol de administrador.']);
        }

        $nombre = $usuario->persona?->nombre_completo ?? $usuario->codigo_unico;

        if ($rol->nombre_rol !== 'Profesional') {
            if ($pendiente = $this->casosPorEntregar($usuario)) {
                return back()->withErrors(['id_rol' => $pendiente])->with('trasladoDe', $usuario->id_usuario);
            }

            $usuario->update(['id_rol' => $rol->id_rol, 'area_profesional' => null]);

            return back()->with('status', "Se asignó el rol {$rol->nombre_rol} a {$nombre}.");
        }

        $area = $request->validate([
            'area_profesional' => ['required', Rule::in(array_keys(User::AREAS))],
        ], [
            'area_profesional.required' => 'Selecciona el área del profesional.',
            'area_profesional.in' => 'Selecciona un área de la lista.',
        ])['area_profesional'];

        $servicios = User::serviciosDeArea($area);
        $casosPorFuera = $usuario->casosAsignados()->activos()
            ->whereHas('solicitud', fn ($solicitud) => $solicitud->whereNotIn('tipo_servicio', $servicios))
            ->count();

        if ($casosPorFuera > 0) {
            return back()->withErrors(['area_profesional' => "{$nombre} tiene {$casosPorFuera} ".($casosPorFuera === 1 ? 'caso activo' : 'casos activos').' fuera de esa área. Debe remitirlos a otro profesional antes de cambiarla.'])->with('trasladoDe', $usuario->id_usuario);
        }

        $desactivadas = DB::transaction(function () use ($usuario, $rol, $area, $servicios) {
            $usuario->update(['id_rol' => $rol->id_rol, 'area_profesional' => $area]);

            return $usuario->asignaciones()->activas()->whereNotIn('tipo_servicio', $servicios)->update(['activa' => false]);
        });

        return back()->with('status', "Se asignó el rol {$rol->nombre_rol} a {$nombre}, en el área ".User::AREAS[$area].'.'
            .($desactivadas ? " Se desactivaron {$desactivadas} ".($desactivadas === 1 ? 'asignación' : 'asignaciones').' de otra área.' : ''));
    }

    /**
     * Quita el rol asignado: el usuario vuelve al rol base "Persona",
     * que es el que recibe todo usuario al registrarse.
     */
    public function quitarRol(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->withErrors(['id_rol' => 'No puedes quitarte tu propio rol de administrador.']);
        }

        $persona = Role::where('nombre_rol', 'Persona')->firstOrFail();
        $nombre = $usuario->persona?->nombre_completo ?? $usuario->codigo_unico;

        if ((int) $usuario->id_rol === (int) $persona->id_rol) {
            return back()->withErrors(['id_rol' => "{$nombre} no tiene un rol asignado para quitar."]);
        }

        if ($pendiente = $this->casosPorEntregar($usuario)) {
            return back()->withErrors(['id_rol' => $pendiente])->with('trasladoDe', $usuario->id_usuario);
        }

        $rolAnterior = $usuario->rolReal?->nombre_rol;
        $usuario->update(['id_rol' => $persona->id_rol, 'area_profesional' => null]);

        return back()->with('status', "Se quitó el rol {$rolAnterior} a {$nombre}; ahora tiene el rol Persona.");
    }

    /**
     * Por qué un profesional no puede dejar su rol o su cuenta activa, o null si puede: primero
     * debe cerrar o remitir sus casos activos, porque después ya no podría atenderlos ni entregarlos.
     */
    private function casosPorEntregar(User $usuario, string $accion = 'cambiarle el rol'): ?string
    {
        $activos = $usuario->role === 'profesional' ? $usuario->casosAsignados()->activos()->count() : 0;

        if ($activos === 0) {
            return null;
        }

        $nombre = $usuario->persona?->nombre_completo ?? $usuario->codigo_unico;

        return "{$nombre} tiene {$activos} ".($activos === 1 ? 'caso activo' : 'casos activos').". Debe cerrarlos o remitirlos a otro profesional antes de {$accion}.";
    }

    /**
     * Activa o desactiva la cuenta de un usuario.
     */
    /**
     * HU-032: autoriza o retira el acceso de un profesional al tablero de indicadores.
     */
    public function cambiarIndicadores(User $usuario): RedirectResponse
    {
        abort_unless($usuario->role === 'profesional', 404);

        $usuario->update(['ve_indicadores' => ! $usuario->ve_indicadores]);
        $nombre = $usuario->persona?->nombre_completo ?? $usuario->codigo_unico;

        return back()->with('status', $usuario->ve_indicadores
            ? "{$nombre} ya puede ver el tablero de indicadores institucionales."
            : "{$nombre} ya no puede ver el tablero de indicadores institucionales.");
    }

    public function cambiarEstado(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->withErrors(['estado' => 'No puedes desactivar tu propia cuenta.']);
        }

        if ($usuario->estado_activo && $pendiente = $this->casosPorEntregar($usuario, 'desactivar su cuenta')) {
            return back()->withErrors(['estado' => $pendiente])->with('trasladoDe', $usuario->id_usuario);
        }

        $usuario->update(['estado_activo' => ! $usuario->estado_activo]);

        $nombre = $usuario->persona?->nombre_completo ?? $usuario->codigo_unico;
        $estado = $usuario->estado_activo ? 'activado' : 'desactivado';

        return back()->with('status', "Se ha {$estado} a {$nombre}.");
    }
}

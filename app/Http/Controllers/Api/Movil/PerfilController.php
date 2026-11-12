<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perfil del estudiante (ProfileController): nombre y correo. La contraseña se cambia con
 * Auth\PasswordController::update.
 */
class PerfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $academicos = $usuario->persona?->datosAcademicos();

        return response()->json([
            'usuario' => AuthController::usuario($usuario),
            'academicos' => $academicos ? [
                'vinculacion' => $academicos['vinculacion'],
                'facultad' => $academicos['facultad']?->nombre,
                'programa' => $academicos['programa'],
                'jornada' => $academicos['jornada'],
                'semestre' => $academicos['semestre'],
            ] : null,
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $persona = $request->user()->persona;
        abort_unless($persona, 403, 'Tu cuenta no tiene datos personales registrados.');
        $persona->nombre_completo = $validated['name'];
        $persona->correo_inst = $validated['email'];
        $persona->save();

        return response()->json([
            'mensaje' => 'Tus datos quedaron actualizados.',
            'usuario' => AuthController::usuario($request->user()->fresh()),
        ]);
    }
}

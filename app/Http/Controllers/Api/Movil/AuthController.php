<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inicio y cierre de sesión de la app móvil. Mismas reglas que el login web (LoginRequest):
 * correo institucional, cuenta activa, mismo mensaje si el correo o la contraseña fallan y
 * bloqueo tras 5 intentos. Solo entra el rol estudiante; cada inicio crea un token de Sanctum.
 */
class AuthController extends Controller
{
    private const CREDENCIALES_INVALIDAS = 'El correo o la contraseña no son correctos.';

    private const MAX_INTENTOS = 5;

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ], [
            'email.required' => 'Escribe tu correo institucional.',
            'email.email' => 'Escribe un correo válido.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        $clave = Str::transliterate(Str::lower($datos['email']).'|'.$request->ip().'|movil');

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($clave);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Intenta de nuevo en {$segundos} segundos.",
            ]);
        }

        $persona = Persona::where('correo_inst', $datos['email'])->first();
        $usuario = $persona?->usuario;

        if (! $usuario || ! $usuario->estado_activo || ! $this->contrasenaValida($usuario, $datos['password'])) {
            RateLimiter::hit($clave);

            throw ValidationException::withMessages(['email' => self::CREDENCIALES_INVALIDAS]);
        }

        RateLimiter::clear($clave);

        if ($usuario->role !== 'estudiante') {
            return response()->json(['message' => 'La app móvil es solo para estudiantes. Ingresa a SIGABU desde la web con esta cuenta.'], 403);
        }

        $token = $usuario->createToken(Str::limit($datos['dispositivo'] ?? 'App móvil', 100))->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => self::usuario($usuario),
        ]);
    }

    public function yo(Request $request): JsonResponse
    {
        return response()->json(['usuario' => self::usuario($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['mensaje' => 'Cerraste sesión.']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function usuario(User $usuario): array
    {
        $persona = $usuario->persona;

        return [
            'id' => $usuario->id_usuario,
            'nombre' => $persona?->nombre_completo,
            'correo' => $persona?->correo_inst,
            'codigo' => $usuario->codigo_unico,
            'documento' => $persona?->numero_documento,
            'tipo_documento' => $persona?->tipo_documento,
            'rol' => $usuario->role,
        ];
    }

    private function contrasenaValida(User $usuario, string $contrasena): bool
    {
        try {
            return Hash::check($contrasena, $usuario->getAuthPassword());
        } catch (\RuntimeException $e) {
            // contrasena_hash no es bcrypt (p. ej. usuario creado a mano en la BD).
            Log::warning('Hash de contraseña inválido para el usuario '.$usuario->id_usuario.': '.$e->getMessage());

            return false;
        }
    }
}

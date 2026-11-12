<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Mismo mensaje si el correo no existe o si la contraseña falla, para no revelar qué correos tienen cuenta.
     */
    private const CREDENCIALES_INVALIDAS = 'El correo o la contraseña no son correctos.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
{
    $this->ensureIsNotRateLimited();

    $persona = \App\Models\Persona::where('correo_inst', $this->email)->first();

    if (! $persona || ! $persona->usuario || ! $persona->usuario->estado_activo) {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => self::CREDENCIALES_INVALIDAS,
        ]);
    }

    try {
        $autenticado = Auth::attempt(['id_usuario' => $persona->usuario->id_usuario, 'password' => $this->password], $this->boolean('remember'));
    } catch (\RuntimeException $e) {
        // contrasena_hash no es bcrypt (p. ej. usuario creado a mano en la BD).
        // Se corrige con: php artisan usuario:password <correo>
        Log::warning('Hash de contraseña inválido para el usuario '.$persona->usuario->id_usuario.': '.$e->getMessage());
        $autenticado = false;
    }

    if (! $autenticado) {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => self::CREDENCIALES_INVALIDAS,
        ]);
    }

    RateLimiter::clear($this->throttleKey());
}

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}

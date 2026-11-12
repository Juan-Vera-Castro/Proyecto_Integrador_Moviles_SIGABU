<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'token.required' => 'El enlace para restablecer la contraseña no es válido. Solicita uno nuevo.',
            'email.required' => 'Escribe tu correo institucional.',
            'email.email' => 'Escribe un correo válido, por ejemplo usuario@uceva.edu.co.',
            'password.required' => 'Escribe la nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
        ]);

        // El usuario se identifica por su correo institucional (personas.correo_inst)
        // y la contraseña se guarda en usuarios.contrasena_hash, cuyo cast "hashed"
        // genera el bcrypt. La tabla usuarios no tiene remember_token.
        $usuario = User::activoPorCorreo($request->input('email'));

        $status = $usuario
            ? Password::reset(
                ['id_usuario' => $usuario->id_usuario] + $request->only('password', 'password_confirmation', 'token'),
                function (User $user) use ($request) {
                    $user->forceFill([
                        'contrasena_hash' => $request->password,
                    ])->save();

                    event(new PasswordReset($user));
                }
            )
            : Password::INVALID_USER;

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}

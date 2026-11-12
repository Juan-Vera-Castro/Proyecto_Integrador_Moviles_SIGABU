<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Escribe tu correo institucional.',
            'email.email' => 'Escribe un correo válido, por ejemplo usuario@uceva.edu.co.',
        ]);

        // La tabla usuarios no tiene columna email: se busca la cuenta activa por
        // personas.correo_inst y el enlace se genera para ese usuario.
        $usuario = User::activoPorCorreo($request->input('email'));

        $status = $usuario
            ? Password::sendResetLink(['id_usuario' => $usuario->id_usuario])
            : Password::INVALID_USER;

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}

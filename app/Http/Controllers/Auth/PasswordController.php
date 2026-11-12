<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ], [
            'current_password.required' => 'Escribe tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Escribe la nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
        ]);

        // La contraseña vive en usuarios.contrasena_hash; su cast "hashed" genera el bcrypt.
        $request->user()->update([
            'contrasena_hash' => $validated['password'],
        ]);

        return back()->with('status', 'password-updated');
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
        $this->user = $this->crearUsuarioConRol('Persona', ['correo_inst' => 'estudiante@uceva.edu.co']);
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertStatus(200)
            ->assertSee('¿Olvidaste tu contraseña?');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'Estudiante@uceva.edu.co'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Te enviamos a tu correo institucional el enlace para restablecer la contraseña.');

        Notification::assertSentTo($this->user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'estudiante@uceva.edu.co']);
    }

    public function test_unknown_or_inactive_accounts_do_not_receive_a_link(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nadie@uceva.edu.co'])
            ->assertSessionHasErrors(['email' => 'No encontramos una cuenta activa con ese correo institucional.']);

        $this->user->update(['estado_activo' => false]);

        $this->post('/forgot-password', ['email' => 'estudiante@uceva.edu.co'])
            ->assertSessionHasErrors(['email' => 'No encontramos una cuenta activa con ese correo institucional.']);

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'estudiante@uceva.edu.co']);

        Notification::assertSentTo($this->user, ResetPassword::class, function ($notification) {
            $this->get('/reset-password/'.$notification->token.'?email=estudiante@uceva.edu.co')
                ->assertStatus(200)
                ->assertSee('value="estudiante@uceva.edu.co"', false);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token_and_used_to_log_in(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'estudiante@uceva.edu.co']);

        Notification::assertSentTo($this->user, ResetPassword::class, function ($notification) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => 'estudiante@uceva.edu.co',
                'password' => 'clave-restablecida',
                'password_confirmation' => 'clave-restablecida',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'))
                ->assertSessionHas('status', 'Tu contraseña fue restablecida. Ya puedes iniciar sesión con la nueva contraseña.');

            return true;
        });

        $this->assertTrue(Hash::check('clave-restablecida', $this->user->refresh()->contrasena_hash));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'estudiante@uceva.edu.co']);

        $this->post('/login', ['email' => 'estudiante@uceva.edu.co', 'password' => 'clave-restablecida'])
            ->assertRedirect(route('estudiante.home'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_password_is_not_reset_with_an_invalid_token(): void
    {
        $this->from('/reset-password/token-falso')->post('/reset-password', [
            'token' => 'token-falso',
            'email' => 'estudiante@uceva.edu.co',
            'password' => 'clave-restablecida',
            'password_confirmation' => 'clave-restablecida',
        ])->assertSessionHasErrors(['email' => 'El enlace para restablecer la contraseña no es válido o ya venció. Solicita uno nuevo.']);

        $this->assertTrue(Hash::check('password', $this->user->refresh()->contrasena_hash));
    }
}

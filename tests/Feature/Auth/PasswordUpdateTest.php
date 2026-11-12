<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
        $this->user = $this->crearUsuarioConRol('Profesional', ['correo_inst' => 'profesional@uceva.edu.co']);
    }

    public function test_password_can_be_updated(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'nueva-clave-segura',
                'password_confirmation' => 'nueva-clave-segura',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'password-updated')
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('nueva-clave-segura', $this->user->refresh()->contrasena_hash));
    }

    public function test_the_new_password_works_on_login_and_the_old_one_does_not(): void
    {
        $this->actingAs($this->user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'nueva-clave-segura',
            'password_confirmation' => 'nueva-clave-segura',
        ]);

        auth()->logout();

        $this->post('/login', ['email' => 'profesional@uceva.edu.co', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'profesional@uceva.edu.co', 'password' => 'nueva-clave-segura'])
            ->assertRedirect(route('profesional.home'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'nueva-clave-segura',
                'password_confirmation' => 'nueva-clave-segura',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'La contraseña actual no es correcta.'])
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('password', $this->user->refresh()->contrasena_hash));
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        $this->actingAs($this->user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'corta',
            'password_confirmation' => 'otra',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue(Hash::check('password', $this->user->refresh()->contrasena_hash));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Las cuentas no se eliminan desde el perfil: conservan el historial clínico y la aplicación
 * no tiene permiso para borrar usuarios en la base.
 */
class PerfilTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
    }

    public function test_el_perfil_no_ofrece_eliminar_la_cuenta(): void
    {
        $usuario = $this->crearUsuarioConRol('Admin');

        $this->actingAs($usuario)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Delete Account');

        $this->actingAs($usuario)->delete('/profile', ['password' => 'password'])
            ->assertStatus(405);

        $this->assertNotNull(User::find($usuario->getKey()));
    }
}

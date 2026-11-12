<?php

namespace Tests\Feature;

use App\Models\Caso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

class SeguridadSesionTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
        $this->user = $this->crearUsuarioConRol('Profesional', ['correo_inst' => 'profesional@uceva.edu.co']);
    }

    public function test_cuenta_desactivada_pierde_la_sesion_abierta(): void
    {
        $this->actingAs($this->user)->get(route('profesional.home'))->assertOk();

        $this->user->update(['estado_activo' => false]);

        $this->actingAs($this->user)->get(route('profesional.home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cuenta_desactivada_recibe_401_en_la_api(): void
    {
        $this->user->update(['estado_activo' => false]);

        $this->actingAs($this->user)->getJson('/api/v1/casos/'.fake()->uuid().'/evolucion')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_login_no_revela_si_el_correo_existe(): void
    {
        $sinCuenta = $this->post('/login', ['email' => 'nadie@uceva.edu.co', 'password' => 'password']);
        $claveMala = $this->post('/login', ['email' => 'profesional@uceva.edu.co', 'password' => 'otra-clave']);

        $sinCuenta->assertSessionHasErrors('email');
        $claveMala->assertSessionHasErrors('email');
        $this->assertSame(session('errors')->first('email'), 'El correo o la contraseña no son correctos.');
        $this->assertGuest();
    }

    public function test_respuestas_llevan_cabeceras_de_seguridad(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_otro_profesional_solo_ve_el_resumen_del_caso(): void
    {
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Ana Gómez', 'correo_inst' => 'ana@uceva.edu.co']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Dificultad para concentrarse.']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'En Seguimiento', 'id_profesional' => $this->user->id_usuario]);
        $this->user->persona->update(['nombre_completo' => 'Carolina Restrepo']);

        // El profesional asignado ve el caso completo
        $this->actingAs($this->user)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertDontSee('Este caso no está asignado a ti.');

        // Otro profesional: datos del estudiante, a quién está asignado, inicio, cierre y estado
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('Este caso no está asignado a ti.')
            ->assertSee('Ana Gómez')
            ->assertSee('ana@uceva.edu.co')
            ->assertSee('Carolina Restrepo')
            ->assertSee($caso->fresh()->fecha_apertura->format('Y-m-d H:i'))
            ->assertSee('Sigue en proceso')
            ->assertSee('En Seguimiento')
            ->assertDontSee('Dificultad para concentrarse.')
            ->assertDontSee(route('profesional.expediente.show', $caso), false)
            ->assertDontSee(route('casos.evolucion', $caso), false);

        $caso->forceFill(['estado_caso' => 'Cerrado', 'fecha_cierre' => '2026-10-20 10:30:00'])->save();

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('casos.show', $caso))
            ->assertSee('2026-10-20 10:30')
            ->assertSee('Cerrado')
            ->assertDontSee('Sigue en proceso');
    }

    public function test_el_rol_se_actualiza_si_cambia_en_la_misma_peticion(): void
    {
        $this->assertSame('profesional', $this->user->role);

        $this->user->update(['id_rol' => Role::where('nombre_rol', 'VBG')->value('id_rol')]);

        $this->assertSame('vbg', $this->user->role);
    }
}

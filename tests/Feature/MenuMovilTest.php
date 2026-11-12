<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * En celular el menú lateral va oculto: la barra superior es el único acceso a la navegación
 * y a cerrar sesión, así que debe estar en la pantalla de inicio de cada rol.
 */
class MenuMovilTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
    }

    /**
     * DADO un usuario de cualquier rol,
     * CUANDO abre su pantalla de inicio,
     * ENTONCES tiene el botón que abre el menú y, dentro del menú, cerrar sesión.
     */
    public function test_cada_rol_tiene_el_boton_del_menu_y_cerrar_sesion(): void
    {
        $inicios = [
            'Admin' => 'admin.dashboard',
            'Profesional' => 'profesional.home',
            'Persona' => 'estudiante.home',
            'Alimentos' => 'almacen.home',
            'VBG' => 'vbg.home',
        ];

        foreach ($inicios as $rol => $ruta) {
            $this->actingAs($this->crearUsuarioConRol($rol))->get(route($ruta))
                ->assertOk()
                ->assertSee('aria-controls="menu-principal"', false)
                ->assertSee('id="menu-principal"', false)
                ->assertSee('aria-label="Cerrar sesión"', false);
        }
    }
}

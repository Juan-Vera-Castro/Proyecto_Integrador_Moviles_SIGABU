<?php

namespace Tests\Feature;

use App\Models\AsignacionProfesional;
use App\Models\Facultad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * El administrador decide qué profesional atiende cada servicio en cada facultad y jornada.
 * Nada se borra: las asignaciones y las facultades se desactivan.
 */
class AsignacionProfesionalesTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $admin;

    private User $profesional;

    private Facultad $ingenieria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
        $this->admin = $this->crearUsuarioConRol('Admin');
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Laura Gómez']);
        $this->ingenieria = Facultad::where('nombre', 'Facultad de Ingeniería')->sole();
    }

    private function asignar(array $cambios = [])
    {
        return $this->actingAs($this->admin)->from(route('admin.asignaciones.index'))->post(route('admin.asignaciones.store'), array_merge([
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_servicio' => 'Salud Mental',
            'id_facultad' => $this->ingenieria->id_facultad,
            'jornadas' => ['Diurna', 'Nocturna'],
        ], $cambios));
    }

    public function test_asigna_un_profesional_a_una_facultad_en_varias_jornadas(): void
    {
        $this->actingAs($this->admin)->get(route('admin.asignaciones.index'))
            ->assertOk()
            ->assertSee('Asignación de profesionales')
            ->assertSee('Sin profesionales asignados');

        $this->asignar()->assertSessionHasNoErrors();
        $this->asignar()->assertSessionHasNoErrors();

        $this->assertSame(['Diurna', 'Nocturna'], AsignacionProfesional::orderBy('jornada')->pluck('jornada')->all());

        $this->actingAs($this->admin)->get(route('admin.asignaciones.index'))
            ->assertSee('Laura Gómez')
            ->assertSee('Salud Mental · jornada Nocturna');
    }

    public function test_valida_la_asignacion(): void
    {
        $estudiante = $this->crearUsuarioConRol('Persona');

        $this->asignar(['id_profesional' => $estudiante->id_usuario, 'jornadas' => []])
            ->assertSessionHasErrorsIn('asignacion', ['id_profesional', 'jornadas']);
        $this->asignar(['tipo_servicio' => 'VBG', 'jornadas' => ['Sabatina']])
            ->assertSessionHasErrorsIn('asignacion', ['tipo_servicio', 'jornadas.0']);

        $this->assertSame(0, AsignacionProfesional::count());
    }

    /**
     * Desactivar no borra: la fila se conserva y se puede volver a activar.
     */
    public function test_desactivar_una_asignacion_o_una_facultad_no_borra_nada(): void
    {
        $this->asignar(['jornadas' => ['Diurna']]);
        $asignacion = AsignacionProfesional::sole();

        $this->actingAs($this->admin)->patch(route('admin.asignaciones.estado', $asignacion))->assertSessionHas('status');
        $this->assertFalse($asignacion->fresh()->activa);
        $this->assertNull(AsignacionProfesional::profesionalPara('Salud Mental', $this->ingenieria->id_facultad, 'Diurna'));

        $this->asignar(['jornadas' => ['Diurna']]);
        $this->assertTrue($asignacion->fresh()->activa);
        $this->assertSame(1, AsignacionProfesional::count());

        $this->actingAs($this->admin)->patch(route('admin.facultades.estado', $this->ingenieria));
        $this->assertFalse($this->ingenieria->fresh()->activa);
        $this->assertSame(1, Facultad::where('id_facultad', $this->ingenieria->id_facultad)->count());
    }

    public function test_agrega_una_facultad(): void
    {
        $this->actingAs($this->admin)->post(route('admin.facultades.store'), ['nombre' => ' Facultad de Artes '])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.facultades.store'), ['nombre' => 'Facultad de Artes'])->assertSessionHasErrorsIn('facultad', ['nombre']);

        $this->assertTrue(Facultad::where('nombre', 'Facultad de Artes')->sole()->activa);
    }

    public function test_solo_el_administrador_gestiona_las_asignaciones(): void
    {
        $this->actingAs($this->profesional)->get(route('admin.asignaciones.index'))->assertRedirect(route('profesional.home'));
        $this->actingAs($this->profesional)->post(route('admin.asignaciones.store'), [])->assertRedirect(route('profesional.home'));

        $this->assertSame(0, AsignacionProfesional::count());
    }
}

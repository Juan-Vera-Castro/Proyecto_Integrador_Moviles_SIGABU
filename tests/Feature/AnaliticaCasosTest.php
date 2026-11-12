<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\User;
use Database\Seeders\CasosAnaliticaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Analítica de los casos activos del profesional.
 */
class AnaliticaCasosTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['correo_inst' => 'cmunoz@uceva.edu.co']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $profesional, string $estudiante, array $datos = []): Caso
    {
        $persona = $this->crearUsuarioConRol('Persona', ['nombre_completo' => $estudiante])->persona;
        $solicitud = $persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => "Motivo de $estudiante."]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(array_merge(['estado_caso' => 'Abierto', 'id_profesional' => $profesional->id_usuario], $datos));

        return $caso->fresh();
    }

    /**
     * DADO un profesional con casos activos, uno cerrado y casos de otro profesional,
     * CUANDO abre la analítica,
     * ENTONCES la estadística cuenta solo sus activos y el cerrado aparece como finalizado.
     */
    public function test_muestra_la_estadistica_de_los_casos_activos_del_profesional(): void
    {
        $alto = $this->crearCaso($this->profesional, 'Samuel Valencia', ['nivel_riesgo' => 'Alto']);
        $this->crearCaso($this->profesional, 'Lina Zapata', ['nivel_riesgo' => 'Bajo', 'estado_caso' => 'En Seguimiento']);
        $cerrado = $this->crearCaso($this->profesional, 'Tomás Arango', ['estado_caso' => 'Cerrado']);
        $this->crearCaso($this->crearUsuarioConRol('Profesional'), 'Estudiante Ajeno');

        $respuesta = $this->actingAs($this->profesional)->get(route('profesional.analitica'))->assertOk();

        $this->assertSame(2, $respuesta->viewData('resumen')['activos']);
        $this->assertSame(1, $respuesta->viewData('resumen')['riesgoAlto']);
        $this->assertSame(1, $respuesta->viewData('resumen')['finalizados']);
        $this->assertSame(['Abierto' => 1, 'En Seguimiento' => 1], $respuesta->viewData('porEstado'));
        $this->assertSame(['Alto' => 1, 'Medio' => 0, 'Bajo' => 1, 'Sin definir' => 0], $respuesta->viewData('porRiesgo'));

        $respuesta->assertSee($alto->codigo_expediente)
            ->assertSee('Samuel Valencia')
            ->assertSee($cerrado->codigo_expediente)
            ->assertDontSee('Estudiante Ajeno');
    }

    public function test_sin_casos_activos_muestra_estado_vacio(): void
    {
        $this->actingAs($this->profesional)->get(route('profesional.analitica'))
            ->assertOk()
            ->assertSee('No tienes casos activos');
    }

    public function test_solo_el_profesional_puede_ver_la_analitica(): void
    {
        $this->actingAs($this->crearUsuarioConRol('Persona'))->get(route('profesional.analitica'))->assertRedirect();
    }

    /**
     * El seeder deja dos casos activos y uno finalizado con sus citas, y no los duplica.
     */
    public function test_el_seeder_crea_dos_casos_activos_y_uno_finalizado_sin_duplicar(): void
    {
        $this->seed(CasosAnaliticaSeeder::class);
        $this->seed(CasosAnaliticaSeeder::class);

        $casos = Caso::where('id_profesional', $this->profesional->id_usuario)->get();

        $this->assertCount(3, $casos);
        $this->assertSame(2, $casos->filter->estaActivo()->count());
        $cerrado = $casos->firstWhere('estado_caso', 'Cerrado');
        $this->assertNotNull($cerrado->fecha_cierre);

        $respuesta = $this->actingAs($this->profesional)->get(route('profesional.analitica'))->assertOk();
        $this->assertSame(2, $respuesta->viewData('resumen')['activos']);
        $this->assertSame(3, $respuesta->viewData('resumen')['sesiones']);
        $this->assertSame(1, $respuesta->viewData('resumen')['finalizados']);
    }
}

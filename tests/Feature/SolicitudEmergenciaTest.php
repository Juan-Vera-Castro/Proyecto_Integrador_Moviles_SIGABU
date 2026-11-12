<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AsignacionProfesional;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\SolicitudAtencion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Al solicitar la cita, el estudiante puede marcarla como caso de emergencia (atención urgente,
 * opcional). El psicólogo ve esos casos resaltados y primero.
 */
class SolicitudEmergenciaTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Facultad $ingenieria;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Laura Gómez']);
        $this->ingenieria = Facultad::where('nombre', 'Facultad de Ingeniería')->sole();
        AsignacionProfesional::create(['id_profesional' => $this->profesional->id_usuario, 'tipo_servicio' => 'Salud Mental', 'id_facultad' => $this->ingenieria->id_facultad, 'jornada' => 'Nocturna', 'activa' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function solicitar(string $nombre, array $extra = [])
    {
        return $this->actingAs($this->crearUsuarioConRol('Persona', ['nombre_completo' => $nombre]))
            ->post(route('citas.solicitar.store'), array_merge([
                'tipo_servicio' => 'Salud Mental',
                'id_facultad' => $this->ingenieria->id_facultad,
                'jornada' => 'Nocturna',
                'franja_horaria' => 'Noche',
            ], $extra));
    }

    public function test_el_formulario_ofrece_la_opcion_de_emergencia_sin_exigirla(): void
    {
        $this->actingAs($this->crearUsuarioConRol('Persona'))->get(route('citas.solicitar'))
            ->assertOk()
            ->assertSee('Caso de emergencia')
            ->assertSee('(opcional)')
            ->assertSee('name="es_emergencia"', false)
            ->assertDontSee('name="es_emergencia" value="1" x-model="emergencia" required', false)
            ->assertSee('123');

        // Sin marcarla, la solicitud se envía igual y no es emergencia.
        $this->solicitar('Ana Normal')->assertSessionHasNoErrors()->assertRedirect(route('citas.miscitas'));
        $this->assertFalse(SolicitudAtencion::sole()->es_emergencia);
    }

    public function test_la_solicitud_marcada_queda_como_emergencia(): void
    {
        $this->solicitar('Beto Urgente', ['es_emergencia' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $m) => str_contains($m, 'La marcaste como emergencia'));

        $this->assertTrue(SolicitudAtencion::sole()->es_emergencia);
        $this->assertTrue(Caso::sole()->esEmergencia());

        $this->solicitar('Valor raro', ['es_emergencia' => 'tal vez'])->assertSessionHasErrors('es_emergencia');
    }

    /**
     * DADO un estudiante que marcó su solicitud como emergencia,
     * CUANDO el psicólogo entra a su panel,
     * ENTONCES ve el caso resaltado como emergencia y antes que los demás.
     */
    public function test_el_psicologo_ve_las_emergencias_resaltadas_y_primero(): void
    {
        $this->solicitar('Ana Normal');
        Carbon::setTestNow(now()->addMinutes(5));
        $this->solicitar('Beto Urgente', ['es_emergencia' => '1']);
        $urgente = Caso::emergencias()->sole();

        $this->actingAs($this->profesional)->get(route('profesional.home'))
            ->assertOk()
            ->assertSee('1 caso de emergencia por atender')
            ->assertSee('Beto Urgente')
            ->assertDontSee('Ana Normal')
            ->assertSee(route('solicitudes.index').'#solicitud-'.$urgente->id_caso, false);

        // En "Solicitudes" la emergencia va primero aunque llegó después.
        $this->actingAs($this->profesional)->get(route('solicitudes.index'))
            ->assertSee('🚨')
            ->assertSeeInOrder(['Beto Urgente', 'Emergencia', 'Ana Normal']);

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))->assertSeeInOrder([$urgente->codigo_expediente, 'Emergencia']);
        $this->actingAs($this->profesional)->get(route('casos.show', $urgente))->assertSee('Emergencia');
        $this->actingAs($this->profesional)->get(route('casos.show', Caso::where('id_caso', '<>', $urgente->id_caso)->sole()))->assertDontSee('🚨');
    }

    public function test_las_emergencias_sin_profesional_tambien_aparecen(): void
    {
        AsignacionProfesional::query()->update(['activa' => false]);
        $this->solicitar('Carla Sin Asignar', ['es_emergencia' => '1']);
        $this->assertSame(Caso::ESTADO_PENDIENTE, Caso::sole()->estado_caso);

        $this->actingAs($this->profesional)->get(route('profesional.home'))
            ->assertSee('1 caso de emergencia por atender')
            ->assertSee('sin profesional');
    }

    public function test_sin_emergencias_no_aparece_el_aviso(): void
    {
        $this->solicitar('Ana Normal');

        $this->actingAs($this->profesional)->get(route('profesional.home'))->assertOk()->assertDontSee('por atender</h2>', false)->assertDontSee('caso de emergencia');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\PropuestaCita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * El profesional busca al paciente, ve cuántas citas lleva y desde cuándo está en proceso, y le
 * envía una solicitud de cita que el paciente acepta o rechaza.
 */
class BuscarPacienteTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private User $estudiante;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        // Martes 20 de octubre de 2026, 8:00 am.
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Muñoz']);
        $this->estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Sofía Ríos', 'numero_documento' => '1112223334', 'correo_inst' => 'sofia.rios@uceva.edu.co']);
        $solicitud = $this->estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->forceFill(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario, 'fecha_apertura' => '2026-09-01 10:00:00'])->save();

        // Dos citas pasadas: asistió a una y faltó a la otra.
        foreach ([['2026-09-08 09:00:00', Actuacion::ASISTIO], ['2026-09-15 09:00:00', 'No asistió']] as [$fecha, $estado]) {
            Actuacion::create(['id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'tipo_actuacion' => Actuacion::TIPO_CITA, 'fecha_hora' => $fecha])
                ->forceFill(['estado_asistencia' => $estado])->save();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function ficha()
    {
        return $this->actingAs($this->profesional)->get(route('casos.create', ['paciente' => $this->estudiante->persona->id_persona]));
    }

    public function test_busca_al_paciente_por_nombre_documento_correo_o_expediente(): void
    {
        $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Andrés Gómez']);
        $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Sofía Profesional']);

        foreach (['sofía', 'SOFÍA RÍOS', '2223', 'sofia.rios', $this->caso->codigo_expediente] as $texto) {
            $this->actingAs($this->profesional)->get(route('casos.create', ['q' => $texto]))
                ->assertOk()
                ->assertSee('Sofía Ríos')
                ->assertDontSee('Andrés Gómez')
                ->assertDontSee('Sofía Profesional');
        }

        $this->actingAs($this->profesional)->get(route('casos.create', ['q' => 'zzz']))->assertSee('No hay estudiantes registrados');
        $this->actingAs($this->profesional)->get(route('casos.create', ['q' => 'so']))->assertSee('al menos 3 caracteres')->assertDontSee('Sofía Ríos');
    }

    /**
     * La ficha muestra cuántas citas lleva y la fecha en que inició el proceso.
     */
    public function test_la_ficha_muestra_citas_e_inicio_del_proceso(): void
    {
        $html = $this->ficha()->assertOk()
            ->assertSee('Sofía Ríos')
            ->assertSee('Inicio del proceso')
            ->assertSee('2026-09-01')
            ->assertSee('de 2 agendadas')
            ->assertSee('Enviar solicitud de cita')
            ->assertDontSee('Guardar caso')
            ->getContent();

        $this->assertMatchesRegularExpression('/Citas a las que asistió<\/dt>\s*<dd[^>]*>1<\/dd>/', $html);
    }

    /**
     * DADO un paciente con caso activo,
     * CUANDO el profesional le envía una solicitud de cita y el paciente la acepta,
     * ENTONCES la cita queda agendada en la agenda del profesional.
     */
    public function test_el_paciente_acepta_la_solicitud_de_cita(): void
    {
        $this->actingAs($this->profesional)->from(route('casos.create', ['paciente' => $this->estudiante->persona->id_persona]))
            ->post(route('propuestas.store', $this->caso), ['opciones' => [['fecha' => '2026-10-22', 'hora' => '09:00']]])
            ->assertRedirect(route('casos.create', ['paciente' => $this->estudiante->persona->id_persona]))
            ->assertSessionHasNoErrors();

        $this->ficha()->assertSee('esperando que el paciente la acepte o la rechace')->assertDontSee('📨 Enviar solicitud de cita');

        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))
            ->assertSee('Solicitud de cita')
            ->assertSee('te propone tu próxima cita')
            ->assertSee('Jueves 22 de octubre · 9:00 am')
            ->assertSee('Rechazar la cita');

        $this->actingAs($this->estudiante)->post(route('propuestas.elegir', PropuestaCita::sole()))->assertSessionHas('status');

        $this->assertSame('2026-10-22 09:00', $this->caso->fresh()->proximaCita()->fecha_hora->format('Y-m-d H:i'));
        $this->ficha()->assertSee('Ya tiene su próxima cita agendada')->assertSee('2026-10-22');
    }

    public function test_el_paciente_rechaza_la_solicitud_y_el_profesional_lo_ve(): void
    {
        $this->actingAs($this->profesional)->post(route('propuestas.store', $this->caso), ['opciones' => [['fecha' => '2026-10-22', 'hora' => '09:00']]]);

        $this->actingAs($this->estudiante)->post(route('propuestas.rechazar', $this->caso))
            ->assertRedirect(route('citas.miscitas').'#caso-'.$this->caso->id_caso)
            ->assertSessionHas('status', 'Rechazaste la solicitud de cita. Tu profesional te enviará otra.');

        $this->assertSame(PropuestaCita::RECHAZADA, PropuestaCita::sole()->estado);
        $this->assertNull($this->caso->fresh()->proximaCita());
        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))->assertDontSee('Rechazar la cita');

        // El profesional ve el rechazo y puede enviar otra fecha.
        $this->ficha()->assertSee('El paciente rechazó la solicitud del Jueves 22 de octubre · 9:00 am')->assertSee('Enviar solicitud de cita');
    }

    public function test_solo_el_paciente_del_caso_puede_rechazar(): void
    {
        $this->actingAs($this->profesional)->post(route('propuestas.store', $this->caso), ['opciones' => [['fecha' => '2026-10-22', 'hora' => '09:00']]]);

        $this->actingAs($this->crearUsuarioConRol('Persona'))->post(route('propuestas.rechazar', $this->caso))->assertForbidden();
        $this->actingAs($this->profesional)->post(route('propuestas.rechazar', $this->caso))->assertRedirect(route('profesional.home'));
        $this->assertSame(PropuestaCita::PROPUESTA, PropuestaCita::sole()->estado);
    }

    public function test_el_caso_de_otro_profesional_no_muestra_detalles_ni_boton(): void
    {
        $otra = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Paula Vargas']);

        $this->actingAs($otra)->get(route('casos.create', ['paciente' => $this->estudiante->persona->id_persona]))
            ->assertOk()
            ->assertSee('con Carolina Muñoz')
            ->assertDontSee('Citas a las que asistió')
            ->assertDontSee('Enviar solicitud de cita')
            ->assertDontSee('Guardar caso');
    }

    /**
     * Sin proceso activo, el profesional abre el caso con los datos ya registrados del estudiante.
     */
    public function test_sin_caso_activo_se_abre_con_sus_datos_registrados(): void
    {
        $this->caso->update(['estado_caso' => Caso::ESTADO_CERRADO]);

        $this->ficha()
            ->assertSee('No tiene un proceso activo (tuvo 1 caso cerrado)')
            ->assertSee('name="numero_documento" value="1112223334"', false)
            ->assertDontSee('id="nombre_completo"', false)
            ->assertSee('Guardar caso');

        $this->actingAs($this->profesional)->post(route('casos.store'), [
            'nombre_completo' => 'Sofía Ríos', 'numero_documento' => '1112223334', 'correo_inst' => 'sofia.rios@uceva.edu.co',
            'tipo_servicio' => 'Salud Mental', 'motivo' => 'Retoma el proceso por ansiedad en exámenes.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->estudiante->persona->casos()->count());
        $this->ficha()->assertSee('Enviar solicitud de cita');
    }

    public function test_el_registro_manual_queda_para_quien_no_tiene_cuenta(): void
    {
        $this->actingAs($this->profesional)->get(route('casos.create'))
            ->assertOk()
            ->assertSee('id="q"', false)
            ->assertDontSee('id="nombre_completo"', false)
            ->assertSee(route('casos.create', ['servicio' => 'Salud Mental', 'nuevo' => 1]));

        $this->actingAs($this->profesional)->get(route('casos.create', ['nuevo' => 1]))->assertSee('id="nombre_completo"', false);
    }
}

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
 * El psicólogo propone varios horarios y el estudiante elige el que le sirve: ese horario se
 * vuelve la cita y los demás se descartan.
 */
class PropuestaCitaTest extends TestCase
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
        $this->estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Sofía Ríos']);
        $solicitud = $this->estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function proponer(array $opciones, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)->from(route('solicitudes.index'))
            ->post(route('propuestas.store', $this->caso), ['opciones' => $opciones]);
    }

    private function tresOpciones(): array
    {
        return [['fecha' => '2026-10-22', 'hora' => '09:00'], ['fecha' => '2026-10-22', 'hora' => '14:00'], ['fecha' => '2026-10-23', 'hora' => '10:00'], ['fecha' => '', 'hora' => '']];
    }

    /**
     * DADO un caso nuevo sin cita,
     * CUANDO el psicólogo propone tres horarios y el estudiante elige uno,
     * ENTONCES ese horario queda como cita y los otros dos se descartan.
     */
    public function test_el_estudiante_elige_uno_de_los_horarios_propuestos(): void
    {
        $this->actingAs($this->profesional)->get(route('solicitudes.index'))->assertSee('Proponer horarios: el estudiante elige el que le sirve');

        $this->proponer($this->tresOpciones())
            ->assertRedirect(route('solicitudes.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $m) => str_contains($m, 'Enviaste 3 horarios'));
        $this->assertSame(3, PropuestaCita::where('estado', 'Propuesta')->count());

        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))
            ->assertSee('Elige tu horario')
            ->assertSee('Solicitud de cita')->assertSee('Ninguno me sirve: rechazar')
            ->assertSee('Jueves 22 de octubre · 2:00 pm')
            ->assertSee('Pendiente: responde la solicitud de cita');

        $elegida = PropuestaCita::where('fecha_hora', '2026-10-22 14:00:00')->sole();
        $this->actingAs($this->estudiante)->post(route('propuestas.elegir', $elegida))
            ->assertRedirect(route('citas.miscitas').'#caso-'.$this->caso->id_caso)
            ->assertSessionHas('status', 'Tu cita quedó agendada el 2026-10-22 a las 2:00 pm.');

        $cita = Actuacion::sole();
        $this->assertSame([$this->caso->id_caso, $this->profesional->id_usuario, '2026-10-22 14:00'], [$cita->id_caso, $cita->id_profesional, $cita->fecha_hora->format('Y-m-d H:i')]);
        $this->assertSame(['Elegida', $cita->id_actuacion], [$elegida->fresh()->estado, $elegida->fresh()->id_cita]);
        $this->assertSame(2, PropuestaCita::where('estado', 'Descartada')->count());

        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))
            ->assertSee('En atención')
            ->assertSee('2026-10-22 · 2:00 pm')
            ->assertDontSee('te propone estos horarios');
    }

    public function test_valida_los_horarios_propuestos(): void
    {
        // Otro paciente ya tiene cita con la profesional el miércoles 28 a las 9:00.
        $otra = $this->crearUsuarioConRol('Persona')->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental']);
        Actuacion::create(['id_caso' => Caso::where('id_solicitud_origen', $otra->id_solicitud)->value('id_caso'), 'id_profesional' => $this->profesional->id_usuario, 'tipo_actuacion' => 'Consulta', 'fecha_hora' => '2026-10-28 09:00:00']);

        $this->proponer([['fecha' => '', 'hora' => '']])->assertSessionHasErrorsIn('propuestas', ['opciones']);
        $this->proponer([
            ['fecha' => '2026-10-24', 'hora' => '09:00'], // sábado
            ['fecha' => '2026-10-19', 'hora' => '09:00'], // ya pasó
            ['fecha' => '2026-10-28', 'hora' => '09:00'], // ocupado en su agenda
            ['fecha' => '2026-10-29', 'hora' => '07:00'], // fuera del horario de atención
        ])->assertSessionHasErrorsIn('propuestas', ['opciones.3.hora']);
        $this->proponer([
            ['fecha' => '2026-10-24', 'hora' => '09:00'],
            ['fecha' => '2026-10-19', 'hora' => '09:00'],
            ['fecha' => '2026-10-28', 'hora' => '09:00'],
            ['fecha' => '2026-10-29', 'hora' => '09:00'],
            ['fecha' => '2026-10-29', 'hora' => '09:00'],
        ])->assertSessionHasErrorsIn('propuestas', ['opciones.0', 'opciones.1', 'opciones.2', 'opciones.3', 'opciones.4']);

        $this->assertSame(0, PropuestaCita::count());
    }

    public function test_nuevas_propuestas_reemplazan_las_anteriores_y_se_pueden_retirar(): void
    {
        $this->proponer($this->tresOpciones());
        $this->proponer([['fecha' => '2026-10-26', 'hora' => '11:00']])->assertSessionHasNoErrors();

        $this->assertSame(1, PropuestaCita::where('estado', 'Propuesta')->count());
        $this->assertSame(3, PropuestaCita::where('estado', 'Descartada')->count());

        $this->actingAs($this->profesional)->post(route('propuestas.retirar', $this->caso))->assertSessionHas('status');
        $this->assertSame(0, PropuestaCita::where('estado', 'Propuesta')->count());
        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))->assertDontSee('te propone estos horarios')->assertSee('Cita pendiente');
    }

    public function test_no_se_elige_un_horario_que_se_ocupo_o_ya_no_esta_vigente(): void
    {
        $this->proponer($this->tresOpciones());
        $ocupada = PropuestaCita::where('fecha_hora', '2026-10-22 09:00:00')->sole();

        // Mientras el estudiante decidía, el profesional agendó otra cita a esa hora.
        $otro = $this->crearUsuarioConRol('Persona')->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental']);
        Actuacion::create(['id_caso' => Caso::where('id_solicitud_origen', $otro->id_solicitud)->value('id_caso'), 'id_profesional' => $this->profesional->id_usuario, 'tipo_actuacion' => 'Consulta', 'fecha_hora' => '2026-10-22 09:00:00']);

        $this->actingAs($this->estudiante)->post(route('propuestas.elegir', $ocupada))
            ->assertSessionHasErrorsIn('propuestas', ['propuesta' => 'Ese horario se ocupó mientras elegías. Elige otro de los propuestos.']);
        $this->assertSame('Propuesta', $ocupada->fresh()->estado);

        // Elige otra; después ya no puede elegir una segunda.
        $this->actingAs($this->estudiante)->post(route('propuestas.elegir', PropuestaCita::where('fecha_hora', '2026-10-23 10:00:00')->sole()))->assertSessionHasNoErrors();
        $this->actingAs($this->estudiante)->post(route('propuestas.elegir', $ocupada))->assertSessionHasErrorsIn('propuestas', ['propuesta']);
        $this->assertSame(1, $this->caso->citas()->count());
    }

    public function test_solo_el_profesional_del_caso_propone_y_solo_su_estudiante_elige(): void
    {
        $this->proponer($this->tresOpciones(), $this->crearUsuarioConRol('Profesional'))->assertForbidden();
        $this->proponer($this->tresOpciones());

        $this->actingAs($this->crearUsuarioConRol('Persona'))->post(route('propuestas.elegir', PropuestaCita::first()))->assertForbidden();
        $this->assertSame(0, Actuacion::count());
    }
}

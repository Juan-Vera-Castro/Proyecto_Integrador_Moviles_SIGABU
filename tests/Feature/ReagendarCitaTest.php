<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Cuando el paciente ya tuvo su cita y el caso sigue abierto, el profesional
 * lo selecciona y le asigna una nueva fecha y hora.
 */
class ReagendarCitaTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        // Martes 6 de octubre de 2026, 11:30 am en Colombia.
        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso = $this->crearCaso($this->profesional);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $profesional, string $estado = 'Abierto'): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona');
        $solicitud = $estudiante->persona->solicitudes()->create([
            'tipo_servicio' => 'Inclusión',
            'descripcion' => 'Acompañamiento académico.',
        ]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $profesional->id_usuario]);

        return $caso->fresh();
    }

    private function crearCita(string $fechaHora, ?Caso $caso = null, string $asistencia = Actuacion::ASISTENCIA_PENDIENTE): Actuacion
    {
        $caso ??= $this->caso;

        return Actuacion::create([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $caso->id_profesional,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => $fechaHora,
            'estado_asistencia' => $asistencia,
        ]);
    }

    private function porReagendar(): array
    {
        return Caso::porReagendarDe($this->profesional)->pluck('id_caso')->all();
    }

    /**
     * DADO un caso abierto cuya cita ya se realizó y sin otra agendada,
     * CUANDO el profesional abre su agenda,
     * ENTONCES ve al paciente en "Pacientes por reagendar" con el enlace para asignarle la nueva cita.
     */
    public function test_la_agenda_lista_al_paciente_que_ya_tuvo_su_cita(): void
    {
        $this->crearCita('2026-10-05 09:00:00', asistencia: Actuacion::ASISTIO);
        $nombre = $this->caso->solicitud->persona->nombre_completo;

        $this->actingAs($this->profesional)->get(route('citas.agenda'))
            ->assertOk()
            ->assertSee('Pacientes por reagendar')
            ->assertSee($nombre)
            ->assertSee('Última cita: 2026-10-05 a las 9:00 am (Asistió)')
            ->assertSee(route('citas.create', ['caso' => $this->caso->id_caso]), false);
    }

    public function test_tambien_cuenta_la_cita_a_la_que_no_asistio_o_sin_registro(): void
    {
        $noAsistio = $this->crearCaso($this->profesional);
        $this->crearCita('2026-10-02 10:00:00', $noAsistio, Actuacion::NO_ASISTIO);
        $this->crearCita('2026-10-06 08:00:00');

        $this->assertEqualsCanonicalizing([$this->caso->id_caso, $noAsistio->id_caso], $this->porReagendar());
    }

    public function test_no_lista_casos_con_la_siguiente_cita_ya_agendada(): void
    {
        $this->crearCita('2026-10-05 09:00:00', asistencia: Actuacion::ASISTIO);
        $this->crearCita('2026-10-08 09:00:00');

        $this->assertSame([], $this->porReagendar());
        $this->assertFalse($this->caso->fresh()->necesitaNuevaCita());
    }

    public function test_no_lista_casos_sin_citas_cerrados_ni_de_otro_profesional(): void
    {
        $cerrado = $this->crearCaso($this->profesional, 'Cerrado');
        $this->crearCita('2026-10-05 09:00:00', $cerrado, Actuacion::ASISTIO);

        $ajeno = $this->crearCaso($this->crearUsuarioConRol('Profesional'));
        $this->crearCita('2026-10-05 10:00:00', $ajeno, Actuacion::ASISTIO);

        $this->assertSame([], $this->porReagendar());

        $this->actingAs($this->profesional)->get(route('citas.agenda'))
            ->assertOk()
            ->assertDontSee('Pacientes por reagendar');
    }

    public function test_una_cita_proxima_cancelada_no_cuenta_como_siguiente_cita(): void
    {
        $this->crearCita('2026-10-05 09:00:00', asistencia: Actuacion::ASISTIO);
        $this->crearCita('2026-10-09 09:00:00', asistencia: Actuacion::CANCELADA);

        $this->assertSame([$this->caso->id_caso], $this->porReagendar());
    }

    public function test_el_caso_invita_a_asignar_la_nueva_cita(): void
    {
        $this->crearCita('2026-10-06 09:00:00', asistencia: Actuacion::ASISTIO);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('La última cita (2026-10-06 a las 9:00 am) ya se realizó y el caso sigue abierto.')
            ->assertSee('Asignar nueva cita');
    }

    public function test_el_formulario_marca_al_paciente_por_reagendar(): void
    {
        $this->crearCita('2026-10-05 09:00:00', asistencia: Actuacion::ASISTIO);

        $this->actingAs($this->profesional)->get(route('citas.create', ['caso' => $this->caso->id_caso]))
            ->assertOk()
            ->assertSee('(Abierto) · por reagendar');
    }

    /**
     * DADO un paciente con sesiones previas y otro caso anterior,
     * CUANDO lo selecciono para asignarle la nueva cita,
     * ENTONCES veo cuántas citas ha tenido, su historial clínico y su información importante.
     */
    public function test_al_seleccionar_al_paciente_muestra_su_resumen(): void
    {
        $this->crearCita('2026-09-29 09:00:00', asistencia: Actuacion::ASISTIO)->update(['descripcion' => 'Primera sesión: evaluación inicial.']);
        $this->crearCita('2026-10-05 10:00:00', asistencia: Actuacion::NO_ASISTIO)->update(['motivo_inasistencia' => 'Parcial de cálculo']);
        $this->caso->update(['nivel_riesgo' => 'Alto']);

        $persona = $this->caso->solicitud->persona;
        $anterior = $persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Caso anterior.']);
        $casoAnterior = Caso::where('id_solicitud_origen', $anterior->id_solicitud)->firstOrFail();
        $casoAnterior->update(['estado_caso' => 'Cerrado']);

        $this->actingAs($this->profesional)->get(route('citas.create', ['caso' => $this->caso->id_caso]))
            ->assertOk()
            ->assertSee('Citas realizadas')
            ->assertSee('Historial clínico del caso')
            ->assertSee('Primera sesión: evaluación inicial.')
            ->assertSee('Motivo de inasistencia: Parcial de cálculo')
            ->assertSee('Acompañamiento académico.')
            ->assertSee('Riesgo alto')
            ->assertSee('Otros casos del paciente')
            ->assertSee($casoAnterior->fresh()->codigo_expediente)
            ->assertSee('No tiene próxima cita agendada');
    }

    /**
     * DADO el paciente por reagendar,
     * CUANDO le asigno una nueva fecha y hora,
     * ENTONCES la cita queda en el caso y el paciente sale de la lista.
     */
    public function test_asignar_la_nueva_cita_lo_saca_de_la_lista(): void
    {
        $this->crearCita('2026-10-05 09:00:00', asistencia: Actuacion::ASISTIO);

        $this->actingAs($this->profesional)->post(route('citas.store'), [
            'id_caso' => $this->caso->id_caso,
            'fecha' => '2026-10-13',
            'hora' => '09:00',
        ])->assertSessionHasNoErrors()->assertRedirect(route('casos.show', $this->caso));

        $this->assertSame(2, $this->caso->citas()->count());
        $this->assertSame([], $this->porReagendar());
    }
}

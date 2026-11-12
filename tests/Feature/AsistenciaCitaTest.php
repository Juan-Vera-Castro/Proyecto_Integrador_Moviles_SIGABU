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
 * HU-012: Registrar la asistencia del estudiante a sus citas.
 */
class AsistenciaCitaTest extends TestCase
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
            'tipo_servicio' => 'Salud Mental',
            'descripcion' => 'Ansiedad y crisis académica.',
        ]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $profesional->id_usuario]);

        // RN-01: el caso ya tiene consentimiento firmado, así que se puede atender.
        $this->crearConsentimientoVigente($caso);

        return $caso->fresh();
    }

    private function crearCita(string $fechaHora, ?Caso $caso = null, string $tipo = Actuacion::TIPO_CITA): Actuacion
    {
        $caso ??= $this->caso;

        return Actuacion::create([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $caso->id_profesional,
            'tipo_actuacion' => $tipo,
            'fecha_hora' => $fechaHora,
        ]);
    }

    private function registrar(Actuacion $cita, array $datos, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->from(route('casos.show', $this->caso))
            ->patch(route('citas.asistencia', $cita), $datos);
    }

    public function test_las_citas_nuevas_quedan_pendientes_de_asistencia(): void
    {
        $this->assertSame(Actuacion::ASISTENCIA_PENDIENTE, $this->crearCita('2026-10-07 09:00:00')->fresh()->estado_asistencia);
    }

    /**
     * DADO una cita cuya hora ya llegó,
     * CUANDO marco que el estudiante asistió,
     * ENTONCES queda registrada y ya no se puede cambiar.
     */
    public function test_registra_que_el_estudiante_asistio(): void
    {
        $cita = $this->crearCita('2026-10-06 09:00:00');

        $this->registrar($cita, ['estado_asistencia' => 'Asistió', 'motivo_inasistencia' => 'se ignora'])
            ->assertRedirect(route('casos.show', $this->caso).'#citas')
            ->assertSessionHas('status', 'Asistencia registrada: Asistió a la cita del 2026-10-06 a las 9:00 am.');

        $cita->refresh();
        $this->assertSame(Actuacion::ASISTIO, $cita->estado_asistencia);
        $this->assertNull($cita->motivo_inasistencia);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('✓ Asistió')
            ->assertDontSee(route('citas.asistencia', $cita), false);
    }

    public function test_registra_la_inasistencia_con_motivo_opcional(): void
    {
        $conMotivo = $this->crearCita('2026-10-05 09:00:00');
        $sinMotivo = $this->crearCita('2026-10-06 08:00:00');

        $this->registrar($conMotivo, ['estado_asistencia' => 'No asistió', 'motivo_inasistencia' => '  Incapacidad médica  '])
            ->assertSessionHasNoErrors();
        $this->registrar($sinMotivo, ['estado_asistencia' => 'No asistió', 'motivo_inasistencia' => '   '])
            ->assertSessionHasNoErrors();

        $this->assertSame('Incapacidad médica', $conMotivo->fresh()->motivo_inasistencia);
        $this->assertSame(Actuacion::NO_ASISTIO, $sinMotivo->fresh()->estado_asistencia);
        $this->assertNull($sinMotivo->fresh()->motivo_inasistencia);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('Motivo: Incapacidad médica');
    }

    public function test_no_registra_asistencia_de_citas_futuras(): void
    {
        $futura = $this->crearCita('2026-10-06 14:00:00');

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('Cita futura: la asistencia se registra a partir de la hora de la cita.')
            ->assertDontSee(route('citas.asistencia', $futura), false);

        $this->registrar($futura, ['estado_asistencia' => 'Asistió'])
            ->assertSessionHas('error', 'La asistencia solo se puede registrar cuando llegue la hora de la cita.');

        $this->assertSame(Actuacion::ASISTENCIA_PENDIENTE, $futura->fresh()->estado_asistencia);
    }

    public function test_la_asistencia_se_registra_una_sola_vez(): void
    {
        $cita = $this->crearCita('2026-10-06 09:00:00');

        $this->registrar($cita, ['estado_asistencia' => 'Asistió']);
        $this->registrar($cita, ['estado_asistencia' => 'No asistió'])
            ->assertSessionHas('error', 'La asistencia de esta cita ya fue registrada como "Asistió".');

        $this->assertSame(Actuacion::ASISTIO, $cita->fresh()->estado_asistencia);
    }

    public function test_valida_el_estado_y_el_largo_del_motivo(): void
    {
        $cita = $this->crearCita('2026-10-06 09:00:00');

        $this->registrar($cita, ['estado_asistencia' => 'Tal vez', 'motivo_inasistencia' => str_repeat('a', 301)])
            ->assertSessionHasErrorsIn('asistencia', [
                'estado_asistencia' => 'Indica si el estudiante asistió o no a la cita.',
                'motivo_inasistencia' => 'El motivo no puede superar los 300 caracteres.',
            ]);

        $this->assertSame(Actuacion::ASISTENCIA_PENDIENTE, $cita->fresh()->estado_asistencia);
    }

    public function test_solo_el_profesional_del_caso_registra_asistencia_de_citas(): void
    {
        $cita = $this->crearCita('2026-10-06 09:00:00');
        $remision = Actuacion::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => 'Remisión',
            'fecha_hora' => '2026-10-06 10:00:00',
            'destino_remision' => 'Externa',
            'entidad_destino' => 'ICBF',
        ]);

        $this->registrar($cita, ['estado_asistencia' => 'Asistió'], $this->crearUsuarioConRol('Profesional'))->assertForbidden();
        $this->registrar($remision, ['estado_asistencia' => 'Asistió'])->assertNotFound();
        $this->registrar($cita, ['estado_asistencia' => 'Asistió'], $this->crearUsuarioConRol('Persona'))->assertRedirect(route('estudiante.home'));

        auth()->logout();
        $this->patch(route('citas.asistencia', $cita), ['estado_asistencia' => 'Asistió'])->assertRedirect(route('login'));

        $this->assertSame(0, Actuacion::where('estado_asistencia', '!=', Actuacion::ASISTENCIA_PENDIENTE)->count());
    }
}

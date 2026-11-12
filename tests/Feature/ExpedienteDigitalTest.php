<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AjusteRazonable;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\SeguimientoEvolucion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-005: Expediente digital único. El profesional consulta en una sola vista todo lo
 * registrado en el caso: datos generales, consentimientos, atenciones, documentos y seguimientos.
 */
class ExpedienteDigitalTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Restrepo']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(string $servicio = 'Salud Mental', ?User $profesional = null): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Ana Gómez']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => $servicio, 'descripcion' => 'Dificultad para concentrarse.']);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'En Seguimiento', 'id_profesional' => ($profesional ?? $this->profesional)->id_usuario, 'nivel_riesgo' => 'Medio']);

        return $caso->fresh();
    }

    /**
     * DADO un caso con consentimiento, atenciones y seguimientos,
     * CUANDO abro su expediente,
     * ENTONCES veo toda su información consolidada en una sola vista.
     */
    public function test_el_expediente_consolida_la_informacion_del_caso(): void
    {
        $caso = $this->crearCaso();
        $this->crearConsentimientoVigente($caso);

        $cita = Actuacion::create([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => '2026-10-20 10:00:00',
            'estado_asistencia' => Actuacion::ASISTIO,
        ]);
        SeguimientoEvolucion::create([
            'id_caso' => $caso->id_caso,
            'id_actuacion' => $cita->id_actuacion,
            'id_profesional' => $this->profesional->id_usuario,
            'fecha_hora' => '2026-10-20 11:00:00',
            'nivel_riesgo' => 'Medio',
            'valoracion_avance' => SeguimientoEvolucion::MEJORA,
            'nota' => 'Duerme mejor y retomó sus clases.',
        ]);

        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $caso))
            ->assertOk()
            ->assertSee('Expediente '.$caso->codigo_expediente)
            ->assertSeeInOrder(['Información general del caso', 'Ana Gómez', 'Carolina Restrepo', 'Dificultad para concentrarse.'])
            ->assertSeeInOrder(['Consentimientos informados', 'Aceptado'])
            ->assertSeeInOrder(['Historial de atenciones', '2026-10-20 10:00', Actuacion::ASISTIO])
            ->assertSeeInOrder(['Documentos', 'Consentimiento informado firmado'])
            ->assertSeeInOrder(['Seguimientos y evolución', 'Mejora', 'Duerme mejor y retomó sus clases.']);

        $this->assertDatabaseHas('auditoria_expedientes', [
            'id_caso' => $caso->id_caso,
            'id_usuario' => $this->profesional->id_usuario,
            'accion' => AuditoriaExpediente::EXPEDIENTE_CONSULTADO,
        ]);
    }

    public function test_el_expediente_de_inclusion_muestra_los_ajustes_razonables(): void
    {
        $caso = $this->crearCaso('Inclusión');
        $ajuste = $caso->ajustes()->create([
            'id_profesional' => $this->profesional->id_usuario,
            'tipo' => 'Metodológico',
            'barrera' => 'Baja audición.',
            'descripcion' => 'Material con subtítulos.',
            'fecha_inicio' => '2026-10-01',
            'fecha_registro' => '2026-10-01 08:00:00',
        ]);
        DB::table('seguimientos_ajuste')->insert([
            'id_seguimiento' => (string) \Illuminate\Support\Str::uuid(),
            'id_ajuste' => $ajuste->id_ajuste,
            'id_profesional' => $this->profesional->id_usuario,
            'fecha' => '2026-10-15',
            'cumplimiento' => 'Se cumple',
            'observaciones' => 'Los docentes aplican el ajuste.',
            'fecha_registro' => '2026-10-15 09:00:00',
        ]);

        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $caso))
            ->assertOk()
            ->assertSeeInOrder(['Ajustes razonables', 'Metodológico · '.AjusteRazonable::VIGENTE, '1 seguimiento', '2026-10-15', 'Se cumple']);
    }

    public function test_sin_registros_el_expediente_abre_vacio(): void
    {
        $caso = $this->crearCaso();

        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $caso))
            ->assertOk()
            ->assertSee('No se han registrado consentimientos informados.')
            ->assertSee('No hay atenciones registradas para este caso.')
            ->assertSee('Este expediente aún no tiene documentos.')
            ->assertSee('No se han registrado seguimientos de las sesiones.');
    }

    public function test_solo_lo_ve_el_profesional_del_caso(): void
    {
        $caso = $this->crearCaso();

        $this->get(route('profesional.expediente.show', $caso))->assertRedirect(route('login'));
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('profesional.expediente.show', $caso))->assertNotFound();
        // El middleware de rol saca a quien no es profesional
        $this->actingAs($this->crearUsuarioConRol('Persona'))->get(route('profesional.expediente.show', $caso))->assertRedirect();

        $this->assertSame(0, AuditoriaExpediente::where('accion', AuditoriaExpediente::EXPEDIENTE_CONSULTADO)->count());
    }

    public function test_el_listado_y_el_caso_enlazan_al_expediente(): void
    {
        $caso = $this->crearCaso();
        $enlace = route('profesional.expediente.show', $caso);

        $this->actingAs($this->profesional)->get(route('profesional.expedientes'))->assertOk()->assertSee($enlace, false);
        $this->actingAs($this->profesional)->get(route('casos.show', $caso))->assertOk()->assertSee($enlace, false);
    }
}

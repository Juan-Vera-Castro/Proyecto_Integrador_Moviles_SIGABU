<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\TareaTerapeutica;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * RN-01 y D-21: sin consentimiento informado firmado no se atiende al estudiante.
 * Se bloquea registrar "Asistió", asignar tareas y remitir; agendar sigue permitido.
 */
class BloqueoAtencionTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private const SIN_CONSENTIMIENTO = 'Registra el consentimiento informado firmado antes de atender al estudiante.';

    private User $profesional;

    private Caso $caso;

    private Actuacion $cita;

    protected function setUp(): void
    {
        parent::setUp();

        // Jueves 8 de octubre de 2026, 10:00 am en Colombia.
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->activarExigenciaConsentimiento();
        $this->profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso = $this->crearCaso();
        $this->cita = Actuacion::create([
            'id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA, 'fecha_hora' => '2026-10-08 09:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(array $solicitud = [], ?string $estado = 'Abierto'): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona');
        $nueva = $estudiante->persona->solicitudes()->create(array_merge(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad.'], $solicitud));
        $caso = Caso::where('id_solicitud_origen', $nueva->id_solicitud)->firstOrFail();

        if ($estado) {
            $caso->update(['estado_caso' => $estado, 'id_profesional' => $this->profesional->id_usuario]);
        }

        return $caso->fresh();
    }

    private function asistencia(string $estado)
    {
        return $this->actingAs($this->profesional)->patch(route('citas.asistencia', $this->cita), ['estado_asistencia' => $estado]);
    }

    private function tarea()
    {
        return $this->actingAs($this->profesional)->post(route('tareas.store', $this->caso), ['titulo' => 'Diario', 'descripcion' => 'Anotar emociones.']);
    }

    private function remitir(array $datos)
    {
        return $this->actingAs($this->profesional)->from(route('casos.show', $this->caso))->post(route('remisiones.store', $this->caso), array_merge(['motivo' => 'Requiere otra atención.'], $datos));
    }

    /**
     * DADO un caso de Salud Mental sin consentimiento firmado,
     * CUANDO el profesional intenta registrar "Asistió", asignar una tarea o remitir,
     * ENTONCES el sistema lo rechaza en el servidor y no guarda nada (CA-03.1, CA-03.2).
     */
    public function test_sin_consentimiento_no_se_atiende(): void
    {
        $this->asistencia('Asistió')->assertSessionHas('error', self::SIN_CONSENTIMIENTO);
        $this->assertSame('Pendiente', $this->cita->fresh()->estado_asistencia);

        $this->tarea()->assertSessionHas('error', self::SIN_CONSENTIMIENTO);
        $this->assertSame(0, TareaTerapeutica::count());

        $destino = $this->crearUsuarioConRol('Profesional');
        $this->remitir(['destino_remision' => 'Interna', 'id_profesional_destino' => $destino->id_usuario])
            ->assertSessionHasErrorsIn('remision', ['consentimiento' => self::SIN_CONSENTIMIENTO]);
        $this->remitir(['destino_remision' => 'Externa', 'entidad_destino' => 'EPS / IPS de salud'])
            ->assertSessionHasErrorsIn('remision', ['consentimiento']);

        $this->assertSame(0, Actuacion::remisiones()->count());
        $this->assertSame($this->profesional->id_usuario, $this->caso->fresh()->id_profesional);
    }

    /**
     * DADO un caso sin consentimiento,
     * CUANDO se agenda una cita, se toma una solicitud o se registra "No asistió",
     * ENTONCES se permite, porque no es atender al estudiante (CA-03.3).
     */
    public function test_sin_consentimiento_si_se_agenda_y_se_registra_no_asistio(): void
    {
        $this->asistencia('No asistió')->assertSessionHas('status');
        $this->assertSame('No asistió', $this->cita->fresh()->estado_asistencia);

        $this->actingAs($this->profesional)->post(route('citas.store'), ['id_caso' => $this->caso->id_caso, 'fecha' => '2026-10-09', 'hora' => '09:00'])
            ->assertSessionHasNoErrors();

        // Asignarse una solicitud sin profesional y agendar su primera cita tampoco es atender.
        $solicitud = $this->crearCaso(estado: null);
        $this->actingAs($this->profesional)->post(route('solicitudes.tomar', $solicitud))->assertSessionHasNoErrors();
        $this->actingAs($this->profesional)->post(route('solicitudes.agendar', $solicitud), ['fecha' => '2026-10-09', 'hora' => '10:00'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Abierto', $solicitud->fresh()->estado_caso);
        $this->assertSame(3, Actuacion::citas()->count());
    }

    public function test_con_consentimiento_firmado_se_atiende_normalmente(): void
    {
        $this->crearConsentimientoVigente($this->caso);

        $this->asistencia('Asistió')->assertSessionHas('status');
        $this->assertSame('Asistió', $this->cita->fresh()->estado_asistencia);

        $this->tarea()->assertSessionHas('status');
        $this->assertSame(1, TareaTerapeutica::count());
    }

    public function test_un_rechazo_posterior_vuelve_a_bloquear_la_atencion(): void
    {
        $aceptacion = $this->crearConsentimientoVigente($this->caso);
        ConsentimientoAceptacion::create([
            'id_caso' => $this->caso->id_caso, 'id_version' => $aceptacion->id_version, 'decision' => 'Rechazado',
            'fecha_hora' => now(Actuacion::ZONA_HORARIA), 'id_profesional' => $this->profesional->id_usuario,
            'texto_presentado' => 'Texto', 'texto_sha256' => hash('sha256', 'Texto'),
        ]);

        $this->tarea()->assertSessionHas('error', 'El estudiante no aceptó el consentimiento informado el 2026-10-08 10:00: no se puede prestar la atención.');
        $this->assertSame(0, TareaTerapeutica::count());
    }

    public function test_la_casilla_anterior_a_la_firma_no_cuenta_como_consentimiento(): void
    {
        $version = $this->crearConsentimientoVigente($this->crearCaso())->version;
        $this->caso->consentimientos()->create(['aceptado' => true, 'fecha_hora_aceptacion' => now(), 'id_version' => $version->id_version, 'version_formato' => 'SM-v1']);

        $this->asistencia('Asistió')->assertSessionHas('error', self::SIN_CONSENTIMIENTO);
        $this->assertInstanceOf(VersionConsentimiento::class, $version);
    }

    public function test_el_expediente_avisa_el_bloqueo_y_deshabilita_las_acciones(): void
    {
        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Atención bloqueada')
            ->assertSee(self::SIN_CONSENTIMIENTO)
            ->assertSee('disabled title="'.self::SIN_CONSENTIMIENTO.'"', false)
            ->assertSee('Por eso no se pueden asignar tareas.')
            ->assertSee('Por eso no se puede remitir el caso.')
            ->assertDontSee('Guardar tarea')
            ->assertDontSee('Registrar remisión')
            ->assertSee(route('citas.create', ['caso' => $this->caso->id_caso]), false);

        $this->crearConsentimientoVigente($this->caso);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertDontSee('Atención bloqueada')
            ->assertSee('Guardar tarea')
            ->assertSee('Registrar remisión');
    }

    public function test_otro_profesional_no_ve_el_aviso_de_bloqueo(): void
    {
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertDontSee('Atención bloqueada');
    }
}

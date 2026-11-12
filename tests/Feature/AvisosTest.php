<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificacionController;
use App\Models\Actuacion;
use App\Models\AsignacionProfesional;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\User;
use App\Notifications\ContenidoAviso;
use App\Notifications\NuevaSolicitudCita;
use App\Notifications\RecordatorioCita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Avisos inmediatos (nueva solicitud y emergencias), la bandeja de la campana y el formato
 * del correo.
 */
class AvisosTest extends TestCase
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

    private function solicitar(array $extra = [], ?User $estudiante = null)
    {
        return $this->actingAs($estudiante ?? $this->crearUsuarioConRol('Persona'))
            ->post(route('citas.solicitar.store'), array_merge([
                'tipo_servicio' => 'Salud Mental',
                'id_facultad' => $this->ingenieria->id_facultad,
                'jornada' => 'Nocturna',
                'franja_horaria' => 'Noche',
            ], $extra));
    }

    private function avisoPara(User $usuario, array $datos = [], bool $leido = false)
    {
        return $usuario->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NuevaSolicitudCita::class,
            'data' => array_merge(['titulo' => 'Aviso de prueba', 'mensaje' => 'Texto', 'url' => '/estudiante/mis-citas', 'prioridad' => 'normal'], $datos),
            'read_at' => $leido ? now() : null,
        ]);
    }

    /**
     * DADO un estudiante cuya facultad y jornada tienen psicólogo asignado,
     * CUANDO solicita una cita,
     * ENTONCES solo ese psicólogo recibe el aviso.
     */
    public function test_avisa_al_profesional_asignado(): void
    {
        Notification::fake();
        $otro = $this->crearUsuarioConRol('Profesional');

        $this->solicitar()->assertSessionHasNoErrors();

        Notification::assertSentTo($this->profesional, NuevaSolicitudCita::class, fn (NuevaSolicitudCita $aviso) => $aviso->caso->is(Caso::sole()));
        Notification::assertNotSentTo($otro, NuevaSolicitudCita::class);
    }

    /**
     * DADA una solicitud que queda sin profesional,
     * ENTONCES la reciben los profesionales activos del área.
     */
    public function test_sin_asignar_avisa_a_los_profesionales_del_area(): void
    {
        Notification::fake();
        AsignacionProfesional::query()->update(['activa' => false]);
        $deInclusion = $this->crearUsuarioConRol('Profesional');
        $deInclusion->forceFill(['area_profesional' => 'Inclusión'])->save();

        $this->solicitar()->assertSessionHasNoErrors();

        Notification::assertSentTo($this->profesional, NuevaSolicitudCita::class);
        Notification::assertNotSentTo($deInclusion, NuevaSolicitudCita::class);
    }

    public function test_la_emergencia_sale_como_urgente(): void
    {
        $this->solicitar(['es_emergencia' => '1'])->assertSessionHasNoErrors();

        $aviso = $this->profesional->notifications()->sole();
        $this->assertSame(ContenidoAviso::PRIORIDAD_ALTA, $aviso->data['prioridad']);
        $this->assertSame('Solicitud de emergencia', $aviso->data['titulo']);

        $correo = (new NuevaSolicitudCita(Caso::sole()))->toMail($this->profesional);
        $this->assertStringStartsWith('[URGENTE]', $correo->subject);
    }

    /**
     * Si el aviso falla, la solicitud del estudiante se registra igual.
     */
    public function test_un_error_en_el_aviso_no_impide_la_solicitud(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP caído'));

        $this->solicitar()->assertSessionHasNoErrors()->assertRedirect(route('citas.miscitas'));

        $this->assertSame(1, Caso::count());
    }

    public function test_cada_usuario_ve_solo_sus_avisos(): void
    {
        $estudiante = $this->crearUsuarioConRol('Persona');
        $this->avisoPara($estudiante, ['titulo' => 'Recordatorio de tu cita']);
        $this->avisoPara($this->profesional, ['titulo' => 'Aviso del profesional']);

        $this->actingAs($estudiante)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee('Recordatorio de tu cita')
            ->assertSee('Tienes 1 aviso sin leer.')
            ->assertDontSee('Aviso del profesional');
    }

    public function test_el_menu_muestra_el_contador_de_no_leidos(): void
    {
        $this->avisoPara($this->profesional);
        $this->avisoPara($this->profesional);
        $this->avisoPara($this->profesional, leido: true);

        $this->actingAs($this->profesional)->get(route('profesional.home'))
            ->assertOk()
            ->assertSee('Notificaciones')
            ->assertSee('aria-label="2 sin leer"', false);
    }

    /**
     * CUANDO abre un aviso,
     * ENTONCES queda leído y lo lleva a la pantalla donde se atiende.
     */
    public function test_abrir_un_aviso_lo_marca_leido_y_redirige(): void
    {
        $estudiante = $this->crearUsuarioConRol('Persona');
        $aviso = $this->avisoPara($estudiante);

        $this->actingAs($estudiante)->post(route('notificaciones.abrir', $aviso->id))
            ->assertRedirect('/estudiante/mis-citas');

        $this->assertNotNull($aviso->fresh()->read_at);
    }

    public function test_no_se_puede_abrir_el_aviso_de_otro(): void
    {
        $aviso = $this->avisoPara($this->profesional);

        $this->actingAs($this->crearUsuarioConRol('Persona'))
            ->post(route('notificaciones.abrir', $aviso->id))
            ->assertNotFound();

        $this->assertNull($aviso->fresh()->read_at);
    }

    public function test_marcar_todas_como_leidas(): void
    {
        $this->avisoPara($this->profesional);
        $this->avisoPara($this->profesional);
        $ajeno = $this->avisoPara($this->crearUsuarioConRol('Persona'));

        $this->actingAs($this->profesional)->post(route('notificaciones.leer-todas'))
            ->assertRedirect(route('notificaciones.index'));

        $this->assertSame(0, $this->profesional->unreadNotifications()->count());
        $this->assertNull($ajeno->fresh()->read_at);
    }

    public function test_solo_redirige_a_rutas_internas(): void
    {
        $bandeja = route('notificaciones.index', absolute: false);

        $this->assertSame('/profesional/agenda', NotificacionController::destinoSeguro('/profesional/agenda'));
        foreach (['https://otro-sitio.com', '//otro-sitio.com', '/\\otro-sitio.com', 'javascript:alert(1)', null, 42] as $url) {
            $this->assertSame($bandeja, NotificacionController::destinoSeguro($url));
        }
    }

    /**
     * El correo al estudiante lo envía la cuenta del sistema, pero muestra el nombre de su
     * profesional y las respuestas le llegan a él.
     */
    public function test_el_correo_al_estudiante_responde_a_su_profesional(): void
    {
        config(['mail.from.address' => 'sigabu@uceva.edu.co']);
        $estudiante = $this->crearUsuarioConRol('Persona');
        $this->solicitar(estudiante: $estudiante);
        $cita = Actuacion::create([
            'id_caso' => Caso::sole()->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => now(Actuacion::ZONA_HORARIA)->addDay()->toDateTimeString(),
        ]);

        $alEstudiante = (new RecordatorioCita($cita, 24))->toMail($estudiante);
        $this->assertSame(['sigabu@uceva.edu.co', 'Laura Gómez · SIGABU'], $alEstudiante->from);
        $this->assertSame([[$this->profesional->email, 'Laura Gómez']], $alEstudiante->replyTo);
        $this->assertStringContainsString('/estudiante/mis-citas', $alEstudiante->actionUrl);

        // Al profesional le escribe el sistema, sin "responder a".
        $alProfesional = (new RecordatorioCita($cita, 24))->toMail($this->profesional);
        $this->assertSame([], $alProfesional->from);
        $this->assertSame([], $alProfesional->replyTo);
    }

    public function test_canales_segun_la_cuenta_y_la_configuracion(): void
    {
        $aviso = new NuevaSolicitudCita(new Caso);

        $this->assertSame(['database', 'mail'], $aviso->via($this->profesional));

        config(['sigabu.notificaciones.correo' => false]);
        $this->assertSame(['database'], $aviso->via($this->profesional));

        $this->profesional->forceFill(['estado_activo' => false])->save();
        $this->assertSame([], $aviso->via($this->profesional->fresh()));
    }
}

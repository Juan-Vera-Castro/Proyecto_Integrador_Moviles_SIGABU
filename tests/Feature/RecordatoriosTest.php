<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\RecordatorioEnviado;
use App\Models\TareaTerapeutica;
use App\Models\User;
use App\Notifications\ConsentimientoPorContrafirmar;
use App\Notifications\ConsentimientoPorFirmar;
use App\Notifications\RecordatorioCita;
use App\Notifications\TareaVencida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Recordatorios automáticos (sigabu:recordatorios): citas próximas, tareas vencidas y
 * consentimientos pendientes de firma. Cada uno sale una sola vez.
 */
class RecordatoriosTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private User $estudiante;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Laura Gómez']);
        $this->estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Ana Torres']);
        $this->caso = $this->crearCaso($this->estudiante);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $estudiante, string $estado = Caso::ESTADO_ABIERTO): Caso
    {
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $this->profesional->id_usuario]);

        return $caso->fresh();
    }

    private function crearCita(Carbon $inicio, string $estado = Actuacion::ASISTENCIA_PENDIENTE, ?Caso $caso = null): Actuacion
    {
        return Actuacion::create([
            'id_caso' => ($caso ?? $this->caso)->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => $inicio->toDateTimeString(),
            'estado_asistencia' => $estado,
        ]);
    }

    private function crearTarea(string $fechaLimite, string $estado = TareaTerapeutica::ESTADO_PENDIENTE): TareaTerapeutica
    {
        return TareaTerapeutica::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'titulo' => 'Registro de emociones',
            'descripcion' => 'Anotar cada día cómo te sentiste.',
            'fecha_limite' => $fechaLimite,
            'fecha_asignacion' => now(),
            'estado' => $estado,
        ]);
    }

    private function ejecutar(): void
    {
        $this->artisan('sigabu:recordatorios')->assertSuccessful();
    }

    private function enHoras(float $horas): Carbon
    {
        return now(Actuacion::ZONA_HORARIA)->addMinutes((int) round($horas * 60));
    }

    /**
     * DADA una cita dentro de las próximas 24 horas,
     * CUANDO corre el programador (dos veces),
     * ENTONCES el estudiante y el profesional reciben un solo recordatorio de 24 h.
     */
    public function test_recuerda_la_cita_del_dia_siguiente_una_sola_vez(): void
    {
        Notification::fake();
        $cita = $this->crearCita($this->enHoras(20));

        $this->ejecutar();
        $this->ejecutar();

        foreach ([$this->estudiante, $this->profesional] as $usuario) {
            Notification::assertSentToTimes($usuario, RecordatorioCita::class, 1);
            Notification::assertSentTo($usuario, RecordatorioCita::class, fn (RecordatorioCita $aviso) => $aviso->horasAntes === 24 && $aviso->cita->is($cita));
        }

        $this->assertTrue(RecordatorioEnviado::where('tipo', 'cita_24h')->where('referencia', $cita->id_actuacion)->exists());
    }

    /**
     * DADA una cita a menos de dos horas,
     * ENTONCES solo sale el recordatorio de 2 h (el de 24 h no se manda tarde).
     */
    public function test_la_cita_proxima_recibe_solo_el_recordatorio_de_dos_horas(): void
    {
        Notification::fake();
        $this->crearCita($this->enHoras(1.5));

        $this->ejecutar();

        Notification::assertSentToTimes($this->estudiante, RecordatorioCita::class, 1);
        Notification::assertSentTo($this->estudiante, RecordatorioCita::class, fn (RecordatorioCita $aviso) => $aviso->horasAntes === 2);
    }

    /**
     * DADA una cita a 20 horas,
     * CUANDO pasa el tiempo hasta quedar a menos de 2 horas,
     * ENTONCES recibe los dos recordatorios, cada uno una vez.
     */
    public function test_la_misma_cita_recibe_los_dos_recordatorios_a_su_tiempo(): void
    {
        Notification::fake();
        $this->crearCita($this->enHoras(20));

        $this->ejecutar();
        Carbon::setTestNow(now()->addHours(19));
        $this->ejecutar();
        $this->ejecutar();

        Notification::assertSentToTimes($this->estudiante, RecordatorioCita::class, 2);
        Notification::assertSentTo($this->estudiante, RecordatorioCita::class, fn (RecordatorioCita $aviso) => $aviso->horasAntes === 2);
    }

    public function test_no_recuerda_citas_lejanas_pasadas_canceladas_ni_de_casos_cerrados(): void
    {
        Notification::fake();
        $this->crearCita($this->enHoras(30));
        $this->crearCita($this->enHoras(-1));
        $this->crearCita($this->enHoras(5), Actuacion::CANCELADA);
        $this->crearCita($this->enHoras(6), Actuacion::ASISTENCIA_PENDIENTE, $this->crearCaso($this->crearUsuarioConRol('Persona'), Caso::ESTADO_CERRADO));

        $this->ejecutar();

        Notification::assertNotSentTo([$this->estudiante, $this->profesional], RecordatorioCita::class);
    }

    public function test_las_horas_de_anticipacion_son_configurables(): void
    {
        Notification::fake();
        config(['sigabu.notificaciones.recordatorios_cita' => [48]]);
        $this->crearCita($this->enHoras(40));

        $this->ejecutar();

        Notification::assertSentTo($this->estudiante, RecordatorioCita::class, fn (RecordatorioCita $aviso) => $aviso->horasAntes === 48);
    }

    /**
     * DADA una tarea pendiente cuya fecha límite ya pasó,
     * ENTONCES el estudiante y el profesional reciben un aviso, una sola vez.
     */
    public function test_avisa_la_tarea_vencida_al_estudiante_y_al_profesional(): void
    {
        Notification::fake();
        $vencida = $this->crearTarea('2026-10-05');
        $this->crearTarea('2026-10-06');
        $this->crearTarea('2026-10-01', TareaTerapeutica::ESTADO_CUMPLIDA);
        $this->crearTarea('2026-08-01');

        $this->ejecutar();
        $this->ejecutar();

        foreach ([$this->estudiante, $this->profesional] as $usuario) {
            Notification::assertSentToTimes($usuario, TareaVencida::class, 1);
            Notification::assertSentTo($usuario, TareaVencida::class, fn (TareaVencida $aviso) => $aviso->tarea->is($vencida));
        }
    }

    /**
     * DADO un caso activo con profesional que exige consentimiento y no tiene ninguna decisión,
     * ENTONCES el estudiante recibe el aviso de firmarlo, si hoy se puede firmar.
     */
    public function test_pide_al_estudiante_firmar_el_consentimiento(): void
    {
        Notification::fake();
        $this->activarExigenciaConsentimiento();

        $this->ejecutar();
        $this->ejecutar();

        Notification::assertSentToTimes($this->estudiante, ConsentimientoPorFirmar::class, 1);
    }

    public function test_no_pide_firmar_si_no_se_puede_o_ya_hay_decision(): void
    {
        Notification::fake();

        // Sin claves del almacenamiento de firmas: el estudiante no podría firmar.
        $this->ejecutar();
        Notification::assertNotSentTo($this->estudiante, ConsentimientoPorFirmar::class);

        $this->activarExigenciaConsentimiento();
        $this->crearConsentimientoVigente($this->caso);
        $this->ejecutar();
        Notification::assertNotSentTo($this->estudiante, ConsentimientoPorFirmar::class);
    }

    /**
     * DADO un consentimiento que el estudiante firmó y el profesional no ha contrafirmado,
     * ENTONCES el profesional recibe el aviso. Los firmados en papel no lo necesitan.
     */
    public function test_pide_al_profesional_la_contrafirma(): void
    {
        Notification::fake();
        $aceptacion = $this->crearConsentimientoVigente($this->caso);

        $otroCaso = $this->crearCaso($this->crearUsuarioConRol('Persona'));
        // Las aceptaciones son inmutables: la de papel se crea así desde el principio.
        ConsentimientoAceptacion::create(array_merge(
            collect($aceptacion->getAttributes())->except('id_aceptacion')->all(),
            ['id_caso' => $otroCaso->id_caso, 'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_PAPEL],
        ));

        $this->ejecutar();
        $this->ejecutar();

        Notification::assertSentToTimes($this->profesional, ConsentimientoPorContrafirmar::class, 1);
        Notification::assertSentTo($this->profesional, ConsentimientoPorContrafirmar::class, fn ($aviso) => $aviso->aceptacion->is($aceptacion));
    }

    /**
     * Sin falsear: el aviso queda en la campana del estudiante y el correo se arma sin errores.
     */
    public function test_el_recordatorio_queda_en_la_campana(): void
    {
        $this->crearCita($this->enHoras(20));

        $this->ejecutar();

        $aviso = $this->estudiante->notifications()->sole();
        $this->assertSame('Recordatorio de tu cita', $aviso->data['titulo']);
        $this->assertSame(route('citas.miscitas', absolute: false), $aviso->data['url']);
        $this->assertStringContainsString('Laura Gómez', $aviso->data['mensaje']);
        $this->assertSame('usuario', DB::table('notifications')->where('id', $aviso->id)->value('notifiable_type'));
        $this->assertSame(1, $this->profesional->unreadNotifications()->where('data', 'like', '%Recordatorio de cita%')->count());
    }

    /**
     * Si la cita se cancela mientras el aviso espera en la cola, ya no sale.
     */
    public function test_no_envia_el_recordatorio_de_una_cita_ya_cancelada(): void
    {
        $cita = $this->crearCita($this->enHoras(20));
        $aviso = new RecordatorioCita($cita, 24);
        $cita->update(['estado_asistencia' => Actuacion::CANCELADA]);

        $this->assertFalse($aviso->shouldSend($this->estudiante, 'mail'));
    }
}

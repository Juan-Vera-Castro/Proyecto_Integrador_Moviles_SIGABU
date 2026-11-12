<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\PropuestaCita;
use App\Models\TareaTerapeutica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Último recurso: el administrador traslada los casos de un profesional que no puede entregarlos,
 * con motivo y autorización, sin ver contenido clínico y dejando rastro en la auditoría.
 */
class TrasladoCasosTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $admin;

    private User $origen;

    private User $destino;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->admin = $this->crearUsuarioConRol('Admin', ['nombre_completo' => 'Ada Admin']);
        $this->origen = $this->profesional('Salud Mental', 'Oscar Origen');
        $this->destino = $this->profesional('Salud Mental', 'Diana Destino');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function profesional(string $area, string $nombre): User
    {
        $profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => $nombre]);
        $profesional->forceFill(['area_profesional' => $area])->save();

        return $profesional;
    }

    private function caso(string $servicio = 'Salud Mental', ?User $profesional = null): Caso
    {
        $solicitud = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Ana Estudiante'])->persona->solicitudes()->create(['tipo_servicio' => $servicio]);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'Abierto', 'id_profesional' => ($profesional ?? $this->origen)->id_usuario]);

        return $caso->fresh();
    }

    private function cita(Caso $caso, string $fecha, User $profesional): Actuacion
    {
        return Actuacion::create([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => $fecha,
            'estado_asistencia' => Actuacion::ASISTENCIA_PENDIENTE,
        ]);
    }

    private function trasladar(array $destinos, array $cambios = [], ?User $origen = null)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.traslados.index', $origen ?? $this->origen))
            ->post(route('admin.traslados.store', $origen ?? $this->origen), array_merge([
                'motivo' => 'Incapacidad o licencia',
                'justificacion' => 'Incapacidad médica prolongada sin fecha de regreso.',
                'autoriza_nombre' => 'Laura Gómez',
                'autoriza_cargo' => 'Coordinadora de Bienestar',
                'confirmacion' => '1',
                'destinos' => $destinos,
            ], $cambios));
    }

    /**
     * La opción solo se ofrece cuando el sistema bloquea el cambio por casos activos.
     */
    public function test_el_enlace_aparece_solo_cuando_hay_bloqueo(): void
    {
        $this->caso();
        $url = route('admin.traslados.index', $this->origen);

        $this->actingAs($this->admin)->get(route('admin.administracion'))->assertOk()->assertDontSee($url, false);

        $this->actingAs($this->admin)->from(route('admin.administracion'))->followingRedirects()
            ->patch(route('admin.usuarios.estado', $this->origen))
            ->assertSee($url, false)
            ->assertSee('Traslado de último recurso');
    }

    public function test_solo_el_administrador_entra(): void
    {
        $this->caso();

        $this->actingAs($this->destino)->get(route('admin.traslados.index', $this->origen))->assertRedirect(route('profesional.home'));
        $this->actingAs($this->destino)->post(route('admin.traslados.store', $this->origen), [])->assertRedirect(route('profesional.home'));
    }

    public function test_sin_casos_activos_no_hay_nada_que_trasladar(): void
    {
        $this->actingAs($this->admin)->get(route('admin.traslados.index', $this->origen))
            ->assertRedirect(route('admin.administracion'));
    }

    public function test_la_pantalla_lista_destinos_del_area_y_no_muestra_contenido_clinico(): void
    {
        $caso = $this->caso();
        $caso->update(['nivel_riesgo' => 'Alto']);
        $caso->solicitud->update(['descripcion' => 'Texto clínico reservado del motivo']);
        $this->profesional('Inclusión', 'Inés Inclusión');

        $this->actingAs($this->admin)->get(route('admin.traslados.index', $this->origen))->assertOk()
            ->assertSee($caso->codigo_expediente)
            ->assertSee('Ana Estudiante')
            ->assertSee('Diana Destino')
            ->assertDontSee('Inés Inclusión')
            ->assertDontSee('Texto clínico reservado del motivo');
    }

    public function test_traslada_el_caso_y_libera_lo_pendiente(): void
    {
        $caso = $this->caso();
        $futura = $this->cita($caso, '2026-11-10 09:00:00', $this->origen);
        $pasada = $this->cita($caso, '2026-11-01 09:00:00', $this->origen);
        $tarea = TareaTerapeutica::create([
            'id_caso' => $caso->id_caso, 'id_profesional' => $this->origen->id_usuario,
            'titulo' => 'Diario', 'descripcion' => 'Registro diario.', 'fecha_asignacion' => '2026-11-01 09:00:00',
        ]);
        $propuesta = PropuestaCita::create([
            'id_caso' => $caso->id_caso, 'id_profesional' => $this->origen->id_usuario,
            'fecha_hora' => '2026-11-12 09:00:00', 'fecha_propuesta' => '2026-11-03 09:00:00',
        ]);

        $this->trasladar([$caso->id_caso => $this->destino->id_usuario])
            ->assertRedirect(route('admin.administracion'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame($this->destino->id_usuario, $caso->fresh()->id_profesional);
        $this->assertSame(Actuacion::CANCELADA, $futura->fresh()->estado_asistencia);
        $this->assertSame(Actuacion::NO_ASISTIO, $pasada->fresh()->estado_asistencia);
        $this->assertSame(TareaTerapeutica::ESTADO_CANCELADA, $tarea->fresh()->estado);
        $this->assertSame(PropuestaCita::DESCARTADA, $propuesta->fresh()->estado);

        $traslado = $caso->fresh()->remisiones->first();
        $this->assertTrue($traslado->esTrasladoAdministrativo());
        $this->assertSame($this->admin->id_usuario, $traslado->id_admin_traslado);
        $this->assertSame($this->origen->id_usuario, $traslado->id_profesional);
        $this->assertSame('Laura Gómez (Coordinadora de Bienestar)', $traslado->autorizacion_traslado);

        $auditoria = AuditoriaExpediente::where('accion', AuditoriaExpediente::CASO_TRASLADADO_ADMIN)->sole();
        $this->assertSame($this->admin->id_usuario, $auditoria->id_usuario);
        $this->assertSame($caso->id_caso, $auditoria->id_caso);
        $this->assertSame('Oscar Origen', $auditoria->detalle['origen']);
        $this->assertSame('Diana Destino', $auditoria->detalle['destino']);

        $this->actingAs($this->admin)->get(route('admin.auditoria'))->assertOk()
            ->assertSee('De Oscar Origen a Diana Destino')
            ->assertSee('Laura Gómez (Coordinadora de Bienestar)');
    }

    /**
     * El traslado es lo que destraba el cambio bloqueado, pero no lo hace por sí solo.
     */
    public function test_despues_del_traslado_se_puede_desactivar_la_cuenta(): void
    {
        $caso = $this->caso();

        $this->trasladar([$caso->id_caso => $this->destino->id_usuario]);
        $this->assertTrue($this->origen->fresh()->estado_activo);

        $this->actingAs($this->admin)->patch(route('admin.usuarios.estado', $this->origen))->assertSessionHasNoErrors();
        $this->assertFalse($this->origen->fresh()->estado_activo);
    }

    public function test_exige_motivo_justificacion_autorizacion_y_confirmacion(): void
    {
        $caso = $this->caso();
        $destinos = [$caso->id_caso => $this->destino->id_usuario];

        $this->trasladar($destinos, ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->trasladar($destinos, ['justificacion' => 'corta'])->assertSessionHasErrors('justificacion');
        $this->trasladar($destinos, ['autoriza_nombre' => ''])->assertSessionHasErrors('autoriza_nombre');
        $this->trasladar($destinos, ['autoriza_cargo' => ''])->assertSessionHasErrors('autoriza_cargo');
        $this->trasladar($destinos, ['confirmacion' => ''])->assertSessionHasErrors('confirmacion');
        $this->trasladar([$caso->id_caso => ''])->assertSessionHasErrors('destinos');

        $this->assertSame($this->origen->id_usuario, $caso->fresh()->id_profesional);
        $this->assertSame(0, AuditoriaExpediente::count());
    }

    public function test_un_caso_sin_destino_sigue_con_su_profesional(): void
    {
        $trasladado = $this->caso();
        $restante = $this->caso();

        $this->trasladar([$trasladado->id_caso => $this->destino->id_usuario, $restante->id_caso => ''])->assertSessionHasNoErrors();

        $this->assertSame($this->destino->id_usuario, $trasladado->fresh()->id_profesional);
        $this->assertSame($this->origen->id_usuario, $restante->fresh()->id_profesional);
    }

    /**
     * Se guardan todos o ninguno: un destino de otra área anula también el caso válido.
     */
    public function test_si_un_destino_no_sirve_no_se_traslada_ninguno(): void
    {
        $valido = $this->caso();
        $invalido = $this->caso();
        $otraArea = $this->profesional('Inclusión', 'Inés Inclusión');

        $this->trasladar([$valido->id_caso => $this->destino->id_usuario, $invalido->id_caso => $otraArea->id_usuario])
            ->assertSessionHasErrors('destinos');

        $this->assertSame($this->origen->id_usuario, $valido->fresh()->id_profesional);
        $this->assertSame($this->origen->id_usuario, $invalido->fresh()->id_profesional);
        $this->assertSame(0, Actuacion::where('tipo_actuacion', Actuacion::TIPO_REMISION)->count());
        $this->assertSame(0, AuditoriaExpediente::count());
    }

    public function test_no_acepta_destinos_inactivos_ni_al_mismo_profesional(): void
    {
        $caso = $this->caso();
        $this->destino->forceFill(['estado_activo' => false])->save();

        $this->trasladar([$caso->id_caso => $this->destino->id_usuario])->assertSessionHasErrors('destinos');
        $this->trasladar([$caso->id_caso => $this->origen->id_usuario])->assertSessionHasErrors('destinos');

        $this->assertSame($this->origen->id_usuario, $caso->fresh()->id_profesional);
    }

    public function test_no_traslada_un_caso_que_ya_cambio(): void
    {
        $remitido = $this->caso(profesional: $this->destino);
        $cerrado = $this->caso();
        $cerrado->update(['estado_caso' => Caso::ESTADO_CERRADO]);

        $this->trasladar([$remitido->id_caso => $this->destino->id_usuario])->assertSessionHasErrors('destinos');
        $this->trasladar([$cerrado->id_caso => $this->destino->id_usuario])->assertSessionHasErrors('destinos');

        $this->assertSame(0, AuditoriaExpediente::count());
    }

    /**
     * Quien ya dejó el rol con casos a su nombre no puede remitirlos: el traslado los recupera.
     */
    public function test_recupera_los_casos_de_quien_ya_no_es_profesional(): void
    {
        $caso = $this->caso();
        $this->origen->forceFill(['id_rol' => DB::table('roles')->where('nombre_rol', 'Alimentos')->value('id_rol'), 'area_profesional' => null])->save();

        $this->actingAs($this->admin)->get(route('admin.traslados.index', $this->origen))->assertOk();
        $this->trasladar([$caso->id_caso => $this->destino->id_usuario])->assertSessionHasNoErrors();

        $this->assertSame($this->destino->id_usuario, $caso->fresh()->id_profesional);
    }

    public function test_el_nuevo_profesional_ve_el_aviso_hasta_agendar_la_cita(): void
    {
        $caso = $this->caso();
        $this->cita($caso, '2026-11-10 09:00:00', $this->origen);
        $this->trasladar([$caso->id_caso => $this->destino->id_usuario]);

        $this->actingAs($this->destino)->get(route('profesional.home'))->assertOk()
            ->assertSee('1 caso recibido por traslado necesita cita')
            ->assertSee(route('citas.create', ['caso' => $caso->id_caso]), false);

        $this->cita($caso, '2026-11-11 09:00:00', $this->destino);

        $this->actingAs($this->destino)->get(route('profesional.home'))->assertOk()
            ->assertDontSee('caso recibido por traslado');
    }

    public function test_el_caso_lo_muestra_como_traslado_administrativo(): void
    {
        $caso = $this->caso();
        $this->trasladar([$caso->id_caso => $this->destino->id_usuario]);

        $this->actingAs($this->destino)->get(route('casos.show', $caso))->assertOk()
            ->assertSee('Recibido por traslado administrativo')
            ->assertSee('Autorizado por: Laura Gómez (Coordinadora de Bienestar)')
            ->assertDontSee('Remitido por');

        $this->actingAs($this->destino)->get(route('profesional.expediente.show', $caso))->assertOk()
            ->assertSee('Traslado administrativo');

        $this->actingAs($this->destino)->get(route('casos.evolucion', $caso))->assertOk()
            ->assertSee('Traslado administrativo');

        $this->actingAs($this->origen)->get(route('casos.show', $caso))->assertOk()
            ->assertSee('La administración trasladó este caso a')
            ->assertDontSee('Remitiste este caso');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AsignacionProfesional;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * El administrador designa al profesional en Psicología, Inclusión social o ambas: solo ve y
 * gestiona lo de su área. Violencias de Género y el Repositorio siguen siendo de todos.
 */
class AreaProfesionalTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
        $this->admin = $this->crearUsuarioConRol('Admin');
    }

    private function profesional(?string $area, string $nombre = 'Paula Vargas'): User
    {
        $profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => $nombre]);
        $profesional->forceFill(['area_profesional' => $area])->save();

        return $profesional;
    }

    private function caso(string $servicio, ?User $profesional = null, string $estado = 'Abierto', string $nombre = 'Ana Estudiante'): Caso
    {
        $solicitud = $this->crearUsuarioConRol('Persona', ['nombre_completo' => $nombre])->persona->solicitudes()->create(['tipo_servicio' => $servicio]);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();

        if ($profesional) {
            $caso->update(['estado_caso' => $estado, 'id_profesional' => $profesional->id_usuario]);
        }

        return $caso->fresh();
    }

    private function cita(Caso $caso, User $profesional): Actuacion
    {
        return Actuacion::create([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => now(Actuacion::ZONA_HORARIA)->addDay()->toDateTimeString(),
            'estado_asistencia' => Actuacion::ASISTENCIA_PENDIENTE,
        ]);
    }

    private function designar(User $usuario, string $rol, ?string $area = null)
    {
        return $this->actingAs($this->admin)->from(route('admin.administracion'))->patch(route('admin.usuarios.rol', $usuario), array_filter([
            'id_rol' => DB::table('roles')->where('nombre_rol', $rol)->value('id_rol'),
            'area_profesional' => $area,
        ]));
    }

    public function test_cada_area_entra_solo_a_su_listado_y_su_menu(): void
    {
        $psicologia = $this->profesional('Salud Mental');
        $inclusion = $this->profesional('Inclusión');
        $ambas = $this->profesional('Ambas');

        $this->actingAs($psicologia)->get(route('profesional.salud-mental'))->assertOk();
        $this->actingAs($psicologia)->get(route('profesional.inclusion'))->assertRedirect(route('profesional.home'));

        $this->actingAs($inclusion)->get(route('profesional.inclusion'))->assertOk();
        $this->actingAs($inclusion)->get(route('profesional.salud-mental'))->assertRedirect(route('profesional.home'));

        $this->actingAs($ambas)->get(route('profesional.salud-mental'))->assertOk();
        $this->actingAs($ambas)->get(route('profesional.inclusion'))->assertOk();

        $this->actingAs($inclusion)->get(route('profesional.home'))->assertOk()
            ->assertSee(route('profesional.inclusion'), false)
            ->assertDontSee(route('profesional.salud-mental'), false);
        $this->actingAs($psicologia)->get(route('profesional.home'))->assertOk()
            ->assertSee(route('profesional.salud-mental'), false)
            ->assertDontSee(route('profesional.inclusion'), false);
    }

    public function test_sin_area_no_ve_ningun_caso(): void
    {
        $sinArea = $this->profesional(null);
        $caso = $this->caso('Salud Mental', $sinArea);

        $this->actingAs($sinArea)->get(route('profesional.salud-mental'))->assertRedirect(route('profesional.home'));
        $this->actingAs($sinArea)->get(route('profesional.inclusion'))->assertRedirect(route('profesional.home'));
        $this->actingAs($sinArea)->get(route('casos.show', $caso))->assertForbidden();
    }

    /**
     * Entrar por URL directa a un caso de otra área se rechaza, también por lo que cuelga del caso.
     */
    public function test_no_entra_ni_modifica_casos_de_otra_area(): void
    {
        $psicologo = $this->profesional('Salud Mental', 'Pedro Psicólogo');
        $inclusion = $this->profesional('Inclusión');
        $caso = $this->caso('Salud Mental', $psicologo);
        $cita = $this->cita($caso, $psicologo);

        $this->actingAs($inclusion);

        $this->get(route('casos.show', $caso))->assertForbidden();
        $this->get(route('profesional.expediente.show', $caso))->assertForbidden();
        $this->get(route('casos.evolucion', $caso))->assertForbidden();
        $this->post(route('tareas.store', $caso), [])->assertForbidden();
        $this->post(route('historia-clinica.store', $caso), [])->assertForbidden();
        $this->post(route('remisiones.store', $caso), [])->assertForbidden();
        $this->patch(route('citas.asistencia', $cita), ['estado_asistencia' => Actuacion::ASISTIO])->assertForbidden();

        $this->assertSame(Actuacion::ASISTENCIA_PENDIENTE, $cita->fresh()->estado_asistencia);
    }

    public function test_el_resumen_de_otro_profesional_solo_se_ve_dentro_del_area(): void
    {
        $caso = $this->caso('Inclusión', $this->profesional('Inclusión'));

        $this->actingAs($this->profesional('Inclusión'))->get(route('casos.show', $caso))->assertOk();
        $this->actingAs($this->profesional('Ambas'))->get(route('casos.show', $caso))->assertOk();
        $this->actingAs($this->profesional('Salud Mental'))->get(route('casos.show', $caso))->assertForbidden();
    }

    /**
     * Quien cambió de área deja de ver lo que atendió antes, aunque siga a su nombre.
     */
    public function test_los_casos_cerrados_de_un_area_anterior_dejan_de_verse(): void
    {
        $profesional = $this->profesional('Inclusión');
        $anterior = $this->caso('Salud Mental', $profesional, Caso::ESTADO_CERRADO, 'Beatriz Anterior');
        $actual = $this->caso('Inclusión', $profesional, 'Abierto', 'Carla Actual');

        $this->actingAs($profesional)->get(route('profesional.expedientes'))->assertOk()
            ->assertSee('Carla Actual')
            ->assertDontSee('Beatriz Anterior');
        $this->actingAs($profesional)->getJson(route('api.casos.evolucion', $anterior))->assertNotFound();
        $this->actingAs($profesional)->getJson(route('api.casos.evolucion', $actual))->assertOk();
        $this->assertFalse($anterior->puedeConsultarEvidencia($profesional));
        $this->assertTrue($anterior->puedeConsultarEvidencia($this->admin));
    }

    public function test_las_solicitudes_sin_profesional_son_del_area(): void
    {
        $psicologia = $this->caso('Salud Mental', null, 'Abierto', 'Diana Psicología');
        $inclusion = $this->caso('Inclusión', null, 'Abierto', 'Elena Inclusión');
        $profesional = $this->profesional('Inclusión');

        $this->actingAs($profesional)->get(route('solicitudes.index'))->assertOk()
            ->assertSee('Elena Inclusión')
            ->assertDontSee('Diana Psicología');
        $this->assertSame(1, Caso::porAtenderDe($profesional));

        $this->actingAs($profesional)->post(route('solicitudes.tomar', $psicologia))->assertForbidden();
        $this->assertNull($psicologia->fresh()->id_profesional);

        $this->actingAs($profesional)->post(route('solicitudes.tomar', $inclusion));
        $this->assertSame($profesional->id_usuario, $inclusion->fresh()->id_profesional);
    }

    public function test_solo_abre_casos_de_su_area(): void
    {
        $profesional = $this->profesional('Inclusión');
        $datos = [
            'nombre_completo' => 'Fabio Nuevo',
            'numero_documento' => '1234567890',
            'correo_inst' => 'fabio.nuevo@uceva.edu.co',
            'motivo' => 'Necesita apoyo en sus evaluaciones.',
        ];

        $this->actingAs($profesional)->from(route('casos.create'))
            ->post(route('casos.store'), $datos + ['tipo_servicio' => 'Salud Mental'])
            ->assertSessionHasErrors('tipo_servicio');
        $this->assertSame(0, Caso::count());

        $this->actingAs($profesional)->post(route('casos.store'), $datos + ['tipo_servicio' => 'Inclusión'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Caso::count());

        $this->actingAs($profesional)->get(route('casos.create', ['servicio' => 'Salud Mental', 'nuevo' => 1]))->assertOk()
            ->assertViewHas('servicios', ['Inclusión'])
            ->assertViewHas('servicioInicial', 'Inclusión');
    }

    public function test_solo_remite_a_profesionales_del_area_del_caso(): void
    {
        $profesional = $this->profesional('Salud Mental');
        $otraArea = $this->profesional('Inclusión', 'Inés Inclusión');
        $misma = $this->profesional('Ambas', 'Marta Ambas');
        $caso = $this->caso('Salud Mental', $profesional);

        $this->actingAs($profesional)->get(route('casos.show', $caso))->assertOk()
            ->assertViewHas('profesionalesDestino', fn ($destinos) => $destinos->pluck('id_usuario')->all() === [$misma->id_usuario]);

        $this->actingAs($profesional)->from(route('casos.show', $caso))->post(route('remisiones.store', $caso), [
            'destino_remision' => Actuacion::DESTINO_INTERNA,
            'id_profesional_destino' => $otraArea->id_usuario,
            'motivo' => 'Requiere otra atención.',
        ])->assertSessionHasErrorsIn('remision', ['id_profesional_destino']);

        $this->assertSame($profesional->id_usuario, $caso->fresh()->id_profesional);
    }

    public function test_violencias_de_genero_y_repositorio_siguen_para_todas_las_areas(): void
    {
        foreach (['Salud Mental', 'Inclusión', 'Ambas'] as $area) {
            $profesional = $this->profesional($area);

            $this->actingAs($profesional)->get(route('profesional.vbg'))->assertOk();
            $this->actingAs($profesional)->get(route('repositorio.index'))->assertOk();
        }
    }

    public function test_el_administrador_designa_el_area_del_profesional(): void
    {
        $usuario = $this->crearUsuarioConRol('Persona');

        $this->designar($usuario, 'Profesional')->assertSessionHasErrors('area_profesional');
        $this->assertSame('estudiante', $usuario->fresh()->role);

        $this->designar($usuario, 'Profesional', 'Otra')->assertSessionHasErrors('area_profesional');

        $this->designar($usuario, 'Profesional', 'Inclusión')->assertSessionHasNoErrors();
        $this->assertSame('profesional', $usuario->fresh()->role);
        $this->assertSame('Inclusión', $usuario->fresh()->area_profesional);

        $this->designar($usuario, 'Profesional', 'Ambas')->assertSessionHasNoErrors();
        $this->assertSame('Ambas', $usuario->fresh()->area_profesional);

        $this->actingAs($this->admin)->get(route('admin.administracion'))->assertOk()
            ->assertSee('Psicología e Inclusión social');
    }

    public function test_el_area_se_borra_al_dejar_de_ser_profesional(): void
    {
        $alimentos = $this->profesional('Inclusión');
        $this->designar($alimentos, 'Alimentos')->assertSessionHasNoErrors();
        $this->assertNull($alimentos->fresh()->area_profesional);

        $persona = $this->profesional('Salud Mental');
        $this->actingAs($this->admin)->delete(route('admin.usuarios.rol.quitar', $persona))->assertSessionHasNoErrors();
        $this->assertNull($persona->fresh()->area_profesional);
    }

    public function test_no_se_quita_un_area_con_casos_activos(): void
    {
        $profesional = $this->profesional('Ambas');
        $caso = $this->caso('Salud Mental', $profesional);

        $this->designar($profesional, 'Profesional', 'Inclusión')->assertSessionHasErrors('area_profesional');
        $this->assertSame('Ambas', $profesional->fresh()->area_profesional);

        $this->designar($profesional, 'Profesional', 'Salud Mental')->assertSessionHasNoErrors();
        $this->assertSame('Salud Mental', $profesional->fresh()->area_profesional);

        $caso->update(['estado_caso' => Caso::ESTADO_CERRADO]);
        $this->designar($profesional, 'Profesional', 'Inclusión')->assertSessionHasNoErrors();
        $this->assertSame('Inclusión', $profesional->fresh()->area_profesional);
    }

    /**
     * Con casos activos no deja el rol, ni cambiándolo ni quitándolo. Cerrarlos o remitirlos
     * lo libera.
     */
    public function test_no_deja_el_rol_con_casos_activos(): void
    {
        $profesional = $this->profesional('Ambas', 'Paula Vargas');
        $colega = $this->profesional('Ambas', 'Marta Ambas');
        $cerrado = $this->caso('Salud Mental', $profesional);
        $remitido = $this->caso('Inclusión', $profesional, 'En Seguimiento');
        $quitar = fn () => $this->actingAs($this->admin)->from(route('admin.administracion'))->delete(route('admin.usuarios.rol.quitar', $profesional));

        foreach (['Alimentos', 'VBG', 'Admin'] as $rol) {
            $this->designar($profesional, $rol)->assertSessionHasErrors(['id_rol' => 'Paula Vargas tiene 2 casos activos. Debe cerrarlos o remitirlos a otro profesional antes de cambiarle el rol.']);
        }
        $quitar()->assertSessionHasErrors('id_rol');
        $this->assertSame('profesional', $profesional->fresh()->role);
        $this->assertSame('Ambas', $profesional->fresh()->area_profesional);

        $cerrado->update(['estado_caso' => Caso::ESTADO_CERRADO]);
        $this->designar($profesional, 'Alimentos')->assertSessionHasErrors(['id_rol' => 'Paula Vargas tiene 1 caso activo. Debe cerrarlos o remitirlos a otro profesional antes de cambiarle el rol.']);
        $quitar()->assertSessionHasErrors('id_rol');

        $remitido->update(['id_profesional' => $colega->id_usuario]);
        $quitar()->assertSessionHasNoErrors();
        $this->assertSame('estudiante', $profesional->fresh()->role);
    }

    public function test_no_se_desactiva_la_cuenta_con_casos_activos(): void
    {
        $profesional = $this->profesional('Salud Mental', 'Paula Vargas');
        $caso = $this->caso('Salud Mental', $profesional);
        $cambiarEstado = fn () => $this->actingAs($this->admin)->from(route('admin.administracion'))->patch(route('admin.usuarios.estado', $profesional));

        $cambiarEstado()->assertSessionHasErrors(['estado' => 'Paula Vargas tiene 1 caso activo. Debe cerrarlos o remitirlos a otro profesional antes de desactivar su cuenta.']);
        $this->assertTrue($profesional->fresh()->estado_activo);

        $caso->update(['estado_caso' => Caso::ESTADO_CERRADO]);
        $cambiarEstado()->assertSessionHasNoErrors();
        $this->assertFalse($profesional->fresh()->estado_activo);
    }

    /**
     * Reactivar nunca se bloquea: es justo lo que permite entregar casos que quedaron a su nombre.
     */
    public function test_una_cuenta_desactivada_con_casos_activos_se_puede_reactivar(): void
    {
        $profesional = $this->profesional('Salud Mental');
        $this->caso('Salud Mental', $profesional);
        $profesional->forceFill(['estado_activo' => false])->save();

        $this->actingAs($this->admin)->from(route('admin.administracion'))->patch(route('admin.usuarios.estado', $profesional))->assertSessionHasNoErrors();

        $this->assertTrue($profesional->fresh()->estado_activo);
    }

    public function test_sin_casos_activos_cambia_a_otro_rol(): void
    {
        $profesional = $this->profesional('Salud Mental');
        $this->caso('Salud Mental', $profesional, Caso::ESTADO_CERRADO);

        $this->designar($profesional, 'VBG')->assertSessionHasNoErrors();

        $this->assertSame('vbg', $profesional->fresh()->role);
    }

    /**
     * Quien ya dejó el rol con casos a su nombre no puede remitirlos: el bloqueo no lo atrapa
     * en su rol actual, pero tampoco vuelve como profesional de un área que los deje por fuera.
     */
    public function test_quien_dejo_el_rol_con_casos_activos_no_vuelve_a_otra_area(): void
    {
        $profesional = $this->profesional('Salud Mental');
        $this->caso('Salud Mental', $profesional);
        $profesional->forceFill(['id_rol' => DB::table('roles')->where('nombre_rol', 'Alimentos')->value('id_rol'), 'area_profesional' => null])->save();

        $this->designar($profesional, 'VBG')->assertSessionHasNoErrors();
        $this->designar($profesional, 'Profesional', 'Inclusión')->assertSessionHasErrors('area_profesional');
        $this->assertSame('vbg', $profesional->fresh()->role);

        $this->designar($profesional, 'Profesional', 'Salud Mental')->assertSessionHasNoErrors();
        $this->assertSame('profesional', $profesional->fresh()->role);
    }

    public function test_al_cambiar_de_area_se_desactivan_las_asignaciones_de_la_otra(): void
    {
        $profesional = $this->profesional('Ambas');
        $facultad = Facultad::create(['nombre' => 'Ingeniería', 'activa' => true]);
        $asignacion = fn (string $servicio) => AsignacionProfesional::create([
            'id_profesional' => $profesional->id_usuario, 'tipo_servicio' => $servicio, 'id_facultad' => $facultad->id_facultad, 'jornada' => 'Diurna', 'activa' => true,
        ]);
        $psicologia = $asignacion('Salud Mental');
        $inclusion = $asignacion('Inclusión');

        $this->designar($profesional, 'Profesional', 'Inclusión')->assertSessionHasNoErrors();

        $this->assertFalse($psicologia->fresh()->activa);
        $this->assertTrue($inclusion->fresh()->activa);

        $this->actingAs($this->admin)->from(route('admin.asignaciones.index'))->patch(route('admin.asignaciones.estado', $psicologia))->assertSessionHas('error');
        $this->assertFalse($psicologia->fresh()->activa);
    }

    public function test_solo_se_asigna_un_servicio_del_area_del_profesional(): void
    {
        $profesional = $this->profesional('Inclusión');
        $facultad = Facultad::create(['nombre' => 'Ingeniería', 'activa' => true]);
        $datos = ['id_profesional' => $profesional->id_usuario, 'id_facultad' => $facultad->id_facultad, 'jornadas' => ['Diurna']];

        $this->actingAs($this->admin)->from(route('admin.asignaciones.index'))
            ->post(route('admin.asignaciones.store'), $datos + ['tipo_servicio' => 'Salud Mental'])
            ->assertSessionHasErrorsIn('asignacion', ['tipo_servicio']);
        $this->assertSame(0, AsignacionProfesional::count());

        $this->actingAs($this->admin)->post(route('admin.asignaciones.store'), $datos + ['tipo_servicio' => 'Inclusión'])->assertSessionHasNoErrors();
        $this->assertSame(1, AsignacionProfesional::count());
    }
}

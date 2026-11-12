<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AsignacionProfesional;
use App\Models\Caso;
use App\Models\Consentimiento;
use App\Models\Facultad;
use App\Models\ModeloConsentimiento;
use App\Models\SolicitudAtencion;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * El estudiante solicita una cita desde su panel: servicio, facultad, jornada, franja horaria y
 * consentimiento. No elige profesional, ni escribe el motivo, ni elige fecha o modalidad
 * (revisión del prototipo con Bienestar, 06/10/2026): el caso llega directo al psicólogo
 * asignado a su facultad y jornada.
 */
class SolicitudCitaEstudianteTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $estudiante;

    private User $profesional;

    private Facultad $ingenieria;

    protected function setUp(): void
    {
        parent::setUp();

        // Martes 6 de octubre de 2026, 11:30 a. m.
        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->estudiante = $this->crearUsuarioConRol('Persona');
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Laura Gómez']);
        $this->ingenieria = Facultad::where('nombre', 'Facultad de Ingeniería')->sole();
        $this->asignar($this->profesional, $this->ingenieria, 'Nocturna');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function asignar(User $profesional, Facultad $facultad, string $jornada, string $servicio = 'Salud Mental', bool $activa = true): void
    {
        AsignacionProfesional::create(['id_profesional' => $profesional->id_usuario, 'tipo_servicio' => $servicio, 'id_facultad' => $facultad->id_facultad, 'jornada' => $jornada, 'activa' => $activa]);
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'tipo_servicio' => 'Salud Mental',
            'id_facultad' => $this->ingenieria->id_facultad,
            'jornada' => 'Nocturna',
            'franja_horaria' => 'Noche',
        ], $cambios);
    }

    private function solicitar(array $datos, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->estudiante)
            ->from(route('citas.solicitar'))
            ->post(route('citas.solicitar.store'), $datos);
    }

    private function publicarConsentimiento(string $servicio = 'Salud Mental'): VersionConsentimiento
    {
        $modelo = ModeloConsentimiento::create(['codigo' => 'SM-CONSENT', 'nombre' => 'Consentimiento de Salud Mental', 'tipo_servicio' => $servicio, 'activo' => true]);

        return VersionConsentimiento::create(['id_modelo' => $modelo->id_modelo, 'numero_version' => 1, 'texto' => 'Autorizo el tratamiento confidencial de mi información.', 'estado' => 'publicada', 'fecha_publicacion' => now()]);
    }

    /**
     * DADO un psicólogo asignado a Ingeniería en la jornada nocturna,
     * CUANDO un estudiante de esa facultad y jornada solicita la cita,
     * ENTONCES el caso llega directo a ese psicólogo, sin motivo, fecha ni modalidad.
     */
    public function test_el_caso_llega_directo_al_psicologo_de_la_facultad_y_jornada(): void
    {
        $this->solicitar($this->datosValidos())
            ->assertRedirect(route('citas.miscitas'))
            ->assertSessionHasNoErrors();

        $solicitud = SolicitudAtencion::sole();
        $this->assertSame($this->estudiante->persona->id_persona, $solicitud->id_persona);
        $this->assertSame([$this->ingenieria->id_facultad, 'Nocturna', 'Noche'], [(int) $solicitud->id_facultad, $solicitud->jornada, $solicitud->franja_horaria]);
        $this->assertNull($solicitud->descripcion);
        $this->assertNull($solicitud->id_profesional_preferido);
        $this->assertNull($solicitud->fecha_preferida);
        $this->assertNull($solicitud->modalidad);

        $caso = Caso::sole();
        $this->assertSame(Caso::ESTADO_ABIERTO, $caso->estado_caso);
        $this->assertSame($this->profesional->id_usuario, $caso->id_profesional);
        $this->assertSame(0, Actuacion::count(), 'La hora la acuerda después el psicólogo con el estudiante.');

        $this->get(route('citas.miscitas'))
            ->assertSee("Recibimos tu solicitud {$caso->codigo_expediente}. Tu caso quedó a cargo de Laura Gómez")
            ->assertSee('Facultad de Ingeniería · Nocturna · franja Noche')
            ->assertSee('Tu caso quedó a cargo de Laura Gómez, que te contactará para acordar la fecha y la hora de tu primera cita.');
    }

    public function test_si_hay_varios_psicologos_llega_al_que_tiene_menos_casos_activos(): void
    {
        $otro = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Andrés Ruiz']);
        $this->asignar($otro, $this->ingenieria, 'Nocturna');

        // Laura ya tiene un caso activo; Andrés ninguno.
        $previo = $this->crearUsuarioConRol('Persona')->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental']);
        Caso::where('id_solicitud_origen', $previo->id_solicitud)->update(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $this->profesional->id_usuario]);

        $this->solicitar($this->datosValidos())->assertSessionHasNoErrors();

        $this->assertSame($otro->id_usuario, SolicitudAtencion::where('id_persona', $this->estudiante->persona->id_persona)->sole()->caso->id_profesional);
    }

    /**
     * DADO que nadie atiende esa facultad y jornada,
     * ENTONCES el caso llega en cascada: a quien atiende la misma facultad en otra jornada y,
     * si tampoco hay, a quien atiende el servicio en otra facultad.
     */
    public function test_sin_psicologo_de_esa_facultad_y_jornada_asigna_en_cascada(): void
    {
        // Misma facultad, otra jornada: Laura atiende Ingeniería solo de noche.
        $this->solicitar($this->datosValidos(['jornada' => 'Diurna']))->assertSessionHasNoErrors();
        $this->assertSame($this->profesional->id_usuario, Caso::sole()->id_profesional);

        // Otra facultad sin nadie asignado: le llega a quien atiende Salud Mental (Laura).
        $salud = Facultad::where('nombre', 'Facultad de Ciencias de la Salud')->sole();
        $otro = $this->crearUsuarioConRol('Persona');
        $this->solicitar($this->datosValidos(['id_facultad' => $salud->id_facultad, 'jornada' => 'Diurna']), $otro)->assertSessionHasNoErrors();
        $this->assertSame($this->profesional->id_usuario, SolicitudAtencion::where('id_persona', $otro->persona->id_persona)->sole()->caso->id_profesional);
    }

    /**
     * Solo si nadie atiende el servicio (ni activo) queda sin profesional.
     */
    public function test_si_nadie_atiende_el_servicio_queda_sin_asignar(): void
    {
        $inclusion = $this->crearUsuarioConRol('Profesional');
        $inactivo = $this->crearUsuarioConRol('Profesional');
        $inactivo->update(['estado_activo' => false]);
        $this->asignar($inactivo, $this->ingenieria, 'Diurna', 'Inclusión');
        $this->asignar($inclusion, $this->ingenieria, 'Diurna', 'Inclusión', activa: false);

        $this->solicitar($this->datosValidos(['tipo_servicio' => 'Inclusión']))->assertSessionHasNoErrors();

        $caso = Caso::sole();
        $this->assertSame(Caso::ESTADO_PENDIENTE, $caso->estado_caso);
        $this->assertNull($caso->id_profesional);

        $this->get(route('citas.miscitas'))
            ->assertSee('Cita pendiente')
            ->assertSee('Bienestar Universitario te asignará un profesional');
    }

    /**
     * DADO una solicitud que quedó sin profesional,
     * CUANDO el administrador crea la asignación que le corresponde,
     * ENTONCES el caso se asigna en ese momento, sin esperar.
     */
    public function test_al_crear_la_asignacion_se_asignan_las_solicitudes_pendientes(): void
    {
        $this->solicitar($this->datosValidos(['tipo_servicio' => 'Inclusión']))->assertSessionHasNoErrors();
        $this->assertNull(Caso::sole()->id_profesional);

        $inclusion = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Marta Inclusión']);
        $this->actingAs($this->crearUsuarioConRol('Admin'))->post(route('admin.asignaciones.store'), [
            'id_profesional' => $inclusion->id_usuario, 'tipo_servicio' => 'Inclusión', 'id_facultad' => $this->ingenieria->id_facultad, 'jornadas' => ['Nocturna'],
        ])->assertSessionHas('status', fn (string $mensaje) => str_contains($mensaje, 'se asignaron 1 solicitud que estaba sin profesional'));

        $caso = Caso::sole();
        $this->assertSame([Caso::ESTADO_ABIERTO, $inclusion->id_usuario], [$caso->estado_caso, $caso->id_profesional]);
    }

    /**
     * DADO el formulario de solicitud,
     * ENTONCES no pregunta con quién, ni el motivo, ni la modalidad, ni la fecha,
     * y sugiere la facultad registrada del estudiante.
     */
    public function test_sin_datos_academicos_registrados_pide_facultad_y_jornada(): void
    {
        $this->actingAs($this->estudiante)->get(route('citas.solicitar'))
            ->assertOk()
            ->assertSee('Selecciona tu facultad…')
            ->assertSee('Tu facultad no está en tu registro')
            ->assertSee('name="jornada"', false)
            ->assertSee('Franja horaria');
    }

    /**
     * DADO un estudiante con su facultad, programa y jornada registrados,
     * ENTONCES la solicitud no los pregunta: los toma del registro, aunque llegue otro valor.
     */
    public function test_con_datos_registrados_no_pregunta_la_facultad_y_los_toma_del_registro(): void
    {
        DB::table('vinculacion')->insert(['id_persona' => $this->estudiante->persona->id_persona, 'tipo_vinculacion' => 'Estudiante', 'facultad' => 'Ingeniería',
            'id_facultad' => $this->ingenieria->id_facultad, 'programa_academico' => 'Ingeniería de Sistemas', 'jornada' => 'Nocturna', 'semestre' => 6]);

        $this->actingAs($this->estudiante)->get(route('citas.solicitar'))
            ->assertOk()
            ->assertSee('Ingeniería de Sistemas')
            ->assertSee('Facultad de Ingeniería')
            ->assertSee('Según tu registro en la universidad')
            ->assertDontSee('Selecciona tu facultad…')
            ->assertDontSee('name="jornada"', false);

        $salud = \App\Models\Facultad::where('nombre', 'Facultad de Ciencias de la Salud')->sole();
        $this->solicitar(['tipo_servicio' => 'Salud Mental', 'franja_horaria' => 'Noche', 'id_facultad' => $salud->id_facultad, 'jornada' => 'Diurna'])
            ->assertSessionHasNoErrors();

        $solicitud = SolicitudAtencion::sole();
        $this->assertSame([$this->ingenieria->id_facultad, 'Nocturna'], [(int) $solicitud->id_facultad, $solicitud->jornada]);
        $this->assertSame($this->profesional->id_usuario, $solicitud->caso->id_profesional, 'Llega al psicólogo de su facultad y jornada registradas.');
    }

    public function test_reconoce_la_facultad_registrada_como_texto(): void
    {
        DB::table('vinculacion')->insert(['id_persona' => $this->estudiante->persona->id_persona, 'tipo_vinculacion' => 'Estudiante', 'facultad' => 'Ingeniería', 'jornada' => 'Nocturna']);

        $this->solicitar(['tipo_servicio' => 'Salud Mental', 'franja_horaria' => 'Todas'])->assertSessionHasNoErrors();
        $this->assertSame($this->ingenieria->id_facultad, (int) SolicitudAtencion::sole()->id_facultad);
    }

    public function test_el_formulario_no_pide_lo_que_se_quito(): void
    {
        $this->actingAs($this->estudiante)->get(route('citas.solicitar'))
            ->assertOk()
            ->assertSee('Vicerrectoría de Bienestar Universitario')
            ->assertDontSee('¿Con quién quieres la cita?')
            ->assertDontSee('Motivo de la consulta')
            ->assertDontSee('Modalidad')
            ->assertDontSee('Fecha preferida');
    }

    /**
     * Aunque llegue el motivo u otro dato que ya no se pide, no se guarda.
     */
    public function test_no_guarda_el_motivo_ni_el_profesional_aunque_lleguen(): void
    {
        $otro = $this->crearUsuarioConRol('Profesional');

        $this->solicitar($this->datosValidos(['motivo' => 'Ideación suicida', 'id_profesional' => $otro->id_usuario, 'modalidad' => 'Virtual']))
            ->assertSessionHasNoErrors();

        $solicitud = SolicitudAtencion::sole();
        $this->assertNull($solicitud->descripcion);
        $this->assertNull($solicitud->id_profesional_preferido);
        $this->assertNull($solicitud->modalidad);
        $this->assertSame($this->profesional->id_usuario, $solicitud->caso->id_profesional);
    }

    public static function datosInvalidos(): array
    {
        return [
            'sin servicio' => [['tipo_servicio' => ''], 'tipo_servicio'],
            'servicio desconocido' => [['tipo_servicio' => 'Orientación Vocacional'], 'tipo_servicio'],
            'sin facultad' => [['id_facultad' => ''], 'id_facultad'],
            'facultad inexistente' => [['id_facultad' => 999], 'id_facultad'],
            'sin jornada' => [['jornada' => ''], 'jornada'],
            'jornada desconocida' => [['jornada' => 'Sabatina'], 'jornada'],
            'sin franja' => [['franja_horaria' => ''], 'franja_horaria'],
            'franja desconocida' => [['franja_horaria' => 'Madrugada'], 'franja_horaria'],
        ];
    }

    #[DataProvider('datosInvalidos')]
    public function test_valida_los_datos_de_la_solicitud(array $cambios, string $campo): void
    {
        $this->solicitar($this->datosValidos($cambios))->assertSessionHasErrors($campo);

        $this->assertSame(0, SolicitudAtencion::count());
    }

    public function test_una_facultad_inactiva_no_se_puede_elegir(): void
    {
        $this->ingenieria->update(['activa' => false]);

        $this->actingAs($this->estudiante)->get(route('citas.solicitar'))->assertDontSee('Facultad de Ingeniería');
        $this->solicitar($this->datosValidos())->assertSessionHasErrors('id_facultad');
    }

    /**
     * DADO un servicio con consentimiento vigente,
     * CUANDO el estudiante lo acepta,
     * ENTONCES queda ligado al caso con la versión exacta.
     */
    public function test_guarda_el_consentimiento_aceptado(): void
    {
        $version = $this->publicarConsentimiento();

        $this->actingAs($this->estudiante)->get(route('citas.solicitar'))->assertSee('Autorizo el tratamiento confidencial de mi información.');

        $this->solicitar($this->datosValidos(['id_version' => $version->id_version, 'acepta_consentimiento' => '1']))
            ->assertRedirect(route('citas.miscitas'));

        $consentimiento = Consentimiento::sole();
        $this->assertSame(Caso::sole()->id_caso, $consentimiento->id_caso);
        $this->assertTrue($consentimiento->aceptado);
        $this->assertSame($version->id_version, $consentimiento->id_version);
        $this->assertSame('SM-CONSENT-v1', $consentimiento->version_formato);
    }

    public function test_exige_aceptar_el_consentimiento_vigente(): void
    {
        $version = $this->publicarConsentimiento();

        $this->solicitar($this->datosValidos(['id_version' => $version->id_version]))
            ->assertSessionHasErrors(['acepta_consentimiento' => 'Para enviar la solicitud debes leer y aceptar el consentimiento informado.']);

        $this->assertSame(0, SolicitudAtencion::count());
    }

    public function test_rechaza_un_consentimiento_desactualizado(): void
    {
        $anterior = $this->publicarConsentimiento();
        $nueva = VersionConsentimiento::create(['id_modelo' => $anterior->id_modelo, 'numero_version' => 2, 'texto' => 'Texto nuevo.']);
        $nueva->publicar();

        $this->solicitar($this->datosValidos(['id_version' => $anterior->id_version, 'acepta_consentimiento' => '1']))
            ->assertSessionHasErrors('acepta_consentimiento');

        $this->assertSame(0, SolicitudAtencion::count());
    }

    public function test_no_duplica_una_solicitud_en_espera_del_mismo_servicio(): void
    {
        // Nadie atiende Inclusión: la solicitud queda en espera.
        $this->solicitar($this->datosValidos(['tipo_servicio' => 'Inclusión']))->assertSessionHasNoErrors();
        $codigo = Caso::sole()->codigo_expediente;

        $this->solicitar($this->datosValidos(['tipo_servicio' => 'Inclusión']))
            ->assertSessionHasErrors(['tipo_servicio' => "Ya tienes una solicitud de Inclusión en espera ({$codigo}). Bienestar te contactará para confirmar tu cita."]);

        $this->assertSame(1, Caso::count());
    }

    public function test_no_duplica_un_caso_en_atencion_del_mismo_servicio(): void
    {
        $this->solicitar($this->datosValidos())->assertSessionHasNoErrors();
        $codigo = Caso::sole()->codigo_expediente;

        $this->solicitar($this->datosValidos())
            ->assertSessionHasErrors(['tipo_servicio' => "Ya tienes un caso de Salud Mental en atención ({$codigo}). Tu profesional agenda las próximas citas contigo."]);
    }

    public function test_permite_otro_servicio_o_un_caso_ya_cerrado(): void
    {
        $this->solicitar($this->datosValidos())->assertSessionHasNoErrors();
        $this->solicitar($this->datosValidos(['tipo_servicio' => 'Inclusión']))->assertSessionHasNoErrors();

        Caso::query()->update(['estado_caso' => Caso::ESTADO_CERRADO]);
        $this->solicitar($this->datosValidos())->assertSessionHasNoErrors();

        $this->assertSame(3, Caso::count());
    }

    public function test_un_estudiante_sin_datos_personales_no_puede_solicitar(): void
    {
        $this->estudiante->persona->delete();
        $this->estudiante = $this->estudiante->fresh();

        $this->solicitar($this->datosValidos())->assertSessionHasErrors('tipo_servicio');

        $this->assertSame(0, SolicitudAtencion::count());
    }

    public function test_solo_el_estudiante_puede_solicitar_citas_desde_su_panel(): void
    {
        $this->solicitar($this->datosValidos(), $this->crearUsuarioConRol('Profesional'))->assertRedirect(route('profesional.home'));

        $this->assertSame(0, SolicitudAtencion::count());
    }

    public function test_el_formulario_solo_ofrece_los_servicios_de_la_base(): void
    {
        $this->actingAs($this->estudiante)
            ->get(route('citas.solicitar'))
            ->assertOk()
            ->assertSee('Atención Psicológica')
            ->assertSee('Inclusión Universitaria')
            ->assertDontSee('Orientación Vocacional');
    }

    /**
     * DADO un caso que ya tiene profesional pero no día y hora de cita,
     * ENTONCES el estudiante ve "Cita pendiente"; cuando se agenda, ve el día y la hora.
     */
    public function test_la_cita_queda_pendiente_hasta_que_se_agenda_el_dia_y_la_hora(): void
    {
        $this->solicitar($this->datosValidos())->assertSessionHasNoErrors();
        $caso = Caso::sole();
        $this->assertSame($this->profesional->id_usuario, $caso->id_profesional);

        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))
            ->assertSee('Cita pendiente')
            ->assertSee('Día y hora de la cita')
            ->assertSee('Laura Gómez')
            ->assertDontSee('Por asignar profesional');

        $this->actingAs($this->profesional)->post(route('solicitudes.agendar', $caso), ['caso' => $caso->id_caso, 'fecha' => '2026-10-08', 'hora' => '15:00'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))
            ->assertSee('En atención')
            ->assertSee('2026-10-08 · 3:00 pm')
            ->assertDontSee('Cita pendiente');
    }
}

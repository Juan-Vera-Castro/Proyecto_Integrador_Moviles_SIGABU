<?php

namespace Tests\Feature;

use App\Models\AlertaTemprana;
use App\Models\Caso;
use App\Models\Persona;
use App\Models\SolicitudAtencion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-004: Registrar la apertura de un nuevo caso de atención.
 */
class AperturaCasoTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Restrepo']);
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'nombre_completo' => 'Juan Esteban Morales Ríos',
            'numero_documento' => '1112223334',
            'correo_inst' => 'juan.morales@uceva.edu.co',
            'tipo_servicio' => 'Salud Mental',
            'nivel_riesgo' => 'Alto',
            'motivo' => 'Ansiedad severa y crisis académica durante parciales.',
        ], $cambios);
    }

    public function test_el_profesional_ve_el_formulario_de_apertura(): void
    {
        $response = $this->actingAs($this->profesional)->get(route('casos.create', ['nuevo' => 1]));

        $response->assertOk()
            ->assertSee('Buscar paciente')
            ->assertSee('for="nombre_completo"', false)
            ->assertSee('for="numero_documento"', false)
            ->assertSee('for="correo_inst"', false)
            ->assertSee('for="motivo"', false)
            ->assertSee('Guardar caso');
    }

    public function test_los_campos_siguen_un_orden_logico_para_el_tabulador(): void
    {
        $html = $this->actingAs($this->profesional)->get(route('casos.create', ['nuevo' => 1]))->getContent();

        $orden = ['id="nombre_completo"', 'id="numero_documento"', 'id="correo_inst"', 'id="tipo_servicio"', 'id="nivel_riesgo"', 'id="motivo"', 'Cancelar', 'Guardar caso'];
        $posiciones = array_map(fn ($marca) => strpos($html, $marca), $orden);

        $this->assertNotContains(false, $posiciones);
        $this->assertSame($posiciones, collect($posiciones)->sort()->values()->all());
        $this->assertStringNotContainsString('tabindex="', $html, 'El orden del foco debe ser el natural del documento.');
    }

    public function test_el_boton_nuevo_caso_de_salud_mental_lleva_al_formulario(): void
    {
        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertOk()
            ->assertSee(route('casos.create', ['servicio' => 'Salud Mental']), false);
    }

    public function test_solo_el_profesional_puede_abrir_casos(): void
    {
        $estudiante = $this->crearUsuarioConRol('Persona');

        $this->actingAs($estudiante)->get(route('casos.create'))->assertRedirect(route('estudiante.home'));
        $this->actingAs($estudiante)->post(route('casos.store'), $this->datosValidos())->assertRedirect(route('estudiante.home'));
        $this->assertSame(0, Caso::count());
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get(route('casos.create'))->assertRedirect(route('login'));
        $this->post(route('casos.store'), $this->datosValidos())->assertRedirect(route('login'));
        $this->assertSame(0, Caso::count());
    }

    /**
     * DADO que registro los datos mínimos del solicitante y el motivo,
     * CUANDO guardo,
     * ENTONCES se crea un caso con estado "Abierto" y un expediente asociado.
     */
    public function test_guardar_crea_caso_abierto_con_expediente_y_solicitante_nuevo(): void
    {
        $response = $this->actingAs($this->profesional)->post(route('casos.store'), $this->datosValidos());

        $caso = Caso::with('solicitud.persona.usuario')->sole();

        $response->assertRedirect(route('casos.show', $caso))
            ->assertSessionHas('status', "Caso {$caso->codigo_expediente} abierto correctamente.");

        $this->assertSame(Caso::ESTADO_ABIERTO, $caso->estado_caso);
        $this->assertSame('Alto', $caso->nivel_riesgo);
        // Abierto en riesgo alto: nace con su alerta temprana (HU-026).
        $this->assertSame(AlertaTemprana::RIESGO_ALTO, $caso->alertas()->sole()->tipo_alerta);
        $this->assertSame($this->profesional->id_usuario, $caso->id_profesional);
        $this->assertMatchesRegularExpression('/^SM-\d{4}-0001$/', $caso->codigo_expediente);

        $this->assertSame('Ansiedad severa y crisis académica durante parciales.', $caso->solicitud->descripcion);
        $this->assertSame('Salud Mental', $caso->solicitud->tipo_servicio);
        $this->assertSame('Procesada', $caso->solicitud->fresh()->estado_solicitud);

        $persona = $caso->solicitud->persona;
        $this->assertSame('1112223334', $persona->numero_documento);
        $this->assertSame('Juan Esteban Morales Ríos', $persona->nombre_completo);
        $this->assertSame('juan.morales@uceva.edu.co', $persona->correo_inst);
        $this->assertSame('1112223334', $persona->usuario->codigo_unico);
        $this->assertSame('estudiante', $persona->usuario->role);

        $this->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee($caso->codigo_expediente)
            ->assertSee('Abierto')
            ->assertSee('Juan Esteban Morales Ríos')
            ->assertSee('Carolina Restrepo')
            ->assertSee('abierto correctamente');
    }

    public function test_reutiliza_la_persona_si_la_cedula_ya_existe(): void
    {
        $existente = $this->crearUsuarioConRol('Persona', [
            'numero_documento' => '1112223334',
            'correo_inst' => 'juan.morales@uceva.edu.co',
            'nombre_completo' => 'Juan Esteban Morales',
        ]);
        $personasAntes = Persona::count();

        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosValidos(['correo_inst' => 'JUAN.MORALES@uceva.edu.co', 'numero_documento' => '1.112.223.334']))
            ->assertSessionHasNoErrors();

        $this->assertSame($personasAntes, Persona::count());
        $this->assertSame($existente->persona->id_persona, Caso::sole()->solicitud->id_persona);
    }

    public function test_el_codigo_de_expediente_depende_del_servicio_y_es_consecutivo(): void
    {
        $this->actingAs($this->profesional)->post(route('casos.store'), $this->datosValidos());
        $this->actingAs($this->profesional)->post(route('casos.store'), $this->datosValidos([
            'numero_documento' => '2223334445',
            'correo_inst' => 'otra.persona@uceva.edu.co',
        ]));
        $this->actingAs($this->profesional)->post(route('casos.store'), $this->datosValidos([
            'numero_documento' => '3334445556',
            'correo_inst' => 'inclusion@uceva.edu.co',
            'tipo_servicio' => 'Inclusión',
            'nivel_riesgo' => '',
        ]));

        $codigos = Caso::orderBy('codigo_expediente')->pluck('codigo_expediente')->all();
        $anio = now()->format('Y');

        $this->assertSame(["IN-$anio-0001", "SM-$anio-0001", "SM-$anio-0002"], $codigos);
        $this->assertNull(Caso::where('codigo_expediente', "IN-$anio-0001")->value('nivel_riesgo'));
        $this->assertSame(3, Caso::where('estado_caso', Caso::ESTADO_ABIERTO)->count());
    }

    public function test_no_guarda_si_faltan_datos_obligatorios(): void
    {
        $response = $this->actingAs($this->profesional)
            ->from(route('casos.create'))
            ->post(route('casos.store'), [
                'nombre_completo' => '',
                'numero_documento' => '',
                'correo_inst' => '',
                'tipo_servicio' => '',
                'motivo' => '   ',
            ]);

        $response->assertRedirect(route('casos.create'))
            ->assertSessionHasErrors([
                'nombre_completo' => 'El campo nombre completo es obligatorio.',
                'numero_documento' => 'El campo documento de identidad es obligatorio.',
                'correo_inst' => 'El campo correo institucional es obligatorio.',
                'tipo_servicio' => 'El campo servicio es obligatorio.',
                'motivo' => 'El campo motivo de consulta es obligatorio.',
            ]);

        $this->assertNadaGuardado();
    }

    public function test_valida_formato_de_correo_documento_y_motivo(): void
    {
        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosValidos([
                'numero_documento' => '12AB45',
                'correo_inst' => 'correo-invalido',
                'motivo' => 'corto',
                'nivel_riesgo' => 'Extremo',
            ]))
            ->assertSessionHasErrors(['numero_documento', 'correo_inst', 'motivo', 'nivel_riesgo']);

        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosValidos(['correo_inst' => 'juan@gmail.com']))
            ->assertSessionHasErrors(['correo_inst' => 'Usa el correo institucional del solicitante (termina en @uceva.edu.co).']);

        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosValidos(['motivo' => str_repeat('a', 301)]))
            ->assertSessionHasErrors('motivo');

        $this->assertNadaGuardado();
    }

    public function test_los_errores_se_muestran_junto_al_campo_y_conservan_lo_escrito(): void
    {
        $this->actingAs($this->profesional)
            ->from(route('casos.create'))
            ->post(route('casos.store'), $this->datosValidos(['correo_inst' => 'juan@gmail.com']));

        $this->get(route('casos.create'))
            ->assertSee('role="alert"', false)
            ->assertSee('id="correo_inst-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Usa el correo institucional del solicitante (termina en @uceva.edu.co).')
            ->assertSee('value="Juan Esteban Morales Ríos"', false);
    }

    public function test_rechaza_cedula_existente_con_otro_correo_o_correo_de_otra_persona(): void
    {
        $this->crearUsuarioConRol('Persona', [
            'numero_documento' => '1112223334',
            'correo_inst' => 'dueno@uceva.edu.co',
        ]);

        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosValidos())
            ->assertSessionHasErrors(['correo_inst' => 'Este documento ya está registrado con otro correo institucional. Verifica el correo del solicitante.']);

        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosValidos(['numero_documento' => '9998887776', 'correo_inst' => 'dueno@uceva.edu.co']))
            ->assertSessionHasErrors(['correo_inst' => 'Este correo institucional ya pertenece a otra persona. Verifica el documento o el correo.']);

        $this->assertSame(0, Caso::count());
    }

    public function test_si_falla_la_creacion_del_caso_no_queda_informacion_parcial(): void
    {
        // Sin el trigger la solicitud no genera caso ni expediente: el guardado debe revertirse completo.
        DB::unprepared('DROP TRIGGER trg_solicitud_crea_caso');
        $usuariosAntes = User::count();

        $response = $this->actingAs($this->profesional)
            ->from(route('casos.create'))
            ->post(route('casos.store'), $this->datosValidos());

        $response->assertRedirect(route('casos.create'))
            ->assertSessionHas('error', 'No fue posible abrir el caso en este momento. No se guardó ningún dato; intenta de nuevo en unos minutos.')
            ->assertSessionHasInput('nombre_completo', 'Juan Esteban Morales Ríos');

        $this->assertSame($usuariosAntes, User::count());
        $this->assertNadaGuardado();
    }

    private function assertNadaGuardado(): void
    {
        $this->assertSame(0, Caso::count());
        $this->assertSame(0, SolicitudAtencion::count());
        $this->assertFalse(Persona::where('numero_documento', '1112223334')->exists());
    }
}

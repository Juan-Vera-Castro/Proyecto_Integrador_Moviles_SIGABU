<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-011: Agendar nueva cita para asesoría psicológica.
 */
class AgendarCitaTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        // Lunes 5 de octubre de 2026, 7:00 am en Colombia.
        Carbon::setTestNow(Carbon::parse('2026-10-05 07:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Restrepo']);
        $this->caso = $this->crearCaso($this->profesional, 'Abierto', 'Juan Esteban Morales');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(?User $profesional, string $estado, string $nombre = 'Valentina Ospina'): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => $nombre]);
        $solicitud = $estudiante->persona->solicitudes()->create([
            'tipo_servicio' => 'Salud Mental',
            'descripcion' => 'Ansiedad y crisis académica.',
        ]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $profesional?->id_usuario]);

        if ($estado === 'Cerrado') {
            DB::table('casos')->where('id_caso', $caso->id_caso)->update(['fecha_cierre' => now()]);
        }

        return $caso->fresh();
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'id_caso' => $this->caso->id_caso,
            'fecha' => '2026-10-06',
            'hora' => '09:00',
            'observaciones' => 'Primera sesión de seguimiento.',
        ], $cambios);
    }

    private function agendar(array $datos, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->from(route('citas.create'))
            ->post(route('citas.store'), $datos);
    }

    public function test_el_profesional_ve_el_formulario_con_sus_casos_activos(): void
    {
        $cerrado = $this->crearCaso($this->profesional, 'Cerrado');
        $pendiente = $this->crearCaso(null, 'Pendiente Asignación');
        $seguimiento = $this->crearCaso($this->profesional, 'En Seguimiento', 'Daniela Herrera');
        $deOtro = $this->crearCaso($this->crearUsuarioConRol('Profesional'), 'Abierto');

        $this->actingAs($this->profesional)->get(route('citas.create'))
            ->assertOk()
            ->assertSee('Agendar cita')
            ->assertSee('for="id_caso"', false)
            ->assertSee('for="fecha"', false)
            ->assertSee('<legend class="block text-sm font-medium text-gray-700 mb-1.5">', false)
            ->assertSee($this->caso->codigo_expediente)
            ->assertSee('Juan Esteban Morales')
            ->assertSee($seguimiento->codigo_expediente)
            ->assertDontSee($cerrado->codigo_expediente)
            ->assertDontSee($pendiente->codigo_expediente)
            ->assertDontSee($deOtro->codigo_expediente)
            ->assertSee('8:00 am')
            ->assertSee('4:00 pm')
            ->assertSee('min="2026-10-05"', false);
    }

    public function test_los_campos_siguen_un_orden_logico_para_el_tabulador(): void
    {
        $html = $this->actingAs($this->profesional)->get(route('citas.create'))->getContent();

        $orden = ['id="id_caso"', 'id="fecha"', 'name="hora" value="08:00"', 'name="hora" value="16:00"', 'id="observaciones"', 'Cancelar', 'Agendar cita</span>'];
        $posiciones = array_map(fn ($marca) => strpos($html, $marca), $orden);

        $this->assertNotContains(false, $posiciones);
        $this->assertSame($posiciones, collect($posiciones)->sort()->values()->all());
        $this->assertStringNotContainsString('tabindex="', $html, 'El orden del foco debe ser el natural del documento.');
    }

    public function test_desde_el_caso_el_formulario_llega_con_el_caso_seleccionado(): void
    {
        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee(route('citas.create', ['caso' => $this->caso->id_caso]), false)
            ->assertSee('No hay citas registradas para este expediente.');

        $this->actingAs($this->profesional)->get(route('citas.create', ['caso' => $this->caso->id_caso]))
            ->assertOk()
            ->assertSee('value="'.$this->caso->id_caso.'" selected', false)
            ->assertSee(route('casos.show', $this->caso), false);
    }

    /**
     * DADO un caso activo,
     * CUANDO agendo una cita con fecha y hora,
     * ENTONCES la cita queda visible en la agenda del profesional y del caso.
     */
    public function test_agendar_crea_la_cita_visible_en_la_agenda_y_en_el_caso(): void
    {
        $response = $this->agendar($this->datosValidos());

        $cita = Actuacion::sole();

        $response->assertRedirect(route('casos.show', $this->caso))
            ->assertSessionHas('status', "Cita agendada para el 2026-10-06 a las 9:00 am. Ya aparece en tu agenda y en el caso {$this->caso->codigo_expediente}.");

        $this->assertSame($this->caso->id_caso, $cita->id_caso);
        $this->assertSame($this->profesional->id_usuario, $cita->id_profesional);
        $this->assertSame(Actuacion::TIPO_CITA, $cita->tipo_actuacion);
        $this->assertSame('2026-10-06 09:00:00', $cita->fecha_hora->toDateTimeString());
        $this->assertSame('Primera sesión de seguimiento.', $cita->descripcion);

        // Visible desde el caso.
        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Cita agendada para el 2026-10-06')
            ->assertSee('2026-10-06')
            ->assertSee('9:00 am')
            ->assertSee('Carolina Restrepo')
            ->assertSee('Primera sesión de seguimiento.')
            ->assertDontSee('No hay citas registradas para este expediente.');

        // Visible en la agenda del profesional.
        $this->actingAs($this->profesional)->get(route('citas.agenda'))
            ->assertOk()
            ->assertSee('1 cita')
            ->assertSee('Juan Esteban Morales')
            ->assertSee($this->caso->codigo_expediente)
            ->assertSee('9:00')
            ->assertSee(route('casos.show', $this->caso), false);
    }

    public function test_la_agenda_respeta_el_filtro_por_fecha_y_solo_muestra_citas_del_profesional(): void
    {
        $this->agendar($this->datosValidos(['fecha' => '2026-10-06', 'observaciones' => 'Cita del martes']));
        $this->agendar($this->datosValidos(['fecha' => '2026-10-07', 'observaciones' => 'Cita del miércoles']));

        $otro = $this->crearUsuarioConRol('Profesional');
        $casoOtro = $this->crearCaso($otro, 'Abierto');
        $this->agendar(['id_caso' => $casoOtro->id_caso, 'fecha' => '2026-10-06', 'hora' => '10:00', 'observaciones' => 'Cita de otro profesional'], $otro);

        $this->assertSame(3, Actuacion::count());

        $this->actingAs($this->profesional)->get(route('citas.agenda', ['fecha' => '2026-10-07']))
            ->assertOk()
            ->assertSee('Cita del miércoles')
            ->assertDontSee('Cita del martes')
            ->assertDontSee('Cita de otro profesional');

        $this->actingAs($this->profesional)->get(route('citas.agenda'))
            ->assertSee('Cita del martes')
            ->assertSee('Cita del miércoles')
            ->assertDontSee('Cita de otro profesional');
    }

    public function test_admite_casos_en_seguimiento(): void
    {
        $seguimiento = $this->crearCaso($this->profesional, 'En Seguimiento');

        $this->agendar($this->datosValidos(['id_caso' => $seguimiento->id_caso]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('casos.show', $seguimiento));

        $this->assertSame($seguimiento->id_caso, Actuacion::sole()->id_caso);
    }

    public function test_no_agenda_citas_para_casos_no_activos(): void
    {
        $cerrado = $this->crearCaso($this->profesional, 'Cerrado');
        $pendiente = $this->crearCaso($this->profesional, 'Pendiente Asignación');

        $this->agendar($this->datosValidos(['id_caso' => $cerrado->id_caso]))
            ->assertRedirect(route('citas.create'))
            ->assertSessionHasErrors(['id_caso' => "Solo se pueden agendar citas para casos activos. El caso {$cerrado->codigo_expediente} está en estado \"Cerrado\"."]);

        $this->agendar($this->datosValidos(['id_caso' => $pendiente->id_caso]))
            ->assertSessionHasErrors(['id_caso' => "Solo se pueden agendar citas para casos activos. El caso {$pendiente->codigo_expediente} está en estado \"Pendiente Asignación\"."]);

        $this->actingAs($this->profesional)->get(route('casos.show', $cerrado))
            ->assertOk()
            ->assertSee('solo se pueden agendar citas para casos activos')
            ->assertDontSee(route('citas.create', ['caso' => $cerrado->id_caso]), false);

        $this->actingAs($this->profesional)->get(route('citas.create', ['caso' => $cerrado->id_caso]))
            ->assertOk()
            ->assertSee("El caso {$cerrado->codigo_expediente} está en estado &quot;Cerrado&quot;. Solo se pueden agendar citas para casos activos.", false);

        $this->assertSame(0, Actuacion::count());
    }

    public function test_no_agenda_en_casos_inexistentes_o_de_otro_profesional(): void
    {
        $casoOtro = $this->crearCaso($this->crearUsuarioConRol('Profesional'), 'Abierto');

        $this->agendar($this->datosValidos(['id_caso' => $casoOtro->id_caso]))
            ->assertSessionHasErrors(['id_caso' => "El caso {$casoOtro->codigo_expediente} no está asignado a ti. Solo puedes agendar citas en tus casos."]);

        $this->agendar($this->datosValidos(['id_caso' => '9b2f4c1e-0000-4000-a000-000000000000']))
            ->assertSessionHasErrors(['id_caso' => 'El caso seleccionado no existe. Elige un caso de la lista.']);

        $this->agendar($this->datosValidos(['id_caso' => 'no-es-un-id']))
            ->assertSessionHasErrors(['id_caso' => 'El caso seleccionado no existe. Elige un caso de la lista.']);

        $this->actingAs($this->profesional)->get(route('citas.create', ['caso' => 'no-es-un-id']))
            ->assertOk()
            ->assertSee('El caso indicado no existe. Elige uno de tus casos activos.');

        $this->assertSame(0, Actuacion::count());
    }

    public function test_no_guarda_sin_caso_fecha_ni_hora(): void
    {
        $this->agendar(['id_caso' => '', 'fecha' => '', 'hora' => ''])
            ->assertRedirect(route('citas.create'))
            ->assertSessionHasErrors([
                'id_caso' => 'Selecciona el caso para el que agendas la cita.',
                'fecha' => 'Selecciona la fecha de la cita.',
                'hora' => 'Selecciona la hora de la cita.',
            ]);

        $this->agendar($this->datosValidos(['fecha' => '']))->assertSessionHasErrors(['fecha' => 'Selecciona la fecha de la cita.']);
        $this->agendar($this->datosValidos(['hora' => '']))->assertSessionHasErrors(['hora' => 'Selecciona la hora de la cita.']);

        $this->assertSame(0, Actuacion::count());
    }

    public function test_valida_formato_de_fecha_hora_y_observaciones(): void
    {
        $this->agendar($this->datosValidos(['fecha' => '06/10/2026', 'hora' => '12:30', 'observaciones' => str_repeat('a', 301)]))
            ->assertSessionHasErrors([
                'fecha' => 'Escribe una fecha válida, por ejemplo 2026-10-05.',
                'hora' => 'Selecciona una de las horas de atención disponibles.',
                'observaciones' => 'Las observaciones no pueden superar los 300 caracteres.',
            ]);

        $this->assertSame(0, Actuacion::count());
    }

    public function test_no_agenda_en_fechas_u_horas_pasadas(): void
    {
        $this->agendar($this->datosValidos(['fecha' => '2026-10-02']))
            ->assertSessionHasErrors(['fecha' => 'La fecha de la cita no puede ser anterior a hoy.']);

        // Hoy a las 7:00 am: la franja de las 8:00 aún se puede agendar; la de hoy a las 7 no existe.
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:30:00', Actuacion::ZONA_HORARIA));

        $this->agendar($this->datosValidos(['fecha' => '2026-10-05', 'hora' => '10:00']))
            ->assertSessionHasErrors(['hora' => 'Esa hora ya pasó. Elige una hora posterior o otra fecha.']);

        $this->agendar($this->datosValidos(['fecha' => '2026-10-05', 'hora' => '11:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Actuacion::count());
    }

    public function test_detecta_el_cruce_de_agenda_del_profesional(): void
    {
        $this->agendar($this->datosValidos())->assertSessionHasNoErrors();

        // Otro caso del mismo profesional, misma fecha y hora.
        $otroCaso = $this->crearCaso($this->profesional, 'Abierto');

        $this->agendar($this->datosValidos(['id_caso' => $otroCaso->id_caso]))
            ->assertRedirect(route('citas.create'))
            ->assertSessionHasErrors(['hora' => 'El horario seleccionado no está disponible: ya tienes otra cita agendada el 2026-10-06 a las 9:00 am. Elige otra hora o fecha.'])
            ->assertSessionHasInput('id_caso', $otroCaso->id_caso);

        $this->assertSame(1, Actuacion::count());

        // La franja siguiente y otro día sí están libres.
        $this->agendar($this->datosValidos(['id_caso' => $otroCaso->id_caso, 'hora' => '10:00']))->assertSessionHasNoErrors();
        $this->agendar($this->datosValidos(['id_caso' => $otroCaso->id_caso, 'fecha' => '2026-10-07']))->assertSessionHasNoErrors();

        $this->assertSame(3, Actuacion::count());
    }

    public function test_citas_con_hora_no_exacta_tambien_bloquean_la_franja(): void
    {
        // Una consulta registrada a las 9:30 ocupa parte de las franjas de 9:00 y 10:00.
        Actuacion::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => '2026-10-06 09:30:00',
        ]);

        $this->agendar($this->datosValidos(['hora' => '09:00']))->assertSessionHasErrors('hora');
        $this->agendar($this->datosValidos(['hora' => '10:00']))->assertSessionHasErrors('hora');
        $this->agendar($this->datosValidos(['hora' => '11:00']))->assertSessionHasNoErrors();
    }

    public function test_otras_actuaciones_y_citas_de_otros_profesionales_no_bloquean_el_horario(): void
    {
        $otro = $this->crearUsuarioConRol('Profesional');
        $casoOtro = $this->crearCaso($otro, 'Abierto');
        $this->agendar(['id_caso' => $casoOtro->id_caso, 'fecha' => '2026-10-06', 'hora' => '09:00'], $otro)->assertSessionHasNoErrors();

        Actuacion::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => 'Remisión',
            'fecha_hora' => '2026-10-06 09:00:00',
            'destino_remision' => 'Externa',
            'entidad_destino' => 'ICBF',
        ]);

        $this->agendar($this->datosValidos())->assertSessionHasNoErrors();

        $this->assertSame(2, Actuacion::citas()->count());
    }

    public function test_la_disponibilidad_marca_franjas_ocupadas_y_pasadas(): void
    {
        $this->agendar($this->datosValidos(['fecha' => '2026-10-05', 'hora' => '14:00']));

        Carbon::setTestNow(Carbon::parse('2026-10-05 09:15:00', Actuacion::ZONA_HORARIA));

        $this->actingAs($this->profesional)->getJson(route('citas.disponibilidad', ['fecha' => '2026-10-05']))
            ->assertOk()
            ->assertExactJson(['ocupadas' => ['14:00'], 'pasadas' => ['08:00', '09:00']]);

        $this->actingAs($this->profesional)->getJson(route('citas.disponibilidad', ['fecha' => '2026-10-06']))
            ->assertExactJson(['ocupadas' => [], 'pasadas' => []]);

        $this->actingAs($this->profesional)->getJson(route('citas.disponibilidad', ['fecha' => 'mañana']))
            ->assertUnprocessable();
    }

    public function test_los_errores_se_muestran_junto_al_campo_y_conservan_lo_escrito(): void
    {
        $this->agendar($this->datosValidos(['hora' => '']));

        $this->actingAs($this->profesional)->get(route('citas.create'))
            ->assertSee('role="alert"', false)
            ->assertSee('No se pudo agendar la cita.')
            ->assertSee('id="hora-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Selecciona la hora de la cita.')
            ->assertSee('value="2026-10-06"', false)
            ->assertSee('Primera sesión de seguimiento.');
    }

    public function test_sin_casos_activos_se_explica_y_no_se_puede_guardar(): void
    {
        $sinCasos = $this->crearUsuarioConRol('Profesional');

        $this->actingAs($sinCasos)->get(route('citas.create'))
            ->assertOk()
            ->assertSee('No tienes casos activos asignados.')
            ->assertSee(route('casos.create'), false);

        $this->actingAs($sinCasos)->get(route('citas.agenda'))
            ->assertOk()
            ->assertSee('No tienes citas próximas');
    }

    public function test_si_falla_el_guardado_no_queda_informacion_parcial(): void
    {
        // Falla simulada de la base al insertar: el usuario ve un mensaje claro, no el error técnico.
        DB::unprepared("CREATE TRIGGER falla_insert BEFORE INSERT ON actuaciones BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");

        $this->agendar($this->datosValidos())
            ->assertRedirect(route('citas.create'))
            ->assertSessionHas('error', 'No fue posible agendar la cita en este momento. No se guardó ningún dato; intenta de nuevo en unos minutos.')
            ->assertSessionHasInput('hora', '09:00');

        $this->assertSame(0, DB::table('actuaciones')->count());
    }

    public function test_solo_el_profesional_con_sesion_puede_agendar(): void
    {
        $estudiante = $this->crearUsuarioConRol('Persona');

        $this->actingAs($estudiante)->get(route('citas.create'))->assertRedirect(route('estudiante.home'));
        $this->actingAs($estudiante)->post(route('citas.store'), $this->datosValidos())->assertRedirect(route('estudiante.home'));
        $this->actingAs($estudiante)->get(route('citas.agenda'))->assertRedirect(route('estudiante.home'));

        auth()->logout();

        $this->get(route('citas.agenda'))->assertRedirect(route('login'));
        $this->post(route('citas.store'), $this->datosValidos())->assertRedirect(route('login'));

        $this->assertSame(0, Actuacion::count());
    }

    public function test_salud_mental_enlaza_la_agenda(): void
    {
        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertOk()
            ->assertSee(route('citas.agenda'), false);
    }
}

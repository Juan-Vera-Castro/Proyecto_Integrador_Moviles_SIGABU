<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Las solicitudes llegan directo al psicólogo de la facultad y jornada (revisión del prototipo,
 * 06/10/2026): ya no se aceptan ni se rechazan. El profesional agenda la primera cita de sus
 * casos nuevos y puede asignarse las solicitudes que llegaron sin profesional.
 */
class SolicitudesProfesionalTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Facultad $ingenieria;

    protected function setUp(): void
    {
        parent::setUp();

        // Lunes 5 de octubre de 2026, 7:00 am en Colombia.
        Carbon::setTestNow(Carbon::parse('2026-10-05 07:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Laura Gómez']);
        $this->ingenieria = Facultad::where('nombre', 'Facultad de Ingeniería')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Solicitud de un estudiante nuevo; con $profesional, el caso ya le llegó a él.
     */
    private function crearSolicitud(?User $profesional, string $nombre = 'Juan Esteban Morales'): Caso
    {
        $solicitud = $this->crearUsuarioConRol('Persona', ['nombre_completo' => $nombre])->persona->solicitudes()->create([
            'tipo_servicio' => 'Salud Mental',
            'id_facultad' => $this->ingenieria->id_facultad,
            'jornada' => 'Nocturna',
            'franja_horaria' => 'Noche',
        ]);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();

        if ($profesional) {
            $caso->forceFill(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $profesional->id_usuario])->save();
        }

        return $caso->fresh();
    }

    private function agendar(Caso $caso, array $datos = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->from(route('solicitudes.index'))
            ->post(route('solicitudes.agendar', $caso), array_merge(['caso' => $caso->id_caso, 'fecha' => '2026-10-07', 'hora' => '10:00'], $datos));
    }

    public function test_el_profesional_ve_sus_casos_nuevos_y_los_que_no_tienen_profesional(): void
    {
        $this->crearSolicitud($this->profesional);
        $this->crearSolicitud(null, 'Valentina Ospina');
        $this->crearSolicitud($this->crearUsuarioConRol('Profesional'), 'Caso De Otro');

        $this->actingAs($this->profesional)->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee('2 solicitudes por atender')
            ->assertSee('Casos nuevos a tu cargo')
            ->assertSee('Juan Esteban Morales')
            ->assertSee('Facultad de Ingeniería · Nocturna · franja Noche')
            ->assertSee('Agendar primera cita')
            ->assertSee('Solicitudes sin profesional asignado')
            ->assertSee('Valentina Ospina')
            ->assertSee('Asignármela')
            ->assertDontSee('Caso De Otro')
            ->assertDontSee('Rechazar');
    }

    /**
     * DADO un caso que le llegó al psicólogo,
     * CUANDO acuerda la hora con el estudiante y la agenda,
     * ENTONCES queda la primera cita y el caso sale de la lista de nuevos.
     */
    public function test_agenda_la_primera_cita_de_un_caso_nuevo(): void
    {
        $caso = $this->crearSolicitud($this->profesional);

        $this->agendar($caso)
            ->assertRedirect(route('casos.show', $caso))
            ->assertSessionHas('status', "Primera cita del caso {$caso->codigo_expediente} agendada el 2026-10-07 a las 10:00 am.");

        $cita = Actuacion::sole();
        $this->assertSame([$caso->id_caso, $this->profesional->id_usuario, 'Consulta', 'Primera cita'], [$cita->id_caso, $cita->id_profesional, $cita->tipo_actuacion, $cita->descripcion]);
        $this->assertSame('2026-10-07 10:00', $cita->fecha_hora->format('Y-m-d H:i'));
        $this->assertSame(0, Caso::nuevosDe($this->profesional)->count());

        // Ya tiene cita: no se agenda otra "primera" cita.
        $this->agendar($caso, ['hora' => '11:00'])->assertSessionHasErrors(['solicitud'], null, 'respuesta');
        $this->assertSame(1, Actuacion::count());
    }

    public function test_no_agenda_en_una_franja_ocupada_o_pasada(): void
    {
        $caso = $this->crearSolicitud($this->profesional);
        Actuacion::create(['id_caso' => $this->crearSolicitud($this->profesional, 'Otro')->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'tipo_actuacion' => 'Consulta', 'fecha_hora' => '2026-10-07 10:00:00']);

        $this->agendar($caso)->assertSessionHasErrors(['hora'], null, 'respuesta');
        $this->agendar($caso, ['fecha' => '2026-10-02'])->assertSessionHasErrors(['fecha'], null, 'respuesta');
        $this->agendar($caso, ['hora' => '7:30'])->assertSessionHasErrors(['hora'], null, 'respuesta');

        $this->assertSame(0, $caso->citas()->count());
    }

    public function test_solo_el_profesional_del_caso_agenda_su_primera_cita(): void
    {
        $caso = $this->crearSolicitud($this->profesional);

        $this->agendar($caso, como: $this->crearUsuarioConRol('Profesional'))->assertForbidden();
        $this->assertSame(0, Actuacion::count());
    }

    /**
     * DADO una solicitud que llegó sin profesional (nadie atiende esa facultad y jornada),
     * CUANDO un profesional se la asigna,
     * ENTONCES queda a su cargo como caso nuevo, y otro ya no puede tomarla.
     */
    public function test_un_profesional_se_asigna_una_solicitud_sin_profesional(): void
    {
        $caso = $this->crearSolicitud(null);

        $this->actingAs($this->profesional)->post(route('solicitudes.tomar', $caso))
            ->assertRedirect(route('solicitudes.index').'#solicitud-'.$caso->id_caso)
            ->assertSessionHas('status', "El caso {$caso->codigo_expediente} quedó a tu cargo. Acuerda con el estudiante la primera cita y agéndala aquí.");

        $caso->refresh();
        $this->assertSame([Caso::ESTADO_ABIERTO, $this->profesional->id_usuario], [$caso->estado_caso, $caso->id_profesional]);
        $this->assertSame(1, Caso::nuevosDe($this->profesional)->count());

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->post(route('solicitudes.tomar', $caso))
            ->assertSessionHasErrors(['solicitud'], null, 'respuesta');
        $this->assertSame($this->profesional->id_usuario, $caso->fresh()->id_profesional);
    }

    public function test_el_menu_y_la_agenda_cuentan_lo_que_hay_por_atender(): void
    {
        $this->crearSolicitud($this->profesional);
        $this->crearSolicitud(null, 'Valentina Ospina');

        $this->assertSame(2, Caso::porAtenderDe($this->profesional));

        $this->actingAs($this->profesional)->get(route('citas.agenda'))
            ->assertSee('Tienes 2 solicitudes por atender');
    }

    public function test_solo_los_profesionales_ven_las_solicitudes(): void
    {
        $this->actingAs($this->crearUsuarioConRol('Persona'))->get(route('solicitudes.index'))->assertRedirect(route('estudiante.home'));
    }
}

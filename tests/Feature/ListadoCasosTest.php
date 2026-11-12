<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Listados de casos del profesional: Salud Mental y Expedientes.
 */
class ListadoCasosTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Restrepo']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $profesional, string $estudiante, array $datos = [], string $servicio = 'Salud Mental'): Caso
    {
        $persona = $this->crearUsuarioConRol('Persona', ['nombre_completo' => $estudiante])->persona;
        $solicitud = $persona->solicitudes()->create(['tipo_servicio' => $servicio, 'descripcion' => "Motivo de $estudiante."]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(array_merge(['estado_caso' => 'Abierto', 'id_profesional' => $profesional->id_usuario], $datos));

        // RN-01: el caso ya tiene consentimiento firmado, así que se puede atender.
        $this->crearConsentimientoVigente($caso);

        return $caso->fresh();
    }

    private function remitir(Caso $caso, User $de, User $a): void
    {
        $this->actingAs($de)->post(route('remisiones.store', $caso), [
            'destino_remision' => 'Interna',
            'id_profesional_destino' => $a->id_usuario,
            'motivo' => 'Cambio de profesional.',
        ])->assertSessionHasNoErrors();
    }

    /**
     * DADO un profesional con casos,
     * CUANDO abre Salud Mental,
     * ENTONCES ve sus casos reales y cada uno lleva a su expediente.
     */
    public function test_lista_los_casos_del_profesional_con_enlace_al_expediente(): void
    {
        $caso = $this->crearCaso($this->profesional, 'Samuel Valencia', ['nivel_riesgo' => 'Alto']);
        $ajeno = $this->crearCaso($this->crearUsuarioConRol('Profesional'), 'Estudiante Ajeno');

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertOk()
            ->assertSee($caso->codigo_expediente)
            ->assertSee('Samuel Valencia')
            ->assertSee(route('casos.show', $caso), false)
            ->assertDontSee('Estudiante Ajeno')
            ->assertDontSee(route('casos.show', $ajeno), false)
            ->assertDontSee('Daniela Herrera');
    }

    public function test_salud_mental_solo_muestra_ese_servicio_y_expedientes_muestra_todos(): void
    {
        $this->crearCaso($this->profesional, 'Samuel Valencia');
        $this->crearCaso($this->profesional, 'Laura Inclusión', servicio: 'Inclusión');

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertSee('Samuel Valencia')
            ->assertDontSee('Laura Inclusión');

        $this->actingAs($this->profesional)->get(route('profesional.expedientes'))
            ->assertOk()
            ->assertSee('Samuel Valencia')
            ->assertSee('Laura Inclusión')
            ->assertSee('2 expedientes a tu cargo o remitidos por ti');
    }

    public function test_muestra_los_casos_remitidos_y_los_recibidos_por_remision(): void
    {
        $otro = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Andrés Gómez']);
        $remitido = $this->crearCaso($this->profesional, 'Caso Que Remití');
        $recibido = $this->crearCaso($otro, 'Caso Que Recibí');

        $this->remitir($remitido, $this->profesional, $otro);
        $this->remitir($recibido, $otro, $this->profesional);

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertSee('Caso Que Remití')
            ->assertSee('Remitido por ti')
            ->assertDontSee('Cerrado y remitido')
            ->assertSee('Caso Que Recibí')
            ->assertSee('Remisión');

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental', ['estado' => 'Remitidos por mí']))
            ->assertSee('Caso Que Remití')
            ->assertDontSee('Caso Que Recibí');
    }

    public function test_busca_por_nombre_documento_o_codigo(): void
    {
        $samuel = $this->crearCaso($this->profesional, 'Samuel Valencia');
        $this->crearCaso($this->profesional, 'Daniela Herrera');

        foreach (['samuel', $samuel->codigo_expediente, $samuel->solicitud->persona->numero_documento] as $busqueda) {
            $this->actingAs($this->profesional)->get(route('profesional.salud-mental', ['q' => $busqueda]))
                ->assertSee('Samuel Valencia')
                ->assertDontSee('Daniela Herrera');
        }

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental', ['q' => 'nadie']))
            ->assertSee('Ningún caso coincide con la búsqueda');
    }

    public function test_filtra_por_estado_y_riesgo(): void
    {
        $this->crearCaso($this->profesional, 'Abierto Alto', ['nivel_riesgo' => 'Alto']);
        $this->crearCaso($this->profesional, 'Seguimiento Bajo', ['estado_caso' => 'En Seguimiento', 'nivel_riesgo' => 'Bajo']);

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental', ['estado' => 'En Seguimiento']))
            ->assertSee('Seguimiento Bajo')
            ->assertDontSee('Abierto Alto');

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental', ['riesgo' => 'Alto']))
            ->assertSee('Abierto Alto')
            ->assertDontSee('Seguimiento Bajo');

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental', ['estado' => 'Inventado']))
            ->assertSessionHasErrors('estado');
    }

    public function test_el_resumen_cuenta_los_casos_reales(): void
    {
        $this->crearCaso($this->profesional, 'Uno', ['nivel_riesgo' => 'Alto']);
        $this->crearCaso($this->profesional, 'Dos', ['estado_caso' => 'En Seguimiento']);
        $this->crearCaso($this->profesional, 'Tres', ['estado_caso' => 'Cerrado']);

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertSee('3 casos · 2 activos a tu cargo');
    }

    public function test_muestra_la_proxima_cita_y_las_sesiones_realizadas(): void
    {
        $caso = $this->crearCaso($this->profesional, 'Samuel Valencia');
        foreach ([['2026-10-01 09:00:00', 'Asistió'], ['2026-10-02 09:00:00', 'Asistió'], ['2026-10-08 14:00:00', 'Pendiente']] as [$fecha, $asistencia]) {
            Actuacion::create([
                'id_caso' => $caso->id_caso,
                'id_profesional' => $this->profesional->id_usuario,
                'tipo_actuacion' => Actuacion::TIPO_CITA,
                'fecha_hora' => $fecha,
                'estado_asistencia' => $asistencia,
            ]);
        }

        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertSee('2 sesiones')
            ->assertSee('Cita: 2026-10-08 2:00 pm');
    }

    public function test_sin_casos_muestra_el_estado_vacio(): void
    {
        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertOk()
            ->assertSee('Aún no tienes casos de Salud Mental');

        $this->actingAs($this->profesional)->get(route('profesional.expedientes'))
            ->assertOk()
            ->assertSee('Aún no tienes expedientes');
    }

    public function test_el_inicio_enlaza_a_nueva_consulta_y_expedientes(): void
    {
        $this->actingAs($this->profesional)->get(route('profesional.home'))
            ->assertOk()
            ->assertSee(route('casos.create', ['servicio' => 'Salud Mental']), false)
            ->assertSee(route('profesional.expedientes'), false);
    }
}

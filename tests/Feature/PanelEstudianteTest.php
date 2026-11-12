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
 * Inicio y "Mis citas" del estudiante: sus citas, el estado de sus solicitudes
 * y las tareas terapéuticas que le asignó su profesional (HU-013).
 */
class PanelEstudianteTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $estudiante;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->estudiante = $this->crearUsuarioConRol('Persona');
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Laura Gómez']);
        $this->caso = $this->crearCaso($this->estudiante);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $estudiante, string $estado = 'Abierto', string $servicio = 'Salud Mental'): Caso
    {
        $solicitud = $estudiante->persona->solicitudes()->create([
            'tipo_servicio' => $servicio,
            'descripcion' => 'Ansiedad y crisis académica.',
        ]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $estado === Caso::ESTADO_PENDIENTE ? null : $this->profesional->id_usuario]);

        return $caso->fresh();
    }

    private function cita(Caso $caso, string $fechaHora, array $datos = []): Actuacion
    {
        return Actuacion::create(array_merge([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => $fechaHora,
        ], $datos));
    }

    private function tarea(Caso $caso, string $titulo, ?string $fechaLimite, string $estado = 'Pendiente de evaluación'): void
    {
        $caso->tareas()->create([
            'id_profesional' => $this->profesional->id_usuario,
            'titulo' => $titulo,
            'descripcion' => "Descripción de {$titulo}.",
            'fecha_limite' => $fechaLimite,
            'fecha_asignacion' => '2026-10-01 10:00:00',
            'estado' => $estado,
        ]);
    }

    public function test_el_inicio_muestra_las_tareas_pendientes_del_estudiante(): void
    {
        $this->tarea($this->caso, 'Diario de emociones', '2026-10-09');
        $this->tarea($this->caso, 'Respiración guiada', null);
        $this->tarea($this->caso, 'Tarea ya evaluada', '2026-10-08', 'Cumplida');
        $this->tarea($this->crearCaso($this->crearUsuarioConRol('Persona')), 'Tarea de otro estudiante', '2026-10-08');

        $this->actingAs($this->estudiante)
            ->get(route('estudiante.home'))
            ->assertOk()
            ->assertSeeInOrder(['Diario de emociones', 'Hasta el 2026-10-09', 'Respiración guiada', 'Sin fecha límite'])
            ->assertDontSee('Tarea ya evaluada')
            ->assertDontSee('Tarea de otro estudiante')
            ->assertDontSee('No tienes tareas pendientes');
    }

    public function test_el_filtro_de_dias_incluye_las_vencidas_y_excluye_las_lejanas(): void
    {
        $this->tarea($this->caso, 'Tarea vencida', '2026-10-02');
        $this->tarea($this->caso, 'Tarea de esta semana', '2026-10-12');
        $this->tarea($this->caso, 'Tarea del otro mes', '2026-11-20');
        $this->tarea($this->caso, 'Tarea sin fecha', null);

        $this->actingAs($this->estudiante)
            ->get(route('estudiante.home', ['tareas' => '7']))
            ->assertSee('Tarea vencida')
            ->assertSee('Venció el 2026-10-02')
            ->assertSee('Tarea de esta semana')
            ->assertDontSee('Tarea del otro mes')
            ->assertDontSee('Tarea sin fecha');

        $this->get(route('estudiante.home', ['tareas' => '30']))
            ->assertSee('Tarea de esta semana')
            ->assertDontSee('Tarea del otro mes');
    }

    public function test_el_inicio_sin_tareas_muestra_el_estado_vacio(): void
    {
        $this->actingAs($this->estudiante)
            ->get(route('estudiante.home'))
            ->assertOk()
            ->assertSee('No tienes tareas pendientes');
    }

    public function test_el_inicio_muestra_la_proxima_cita(): void
    {
        $this->cita($this->caso, '2026-10-01 09:00:00');
        $this->cita($this->caso, '2026-10-20 15:00:00');
        $this->cita($this->caso, '2026-10-08 10:00:00');

        $this->actingAs($this->estudiante)
            ->get(route('estudiante.home'))
            ->assertSee('Tu próxima cita')
            ->assertSee('8 de octubre · 10:00 am')
            ->assertSee('Con Laura Gómez');
    }

    public function test_mis_citas_sin_solicitudes_muestra_el_estado_vacio(): void
    {
        $otro = $this->crearUsuarioConRol('Persona');

        $this->actingAs($otro)
            ->get(route('citas.miscitas'))
            ->assertOk()
            ->assertSee('Aún no tienes citas');
    }

    public function test_mis_citas_muestra_proximas_citas_historial_y_solicitudes(): void
    {
        $this->cita($this->caso, '2026-10-08 10:00:00', ['descripcion' => 'Trae tu diario de emociones.']);
        $this->cita($this->caso, '2026-09-29 09:00:00', ['estado_asistencia' => Actuacion::ASISTIO]);
        $this->cita($this->caso, '2026-09-22 09:00:00', ['estado_asistencia' => Actuacion::NO_ASISTIO]);
        $pendiente = $this->crearCaso($this->estudiante, Caso::ESTADO_PENDIENTE, 'Inclusión');
        $pendiente->solicitud->update(['fecha_preferida' => '2026-10-09', 'hora_preferida' => '14:00', 'modalidad' => 'Virtual']);

        $this->actingAs($this->estudiante)
            ->get(route('citas.miscitas'))
            ->assertOk()
            ->assertSee('Trae tu diario de emociones.')
            ->assertSee($this->caso->codigo_expediente)
            ->assertSee('En atención')
            ->assertSee('Laura Gómez')
            ->assertSeeInOrder(['2026-09-29', 'Asistió', '2026-09-22', 'No asistió'])
            ->assertSee($pendiente->codigo_expediente)
            ->assertSee('Cita pendiente')
            // Las solicitudes anteriores a la revisión del 06/10/2026 conservan su preferencia.
            ->assertSee('2026-10-09 · 2:00 pm · Virtual')
            ->assertSee('Por asignar');
    }

    public function test_mis_citas_no_muestra_casos_de_otros_estudiantes(): void
    {
        $ajeno = $this->crearCaso($this->crearUsuarioConRol('Persona'));

        $this->actingAs($this->estudiante)
            ->get(route('citas.miscitas'))
            ->assertSee($this->caso->codigo_expediente)
            ->assertDontSee($ajeno->codigo_expediente);
    }
}

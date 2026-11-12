<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\TareaTerapeutica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-013: Asignar tareas terapéuticas al estudiante.
 */
class TareaTerapeuticaTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso = $this->crearCaso($this->profesional, 'Abierto');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $profesional, string $estado): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona');
        $solicitud = $estudiante->persona->solicitudes()->create([
            'tipo_servicio' => 'Salud Mental',
            'descripcion' => 'Ansiedad y crisis académica.',
        ]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $profesional->id_usuario]);

        if ($estado === 'Cerrado') {
            DB::table('casos')->where('id_caso', $caso->id_caso)->update(['fecha_cierre' => now()]);
        }

        // RN-01: el caso ya tiene consentimiento firmado, así que se puede atender.
        $this->crearConsentimientoVigente($caso);

        return $caso->fresh();
    }

    private function asignar(array $datos, ?Caso $caso = null, ?User $como = null)
    {
        $caso ??= $this->caso;

        return $this->actingAs($como ?? $this->profesional)
            ->from(route('casos.show', $caso))
            ->post(route('tareas.store', $caso), $datos);
    }

    /**
     * DADO un caso activo,
     * CUANDO asigno una tarea con título y descripción,
     * ENTONCES queda en el expediente como "Pendiente de evaluación".
     */
    public function test_asigna_la_tarea_pendiente_de_evaluacion(): void
    {
        $this->asignar(['titulo' => '  Diario de emociones ', 'descripcion' => 'Anotar cada noche tres emociones del día.', 'fecha_limite' => '2026-10-13'])
            ->assertRedirect(route('casos.show', $this->caso).'#tareas')
            ->assertSessionHas('status', 'Tarea "Diario de emociones" asignada. Queda pendiente de evaluación.');

        $tarea = TareaTerapeutica::sole();
        $this->assertSame($this->caso->id_caso, $tarea->id_caso);
        $this->assertSame($this->profesional->id_usuario, $tarea->id_profesional);
        $this->assertSame('Diario de emociones', $tarea->titulo);
        $this->assertSame(TareaTerapeutica::ESTADO_PENDIENTE, $tarea->fresh()->estado);
        $this->assertSame('2026-10-13', $tarea->fecha_limite->toDateString());
        $this->assertSame('2026-10-06', $tarea->fecha_asignacion->toDateString());

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Diario de emociones')
            ->assertSee('Anotar cada noche tres emociones del día.')
            ->assertSee('Pendiente de evaluación')
            ->assertSee('Fecha límite: 2026-10-13')
            ->assertDontSee('No hay tareas terapéuticas asignadas a este expediente.');
    }

    public function test_la_fecha_limite_es_opcional(): void
    {
        $this->asignar(['titulo' => 'Respiración', 'descripcion' => 'Ejercicio 4-7-8 dos veces al día.'])
            ->assertSessionHasNoErrors();

        $this->assertNull(TareaTerapeutica::sole()->fecha_limite);
    }

    public function test_titulo_y_descripcion_son_obligatorios(): void
    {
        $this->asignar(['titulo' => '   ', 'descripcion' => ''])
            ->assertSessionHasErrorsIn('tarea', [
                'titulo' => 'El título de la actividad es obligatorio.',
                'descripcion' => 'La descripción de la actividad es obligatoria.',
            ]);

        $this->assertSame(0, TareaTerapeutica::count());

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('id="titulo-error"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_valida_largos_y_fecha_limite(): void
    {
        $this->asignar(['titulo' => str_repeat('a', 151), 'descripcion' => 'Ok', 'fecha_limite' => '2026-10-05'])
            ->assertSessionHasErrorsIn('tarea', [
                'titulo' => 'El título no puede superar los 150 caracteres.',
                'fecha_limite' => 'La fecha límite no puede ser anterior a hoy.',
            ]);

        $this->assertSame(0, TareaTerapeutica::count());
    }

    public function test_no_asigna_tareas_en_casos_no_activos(): void
    {
        $cerrado = $this->crearCaso($this->profesional, 'Cerrado');

        $this->asignar(['titulo' => 'Respiración', 'descripcion' => 'Ejercicio diario.'], $cerrado)
            ->assertSessionHas('error', "Solo se pueden asignar tareas en casos activos. El caso {$cerrado->codigo_expediente} está en estado \"Cerrado\".");

        $this->actingAs($this->profesional)->get(route('casos.show', $cerrado))
            ->assertSee('solo se pueden asignar tareas en casos activos')
            ->assertDontSee(route('tareas.store', $cerrado), false);

        $this->assertSame(0, TareaTerapeutica::count());
    }

    public function test_admite_casos_en_seguimiento(): void
    {
        $seguimiento = $this->crearCaso($this->profesional, 'En Seguimiento');

        $this->asignar(['titulo' => 'Respiración', 'descripcion' => 'Ejercicio diario.'], $seguimiento)
            ->assertSessionHasNoErrors();

        $this->assertSame($seguimiento->id_caso, TareaTerapeutica::sole()->id_caso);
    }

    public function test_solo_el_profesional_del_caso_asigna_tareas(): void
    {
        $datos = ['titulo' => 'Respiración', 'descripcion' => 'Ejercicio diario.'];

        $this->asignar($datos, como: $this->crearUsuarioConRol('Profesional'))->assertForbidden();
        $this->asignar($datos, como: $this->crearUsuarioConRol('Persona'))->assertRedirect(route('estudiante.home'));

        auth()->logout();
        $this->post(route('tareas.store', $this->caso), $datos)->assertRedirect(route('login'));

        $this->assertSame(0, TareaTerapeutica::count());
    }

    public function test_las_tareas_se_listan_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $this->asignar(['titulo' => 'Primera tarea', 'descripcion' => 'Uno.']);
        Carbon::setTestNow(Carbon::parse('2026-10-07 09:00:00', Actuacion::ZONA_HORARIA));
        $this->asignar(['titulo' => 'Segunda tarea', 'descripcion' => 'Dos.']);

        $html = $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))->getContent();

        $titulo = fn (string $texto) => strpos($html, 'break-words">'.$texto.'</p>');

        $this->assertNotFalse($titulo('Primera tarea'));
        $this->assertLessThan($titulo('Primera tarea'), $titulo('Segunda tarea'));
    }
}

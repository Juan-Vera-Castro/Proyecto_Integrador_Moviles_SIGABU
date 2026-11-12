<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Desistimiento;
use App\Models\TareaTerapeutica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Desistimiento de la atención: la persona decide no continuar o no contar lo sucedido. Se
 * registra en el expediente, cierra el caso y deja el formato para firmar (respaldo legal).
 */
class DesistimientoTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Muñoz']);
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Daniel Henao', 'numero_documento' => '1116300101', 'tipo_documento' => 'CC']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function desistir(array $cambios = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)->from(route('casos.show', $this->caso))
            ->post(route('desistimientos.store', $this->caso), array_merge(['motivo' => 'No desea continuar la atención', 'confirmacion' => '1'], $cambios));
    }

    /**
     * DADO un caso activo con citas y tareas pendientes,
     * CUANDO el profesional registra el desistimiento,
     * ENTONCES el caso se cierra, lo pendiente se cancela y queda el registro inmutable y auditado.
     */
    public function test_registra_el_desistimiento_y_cierra_el_caso(): void
    {
        $proxima = Actuacion::create(['id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'tipo_actuacion' => 'Consulta', 'fecha_hora' => '2026-10-23 09:00:00']);
        $pasada = Actuacion::create(['id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'tipo_actuacion' => 'Consulta', 'fecha_hora' => '2026-10-14 09:00:00']);
        $tarea = $this->caso->tareas()->create(['id_profesional' => $this->profesional->id_usuario, 'titulo' => 'Diario', 'descripcion' => 'x', 'fecha_asignacion' => '2026-10-14 10:00:00']);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))->assertSee('Registrar desistimiento y cerrar el caso');

        $this->desistir(['observaciones' => 'Refiere que prefiere no hablar del tema por ahora.'])
            ->assertRedirect(route('casos.show', $this->caso).'#desistimiento')
            ->assertSessionHasNoErrors();

        $desistimiento = Desistimiento::sole();
        $this->assertSame(['Daniel Henao', 'CC', '1116300101', 'Carolina Muñoz', 'POR-ASIGNAR'], [
            $desistimiento->estudiante_nombre, $desistimiento->estudiante_tipo_documento, $desistimiento->estudiante_numero_documento,
            $desistimiento->profesional_nombre, $desistimiento->formato_codigo,
        ]);

        $this->caso->refresh();
        $this->assertSame([Caso::ESTADO_CERRADO, '2026-10-21'], [$this->caso->estado_caso, $this->caso->fecha_cierre->format('Y-m-d')]);
        $this->assertSame(Actuacion::CANCELADA, $proxima->fresh()->estado_asistencia);
        $this->assertSame(Actuacion::NO_ASISTIO, $pasada->fresh()->estado_asistencia);
        $this->assertSame(TareaTerapeutica::ESTADO_CANCELADA, $tarea->fresh()->estado);
        $this->assertSame(1, AuditoriaExpediente::where('accion', 'desistimiento_registrado')->count());

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('No desea continuar la atención')
            ->assertSee(route('desistimientos.pdf', $desistimiento), false)
            ->assertDontSee('Registrar desistimiento y cerrar el caso');

        $this->expectException(\Throwable::class);
        DB::table('desistimientos')->update(['motivo' => 'Otro']);
    }

    public function test_descarga_el_formato_en_pdf(): void
    {
        $this->desistir();
        $desistimiento = Desistimiento::sole();

        $this->actingAs($this->profesional)->get(route('desistimientos.pdf', $desistimiento))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, AuditoriaExpediente::where('accion', 'documento_desistimiento_pdf')->count());

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('desistimientos.pdf', $desistimiento))->assertForbidden();
    }

    public function test_valida_el_desistimiento(): void
    {
        $this->desistir(['motivo' => '', 'confirmacion' => null])->assertSessionHasErrorsIn('desistimiento', ['motivo', 'confirmacion']);
        $this->desistir(['motivo' => 'Otro'])->assertSessionHasErrorsIn('desistimiento', ['observaciones']);

        $this->assertSame(0, Desistimiento::count());
        $this->assertTrue($this->caso->fresh()->estaActivo());
    }

    public function test_solo_el_profesional_del_caso_y_en_un_caso_activo(): void
    {
        $this->desistir(como: $this->crearUsuarioConRol('Profesional'))->assertForbidden();

        $this->caso->update(['estado_caso' => Caso::ESTADO_CERRADO]);
        $this->desistir()->assertSessionHasErrorsIn('desistimiento', ['motivo']);

        $this->assertSame(0, Desistimiento::count());
    }

    public function test_el_boton_de_la_lista_lleva_a_los_casos_activos(): void
    {
        $this->actingAs($this->profesional)->get(route('profesional.salud-mental'))
            ->assertSee('Elige el caso en el que la persona desiste')
            ->assertSee(route('casos.show', $this->caso).'#desistimiento', false);
    }
}

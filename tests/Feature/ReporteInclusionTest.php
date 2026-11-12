<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AjusteRazonable;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-020: el profesional de Inclusión genera el reporte de seguimiento de un caso, un PDF con
 * la información consolidada del proceso (ajustes razonables y su historial de seguimiento).
 */
class ReporteInclusionTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Paula Vargas']);
        $this->caso = $this->crearCaso('Inclusión');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(string $servicio): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Luis Ortiz']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => $servicio]);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);

        return $caso->fresh();
    }

    /**
     * Registra un ajuste con dos seguimientos por las rutas de HU-018 y HU-019.
     */
    private function conHistorial(): AjusteRazonable
    {
        $this->actingAs($this->profesional)->post(route('ajustes.store', $this->caso), [
            'tipo' => 'Académico o de evaluación',
            'barrera' => 'Baja visión: dificultad para leer material impreso.',
            'descripcion' => 'Material digital ampliado y 30 minutos adicionales en evaluaciones.',
            'responsable' => 'Docentes de Cálculo II',
            'fecha_inicio' => '2026-10-01',
        ])->assertSessionHasNoErrors();
        $ajuste = AjusteRazonable::sole();

        foreach ([
            ['fecha' => '2026-10-10', 'cumplimiento' => 'Se cumple parcialmente', 'observaciones' => 'Dos de tres docentes entregan el material ampliado.'],
            ['fecha' => '2026-10-18', 'cumplimiento' => 'Se cumple', 'observaciones' => 'Los tres docentes ya aplican el ajuste.', 'estado_resultante' => 'Cumplido'],
        ] as $seguimiento) {
            $this->actingAs($this->profesional)->post(route('ajustes.seguimiento', $ajuste), $seguimiento)->assertSessionHasNoErrors();
        }

        return $ajuste;
    }

    /**
     * Captura los datos con que se arma el PDF y devuelve el HTML del documento.
     */
    private function documento(callable $peticion): string
    {
        $datos = null;
        View::composer('documentos.reporte-inclusion', function ($vista) use (&$datos) {
            $datos ??= $vista->getData();
        });

        $peticion();

        $this->assertNotNull($datos, 'No se generó el documento.');
        $datos['logo'] = 'data:image/jpeg;base64,';

        return view('documentos.reporte-inclusion', $datos)->render();
    }

    /**
     * DADO un caso con historial de seguimiento,
     * CUANDO genero el reporte,
     * ENTONCES se produce un documento con la información consolidada del proceso.
     */
    public function test_genera_el_reporte_con_la_informacion_consolidada(): void
    {
        $this->conHistorial();

        $html = $this->documento(function () {
            $respuesta = $this->actingAs($this->profesional)->get(route('casos.reporte-inclusion', $this->caso))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->assertStringStartsWith('%PDF', $respuesta->getContent());
            $this->assertStringContainsString('Reporte-seguimiento-'.$this->caso->codigo_expediente, $respuesta->headers->get('Content-Disposition'));
        });

        $this->assertStringContainsString($this->caso->codigo_expediente, $html);
        $this->assertStringContainsString('Luis Ortiz', $html);
        $this->assertStringContainsString('Paula Vargas', $html);
        $this->assertStringContainsString('1 en total', $html);
        $this->assertStringContainsString('cumplido: 1', $html);
        $this->assertStringContainsString('18/10/2026 · Se cumple', $html);
        $this->assertMatchesRegularExpression('/10\/10\/2026.*Se cumple parcialmente.*Dos de tres docentes.*18\/10\/2026.*Los tres docentes ya aplican el ajuste\..*pasó a "Cumplido"/s', $html);

        $this->assertDatabaseHas('auditoria_expedientes', [
            'id_caso' => $this->caso->id_caso,
            'id_usuario' => $this->profesional->id_usuario,
            'accion' => AuditoriaExpediente::REPORTE_INCLUSION_PDF,
        ]);
    }

    public function test_el_caso_y_el_expediente_ofrecen_la_descarga(): void
    {
        $enlace = route('casos.reporte-inclusion', $this->caso);
        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))->assertOk()->assertDontSee($enlace, false);

        $this->conHistorial();

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))->assertOk()->assertSee($enlace, false);
        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $this->caso))->assertOk()->assertSee($enlace, false);
    }

    public function test_un_ajuste_sin_seguimientos_sale_en_el_reporte(): void
    {
        $this->actingAs($this->profesional)->post(route('ajustes.store', $this->caso), [
            'tipo' => 'De tiempo',
            'barrera' => 'Fatiga por tratamiento médico.',
            'descripcion' => 'Plazos ampliados para entregas.',
            'fecha_inicio' => '2026-10-05',
        ]);

        $html = $this->documento(fn () => $this->actingAs($this->profesional)->get(route('casos.reporte-inclusion', $this->caso))->assertOk());

        $this->assertStringContainsString('De tiempo · Vigente', $html);
        $this->assertStringContainsString('Este ajuste todavía no tiene seguimientos.', $html);
    }

    public function test_solo_el_profesional_del_caso_y_solo_en_inclusion(): void
    {
        $this->conHistorial();

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('casos.reporte-inclusion', $this->caso))->assertNotFound();
        $this->actingAs($this->profesional)->get(route('casos.reporte-inclusion', $this->crearCaso('Salud Mental')))->assertNotFound();

        $this->assertSame(0, AuditoriaExpediente::where('accion', AuditoriaExpediente::REPORTE_INCLUSION_PDF)->count());
    }
}

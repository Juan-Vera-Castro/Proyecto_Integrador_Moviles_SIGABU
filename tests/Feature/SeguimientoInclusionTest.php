<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AjusteRazonable;
use App\Models\Caso;
use App\Models\SeguimientoAjuste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-018 y HU-019: el profesional de Inclusión registra ajustes razonables y da seguimiento
 * continuo a su cumplimiento; el historial refleja el avance con fecha y observaciones.
 */
class SeguimientoInclusionTest extends TestCase
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
        $solicitud = $this->crearUsuarioConRol('Persona')->persona->solicitudes()->create(['tipo_servicio' => $servicio]);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);

        return $caso->fresh();
    }

    private function registrarAjuste(array $cambios = [], ?Caso $caso = null)
    {
        return $this->actingAs($this->profesional)->from(route('casos.show', $caso ?? $this->caso))->post(route('ajustes.store', $caso ?? $this->caso), array_merge([
            'tipo' => 'Académico o de evaluación',
            'barrera' => 'Baja visión: dificultad para leer material impreso.',
            'descripcion' => 'Material digital ampliado y 30 minutos adicionales en evaluaciones.',
            'responsable' => 'Docentes de Cálculo II',
            'fecha_inicio' => '2026-10-01',
        ], $cambios));
    }

    private function seguimiento(AjusteRazonable $ajuste, array $cambios = [])
    {
        return $this->actingAs($this->profesional)->post(route('ajustes.seguimiento', $ajuste), array_merge([
            'fecha' => '2026-10-15',
            'cumplimiento' => 'Se cumple parcialmente',
            'observaciones' => 'Dos de tres docentes entregan el material ampliado.',
        ], $cambios));
    }

    /**
     * DADO un ajuste razonable registrado,
     * CUANDO registro un seguimiento,
     * ENTONCES el historial refleja el avance con fecha y observaciones.
     */
    public function test_el_historial_refleja_el_avance_con_fecha_y_observaciones(): void
    {
        $this->registrarAjuste()->assertRedirect(route('casos.show', $this->caso).'#ajustes')->assertSessionHasNoErrors();
        $ajuste = AjusteRazonable::sole();
        $this->assertSame(['Vigente', 'Docentes de Cálculo II'], [$ajuste->estado, $ajuste->responsable]);

        $this->seguimiento($ajuste)->assertRedirect(route('casos.show', $this->caso).'#ajuste-'.$ajuste->id_ajuste)->assertSessionHasNoErrors();
        $this->seguimiento($ajuste, ['fecha' => '2026-10-20', 'cumplimiento' => 'Se cumple', 'observaciones' => 'Los tres docentes ya aplican el ajuste.', 'estado_resultante' => 'Cumplido']);

        $historial = $ajuste->fresh()->seguimientos;
        $this->assertSame(['2026-10-15', '2026-10-20'], $historial->map(fn ($s) => $s->fecha->format('Y-m-d'))->all());
        $this->assertSame('Cumplido', $ajuste->fresh()->estado);
        $this->assertSame([null, 'Cumplido'], $historial->pluck('estado_resultante')->all());

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Ajustes razonables')
            ->assertSeeInOrder(['Historial de seguimiento', '2026-10-15', 'Se cumple parcialmente', 'Dos de tres docentes', '2026-10-20', 'Los tres docentes ya aplican el ajuste.'])
            ->assertSee('ajuste pasó a "Cumplido"', false);
    }

    public function test_el_historial_de_seguimiento_es_inmutable(): void
    {
        $this->registrarAjuste();
        $this->seguimiento(AjusteRazonable::sole());

        $this->expectException(\Throwable::class);
        DB::table('seguimientos_ajuste')->update(['observaciones' => 'cambio']);
    }

    public function test_valida_el_ajuste_y_el_seguimiento(): void
    {
        $this->registrarAjuste(['tipo' => 'Mágico', 'barrera' => '', 'descripcion' => ''])->assertSessionHasErrorsIn('ajuste', ['tipo', 'barrera', 'descripcion']);
        $this->assertSame(0, AjusteRazonable::count());

        $this->registrarAjuste();
        $ajuste = AjusteRazonable::sole();
        $bolsa = 'seguimiento-'.$ajuste->id_ajuste;

        $this->seguimiento($ajuste, ['fecha' => '2026-10-25'])->assertSessionHasErrorsIn($bolsa, ['fecha']);
        $this->seguimiento($ajuste, ['fecha' => '2026-09-30'])->assertSessionHasErrorsIn($bolsa, ['fecha']);
        $this->seguimiento($ajuste, ['cumplimiento' => 'Más o menos', 'observaciones' => ''])->assertSessionHasErrorsIn($bolsa, ['cumplimiento', 'observaciones']);
        $this->assertSame(0, SeguimientoAjuste::count());
    }

    public function test_solo_en_casos_de_inclusion_activos_y_del_profesional(): void
    {
        $this->registrarAjuste(caso: $this->crearCaso('Salud Mental'))->assertNotFound();
        $this->actingAs($this->profesional)->get(route('casos.show', $this->crearCaso('Salud Mental')))->assertDontSee('Ajustes razonables');

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->post(route('ajustes.store', $this->caso), ['tipo' => 'Otro'])->assertForbidden();

        $this->registrarAjuste();
        $ajuste = AjusteRazonable::sole();
        $this->caso->update(['estado_caso' => Caso::ESTADO_CERRADO]);
        $this->seguimiento($ajuste)->assertSessionHas('error');
        $this->assertSame(0, SeguimientoAjuste::count());
    }

    public function test_exige_el_consentimiento_cuando_esta_activa_la_exigencia(): void
    {
        $this->activarExigenciaConsentimiento(desde: null);
        \App\Models\VersionConsentimiento::where('estado', 'publicada')->first()?->modelo->update(['tipo_servicio' => 'Inclusión']);

        $this->registrarAjuste()->assertSessionHas('error');
        $this->assertSame(0, AjusteRazonable::count());

        $this->crearConsentimientoVigente($this->caso);
        $this->registrarAjuste()->assertSessionHasNoErrors();
        $this->assertSame(1, AjusteRazonable::count());
    }
}

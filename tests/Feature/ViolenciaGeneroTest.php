<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\User;
use App\Models\Vbg\Accion;
use App\Models\Vbg\Reporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Módulo de Violencias Basadas en Género dentro de SIGABU: el estudiante reporta (como
 * afectado, en nombre de otro o de forma anónima) y el equipo VBG gestiona cada caso.
 */
class ViolenciaGeneroTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $estudiante;

    private User $vbg;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Sofía Ríos']);
        $this->vbg = $this->crearUsuarioConRol('VBG', ['nombre_completo' => 'Valentina López']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function reportar(array $cambios = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->estudiante)->from(route('reportes.crear'))->post(route('reportes.store'), array_merge([
            'tipo_reportante' => 'Afectada',
            'relato' => 'Un compañero me envía mensajes intimidantes desde hace un mes.',
            'tipo_violencia' => 'Digital',
            'lugar' => 'Redes sociales',
            'fecha_hechos' => '2026-10-01',
            'desea_contacto' => '1',
            'medio_contacto' => 'Correo institucional en las tardes',
            'acepta_datos' => '1',
        ], $cambios));
    }

    private function crearReporte(): Reporte
    {
        $this->reportar()->assertSessionHasNoErrors();

        return Reporte::sole();
    }

    public function test_la_persona_afectada_reporta_y_ve_el_estado_de_su_reporte(): void
    {
        $this->actingAs($this->estudiante)->get(route('reportes.crear'))->assertOk()->assertSee('¿Quién está reportando?');

        $this->reportar()->assertRedirect(route('reportes.crear'))->assertSessionHas('reporte_enviado.codigo', 'VG-2026-0001');

        $reporte = Reporte::sole();
        $this->assertSame([$this->estudiante->persona->id_persona, 'Recibido', true], [$reporte->id_persona_reporta, $reporte->estado, $reporte->desea_contacto]);

        $this->actingAs($this->estudiante)->get(route('reportes.crear'))
            ->assertSee('Mis reportes')
            ->assertSee('VG-2026-0001');
    }

    /**
     * DADO un reporte anónimo,
     * ENTONCES no queda quién lo hizo ni un medio de contacto, aunque lleguen.
     */
    public function test_el_reporte_anonimo_no_guarda_quien_reporta(): void
    {
        $this->reportar(['tipo_reportante' => 'Anónimo'])->assertSessionHasNoErrors()->assertSessionHas('reporte_enviado.anonimo', true);

        $reporte = Reporte::sole();
        $this->assertNull($reporte->id_persona_reporta);
        $this->assertFalse($reporte->desea_contacto);
        $this->assertNull($reporte->medio_contacto);

        // La confirmación muestra el código una vez; después no aparece en "Mis reportes".
        $this->actingAs($this->estudiante)->get(route('reportes.crear'))->assertSee('VG-2026-0001');
        $this->actingAs($this->estudiante)->get(route('reportes.crear'))->assertDontSee('VG-2026-0001')->assertDontSee('Mis reportes');
    }

    public function test_el_reporte_de_un_tercero_exige_el_nombre_de_la_persona_afectada(): void
    {
        $this->reportar(['tipo_reportante' => 'Tercero'])->assertSessionHasErrors('afectado_nombre');

        $this->reportar(['tipo_reportante' => 'Tercero', 'afectado_nombre' => 'Laura M.', 'afectado_vinculo' => 'Estudiante de Derecho'])->assertSessionHasNoErrors();
        $this->assertSame('Laura M. (Estudiante de Derecho)', Reporte::sole()->etiquetaAfectado());
    }

    public function test_valida_el_reporte(): void
    {
        $this->reportar(['relato' => 'Muy corto', 'acepta_datos' => null, 'fecha_hechos' => '2026-12-01', 'medio_contacto' => ''])
            ->assertSessionHasErrors(['relato', 'acepta_datos', 'fecha_hechos', 'medio_contacto']);
        $this->reportar(['tipo_reportante' => 'Nadie'])->assertSessionHasErrors('tipo_reportante');

        $this->assertSame(0, Reporte::count());
    }

    /**
     * DADO un reporte recibido,
     * CUANDO un profesional VBG lo toma, lo valora y activa rutas,
     * ENTONCES el caso queda a su cargo y cada acción registrada e inmutable.
     */
    public function test_el_equipo_vbg_toma_valora_y_gestiona_el_caso(): void
    {
        $reporte = $this->crearReporte();

        $this->actingAs($this->vbg)->get(route('vbg.home'))
            ->assertOk()
            ->assertSee('VG-2026-0001 · Sofía Ríos')
            ->assertSee('Recibido');

        $this->actingAs($this->vbg)->post(route('vbg.reportes.tomar', $reporte))->assertRedirect(route('vbg.reportes.show', $reporte));
        $reporte->refresh();
        $this->assertSame(['En atención', $this->vbg->id_usuario], [$reporte->estado, $reporte->id_profesional]);

        $this->actingAs($this->vbg)->patch(route('vbg.reportes.valorar', $reporte), ['nivel_riesgo' => 'Alto', 'tipo_violencia' => 'Psicológica'])->assertSessionHasNoErrors();
        $this->actingAs($this->vbg)->post(route('vbg.reportes.accion', $reporte), ['tipo_accion' => 'Remisión jurídica', 'descripcion' => 'Se orienta con la abogada de Bienestar.'])->assertSessionHasNoErrors();

        $this->assertSame(['Alto', 'Psicológica'], [$reporte->fresh()->nivel_riesgo, $reporte->fresh()->tipo_violencia]);
        $this->assertSame(['Caso tomado', 'Remisión jurídica'], Accion::orderBy('fecha_hora')->pluck('tipo_accion')->all());

        $this->actingAs($this->vbg)->get(route('vbg.reportes.show', $reporte))
            ->assertSee('Un compañero me envía mensajes intimidantes')
            ->assertSee('Quiere ser contactado: Correo institucional en las tardes')
            ->assertSee('Se orienta con la abogada de Bienestar.');

        $this->expectException(\Throwable::class);
        DB::table('vbg_acciones')->update(['descripcion' => 'cambio']);
    }

    public function test_cerrar_y_reabrir_el_caso(): void
    {
        $reporte = $this->crearReporte();
        $this->actingAs($this->vbg)->post(route('vbg.reportes.tomar', $reporte));

        $this->actingAs($this->vbg)->post(route('vbg.reportes.cerrar', $reporte), ['motivo' => ''])->assertSessionHasErrorsIn('cierre', ['motivo']);
        $this->actingAs($this->vbg)->post(route('vbg.reportes.cerrar', $reporte), ['motivo' => 'La persona desiste de continuar.'])->assertSessionHasNoErrors();
        $this->assertSame('Cerrado', $reporte->fresh()->estado);

        // Cerrado no admite acciones.
        $this->actingAs($this->vbg)->post(route('vbg.reportes.accion', $reporte), ['tipo_accion' => 'Nota de seguimiento', 'descripcion' => 'x'])->assertSessionHasErrors('reporte');

        $this->actingAs($this->vbg)->post(route('vbg.reportes.reabrir', $reporte))->assertSessionHasNoErrors();
        $this->assertSame('En atención', $reporte->fresh()->estado);
        $this->assertSame(['Caso tomado', 'Caso cerrado', 'Caso reabierto'], Accion::orderBy('fecha_hora')->pluck('tipo_accion')->all());
    }

    public function test_solo_el_profesional_a_cargo_gestiona_y_nadie_toma_un_caso_ya_tomado(): void
    {
        $reporte = $this->crearReporte();
        $otro = $this->crearUsuarioConRol('VBG');

        $this->actingAs($this->vbg)->post(route('vbg.reportes.tomar', $reporte));
        $this->actingAs($otro)->post(route('vbg.reportes.tomar', $reporte))->assertSessionHasErrors('reporte');
        $this->actingAs($otro)->post(route('vbg.reportes.accion', $reporte), ['tipo_accion' => 'Nota de seguimiento', 'descripcion' => 'x'])->assertForbidden();

        $this->assertSame($this->vbg->id_usuario, $reporte->fresh()->id_profesional);
    }

    public function test_solo_el_equipo_vbg_ve_los_reportes(): void
    {
        $reporte = $this->crearReporte();

        $this->actingAs($this->estudiante)->get(route('vbg.reportes.show', $reporte))->assertRedirect(route('estudiante.home'));
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('vbg.home'))->assertRedirect(route('profesional.home'));
    }
}

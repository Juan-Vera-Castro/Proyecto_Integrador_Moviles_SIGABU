<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\User;
use App\Support\IndicadoresInstitucionales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-032: dashboards e indicadores institucionales. Los ven el administrador (directivo) y los
 * profesionales que él autorice, con datos agregados y anonimizados.
 */
class IndicadoresInstitucionalesTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $admin;

    private User $profesional;

    private Facultad $ingenieria;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->admin = $this->crearUsuarioConRol('Admin');
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Muñoz']);
        $this->ingenieria = Facultad::where('nombre', 'Facultad de Ingeniería')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function caso(string $nombre, string $documento, string $servicio, ?int $facultad, array $cambios = []): Caso
    {
        $solicitud = $this->crearUsuarioConRol('Persona', ['nombre_completo' => $nombre, 'numero_documento' => $documento])
            ->persona->solicitudes()->create(['tipo_servicio' => $servicio, 'id_facultad' => $facultad, 'jornada' => 'Diurna', 'franja_horaria' => 'Mañana']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(array_merge(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario], $cambios));

        return $caso->fresh();
    }

    /**
     * DADO datos de casos existentes,
     * CUANDO accedo al dashboard,
     * ENTONCES visualizo indicadores agregados y anonimizados.
     */
    public function test_muestra_indicadores_agregados_y_anonimizados(): void
    {
        // Seis casos de Ingeniería (grupo visible) y uno de Derecho (grupo pequeño: se suprime).
        $casos = collect(range(1, 6))->map(fn ($i) => $this->caso("Estudiante Ing {$i}", "90000000{$i}", 'Salud Mental', $this->ingenieria->id_facultad, ['nivel_riesgo' => 'Alto']));
        $derecho = Facultad::where('nombre', 'like', 'Facultad de Derecho%')->sole();
        $solitario = $this->caso('Ana Única Derecho', '777777777', 'Inclusión', $derecho->id_facultad);

        $respuesta = $this->actingAs($this->admin)->get(route('indicadores.index'))->assertOk();

        $respuesta->assertSee('Indicadores institucionales')
            ->assertSee('Solicitudes de atención por mes')
            ->assertSee('Solicitudes por facultad')
            ->assertSee('Ingeniería')
            ->assertSee('&lt;5', false)
            ->assertSee('oculto para proteger la identidad');

        // Agregado: 7 solicitudes y 7 casos activos en total.
        $this->assertMatchesRegularExpression('/Solicitudes de atención<\/p>\s*<p[^>]*>7<\/p>/', $respuesta->getContent());

        // Anonimizado: ningún nombre, documento ni código de expediente.
        foreach ([...$casos, $solitario] as $caso) {
            $respuesta->assertDontSee($caso->solicitud->persona->nombre_completo)
                ->assertDontSee($caso->solicitud->persona->numero_documento)
                ->assertDontSee($caso->codigo_expediente);
        }
        $respuesta->assertDontSee('Carolina Muñoz');
    }

    public function test_suprime_los_grupos_pequenos(): void
    {
        $this->assertSame(0, IndicadoresInstitucionales::suprimir(0));
        $this->assertNull(IndicadoresInstitucionales::suprimir(1));
        $this->assertNull(IndicadoresInstitucionales::suprimir(4));
        $this->assertSame(5, IndicadoresInstitucionales::suprimir(5));

        config(['sigabu.indicadores.minimo_por_grupo' => 3]);
        $this->assertSame(3, IndicadoresInstitucionales::suprimir(3));
    }

    public function test_filtra_por_periodo_y_servicio(): void
    {
        $this->caso('Salud Uno', '100000001', 'Salud Mental', $this->ingenieria->id_facultad);
        $this->caso('Inclusión Uno', '100000002', 'Inclusión', $this->ingenieria->id_facultad);

        $datos = (new IndicadoresInstitucionales(3, 'Inclusión'))->todo();
        $this->assertSame(1, $datos['kpis']['solicitudes']);
        $this->assertSame(['Inclusión'], array_keys($datos['tendencia']));
        $this->assertCount(3, $datos['meses']);
        $this->assertNull($datos['vbgPorTipo'], 'VBG y alimentos solo aparecen con todos los servicios.');

        $this->actingAs($this->admin)->get(route('indicadores.index', ['meses' => 12, 'servicio' => 'Inclusión']))
            ->assertOk()
            ->assertSee('<option value="12" selected>', false)
            ->assertDontSee('Reportes de VBG por tipo de violencia');

        // Valores fuera de la lista vuelven al predeterminado.
        $this->actingAs($this->admin)->get(route('indicadores.index', ['meses' => 99, 'servicio' => 'X']))->assertOk()->assertSee('<option value="6" selected>', false);
    }

    /**
     * Solo el directivo y los profesionales autorizados.
     */
    public function test_solo_el_admin_y_los_profesionales_autorizados_lo_ven(): void
    {
        $this->actingAs($this->profesional)->get(route('indicadores.index'))->assertForbidden();
        $this->actingAs($this->profesional)->get(route('profesional.home'))->assertDontSee(route('indicadores.index'), false);
        $this->actingAs($this->crearUsuarioConRol('Persona'))->get(route('indicadores.index'))->assertRedirect(route('estudiante.home'));
        $this->actingAs($this->crearUsuarioConRol('VBG'))->get(route('indicadores.index'))->assertRedirect(route('vbg.home'));

        // El administrador autoriza al profesional desde Administración.
        $this->actingAs($this->admin)->get(route('admin.administracion'))->assertSee('Dar acceso a indicadores');
        $this->actingAs($this->admin)->patch(route('admin.usuarios.indicadores', $this->profesional))->assertSessionHas('status');
        $this->assertTrue($this->profesional->fresh()->ve_indicadores);

        $this->actingAs($this->profesional->fresh())->get(route('indicadores.index'))->assertOk();
        $this->actingAs($this->profesional->fresh())->get(route('profesional.home'))->assertSee(route('indicadores.index'), false);

        // Y lo retira.
        $this->actingAs($this->admin)->patch(route('admin.usuarios.indicadores', $this->profesional));
        $this->actingAs($this->profesional->fresh())->get(route('indicadores.index'))->assertForbidden();

        // Solo se autoriza a profesionales.
        $this->actingAs($this->admin)->patch(route('admin.usuarios.indicadores', $this->crearUsuarioConRol('VBG')))->assertNotFound();
        $this->actingAs($this->profesional)->patch(route('admin.usuarios.indicadores', $this->profesional))->assertRedirect(route('profesional.home'));
    }

    public function test_sin_datos_el_tablero_abre_vacio(): void
    {
        $this->actingAs($this->admin)->get(route('indicadores.index'))
            ->assertOk()
            ->assertSee('Sin datos en el periodo.');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\RepositorioDocumento;
use App\Models\User;
use App\Models\Vbg\Reporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Todo lo que ofrece el menú de cada rol abre una pantalla que funciona, con datos reales.
 */
class PanelesTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', Actuacion::ZONA_HORARIA));
        $this->crearEsquemaSigabu();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function caso(User $profesional, string $servicio, string $nombre, array $cambios = []): Caso
    {
        $solicitud = $this->crearUsuarioConRol('Persona', ['nombre_completo' => $nombre])->persona->solicitudes()->create(['tipo_servicio' => $servicio]);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(array_merge(['estado_caso' => 'Abierto', 'id_profesional' => $profesional->id_usuario], $cambios));

        return $caso->fresh();
    }

    /**
     * Cada enlace del menú lateral de cada rol responde 200 (ninguno queda en "#").
     */
    public function test_todos_los_enlaces_del_menu_de_cada_rol_funcionan(): void
    {
        foreach (['Admin', 'Profesional', 'Alimentos', 'VBG', 'Persona'] as $rol) {
            $usuario = $this->crearUsuarioConRol($rol);
            $inicio = $this->actingAs($usuario)->get($usuario->getDashboardUrl())->assertOk();

            preg_match('/<aside.*?<\/aside>/s', $inicio->getContent(), $menu);
            $this->assertNotEmpty($menu, "El rol {$rol} no tiene menú lateral.");
            $this->assertStringNotContainsString('href="#"', $menu[0], "El menú del rol {$rol} tiene enlaces sin destino.");

            preg_match_all('/href="(http[^"]+)"/', $menu[0], $enlaces);
            $this->assertNotEmpty($enlaces[1]);

            foreach (array_unique($enlaces[1]) as $enlace) {
                $this->actingAs($usuario)->get($enlace)->assertOk();
            }
        }
    }

    public function test_el_inicio_del_profesional_muestra_sus_indicadores_reales(): void
    {
        $profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso($profesional, 'Salud Mental', 'Ana', ['nivel_riesgo' => 'Alto']);
        $this->caso($profesional, 'Inclusión', 'Beto', ['estado_caso' => 'En Seguimiento']);
        $this->caso($this->crearUsuarioConRol('Profesional'), 'Salud Mental', 'De otro');

        $respuesta = $this->actingAs($profesional)->get(route('profesional.home'))->assertOk();
        $this->assertMatchesRegularExpression('/leading-none">2<\/span>\s*<span[^>]*>Casos activos/', $respuesta->getContent());
        $this->assertMatchesRegularExpression('/leading-none">1<\/span>\s*<span[^>]*>Riesgo alto/', $respuesta->getContent());
        $respuesta->assertSee(route('citas.agenda'), false)->assertSee(route('profesional.vbg'), false)->assertSee(route('repositorio.index'), false);
    }

    public function test_inclusion_lista_los_casos_reales_de_inclusion(): void
    {
        $profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso($profesional, 'Inclusión', 'Carla Inclusión');
        $this->caso($profesional, 'Salud Mental', 'Diego Salud');

        $this->actingAs($profesional)->get(route('profesional.inclusion'))
            ->assertOk()
            ->assertSee('Inclusión Universitaria')
            ->assertSee('Carla Inclusión')
            ->assertDontSee('Diego Salud')
            ->assertSee(route('casos.create', ['servicio' => 'Inclusión']), false);

        $this->actingAs($profesional)->get(route('profesional.salud-mental'))->assertSee('Diego Salud')->assertDontSee('Carla Inclusión');
    }

    /**
     * El profesional activa la ruta VBG en nombre de un estudiante y sigue su estado.
     */
    public function test_el_profesional_reporta_a_vbg_y_ve_el_estado(): void
    {
        $profesional = $this->crearUsuarioConRol('Profesional');

        $this->actingAs($profesional)->post(route('profesional.vbg.store'), [
            'tipo_reportante' => 'Anónimo', // se ignora: el profesional reporta en nombre de otra persona
            'afectado_nombre' => 'Estudiante E-114',
            'relato' => 'La estudiante refiere hostigamiento de un docente en clase.',
            'desea_contacto' => '1',
            'medio_contacto' => 'correo institucional',
            'acepta_datos' => '1',
        ])->assertRedirect(route('profesional.vbg'))->assertSessionHasNoErrors();

        $reporte = Reporte::sole();
        $this->assertSame([Reporte::TERCERO, $profesional->persona->id_persona], [$reporte->tipo_reportante, $reporte->id_persona_reporta]);

        $this->actingAs($profesional)->get(route('profesional.vbg'))->assertSee($reporte->codigo)->assertSee('Recibido');
        $this->actingAs($this->crearUsuarioConRol('VBG'))->get(route('vbg.home'))->assertSee($reporte->codigo);
    }

    public function test_el_repositorio_lo_administra_el_admin_y_lo_consultan_los_equipos_y_estudiantes(): void
    {
        $admin = $this->crearUsuarioConRol('Admin');
        $publicar = fn (array $datos) => $this->actingAs($admin)->post(route('repositorio.store'), $datos + ['categoria' => 'Protocolo', 'audiencia' => 'Profesionales']);

        $publicar(['titulo' => 'Protocolo VBG', 'url' => 'https://uceva.edu.co/protocolo.pdf'])->assertSessionHasNoErrors();
        $publicar(['titulo' => 'Guía de ansiedad', 'url' => 'https://uceva.edu.co/ansiedad.pdf', 'categoria' => 'Material psicoeducativo', 'audiencia' => 'Todos'])->assertSessionHasNoErrors();
        $publicar(['titulo' => 'Inseguro', 'url' => 'http://sitio.com/x'])->assertSessionHasErrorsIn('documento', ['url']);

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('repositorio.index'))->assertSee('Protocolo VBG')->assertSee('Guía de ansiedad')->assertSee('Publicar documento');
        // El estudiante no publica.
        $this->actingAs($this->crearUsuarioConRol('Persona'))->post(route('repositorio.store'), ['titulo' => 'x'])->assertRedirect(route('estudiante.home'));

        // El estudiante solo ve lo publicado para todos.
        $estudiante = $this->crearUsuarioConRol('Persona');
        $this->actingAs($estudiante)->get(route('recursos.index'))->assertSee('Guía de ansiedad')->assertDontSee('Protocolo VBG');

        // Retirar no borra.
        $this->actingAs($admin)->patch(route('repositorio.estado', RepositorioDocumento::where('titulo', 'Guía de ansiedad')->sole()));
        $this->actingAs($estudiante)->get(route('recursos.index'))->assertDontSee('Guía de ansiedad');
        $this->assertSame(2, RepositorioDocumento::count());
    }

    /**
     * Recursos de bienestar solo muestra lo publicado por los profesionales o el administrador.
     */
    public function test_recursos_queda_vacio_si_nadie_ha_publicado(): void
    {
        $estudiante = $this->crearUsuarioConRol('Persona');

        $this->actingAs($estudiante)->get(route('recursos.index'))
            ->assertOk()
            ->assertSee('Aún no hay recursos publicados.')
            ->assertDontSee('Manejo de la ansiedad en época de exámenes')
            ->assertDontSee('Buscar recursos...');

        $this->actingAs($this->crearUsuarioConRol('Admin'))->post(route('repositorio.store'), [
            'titulo' => 'Guía de ansiedad', 'url' => 'https://uceva.edu.co/ansiedad.pdf', 'categoria' => 'Material psicoeducativo', 'audiencia' => 'Todos',
        ])->assertSessionHasNoErrors();

        $this->actingAs($estudiante)->get(route('recursos.index'))
            ->assertSee('Guía de ansiedad')
            ->assertSee('Abrir recurso')
            ->assertSee('https://uceva.edu.co/ansiedad.pdf', false)
            ->assertDontSee('Aún no hay recursos publicados.')
            ->assertDontSee('Meditación guiada para la concentración');
    }

    public function test_el_panel_del_admin_muestra_datos_reales(): void
    {
        $admin = $this->crearUsuarioConRol('Admin');
        $profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso($profesional, 'Salud Mental', 'Ana', ['nivel_riesgo' => 'Alto']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.auditoria'), false)
            ->assertSee(route('admin.analitica'), false)
            ->assertDontSee('Laura Torres');

        $this->actingAs($admin)->get(route('admin.global'))->assertOk()
            ->assertSee('Dashboard global')
            ->assertSee('Violencias de Género')
            ->assertSee('Donación Alimentaria')
            ->assertSee('Persona (estudiante)');

        $this->actingAs($admin)->get(route('admin.analitica'))->assertRedirect('/indicadores');
        $this->actingAs($admin)->get(route('indicadores.index'))->assertOk()->assertSee('Solicitudes por facultad')->assertSee('Solicitudes de atención por mes');
        $this->actingAs($admin)->get(route('admin.auditoria'))->assertOk()->assertSee('Auditoría de expedientes');
        $this->actingAs($profesional)->get(route('admin.auditoria'))->assertRedirect(route('profesional.home'));
    }
}

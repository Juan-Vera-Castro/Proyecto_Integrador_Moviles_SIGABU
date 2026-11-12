<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use App\Models\TareaTerapeutica;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * D-28 y D-29: cuándo se exige el consentimiento firmado. Un interruptor en el .env lo
 * activa; aunque esté activo, no bloquea si no se puede firmar (falta el texto o las claves),
 * y los casos abiertos antes de la fecha de activación quedan eximidos.
 */
class ActivacionConsentimientoTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private const SIN_CONSENTIMIENTO = 'Registra el consentimiento informado firmado antes de atender al estudiante.';

    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-13 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(string $abiertoEl = '2026-10-13 08:00:00', array $persona = []): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona', $persona);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad.']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);
        DB::table('casos')->where('id_caso', $caso->id_caso)->update(['fecha_apertura' => $abiertoEl]);

        return $caso->fresh();
    }

    private function asignarTarea(Caso $caso)
    {
        return $this->actingAs($this->profesional)->post(route('tareas.store', $caso), ['titulo' => 'Diario', 'descripcion' => 'Anotar emociones.']);
    }

    /**
     * DADO el interruptor apagado (valor por defecto),
     * CUANDO el profesional atiende un caso sin firma,
     * ENTONCES se permite, y el expediente dice que aún no es obligatorio.
     */
    public function test_con_el_interruptor_apagado_no_se_bloquea(): void
    {
        $caso = $this->crearCaso();

        $this->asignarTarea($caso)->assertSessionHas('status');
        $this->assertSame(1, TareaTerapeutica::count());

        $this->actingAs($this->profesional)->get(route('casos.show', $caso))
            ->assertDontSee('Atención bloqueada')
            ->assertSee('El consentimiento firmado todavía no es obligatorio en el sistema. El estudiante ya puede firmarlo desde su cuenta.');
    }

    public function test_un_rechazo_registrado_bloquea_aunque_el_interruptor_este_apagado(): void
    {
        $caso = $this->crearCaso();
        $version = VersionConsentimiento::create([
            'id_modelo' => ModeloConsentimiento::create(['codigo' => 'X', 'nombre' => 'X', 'tipo_servicio' => 'Salud Mental'])->id_modelo,
            'numero_version' => 1, 'texto' => 'Texto', 'estado' => 'publicada',
        ]);
        ConsentimientoAceptacion::create([
            'id_caso' => $caso->id_caso, 'id_version' => $version->id_version, 'decision' => 'Rechazado',
            'fecha_hora' => now(Actuacion::ZONA_HORARIA), 'id_profesional' => $this->profesional->id_usuario,
            'texto_presentado' => 'Texto', 'texto_sha256' => hash('sha256', 'Texto'),
        ]);

        $this->asignarTarea($caso)->assertSessionHas('error', 'El estudiante no aceptó el consentimiento informado el 2026-10-13 10:00: no se puede prestar la atención.');
        $this->assertSame(0, TareaTerapeutica::count());
    }

    public function test_con_el_interruptor_encendido_y_todo_listo_se_bloquea(): void
    {
        $this->activarExigenciaConsentimiento();
        $caso = $this->crearCaso();

        $this->asignarTarea($caso)->assertSessionHas('error', self::SIN_CONSENTIMIENTO);
        $this->assertSame(0, TareaTerapeutica::count());
    }

    /**
     * DADO el interruptor encendido pero sin las claves del almacenamiento,
     * CUANDO el profesional atiende,
     * ENTONCES no se bloquea, porque no habría forma de firmar, y se avisa qué falta.
     */
    public function test_sin_claves_de_almacenamiento_no_se_bloquea_y_avisa(): void
    {
        $this->activarExigenciaConsentimiento();
        config(['filesystems.disks.firmas.key' => null]);
        $caso = $this->crearCaso();

        $this->asignarTarea($caso)->assertSessionHas('status');

        $this->actingAs($this->profesional)->get(route('casos.show', $caso))
            ->assertDontSee('Atención bloqueada')
            ->assertSee('La firma aún no se exige en este caso porque falta lo siguiente: las claves del almacenamiento de firmas. Avisa al administrador.');
    }

    public function test_sin_texto_publicado_no_se_bloquea(): void
    {
        $this->activarExigenciaConsentimiento(publicarTexto: false);
        $caso = $this->crearCaso();

        $this->asignarTarea($caso)->assertSessionHas('status');
        $this->assertSame(['el texto de consentimiento publicado para Salud Mental'], $caso->fresh()->faltantesParaFirmar());
    }

    public function test_un_menor_sin_texto_de_menores_publicado_no_se_bloquea(): void
    {
        $this->activarExigenciaConsentimiento();
        $caso = $this->crearCaso(persona: ['fecha_nacimiento' => '2010-01-15']);

        $this->asignarTarea($caso)->assertSessionHas('status');
        $this->assertSame(['el texto de consentimiento publicado para Salud Mental (menores de edad)'], $caso->fresh()->faltantesParaFirmar());
    }

    /**
     * DADO el consentimiento exigido desde el 2026-10-13,
     * CUANDO se atiende un caso abierto antes de esa fecha,
     * ENTONCES está eximido; uno abierto ese día o después sí se bloquea (D-29).
     */
    public function test_los_casos_abiertos_antes_de_la_activacion_quedan_eximidos(): void
    {
        $this->activarExigenciaConsentimiento(desde: '2026-10-13');
        $anterior = $this->crearCaso('2026-10-12 17:00:00');
        $nuevo = $this->crearCaso('2026-10-13 08:00:00');

        $this->assertTrue($anterior->eximidoPorFecha());
        $this->asignarTarea($anterior)->assertSessionHas('status');
        $this->asignarTarea($nuevo)->assertSessionHas('error', self::SIN_CONSENTIMIENTO);
        $this->assertSame(1, TareaTerapeutica::count());

        $this->actingAs($this->profesional)->get(route('casos.show', $anterior))
            ->assertSee('Este caso se abrió antes de que se exigiera el consentimiento firmado: no es obligatorio, pero el estudiante puede firmarlo desde su cuenta.');
    }

    public function test_el_panel_del_administrador_muestra_si_se_exige_y_que_falta(): void
    {
        $admin = $this->crearUsuarioConRol('Admin');

        $this->actingAs($admin)->get(route('admin.consentimientos.index'))
            ->assertSee('Consentimiento firmado: todavía no se exige')
            ->assertSee('SIGABU_EXIGIR_CONSENTIMIENTO=true')
            ->assertSee('texto publicado de Inclusión para menores de edad')
            ->assertSee('claves S3 del almacenamiento de firmas');

        $this->activarExigenciaConsentimiento(desde: '2026-10-13');

        $this->actingAs($admin)->get(route('admin.consentimientos.index'))
            ->assertSee('Consentimiento firmado: se exige para atender')
            ->assertSee('(casos abiertos desde el 2026-10-13)')
            ->assertDontSee('claves S3 del almacenamiento de firmas')
            ->assertSee('texto publicado de Inclusión');
    }
}

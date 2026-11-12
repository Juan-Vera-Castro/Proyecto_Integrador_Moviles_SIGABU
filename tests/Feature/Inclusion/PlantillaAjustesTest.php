<?php

namespace Tests\Feature\Inclusion;

use App\Documentos\RecursosInclusion;
use App\Models\Actuacion;
use App\Models\AjusteRazonable;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\Inclusion\Evidencia;
use App\Models\Inclusion\Recurso;
use App\Models\ModeloConsentimiento;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * H5: recurso pedagógico y plantilla de ajustes, con datos ficticios.
 */
class PlantillaAjustesTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private User $persona;

    private Caso $caso;

    private Recurso $recurso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', Actuacion::ZONA_HORARIA));
        Storage::fake(Evidencia::DISCO);

        $this->crearEsquemaSigabu();
        RecursosInclusion::cargar();
        $this->recurso = Recurso::firstOrFail();

        $this->profesional = $this->crearProfesional('Inclusión');
        $this->persona = $this->crearUsuarioConRol('Persona');

        $solicitud = $this->persona->persona->solicitudes()->create(['tipo_servicio' => 'Inclusión', 'descripcion' => 'Prueba']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->update(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $this->profesional->id_usuario]);
        $this->caso = $this->caso->fresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearProfesional(string $area): User
    {
        $profesional = $this->crearUsuarioConRol('Profesional');
        $profesional->forceFill(['area_profesional' => $area])->save();

        return $profesional;
    }

    private function fila(array $cambios = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)->post(route('inclusion.plantilla.guardar', $this->caso), array_merge([
            'area' => 'Evaluación',
            'barrera' => 'Barrera ficticia',
            'descripcion' => 'Ajuste ficticio',
            'responsable' => 'Docente ficticio',
            'fecha_inicio' => '2026-11-06',
            'observaciones' => 'Observación ficticia',
            'id_recurso' => $this->recurso->id_recurso,
        ], $cambios));
    }

    public function test_el_catalogo_y_el_recurso_son_solo_para_inclusion_o_ambas(): void
    {
        $this->actingAs($this->profesional)->get(route('inclusion.recursos.index'))->assertOk()->assertSee('Estrategias de Accesibilidad Académica');
        $this->actingAs($this->profesional)->get(route('inclusion.recursos.show', $this->recurso))->assertOk()->assertSee('1. Introducción')->assertSee('PROPÓSITO')->assertSee('5.8 Plantilla de ajustes');
        $this->actingAs($this->crearProfesional('Ambas'))->get(route('inclusion.recursos.index'))->assertOk();

        foreach ([$this->crearProfesional('Salud Mental'), $this->persona, $this->crearUsuarioConRol('Admin')] as $sinPermiso) {
            $this->assertNotSame(200, $this->actingAs($sinPermiso)->get(route('inclusion.recursos.show', $this->recurso))->getStatusCode());
        }
    }

    public function test_el_menu_muestra_el_catalogo_solo_a_inclusion(): void
    {
        $this->actingAs($this->profesional)->get(route('profesional.inclusion'))->assertSee('Recursos de inclusión');
        $this->actingAs($this->crearProfesional('Salud Mental'))->get(route('profesional.salud-mental'))->assertDontSee('Recursos de inclusión');
    }

    public function test_una_fila_se_guarda_en_los_ajustes_del_caso_con_su_recurso(): void
    {
        $this->fila()->assertRedirect(route('inclusion.plantilla', $this->caso));

        $ajuste = AjusteRazonable::firstOrFail();

        $this->assertNull($ajuste->tipo);
        $this->assertSame('Evaluación', $ajuste->area);
        $this->assertSame('Evaluación', $ajuste->etiqueta());
        $this->assertSame('Observación ficticia', $ajuste->observaciones);
        $this->assertSame($this->recurso->id_recurso, $ajuste->id_recurso);
        $this->assertSame(AjusteRazonable::VIGENTE, $ajuste->estado);
        $this->assertTrue(AuditoriaExpediente::where('accion', AuditoriaExpediente::AJUSTE_PLANTILLA_REGISTRADO)->exists());
        $this->actingAs($this->profesional)->get(route('inclusion.plantilla', $this->caso))->assertOk()->assertSee('Barrera ficticia')->assertSee('Completado');
    }

    public function test_valida_los_campos_obligatorios(): void
    {
        $this->fila(['area' => 'Otra', 'barrera' => '', 'descripcion' => '', 'responsable' => '', 'fecha_inicio' => 'ayer'])
            ->assertSessionHasErrors(['area', 'barrera', 'descripcion', 'responsable', 'fecha_inicio']);

        $this->assertSame(0, AjusteRazonable::count());
    }

    public function test_la_evidencia_queda_con_la_fila_y_el_borrador_se_termina(): void
    {
        $id = $this->actingAs($this->profesional)
            ->postJson(route('inclusion.evidencias.store', $this->caso), ['archivo' => UploadedFile::fake()->create('rubrica.pdf', 20, 'application/pdf')])
            ->assertCreated()->json('id');
        Borrador::guardar($this->caso, $this->profesional, Borrador::PLANTILLA, ['barrera' => 'A medias', 'evidencias' => [$id]]);

        $this->actingAs($this->profesional)->get(route('inclusion.plantilla', $this->caso))->assertSee('A medias')->assertSee('rubrica.pdf');

        $this->fila(['evidencias' => [$id]])->assertSessionHasNoErrors();

        $this->assertSame([$id], AjusteRazonable::firstOrFail()->evidencias->pluck('id_evidencia')->all());
        $this->assertNull(Borrador::de($this->caso, $this->profesional, Borrador::PLANTILLA));
        $this->actingAs($this->profesional)->get(route('inclusion.plantilla', $this->caso))->assertSee('rubrica.pdf')->assertDontSee('A medias');
    }

    public function test_una_evidencia_ajena_no_deja_la_fila_a_medias(): void
    {
        $this->fila(['evidencias' => [(string) Str::uuid()]])->assertSessionHasErrors('evidencias');

        $this->assertSame(0, AjusteRazonable::count());
    }

    public function test_solo_el_asignado_ve_y_llena_la_plantilla(): void
    {
        $url = route('inclusion.plantilla', $this->caso);

        foreach ([$this->crearProfesional('Inclusión'), $this->crearProfesional('Salud Mental'), $this->crearUsuarioConRol('Admin'), $this->persona] as $sinPermiso) {
            $this->assertNotSame(200, $this->actingAs($sinPermiso)->get($url)->getStatusCode());
            $this->fila([], $sinPermiso);
        }

        $this->assertSame(0, AjusteRazonable::count());
    }

    public function test_un_caso_cerrado_no_admite_filas(): void
    {
        $this->caso->forceFill(['estado_caso' => Caso::ESTADO_CERRADO, 'fecha_cierre' => now()])->save();

        $this->fila()->assertSessionHas('error');
        $this->actingAs($this->profesional)->get(route('inclusion.plantilla', $this->caso))->assertOk()->assertDontSee('Agregar fila');

        $this->assertSame(0, AjusteRazonable::count());
    }

    public function test_un_consentimiento_revocado_bloquea_filas_nuevas(): void
    {
        $modelo = ModeloConsentimiento::create(['codigo' => 'IN-PRUEBA', 'nombre' => 'Prueba', 'tipo_servicio' => 'Inclusión', 'poblacion' => 'General', 'activo' => true]);
        $version = VersionConsentimiento::create(['id_modelo' => $modelo->id_modelo, 'numero_version' => 1, 'texto' => 'Texto', 'estado' => 'publicada']);
        $id = (string) Str::uuid();
        ConsentimientoAceptacion::create([
            'id_aceptacion' => $id, 'id_caso' => $this->caso->id_caso, 'id_version' => $version->id_version, 'decision' => 'Rechazado',
            'fecha_hora' => now(Actuacion::ZONA_HORARIA), 'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION, 'id_profesional' => $this->profesional->id_usuario,
            'texto_presentado' => 'Texto', 'texto_sha256' => hash('sha256', 'Texto'),
        ]);
        ConsentimientoDetalle::create(['id_aceptacion' => $id, 'es_revocacion' => true]);

        $this->fila()->assertSessionHas('error');

        $this->assertSame(0, AjusteRazonable::count());
    }

    public function test_la_pantalla_del_caso_enlaza_la_plantilla_y_conserva_los_ajustes_antiguos(): void
    {
        $antiguo = AjusteRazonable::create([
            'id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'tipo' => 'Metodológico',
            'barrera' => 'Barrera antigua', 'descripcion' => 'Ajuste antiguo', 'fecha_inicio' => '2026-10-01', 'fecha_registro' => '2026-10-01 10:00:00',
        ]);
        $this->fila();

        $respuesta = $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))->assertOk()
            ->assertSee('Abrir plantilla de ajustes')->assertSee('Metodológico')->assertSee('Barrera antigua')->assertSee('Evaluación')->assertSee('Observación ficticia');

        $this->assertStringNotContainsString('name="fecha_inicio"', $respuesta->getContent());

        $this->actingAs($this->profesional)->post(route('ajustes.seguimiento', $antiguo), [
            'fecha' => '2026-11-06', 'cumplimiento' => 'Se cumple', 'observaciones' => 'Avance ficticio',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $antiguo->seguimientos()->count());
    }

    public function test_las_filas_nuevas_tambien_usan_el_seguimiento_existente_y_el_reporte(): void
    {
        $this->fila();
        $ajuste = AjusteRazonable::firstOrFail();

        $this->actingAs($this->profesional)->post(route('ajustes.seguimiento', $ajuste), [
            'fecha' => '2026-11-06', 'cumplimiento' => 'Se cumple', 'observaciones' => 'Avance ficticio',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->profesional)->get(route('casos.reporte-inclusion', $this->caso))->assertOk();
        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $this->caso))->assertOk()->assertSee('Plantilla de ajustes')->assertSee('Evaluación · Vigente');
    }
}

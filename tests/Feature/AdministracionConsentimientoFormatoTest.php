<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\FormatoInstitucional;
use App\Models\ModeloConsentimiento;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Administración (G5): población y marcadores en los modelos de consentimiento (D-03, D-24)
 * y formatos institucionales editables (D-17, RN-13).
 */
class AdministracionConsentimientoFormatoTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->admin = $this->crearUsuarioConRol('Admin', ['nombre_completo' => 'Ana Admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearModelo(string $codigo, string $poblacion, bool $activo = true): ModeloConsentimiento
    {
        return ModeloConsentimiento::create(['codigo' => $codigo, 'nombre' => $codigo, 'tipo_servicio' => 'Salud Mental', 'poblacion' => $poblacion, 'activo' => $activo]);
    }

    /**
     * DADO un servicio con un modelo general activo,
     * CUANDO se crea el modelo de menores de edad,
     * ENTONCES queda activo también, porque es otra población.
     */
    public function test_cada_servicio_tiene_un_modelo_activo_por_poblacion(): void
    {
        $this->crearModelo('SM-ORIENTACION', 'General');

        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), [
            'codigo' => 'sm-menores', 'nombre' => 'Menores de edad', 'tipo_servicio' => 'Salud Mental', 'poblacion' => 'Menores',
        ])->assertSessionHasNoErrors();

        $menores = ModeloConsentimiento::where('codigo', 'SM-MENORES')->sole();
        $this->assertSame('Menores', $menores->poblacion);
        $this->assertTrue($menores->activo);

        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), [
            'codigo' => 'SM-MENORES-2', 'nombre' => 'Otro de menores', 'tipo_servicio' => 'Salud Mental', 'poblacion' => 'Menores',
        ])->assertSessionHas('status', fn (string $mensaje) => str_contains($mensaje, 'Quedó inactivo porque Salud Mental (Menores) ya usa "Menores de edad".'));

        $this->assertFalse(ModeloConsentimiento::where('codigo', 'SM-MENORES-2')->sole()->activo);
    }

    public function test_no_se_activan_dos_modelos_de_la_misma_poblacion(): void
    {
        $this->crearModelo('SM-MENORES', 'Menores');
        $otro = $this->crearModelo('SM-MENORES-2', 'Menores', activo: false);

        $this->actingAs($this->admin)->from(route('admin.consentimientos.show', $otro))->patch(route('admin.consentimientos.update', $otro), [
            'nombre' => 'Otro', 'tipo_servicio' => 'Salud Mental', 'poblacion' => 'Menores', 'activo' => '1',
        ])->assertSessionHasErrorsIn('modelo', ['activo' => 'El servicio Salud Mental ya tiene activo el modelo "SM-MENORES" para menores de edad. Desactívalo primero.']);

        $this->actingAs($this->admin)->patch(route('admin.consentimientos.update', $otro), [
            'nombre' => 'Otro', 'tipo_servicio' => 'Salud Mental', 'poblacion' => 'General', 'activo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($otro->fresh()->activo);
        $this->assertSame('General', $otro->fresh()->poblacion);
    }

    public function test_la_poblacion_debe_ser_valida_y_por_defecto_es_general(): void
    {
        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), [
            'codigo' => 'X1', 'nombre' => 'X', 'tipo_servicio' => 'Salud Mental', 'poblacion' => 'Adultos',
        ])->assertSessionHasErrorsIn('modelo', ['poblacion' => 'Indica si el consentimiento es general o para menores de edad.']);

        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), ['codigo' => 'X2', 'nombre' => 'X', 'tipo_servicio' => 'Inclusión'])
            ->assertSessionHasNoErrors();
        $this->assertSame('General', ModeloConsentimiento::where('codigo', 'X2')->sole()->poblacion);
    }

    /**
     * DADO un texto con un marcador mal escrito,
     * CUANDO el administrador guarda la versión,
     * ENTONCES el sistema lo rechaza indicando cuál marcador no existe (D-24).
     */
    public function test_el_texto_solo_admite_marcadores_conocidos(): void
    {
        $modelo = $this->crearModelo('SM-ORIENTACION', 'General');

        $this->actingAs($this->admin)->post(route('admin.consentimientos.versiones.store', $modelo), [
            'texto' => 'Yo, {nombre_estudiante}, con documento {numero_documento} y firma {firma}.',
        ])->assertSessionHasErrorsIn('version', ['texto' => 'Estos marcadores no existen: {numero_documento}, {firma}. Revisa la lista de marcadores disponibles.']);
        $this->assertSame(0, VersionConsentimiento::count());

        $this->actingAs($this->admin)->post(route('admin.consentimientos.versiones.store', $modelo), [
            'texto' => 'Yo, {nombre_estudiante}, con documento {tipo_documento_estudiante} {documento_estudiante} de {lugar_expedicion}.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, VersionConsentimiento::count());

        $this->actingAs($this->admin)->get(route('admin.consentimientos.show', $modelo))
            ->assertSee('Marcadores disponibles')
            ->assertSee('{acudiente_notificacion}')
            ->assertSee('Población general');
    }

    /**
     * DADO el formato de remisión configurado,
     * CUANDO Calidad lo actualiza desde Administración,
     * ENTONCES queda el nuevo código, versión y fecha, con su autor y auditoría (RN-13).
     */
    public function test_el_administrador_actualiza_el_formato_de_remision(): void
    {
        $formato = FormatoInstitucional::deClave('REMISION_PSICOLOGICA');

        $this->actingAs($this->admin)->get(route('admin.formatos.index'))
            ->assertOk()
            ->assertSee('REMISIÓN PSICOLÓGICA')
            ->assertSee('Vigente: GBU-GPGR-F-016 · VERSIÓN: 3 · FECHA: 18/ABR/2024');

        $this->actingAs($this->admin)->patch(route('admin.formatos.update', $formato), [
            'proceso' => 'GESTIÓN APOYO AL ESTUDIANTE', 'nombre' => 'REMISIÓN PSICOLÓGICA',
            'codigo' => ' gbu-gpgr-f-016 ', 'version' => '4', 'fecha_formato' => '2026-09-01',
        ])->assertRedirect(route('admin.formatos.index'))->assertSessionHasNoErrors();

        $formato->refresh();
        $this->assertSame(['GBU-GPGR-F-016', '4', '2026-09-01'], [$formato->codigo, $formato->version, $formato->fecha_formato->toDateString()]);
        $this->assertSame($this->admin->id_usuario, $formato->actualizado_por);

        $auditoria = AuditoriaExpediente::sole();
        $this->assertSame('formato_actualizado', $auditoria->accion);
        $this->assertSame('3', $auditoria->detalle['anterior']['version']);
        $this->assertSame('4', $auditoria->detalle['nuevo']['version']);

        $this->actingAs($this->admin)->get(route('admin.formatos.index'))
            ->assertSee('Vigente: GBU-GPGR-F-016 · VERSIÓN: 4 · FECHA: 01/SEP/2026')
            ->assertSee('actualizado por Ana Admin el 2026-10-13');
    }

    public function test_valida_los_datos_del_formato(): void
    {
        $formato = FormatoInstitucional::deClave('REMISION_PSICOLOGICA');

        $this->actingAs($this->admin)->patch(route('admin.formatos.update', $formato), [
            'proceso' => '', 'nombre' => 'X', 'codigo' => 'GBU GPGR/016', 'version' => 'v 3', 'fecha_formato' => '2027-01-01',
        ])->assertSessionHasErrorsIn('formato-'.$formato->id_formato, ['proceso', 'codigo', 'version', 'fecha_formato']);

        $this->assertSame('3', $formato->fresh()->version);
        $this->assertSame(0, AuditoriaExpediente::count());
    }

    public function test_solo_el_administrador_gestiona_los_formatos(): void
    {
        $formato = FormatoInstitucional::deClave('REMISION_PSICOLOGICA');
        $profesional = $this->crearUsuarioConRol('Profesional');

        $this->actingAs($profesional)->get(route('admin.formatos.index'))->assertRedirect(route('profesional.home'));
        $this->actingAs($profesional)->patch(route('admin.formatos.update', $formato), ['version' => '9'])->assertRedirect(route('profesional.home'));

        $this->assertSame('3', $formato->fresh()->version);
    }
}

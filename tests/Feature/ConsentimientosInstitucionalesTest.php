<?php

namespace Tests\Feature;

use App\Documentos\ConsentimientosInstitucionales;
use App\Documentos\TextoConsentimiento;
use App\Models\ModeloConsentimiento;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-009: los consentimientos oficiales (GBU-GPGR-F-032 y GBU-GPGR-F-019) quedan publicados
 * para Salud Mental e Inclusión y se pueden actualizar después desde Administración.
 */
class ConsentimientosInstitucionalesTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
    }

    public function test_publica_el_texto_general_y_el_de_menores_de_cada_servicio(): void
    {
        $this->assertSame(['SM-ORIENTACION', 'SM-MENORES', 'IN-ORIENTACION', 'IN-MENORES'], ConsentimientosInstitucionales::cargar());

        foreach (['Salud Mental', 'Inclusión'] as $servicio) {
            $general = ModeloConsentimiento::versionVigenteDe($servicio);
            $menores = ModeloConsentimiento::versionVigenteDe($servicio, ModeloConsentimiento::POBLACION_MENORES);

            $this->assertSame(ConsentimientosInstitucionales::TEXTO_GENERAL, $general?->texto, $servicio);
            $this->assertSame(ConsentimientosInstitucionales::TEXTO_MENORES, $menores?->texto, $servicio);
            $this->assertSame(1, $general->numero_version);
            $this->assertStringContainsString('GBU-GPGR-F-032', $general->modelo->descripcion);
            $this->assertStringContainsString('GBU-GPGR-F-019', $menores->modelo->descripcion);
        }
    }

    public function test_los_textos_solo_usan_marcadores_que_el_sistema_llena(): void
    {
        foreach ([ConsentimientosInstitucionales::TEXTO_GENERAL, ConsentimientosInstitucionales::TEXTO_MENORES] as $texto) {
            $this->assertSame([], TextoConsentimiento::marcadoresDesconocidos($texto));
        }

        $datos = array_fill_keys(array_keys(TextoConsentimiento::MARCADORES), 'DATO');
        $this->assertStringNotContainsString('{', TextoConsentimiento::renderizar(ConsentimientosInstitucionales::TEXTO_GENERAL, $datos));
        $this->assertStringNotContainsString(TextoConsentimiento::VACIO, TextoConsentimiento::renderizar(ConsentimientosInstitucionales::TEXTO_MENORES, $datos));
    }

    public function test_cargar_dos_veces_no_duplica_nada(): void
    {
        ConsentimientosInstitucionales::cargar();

        $this->assertSame([], ConsentimientosInstitucionales::cargar());
        $this->assertSame(4, ModeloConsentimiento::count());
        $this->assertSame(4, VersionConsentimiento::count());
    }

    public function test_no_reemplaza_un_modelo_que_el_administrador_ya_publico(): void
    {
        $propio = ModeloConsentimiento::create(['codigo' => 'SM-PROPIO', 'nombre' => 'Propio', 'tipo_servicio' => 'Salud Mental', 'activo' => true]);
        $propio->versiones()->create(['numero_version' => 3, 'texto' => 'Texto propio del consentimiento.', 'estado' => VersionConsentimiento::PUBLICADA]);

        $this->assertNotContains('SM-ORIENTACION', ConsentimientosInstitucionales::cargar());
        $this->assertSame('Texto propio del consentimiento.', ModeloConsentimiento::versionVigenteDe('Salud Mental')->texto);
        $this->assertSame(ConsentimientosInstitucionales::TEXTO_MENORES, ModeloConsentimiento::versionVigenteDe('Salud Mental', ModeloConsentimiento::POBLACION_MENORES)->texto);
    }

    public function test_el_administrador_ve_los_textos_publicados_y_puede_crear_una_version_nueva(): void
    {
        ConsentimientosInstitucionales::cargar();
        $admin = $this->crearUsuarioConRol('Admin');
        $modelo = ModeloConsentimiento::where('codigo', 'SM-ORIENTACION')->sole();

        $this->actingAs($admin)->get(route('admin.consentimientos.index'))
            ->assertOk()
            ->assertSee('Consentimiento informado orientación psicológica')
            ->assertSee('Consentimiento informado menores de edad')
            ->assertDontSee('texto publicado de Salud Mental');

        $this->actingAs($admin)
            ->post(route('admin.consentimientos.versiones.store', $modelo), ['texto' => ConsentimientosInstitucionales::TEXTO_GENERAL."\nTexto ajustado por Calidad."])
            ->assertSessionHasNoErrors();
        $v2 = $modelo->versiones()->where('numero_version', 2)->sole();
        $this->actingAs($admin)->post(route('admin.consentimientos.versiones.publicar', $v2))->assertSessionHasNoErrors();

        $this->assertStringEndsWith('Texto ajustado por Calidad.', ModeloConsentimiento::versionVigenteDe('Salud Mental')->texto);
        $this->assertSame(VersionConsentimiento::RETIRADA, $modelo->versiones()->where('numero_version', 1)->value('estado'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Caso;
use App\Models\Consentimiento;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-009: Parametrización de modelos de consentimiento y su uso al abrir casos.
 */
class ConsentimientoTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $admin;

    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
        $this->admin = $this->crearUsuarioConRol('Admin', ['nombre_completo' => 'Ana Admin']);
        $this->profesional = $this->crearUsuarioConRol('Profesional');
    }

    private function crearModelo(array $datos = []): ModeloConsentimiento
    {
        return ModeloConsentimiento::create(array_merge([
            'codigo' => 'SM-CONSENT',
            'nombre' => 'Consentimiento de atención psicológica',
            'tipo_servicio' => 'Salud Mental',
            'activo' => true,
        ], $datos));
    }

    private function crearVersion(ModeloConsentimiento $modelo, int $numero, string $estado, string $texto = 'Texto del consentimiento informado de prueba.'): VersionConsentimiento
    {
        return $modelo->versiones()->create([
            'numero_version' => $numero,
            'texto' => $texto,
            'estado' => $estado,
            'fecha_publicacion' => $estado === VersionConsentimiento::BORRADOR ? null : now(),
        ]);
    }

    private function datosCaso(array $cambios = []): array
    {
        return array_merge([
            'nombre_completo' => 'Juan Esteban Morales Ríos',
            'numero_documento' => '1112223334',
            'correo_inst' => 'juan.morales@uceva.edu.co',
            'tipo_servicio' => 'Salud Mental',
            'motivo' => 'Ansiedad severa y crisis académica durante parciales.',
        ], $cambios);
    }

    // --- Panel de administración ---

    public function test_el_admin_crea_un_modelo_y_publica_su_primera_version(): void
    {
        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), [
            'codigo' => ' sm-consent ',
            'nombre' => 'Consentimiento de atención psicológica',
            'tipo_servicio' => 'Salud Mental',
        ])->assertSessionHasNoErrors();

        $modelo = ModeloConsentimiento::sole();
        $this->assertSame('SM-CONSENT', $modelo->codigo);
        $this->assertTrue($modelo->activo);

        $this->actingAs($this->admin)
            ->post(route('admin.consentimientos.versiones.store', $modelo), ['texto' => 'Autorizo la atención psicológica y el tratamiento de mis datos.'])
            ->assertRedirect(route('admin.consentimientos.show', $modelo));

        $version = VersionConsentimiento::sole();
        $this->assertSame(1, $version->numero_version);
        $this->assertSame(VersionConsentimiento::BORRADOR, $version->fresh()->estado);
        $this->assertSame($this->admin->id_usuario, $version->creado_por);
        $this->assertNull(ModeloConsentimiento::versionVigenteDe('Salud Mental'));

        $this->actingAs($this->admin)
            ->post(route('admin.consentimientos.versiones.publicar', $version))
            ->assertSessionHas('status', 'Versión 1 publicada. Desde ahora es la que se acepta al abrir casos.');

        $this->assertSame($version->id_version, ModeloConsentimiento::versionVigenteDe('Salud Mental')?->id_version);

        $this->actingAs($this->admin)->get(route('admin.consentimientos.index'))
            ->assertOk()
            ->assertSee('Consentimiento de atención psicológica')
            ->assertSee('v1');
    }

    public function test_publicar_una_version_nueva_retira_la_anterior(): void
    {
        $modelo = $this->crearModelo();
        $v1 = $this->crearVersion($modelo, 1, VersionConsentimiento::PUBLICADA);

        $this->actingAs($this->admin)
            ->post(route('admin.consentimientos.versiones.store', $modelo), ['texto' => 'Texto nuevo del consentimiento, versión dos.']);

        $v2 = VersionConsentimiento::where('numero_version', 2)->sole();
        $this->assertSame($v1->id_version, ModeloConsentimiento::versionVigenteDe('Salud Mental')->id_version);

        $this->actingAs($this->admin)->post(route('admin.consentimientos.versiones.publicar', $v2))->assertSessionHasNoErrors();

        $this->assertSame(VersionConsentimiento::RETIRADA, $v1->fresh()->estado);
        $this->assertSame(VersionConsentimiento::PUBLICADA, $v2->fresh()->estado);
        $this->assertSame(1, VersionConsentimiento::where('estado', VersionConsentimiento::PUBLICADA)->count());
    }

    public function test_solo_el_borrador_se_puede_editar_y_publicar(): void
    {
        $modelo = $this->crearModelo();
        $publicada = $this->crearVersion($modelo, 1, VersionConsentimiento::PUBLICADA, 'Texto original publicado.');
        $borrador = $this->crearVersion($modelo, 2, VersionConsentimiento::BORRADOR);

        $this->actingAs($this->admin)
            ->patch(route('admin.consentimientos.versiones.update', $publicada), ['texto' => 'Intento de cambiar lo publicado.'])
            ->assertSessionHas('error', 'La versión 1 ya fue publicada y no se puede editar. Crea una versión nueva.');
        $this->assertSame('Texto original publicado.', $publicada->fresh()->texto);

        $this->actingAs($this->admin)
            ->post(route('admin.consentimientos.versiones.publicar', $publicada))
            ->assertSessionHas('error', 'La versión 1 ya no está en borrador.');

        $this->actingAs($this->admin)
            ->patch(route('admin.consentimientos.versiones.update', $borrador), ['texto' => 'Borrador corregido antes de publicar.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Borrador corregido antes de publicar.', $borrador->fresh()->texto);

        // Ya hay un borrador: no se crea otro.
        $this->actingAs($this->admin)
            ->post(route('admin.consentimientos.versiones.store', $modelo), ['texto' => 'Otro borrador más en paralelo.'])
            ->assertSessionHas('error', 'Este modelo ya tiene un borrador. Edítalo o publícalo antes de crear otra versión.');
        $this->assertSame(2, $modelo->versiones()->count());
    }

    public function test_valida_los_datos_del_modelo_y_del_texto(): void
    {
        $this->crearModelo();

        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), [
            'codigo' => 'sm consent!',
            'nombre' => '',
            'tipo_servicio' => 'Otro',
        ])->assertSessionHasErrorsIn('modelo', [
            'codigo' => 'El código solo puede tener letras, números, guiones y guiones bajos.',
            'nombre' => 'Escribe el nombre del modelo.',
            'tipo_servicio' => 'Selecciona un servicio de la lista.',
        ]);

        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), ['codigo' => 'sm-consent', 'nombre' => 'X', 'tipo_servicio' => 'Inclusión'])
            ->assertSessionHasErrorsIn('modelo', ['codigo' => 'Ya existe un modelo con ese código.']);

        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), ['codigo' => 'legado', 'nombre' => 'X', 'tipo_servicio' => 'Inclusión'])
            ->assertSessionHasErrorsIn('modelo', ['codigo' => 'El código LEGADO está reservado para los consentimientos anteriores.']);

        $this->actingAs($this->admin)
            ->post(route('admin.consentimientos.versiones.store', ModeloConsentimiento::first()), ['texto' => 'Muy corto'])
            ->assertSessionHasErrorsIn('version', ['texto' => 'El texto del consentimiento debe tener al menos 20 caracteres.']);

        $this->assertSame(1, ModeloConsentimiento::count());
        $this->assertSame(0, VersionConsentimiento::count());
    }

    public function test_un_servicio_solo_tiene_un_modelo_activo(): void
    {
        $actual = $this->crearModelo();

        $this->actingAs($this->admin)->post(route('admin.consentimientos.store'), [
            'codigo' => 'SM-CONSENT-2027',
            'nombre' => 'Consentimiento 2027',
            'tipo_servicio' => 'Salud Mental',
        ]);

        $nuevo = ModeloConsentimiento::where('codigo', 'SM-CONSENT-2027')->sole();
        $this->assertFalse($nuevo->activo);

        $this->actingAs($this->admin)->patch(route('admin.consentimientos.update', $nuevo), [
            'nombre' => 'Consentimiento 2027',
            'tipo_servicio' => 'Salud Mental',
            'activo' => '1',
        ])->assertSessionHasErrorsIn('modelo', ['activo' => 'El servicio Salud Mental ya tiene activo el modelo "Consentimiento de atención psicológica". Desactívalo primero.']);

        $this->actingAs($this->admin)->patch(route('admin.consentimientos.update', $actual), [
            'nombre' => $actual->nombre,
            'tipo_servicio' => 'Salud Mental',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($actual->fresh()->activo);

        $this->actingAs($this->admin)->patch(route('admin.consentimientos.update', $nuevo), [
            'nombre' => 'Consentimiento 2027',
            'tipo_servicio' => 'Salud Mental',
            'activo' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($nuevo->fresh()->activo);
    }

    public function test_el_modelo_legado_es_solo_de_consulta(): void
    {
        $legado = $this->crearModelo(['codigo' => 'LEGADO', 'nombre' => 'Consentimientos anteriores a HU-009', 'tipo_servicio' => null, 'activo' => false]);
        $this->crearVersion($legado, 1, VersionConsentimiento::RETIRADA, '[Texto original no registrado en BD.]');

        $this->actingAs($this->admin)->get(route('admin.consentimientos.show', $legado))
            ->assertOk()
            ->assertSee('Es solo de consulta')
            ->assertDontSee(route('admin.consentimientos.versiones.store', $legado), false);

        $this->actingAs($this->admin)->post(route('admin.consentimientos.versiones.store', $legado), ['texto' => 'Intento de versión nueva en LEGADO.'])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('admin.consentimientos.update', $legado), ['nombre' => 'X', 'tipo_servicio' => 'Salud Mental', 'activo' => '1'])->assertForbidden();
    }

    public function test_solo_el_admin_gestiona_los_consentimientos(): void
    {
        $modelo = $this->crearModelo();

        $this->actingAs($this->profesional)->get(route('admin.consentimientos.index'))->assertRedirect(route('profesional.home'));
        $this->actingAs($this->profesional)->post(route('admin.consentimientos.versiones.store', $modelo), ['texto' => 'Texto que no debería guardarse.'])->assertRedirect(route('profesional.home'));

        auth()->logout();
        $this->get(route('admin.consentimientos.index'))->assertRedirect(route('login'));

        $this->assertSame(0, VersionConsentimiento::count());
    }

    // --- Uso al abrir un caso ---

    public function test_sin_consentimiento_publicado_el_caso_se_abre_como_antes(): void
    {
        $this->actingAs($this->profesional)->get(route('casos.create', ['nuevo' => 1]))
            ->assertOk()
            ->assertDontSee('name="acepta_consentimiento"', false);

        $this->actingAs($this->profesional)->post(route('casos.store'), $this->datosCaso())->assertSessionHasNoErrors();

        $this->assertSame(0, Consentimiento::count());
    }

    /**
     * DADO un servicio con consentimiento publicado,
     * CUANDO abro un caso y confirmo que el solicitante lo aceptó,
     * ENTONCES queda registrada la versión exacta aceptada.
     */
    public function test_al_abrir_el_caso_se_registra_la_version_aceptada(): void
    {
        $modelo = $this->crearModelo();
        $version = $this->crearVersion($modelo, 3, VersionConsentimiento::PUBLICADA, 'Autorizo la atención psicológica bajo confidencialidad.');

        $this->actingAs($this->profesional)->get(route('casos.create', ['nuevo' => 1]))
            ->assertOk()
            ->assertSee('Autorizo la atención psicológica bajo confidencialidad.')
            ->assertSee('versión 3')
            ->assertSee('name="id_version" value="'.$version->id_version.'"', false);

        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosCaso(['id_version' => $version->id_version, 'acepta_consentimiento' => '1']))
            ->assertSessionHasNoErrors();

        $caso = Caso::sole();
        $consentimiento = Consentimiento::sole();
        $this->assertSame($caso->id_caso, $consentimiento->id_caso);
        $this->assertTrue($consentimiento->aceptado);
        $this->assertSame($version->id_version, $consentimiento->id_version);
        $this->assertSame('SM-CONSENT-v3', $consentimiento->version_formato);

        $this->actingAs($this->profesional)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('Casilla (antes de la firma digital): aceptado')
            ->assertSee('SM-CONSENT-v3');
    }

    public function test_exige_confirmar_la_aceptacion(): void
    {
        $version = $this->crearVersion($this->crearModelo(), 1, VersionConsentimiento::PUBLICADA);

        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosCaso(['id_version' => $version->id_version]))
            ->assertSessionHasErrors(['acepta_consentimiento' => 'Confirma que el solicitante leyó y aceptó el consentimiento informado.']);

        $this->assertSame(0, Caso::count());
        $this->assertSame(0, Consentimiento::count());
    }

    public function test_rechaza_si_la_version_cambio_mientras_se_llenaba_el_formulario(): void
    {
        $modelo = $this->crearModelo();
        $vieja = $this->crearVersion($modelo, 1, VersionConsentimiento::BORRADOR);
        $vieja->publicar();
        $nueva = $this->crearVersion($modelo, 2, VersionConsentimiento::BORRADOR);
        $nueva->publicar();

        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosCaso(['id_version' => $vieja->id_version, 'acepta_consentimiento' => '1']))
            ->assertSessionHasErrors(['acepta_consentimiento' => 'El consentimiento de Salud Mental se actualizó mientras llenabas el formulario. Lee la versión 2 y confirma de nuevo que el solicitante la aceptó.']);

        $this->assertSame(0, Consentimiento::count());
    }

    public function test_cada_servicio_usa_su_propio_modelo_activo(): void
    {
        $this->crearVersion($this->crearModelo(), 1, VersionConsentimiento::PUBLICADA, 'Texto de Salud Mental.');
        $inactivo = $this->crearModelo(['codigo' => 'IN-CONSENT', 'nombre' => 'Inclusión', 'tipo_servicio' => 'Inclusión', 'activo' => false]);
        $this->crearVersion($inactivo, 1, VersionConsentimiento::PUBLICADA, 'Texto de Inclusión.');

        // Inclusión tiene versión publicada, pero su modelo está inactivo: no se exige.
        $this->actingAs($this->profesional)
            ->post(route('casos.store'), $this->datosCaso(['tipo_servicio' => 'Inclusión']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Consentimiento::count());
        $this->assertSame('Texto de Salud Mental.', ModeloConsentimiento::versionVigenteDe('Salud Mental')->texto);
    }

    // --- Versiones aceptadas ---

    public function test_al_publicar_una_version_nueva_los_consentimientos_anteriores_conservan_la_suya(): void
    {
        $modelo = $this->crearModelo();
        $v1 = $this->crearVersion($modelo, 1, VersionConsentimiento::PUBLICADA, 'Texto de la versión uno del consentimiento.');
        $aceptacion = $this->aceptarCon($v1);

        $this->actingAs($this->admin)->post(route('admin.consentimientos.versiones.store', $modelo), ['texto' => 'Texto de la versión dos del consentimiento.']);
        $v2 = VersionConsentimiento::where('numero_version', 2)->sole();
        $this->actingAs($this->admin)->post(route('admin.consentimientos.versiones.publicar', $v2))->assertSessionHasNoErrors();

        // Los nuevos consentimientos usan la versión 2...
        $this->assertSame($v2->id_version, ModeloConsentimiento::versionVigenteDe('Salud Mental')->id_version);

        // ...y el anterior sigue ligado a la versión 1, con su texto intacto.
        $aceptacion = $aceptacion->fresh();
        $this->assertSame($v1->id_version, $aceptacion->id_version);
        $this->assertSame('Texto de la versión uno del consentimiento.', $aceptacion->version->texto);
        $this->assertSame('Texto de la versión uno del consentimiento.', $aceptacion->texto_presentado);
        $this->assertSame(VersionConsentimiento::RETIRADA, $v1->fresh()->estado);

        $this->actingAs($this->admin)->get(route('admin.consentimientos.show', $modelo))
            ->assertOk()
            ->assertSee('1 consentimiento registrado con esta versión')
            ->assertSee('0 consentimientos registrados con esta versión');
    }

    private function aceptarCon(VersionConsentimiento $version): ConsentimientoAceptacion
    {
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Samuel Valencia', 'numero_documento' => '1112223334']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad.']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);

        return ConsentimientoAceptacion::create([
            'id_caso' => $caso->id_caso,
            'id_version' => $version->id_version,
            'decision' => ConsentimientoAceptacion::ACEPTADO,
            'fecha_hora' => now(),
            'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_ENLACE,
            'id_profesional' => $this->profesional->id_usuario,
            'lugar_expedicion' => 'Tuluá',
            'eps_ips' => 'EPS Prueba',
            'contacto_urgencia' => 'Madre, 3001234567',
            'vinculacion' => 'Funcionario',
            'dependencia_facultad' => 'Bienestar Universitario',
            'texto_presentado' => $version->texto,
            'texto_sha256' => hash('sha256', $version->texto),
            'firma_ruta' => 'casos/prueba/firma.png',
            'firma_sha256' => str_repeat('a', 64),
        ]);
    }
}

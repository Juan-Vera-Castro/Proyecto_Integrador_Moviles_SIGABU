<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\Concerns\GeneraFirmaPng;
use Tests\TestCase;

/**
 * RQ-1: consentimiento informado con firma táctil (HU-CI-01, 02, 04 y 05). Lo firma el
 * estudiante desde su cuenta; el profesional del caso solo consulta la decisión.
 */
class ConsentimientoFirmaTest extends TestCase
{
    use CreaEsquemaSigabu, GeneraFirmaPng, RefreshDatabase;

    private const TEXTO_GENERAL = 'Ciudad: {ciudad}. Yo, {nombre_estudiante}, con documento {tipo_documento_estudiante} número {documento_estudiante} de {lugar_expedicion}, EPS {eps_ips}, se notificará a {acudiente_notificacion}. Profesional {profesional}.';

    private const TEXTO_MENORES = 'Yo, {nombre_acudiente}, {tipo_documento_acudiente} {documento_acudiente} de {lugar_expedicion_acudiente}, {parentesco_acudiente} del menor {nombre_estudiante}. EPS {eps_ips}.';

    private User $profesional;

    private User $estudiante;

    private Caso $caso;

    private VersionConsentimiento $version;

    protected function setUp(): void
    {
        parent::setUp();

        // Jueves 8 de octubre de 2026, 10:00 am en Colombia.
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', Actuacion::ZONA_HORARIA));

        Storage::fake('firmas');
        config(['filesystems.disks.firmas.key' => 'clave', 'filesystems.disks.firmas.secret' => 'secreto', 'filesystems.disks.firmas.endpoint' => 'https://ejemplo.supabase.co/storage/v1/s3']);

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Camila Muñoz', 'numero_documento' => '29111222']);
        $this->caso = $this->crearCaso($this->profesional, ['nombre_completo' => 'Samuel Valencia', 'numero_documento' => '1112223334', 'tipo_documento' => 'CC', 'sexo' => 'M', 'fecha_nacimiento' => '2005-03-10']);
        $this->estudiante = $this->caso->solicitud->persona->usuario;
        $this->version = $this->publicar('SM-ORIENTACION', ModeloConsentimiento::POBLACION_GENERAL, self::TEXTO_GENERAL);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $profesional, array $persona): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona', $persona);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad.']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $profesional->id_usuario]);

        return $caso->fresh();
    }

    private function publicar(string $codigo, string $poblacion, string $texto, int $numero = 1): VersionConsentimiento
    {
        $modelo = ModeloConsentimiento::firstOrCreate(['codigo' => $codigo], ['nombre' => "Consentimiento $codigo", 'tipo_servicio' => 'Salud Mental', 'poblacion' => $poblacion, 'activo' => true]);

        return VersionConsentimiento::create(['id_modelo' => $modelo->id_modelo, 'numero_version' => $numero, 'texto' => $texto, 'estado' => 'publicada']);
    }

    private function firma(bool $trazo = true): string
    {
        return 'data:image/png;base64,'.base64_encode($this->firmaPng($trazo));
    }

    private function aceptar(array $cambios = [], ?User $como = null, bool $json = true)
    {
        $datos = array_merge([
            'decision' => 'Aceptado',
            'id_version' => $this->version->id_version,
            'lugar_expedicion' => ' Tuluá ',
            'eps_ips' => 'Nueva EPS',
            'contactos' => [['nombre' => 'María Pérez', 'telefono' => '300 123 4567', 'parentesco' => 'Mamá']],
            'vinculacion' => 'Estudiante',
            'programa_academico' => 'Psicología',
            'semestre' => '3',
            'dependencia_facultad' => 'Facultad de Ciencias de la Salud',
            'firma' => $this->firma(),
        ], $cambios);

        $peticion = $this->actingAs($como ?? $this->estudiante)->from(route('consentimientos.firmar', $this->caso));

        return $json
            ? $peticion->postJson(route('consentimientos.registrar', $this->caso), $datos)
            : $peticion->post(route('consentimientos.registrar', $this->caso), $datos);
    }

    private function urlMisCitas(): string
    {
        return route('citas.miscitas').'#caso-'.$this->caso->id_caso;
    }

    private function casoFresco(): Caso
    {
        return Caso::with(['solicitud', 'aceptacionesConsentimiento'])->findOrFail($this->caso->id_caso);
    }

    private function assertNadaGuardado(): void
    {
        $this->assertSame(0, ConsentimientoAceptacion::count());
        $this->assertSame([], Storage::disk('firmas')->allFiles());
    }

    /**
     * DADO un consentimiento firmado por el estudiante y contrafirmado por el profesional,
     * CUANDO quien puede consultar la evidencia lo descarga,
     * ENTONCES recibe un PDF con el texto exacto, los datos, las firmas y su verificación, y queda auditado.
     */
    public function test_el_consentimiento_firmado_se_descarga_en_pdf_con_toda_su_informacion(): void
    {
        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();
        $this->actingAs($this->profesional)->postJson(route('consentimientos.firmar-profesional', $aceptacion), ['firma' => $this->firma()])->assertOk();
        $url = route('consentimientos.pdf', $aceptacion);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))->assertSee($url, false)->assertSee('Descargar consentimiento (PDF)');

        $this->actingAs($this->profesional)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Consentimiento-'.$this->caso->codigo_expediente.'-08-10-26-1000.pdf"')
            ->assertHeader('Cache-Control', 'no-store, private');

        $auditoria = AuditoriaExpediente::where('accion', 'consentimiento_pdf')->sole();
        $this->assertSame([$aceptacion->id_aceptacion, true], [$auditoria->detalle['id_aceptacion'], $auditoria->detalle['integro']]);

        $html = view('documentos.consentimiento', \App\Documentos\PdfConsentimiento::datos($aceptacion->fresh()))->render();
        foreach ([
            'GBU-GPGR-F-032', 'CONSENTIMIENTO INFORMADO ORIENTACIÓN PSICOLÓGICA', 'ACEPTÓ', $this->caso->codigo_expediente,
            'Samuel Valencia', 'CC 1112223334 de Tuluá', '21 años', 'Nueva EPS', 'María Pérez · Mamá · Tel. 3001234567', 'Psicología',
            'Facultad de Ciencias de la Salud', 'Camila Muñoz', $aceptacion->texto_presentado, $aceptacion->texto_sha256,
            $aceptacion->firma_sha256, $aceptacion->fresh()->firmaProfesional->firma_sha256, 'Firma táctil del estudiante desde su cuenta',
        ] as $esperado) {
            $this->assertStringContainsString(e($esperado), $html, "Falta en el PDF: $esperado");
        }
        $this->assertSame(3, substr_count($html, 'Verificada:'));
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
    }

    public function test_el_pdf_lo_descargan_solo_quienes_pueden_consultar_la_evidencia(): void
    {
        $this->aceptar();
        $url = route('consentimientos.pdf', ConsentimientoAceptacion::sole());

        $this->actingAs($this->crearUsuarioConRol('Admin'))->get($url)->assertOk();
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get($url)->assertForbidden();
        $this->actingAs($this->estudiante)->get($url)->assertForbidden();
        $this->assertSame(1, AuditoriaExpediente::where('accion', 'consentimiento_pdf')->count());
    }

    public function test_el_pdf_avisa_si_la_firma_guardada_no_coincide(): void
    {
        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();
        Storage::disk('firmas')->put($aceptacion->firma_ruta, $this->firmaPng(false));

        $this->actingAs($this->profesional)->get(route('consentimientos.pdf', $aceptacion))->assertOk();
        $this->assertFalse(AuditoriaExpediente::where('accion', 'consentimiento_pdf')->sole()->detalle['integro']);

        $html = view('documentos.consentimiento', \App\Documentos\PdfConsentimiento::datos($aceptacion))->render();
        $this->assertStringContainsString('NO COINCIDE: la imagen no corresponde a la registrada.', $html);
        $this->assertStringContainsString('Pendiente', $html);
    }

    public function test_el_pdf_del_menor_usa_el_formato_de_menores_y_los_datos_del_acudiente(): void
    {
        $this->caso->solicitud->persona->update(['fecha_nacimiento' => '2010-01-15']);
        $menores = $this->publicar('SM-MENORES', ModeloConsentimiento::POBLACION_MENORES, self::TEXTO_MENORES);
        $this->aceptar([
            'id_version' => $menores->id_version, 'lugar_expedicion' => null, 'contactos' => [],
            'acudiente_nombre' => 'María Pérez', 'acudiente_tipo_documento' => 'CC', 'acudiente_numero_documento' => '29876543',
            'acudiente_parentesco' => 'Madre', 'acudiente_lugar_expedicion' => 'Buga',
            'acudiente_telefono' => '311 222 3344', 'firma_estudiante' => $this->firma(),
        ])->assertOk();

        $html = view('documentos.consentimiento', \App\Documentos\PdfConsentimiento::datos(ConsentimientoAceptacion::sole()))->render();

        foreach (['GBU-GPGR-F-019', 'CONSENTIMIENTO INFORMADO MENORES DE EDAD', 'Lo otorga el acudiente', 'CC 29876543 de Buga', 'Madre', '16 años'] as $esperado) {
            $this->assertStringContainsString(e($esperado), $html, "Falta en el PDF: $esperado");
        }
    }

    public function test_el_pdf_de_un_consentimiento_no_aceptado_lo_dice(): void
    {
        $this->aceptar(['decision' => 'Rechazado', 'firma' => null, 'vinculacion' => null, 'dependencia_facultad' => null])->assertOk();
        $aceptacion = ConsentimientoAceptacion::sole();

        $this->actingAs($this->profesional)->get(route('consentimientos.pdf', $aceptacion))->assertOk();

        $html = view('documentos.consentimiento', \App\Documentos\PdfConsentimiento::datos($aceptacion))->render();
        $this->assertStringContainsString('NO ACEPTÓ', $html);
        $this->assertStringContainsString('No aplica', $html);
        $this->assertStringNotContainsString('data:image/png;base64,', $html);
    }

    public function test_los_consentimientos_de_inclusion_usan_su_propio_pdf(): void
    {
        $con = fn (string $medio) => \App\Documentos\PdfConsentimiento::aplicaA(new ConsentimientoAceptacion(['medio' => $medio]));

        $this->assertTrue($con(ConsentimientoAceptacion::MEDIO_FIRMA_ENLACE));
        $this->assertTrue($con(ConsentimientoAceptacion::MEDIO_FIRMA_EXPEDIENTE));
        $this->assertFalse($con(ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION));
        $this->assertFalse($con(ConsentimientoAceptacion::MEDIO_FIRMA_PAPEL));
    }

    public function test_la_pantalla_muestra_el_texto_vigente_con_los_datos_del_caso(): void
    {
        $this->actingAs($this->estudiante)->get(route('consentimientos.firmar', $this->caso))
            ->assertOk()
            ->assertSee('Ciudad: Tuluá. Yo, Samuel Valencia, con documento CC número 1112223334 de ____')
            ->assertSee('Profesional Camila Muñoz.')
            ->assertSee('name="id_version" value="'.$this->version->id_version.'"', false)
            ->assertSee('Área de firma')
            ->assertSee('Acepto y firmo')
            ->assertSee('No acepto')
            ->assertSee('Tus datos para el consentimiento')
            ->assertSee('Personas a quienes avisar en caso de urgencia')
            ->assertSeeInOrder(['Mamá', 'Papá', 'Hermano', 'Hermana', 'Familiar', 'Amigo(a)'])
            ->assertSee('Agregar otra persona')
            ->assertSee(route('citas.miscitas'), false);
    }

    /**
     * DADO un caso sin consentimiento,
     * CUANDO el estudiante firma y acepta,
     * ENTONCES se guardan la firma en el bucket privado y la evidencia completa (RN-04).
     */
    public function test_acepta_con_firma_y_guarda_la_evidencia(): void
    {
        $this->aceptar()
            ->assertOk()
            ->assertJson(['redirect' => $this->urlMisCitas()]);

        $aceptacion = ConsentimientoAceptacion::sole();
        $this->assertSame('Aceptado', $aceptacion->decision);
        $this->assertSame('firma_tactil_enlace', $aceptacion->medio);
        // Queda como responsable el profesional del caso, aunque firme el estudiante.
        $this->assertSame($this->profesional->id_usuario, $aceptacion->id_profesional);
        $this->assertSame($this->version->id_version, $aceptacion->id_version);
        $this->assertSame('Estudiante', $aceptacion->otorgante);
        $this->assertSame(['Tuluá', 'Nueva EPS', 'María Pérez (Mamá) - Tel. 3001234567'], [$aceptacion->lugar_expedicion, $aceptacion->eps_ips, $aceptacion->contacto_urgencia]);
        $this->assertSame('2026-10-08 10:00:00', $aceptacion->fecha_hora->format('Y-m-d H:i:s'));
        $this->assertSame('127.0.0.1', $aceptacion->ip_origen);

        $this->assertSame(
            'Ciudad: Tuluá. Yo, Samuel Valencia, con documento CC número 1112223334 de Tuluá, EPS Nueva EPS, se notificará a María Pérez (Mamá) - Tel. 3001234567. Profesional Camila Muñoz.',
            $aceptacion->texto_presentado,
        );
        $this->assertSame(hash('sha256', $aceptacion->texto_presentado), $aceptacion->texto_sha256);

        $this->assertSame("casos/{$this->caso->id_caso}/{$aceptacion->id_aceptacion}.png", $aceptacion->firma_ruta);
        Storage::disk('firmas')->assertExists($aceptacion->firma_ruta);
        $this->assertSame($aceptacion->firma_sha256, hash('sha256', Storage::disk('firmas')->get($aceptacion->firma_ruta)));

        $this->assertSame(1, AuditoriaExpediente::where('accion', 'consentimiento_aceptado')->where('id_caso', $this->caso->id_caso)->count());
        $this->assertTrue($this->casoFresco()->admiteAtencion());
        $this->assertSame('Gracias. Tu consentimiento informado quedó aceptado y firmado; tu profesional ya puede atenderte.', session('status'));
    }

    public function test_sin_javascript_el_formulario_tambien_registra_y_vuelve_a_mis_citas(): void
    {
        $this->aceptar(json: false)->assertRedirect($this->urlMisCitas());

        $this->assertSame(1, ConsentimientoAceptacion::count());
    }

    /**
     * DADO un estudiante que no acepta,
     * CUANDO lo registra desde su cuenta,
     * ENTONCES queda la evidencia del rechazo sin firma y la atención queda bloqueada.
     */
    public function test_registra_el_rechazo_sin_firma(): void
    {
        $this->aceptar(['decision' => 'Rechazado', 'firma' => null, 'eps_ips' => null])->assertOk();

        $rechazo = ConsentimientoAceptacion::sole();
        $this->assertSame('Rechazado', $rechazo->decision);
        $this->assertNull($rechazo->firma_ruta);
        $this->assertNull($rechazo->eps_ips);
        $this->assertSame([], Storage::disk('firmas')->allFiles());
        $this->assertSame(1, AuditoriaExpediente::where('accion', 'consentimiento_rechazado')->count());
        $this->assertFalse($this->casoFresco()->admiteAtencion());

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('El estudiante no aceptó el consentimiento informado el 2026-10-08 10:00')
            ->assertSee('✕ No aceptado');
    }

    public function test_despues_de_un_rechazo_se_puede_aceptar_y_ambos_quedan_en_el_historial(): void
    {
        $this->aceptar(['decision' => 'Rechazado', 'firma' => null]);
        Carbon::setTestNow(now()->addMinutes(30));
        $this->aceptar()->assertOk();

        $this->assertSame(2, ConsentimientoAceptacion::count());
        $this->assertTrue($this->casoFresco()->admiteAtencion());
    }

    public function test_rechaza_firmas_vacias_o_que_no_son_imagenes(): void
    {
        $this->aceptar(['firma' => $this->firma(trazo: false)])->assertUnprocessable()->assertJsonValidationErrors(['firma' => 'La firma está vacía o es muy corta. Bórrala y vuelve a firmar.']);
        $this->aceptar(['firma' => 'data:image/png;base64,'.base64_encode('no es una imagen')])->assertJsonValidationErrors(['firma' => 'La firma no se pudo leer. Bórrala y vuelve a firmar.']);
        $this->aceptar(['firma' => 'data:image/jpeg;base64,AAAA'])->assertJsonValidationErrors(['firma']);
        $this->aceptar(['firma' => null])->assertJsonValidationErrors(['firma' => 'Falta la firma. Firma dentro del recuadro.']);

        $this->assertNadaGuardado();
    }

    public function test_al_aceptar_exige_los_datos_que_llenan_el_texto(): void
    {
        $this->aceptar(['lugar_expedicion' => '  ', 'eps_ips' => null, 'contactos' => []])
            ->assertJsonValidationErrors(['lugar_expedicion', 'eps_ips', 'contactos']);

        $this->assertNadaGuardado();
    }

    /**
     * DADO un estudiante que firma,
     * CUANDO deja incompleta a una persona de contacto para urgencias,
     * ENTONCES se le piden su nombre, su teléfono y su parentesco, y no se registra nada.
     */
    public function test_cada_contacto_de_urgencia_exige_nombre_telefono_y_parentesco(): void
    {
        $this->aceptar(['contactos' => [['nombre' => 'María Pérez', 'telefono' => '', 'parentesco' => '']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contactos.0.telefono' => 'Escribe el teléfono de la persona.',
                'contactos.0.parentesco' => 'Selecciona el parentesco.',
            ])
            ->assertJsonMissingValidationErrors(['contactos.0.nombre']);

        $this->aceptar(['contactos' => [['nombre' => '', 'telefono' => '3001234567', 'parentesco' => 'Papá']]])
            ->assertJsonValidationErrors(['contactos.0.nombre' => 'Escribe el nombre de la persona.']);

        $this->aceptar(['contactos' => [['nombre' => 'Juan Pérez', 'telefono' => '300-ABC', 'parentesco' => 'Papá']]])
            ->assertJsonValidationErrors(['contactos.0.telefono' => 'Escribe un teléfono válido: solo números, entre 7 y 15 dígitos.']);

        $this->aceptar(['contactos' => [['nombre' => 'Juan Pérez', 'telefono' => '3001234567', 'parentesco' => 'Vecino']]])
            ->assertJsonValidationErrors(['contactos.0.parentesco' => 'Selecciona un parentesco de la lista.']);

        $this->assertNadaGuardado();
    }

    /**
     * DADO un estudiante que agrega varias personas para urgencias (y deja una fila vacía),
     * CUANDO firma,
     * ENTONCES se guardan todas con su parentesco, sin la fila vacía, y el texto las nombra a todas.
     */
    public function test_guarda_varias_personas_de_contacto_y_las_nombra_en_el_texto(): void
    {
        $this->aceptar(['contactos' => [
            ['nombre' => ' María Pérez ', 'telefono' => '(300) 123-4567', 'parentesco' => 'Mamá'],
            ['nombre' => 'Andrés Valencia', 'telefono' => '+57 310 987 6543', 'parentesco' => 'Hermano'],
            ['nombre' => '', 'telefono' => '', 'parentesco' => ''],
        ]])->assertOk();

        $aceptacion = ConsentimientoAceptacion::sole();
        $this->assertSame([
            ['nombre' => 'María Pérez', 'telefono' => '3001234567', 'parentesco' => 'Mamá'],
            ['nombre' => 'Andrés Valencia', 'telefono' => '+573109876543', 'parentesco' => 'Hermano'],
        ], $aceptacion->contactos_urgencia);
        $this->assertSame('María Pérez (Mamá) - Tel. 3001234567; Andrés Valencia (Hermano) - Tel. +573109876543', $aceptacion->contacto_urgencia);
        $this->assertStringContainsString('se notificará a María Pérez (Mamá) - Tel. 3001234567; Andrés Valencia (Hermano) - Tel. +573109876543.', $aceptacion->texto_presentado);

        $html = view('documentos.consentimiento', \App\Documentos\PdfConsentimiento::datos($aceptacion))->render();
        $this->assertStringContainsString(e('María Pérez · Mamá · Tel. 3001234567'), $html);
        $this->assertStringContainsString(e('Andrés Valencia · Hermano · Tel. +573109876543'), $html);
    }

    public function test_admite_hasta_tres_personas_de_contacto(): void
    {
        $persona = fn (string $nombre) => ['nombre' => $nombre, 'telefono' => '3001234567', 'parentesco' => 'Familiar'];

        $this->aceptar(['contactos' => [$persona('Uno'), $persona('Dos'), $persona('Tres'), $persona('Cuatro')]])
            ->assertJsonValidationErrors(['contactos' => 'Puedes agregar hasta 3 personas para avisar en caso de urgencia.']);
        $this->assertNadaGuardado();

        $this->aceptar(['contactos' => [$persona('Uno'), $persona('Dos'), $persona('Tres')]])->assertOk();
        $this->assertCount(3, ConsentimientoAceptacion::sole()->contactos_urgencia);
    }

    /**
     * Los consentimientos firmados antes de este cambio solo tienen el texto libre: el PDF lo muestra igual.
     */
    public function test_el_pdf_de_un_consentimiento_anterior_muestra_su_contacto_en_texto(): void
    {
        $this->aceptar()->assertOk();
        // Los consentimientos firmados no se pueden modificar: se simula uno anterior en memoria.
        $anterior = ConsentimientoAceptacion::sole()->forceFill(['contactos_urgencia' => null, 'contacto_urgencia' => 'Madre, 3001234567']);

        $html = view('documentos.consentimiento', \App\Documentos\PdfConsentimiento::datos($anterior))->render();
        $this->assertStringContainsString('Madre, 3001234567', $html);
    }

    public function test_si_la_version_cambio_mientras_se_leia_no_registra(): void
    {
        $nueva = $this->publicarNuevaVersion();

        $this->aceptar()->assertJsonValidationErrors(['id_version' => "El consentimiento se actualizó mientras lo leías. Vuelve a cargar la página y lee la versión {$nueva->numero_version}."]);

        $this->assertNadaGuardado();
    }

    private function publicarNuevaVersion(): VersionConsentimiento
    {
        VersionConsentimiento::whereKey($this->version->id_version)->update(['estado' => 'retirada']);

        return VersionConsentimiento::create(['id_modelo' => $this->version->id_modelo, 'numero_version' => 2, 'texto' => self::TEXTO_GENERAL, 'estado' => 'publicada']);
    }

    /**
     * DADO un estudiante menor de edad,
     * CUANDO se abre la pantalla de firma,
     * ENTONCES se usa el consentimiento de menores y lo otorga el acudiente (HU-CI-05).
     */
    public function test_el_menor_de_edad_firma_por_medio_de_su_acudiente(): void
    {
        $this->caso->solicitud->persona->update(['fecha_nacimiento' => '2010-01-15']);
        $menores = $this->publicar('SM-MENORES', ModeloConsentimiento::POBLACION_MENORES, self::TEXTO_MENORES);

        $this->actingAs($this->estudiante)->get(route('consentimientos.firmar', $this->caso))
            ->assertOk()
            ->assertSee('Datos del acudiente que otorga el consentimiento')
            ->assertSee('16 años · menor de edad')
            ->assertSee('name="id_version" value="'.$menores->id_version.'"', false)
            ->assertSee('id="acudiente_nombre"', false)
            ->assertDontSee('id="contacto_urgencia"', false)
            ->assertDontSee('Personas a quienes avisar en caso de urgencia');

        $acudiente = [
            'id_version' => $menores->id_version, 'lugar_expedicion' => null, 'contactos' => [],
            'acudiente_nombre' => 'María Pérez', 'acudiente_tipo_documento' => 'CC', 'acudiente_numero_documento' => '29876543',
            'acudiente_parentesco' => 'Madre', 'acudiente_lugar_expedicion' => 'Buga',
            'acudiente_telefono' => '311 222 3344', 'firma_estudiante' => $this->firma(),
        ];

        $this->aceptar(array_merge($acudiente, ['acudiente_nombre' => '', 'acudiente_tipo_documento' => 'NIT']))
            ->assertJsonValidationErrors(['acudiente_nombre', 'acudiente_tipo_documento']);

        $this->aceptar($acudiente)->assertOk();

        $aceptacion = ConsentimientoAceptacion::sole();
        $this->assertSame('Acudiente', $aceptacion->otorgante);
        $this->assertSame(['María Pérez', 'CC', '29876543', 'Madre', 'Buga'], [
            $aceptacion->acudiente_nombre, $aceptacion->acudiente_tipo_documento, $aceptacion->acudiente_numero_documento,
            $aceptacion->acudiente_parentesco, $aceptacion->acudiente_lugar_expedicion,
        ]);
        $this->assertSame('Yo, María Pérez, CC 29876543 de Buga, Madre del menor Samuel Valencia. EPS Nueva EPS.', $aceptacion->texto_presentado);
    }

    /**
     * Datos del acudiente y firmas de un consentimiento de menores, listos para enviar.
     */
    private function datosDeMenor(array $cambios = []): array
    {
        $this->caso->solicitud->persona->update(['fecha_nacimiento' => '2010-01-15']);
        $menores = $this->publicar('SM-MENORES', ModeloConsentimiento::POBLACION_MENORES, self::TEXTO_MENORES);

        return array_merge([
            'id_version' => $menores->id_version, 'lugar_expedicion' => null, 'contactos' => [],
            'acudiente_nombre' => 'María Pérez', 'acudiente_tipo_documento' => 'CC', 'acudiente_numero_documento' => '29876543',
            'acudiente_parentesco' => 'Madre', 'acudiente_lugar_expedicion' => 'Buga',
            'acudiente_telefono' => '311 222 3344', 'firma_estudiante' => $this->firma(),
        ], $cambios);
    }

    /**
     * DADO un estudiante menor de edad,
     * CUANDO abre el consentimiento,
     * ENTONCES se piden el teléfono y la cédula del acudiente, y hay un recuadro para la firma del estudiante y otro para la del acudiente.
     */
    public function test_el_menor_ve_los_datos_del_acudiente_y_las_dos_firmas(): void
    {
        $this->datosDeMenor();

        $this->actingAs($this->estudiante)->get(route('consentimientos.firmar', $this->caso))
            ->assertOk()
            ->assertSee('id="acudiente_telefono"', false)
            ->assertSee('Número de cédula del acudiente')
            ->assertSee('Cédula de ciudadanía')
            ->assertSee('Parentesco con el estudiante')
            ->assertSeeInOrder(['data-lienzo="firma_estudiante"', 'Firma del estudiante', 'Firma del acudiente'], false)
            ->assertSee('firmasCompletas()', false);
    }

    /**
     * DADO un menor de edad,
     * CUANDO falta la firma del estudiante o el teléfono del acudiente,
     * ENTONCES no se registra nada y se indica qué falta.
     */
    public function test_el_menor_exige_su_firma_y_el_telefono_del_acudiente(): void
    {
        $datos = $this->datosDeMenor();

        $this->aceptar(array_merge($datos, ['firma_estudiante' => null, 'acudiente_telefono' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'firma_estudiante' => 'Falta la firma del estudiante. Firma dentro de su recuadro.',
                'acudiente_telefono' => 'Escribe el teléfono del acudiente.',
            ]);

        $this->aceptar(array_merge($datos, ['firma_estudiante' => $this->firma(trazo: false), 'acudiente_telefono' => '31-AB']))
            ->assertJsonValidationErrors([
                'firma_estudiante' => 'La firma está vacía o es muy corta. Bórrala y vuelve a firmar.',
                'acudiente_telefono' => 'Escribe un teléfono válido para el acudiente: solo números, entre 7 y 15 dígitos.',
            ]);

        $this->assertNadaGuardado();
    }

    /**
     * DADO un menor y su acudiente que llenan todo y firman los dos,
     * CUANDO aceptan,
     * ENTONCES se guardan las dos firmas en el bucket privado con su huella, el teléfono del
     * acudiente, y el PDF muestra ambas firmas verificadas.
     */
    public function test_el_menor_y_su_acudiente_firman_y_quedan_las_dos_firmas(): void
    {
        $this->aceptar($this->datosDeMenor())->assertOk();

        $aceptacion = ConsentimientoAceptacion::sole();
        $this->assertSame('Acudiente', $aceptacion->otorgante);
        $this->assertSame('3112223344', $aceptacion->acudiente_telefono);
        $this->assertSame("casos/{$this->caso->id_caso}/{$aceptacion->id_aceptacion}-estudiante.png", $aceptacion->firma_estudiante_ruta);

        $disco = Storage::disk('firmas');
        $this->assertTrue($disco->exists($aceptacion->firma_ruta));
        $this->assertTrue($disco->exists($aceptacion->firma_estudiante_ruta));
        $this->assertSame(hash('sha256', $disco->get($aceptacion->firma_estudiante_ruta)), $aceptacion->firma_estudiante_sha256);
        $this->assertNotSame($aceptacion->firma_sha256, null);

        $html = view('documentos.consentimiento', \App\Documentos\PdfConsentimiento::datos($aceptacion))->render();
        foreach (['Estudiante (menor de edad)', 'Acudiente', 'Teléfono', '3112223344', 'Huella firma del estudiante'] as $esperado) {
            $this->assertStringContainsString(e($esperado), $html, "Falta en el PDF: $esperado");
        }
        $this->assertSame(3, substr_count($html, 'Verificada:'));
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
    }

    /**
     * La firma del menor se consulta como las demás: solo quien puede ver la evidencia, y queda auditada.
     */
    public function test_la_firma_del_menor_solo_la_ven_quienes_pueden_consultar_la_evidencia(): void
    {
        $this->aceptar($this->datosDeMenor())->assertOk();
        $aceptacion = ConsentimientoAceptacion::sole();
        $url = route('consentimientos.firma-profesional', [$aceptacion, 'estudiante']);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee($url, false)
            ->assertSee('Firma del estudiante (menor de edad)');

        $this->actingAs($this->profesional)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Firma-Integra', 'si');
        $this->assertSame('estudiante', AuditoriaExpediente::where('accion', 'firma_consultada')->latest('id_auditoria')->first()->detalle['firmante']);

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get($url)->assertForbidden();
    }

    /**
     * DADO un menor y su acudiente que firman,
     * CUANDO falla la base al registrar,
     * ENTONCES se borran las dos firmas subidas: nunca queda una firma sin su registro.
     */
    public function test_si_falla_la_base_se_borran_las_dos_firmas_del_menor(): void
    {
        $datos = $this->datosDeMenor();
        DB::unprepared("CREATE TRIGGER falla_insercion BEFORE INSERT ON consentimiento_aceptaciones BEGIN SELECT RAISE(ABORT, 'falla simulada'); END;");

        $this->aceptar($datos)->assertStatus(409);

        $this->assertNadaGuardado();
    }

    /**
     * Un adulto firma solo él: no tiene firma de menor ni se puede pedir una.
     */
    public function test_un_adulto_no_tiene_firma_de_menor(): void
    {
        $this->aceptar(['firma_estudiante' => $this->firma()])->assertOk();
        $aceptacion = ConsentimientoAceptacion::sole();

        $this->assertNull($aceptacion->firma_estudiante_ruta);
        $this->assertCount(1, Storage::disk('firmas')->allFiles());
        $this->actingAs($this->profesional)->get(route('consentimientos.firma-profesional', [$aceptacion, 'estudiante']))->assertNotFound();
    }

    public function test_sin_consentimiento_de_menores_publicado_no_se_puede_firmar(): void
    {
        $this->caso->solicitud->persona->update(['fecha_nacimiento' => '2010-01-15']);

        $this->actingAs($this->estudiante)->get(route('consentimientos.firmar', $this->caso))
            ->assertOk()
            ->assertSee('No hay consentimiento para menores de edad publicado para Salud Mental.')
            ->assertDontSee('Acepto y firmo');
    }

    public function test_sin_datos_del_estudiante_le_indica_que_los_complete_su_profesional(): void
    {
        $this->caso->solicitud->persona->update(['fecha_nacimiento' => null]);

        $this->actingAs($this->estudiante)->get(route('consentimientos.firmar', $this->caso))
            ->assertOk()
            ->assertSee('Aún no puedes firmar')
            ->assertSee('tu profesional (Camila Muñoz) debe registrar', false)
            ->assertDontSee('name="volver"', false)
            ->assertDontSee('Acepto y firmo');
    }

    /**
     * DADO un caso de Salud Mental,
     * CUANDO alguien distinto del estudiante intenta firmar,
     * ENTONCES no puede: ni otro estudiante ni el profesional del caso.
     */
    public function test_solo_el_estudiante_del_caso_firma_su_consentimiento(): void
    {
        $otroEstudiante = $this->crearUsuarioConRol('Persona');

        $this->actingAs($otroEstudiante)->get(route('consentimientos.firmar', $this->caso))->assertForbidden();
        $this->aceptar(como: $otroEstudiante)->assertForbidden();

        $this->actingAs($this->profesional)->get(route('consentimientos.firmar', $this->caso))->assertRedirect($this->profesional->getDashboardUrl());
        $this->aceptar(como: $this->profesional, json: false)->assertRedirect($this->profesional->getDashboardUrl());

        $this->assertNadaGuardado();
    }

    public function test_en_un_caso_cerrado_no_se_registra(): void
    {
        $this->caso->update(['estado_caso' => 'Cerrado']);

        $this->actingAs($this->estudiante)->get(route('consentimientos.firmar', $this->caso))
            ->assertRedirect($this->urlMisCitas());
        $this->aceptar()->assertStatus(409);

        $this->assertNadaGuardado();
    }

    public function test_sin_almacenamiento_configurado_solo_se_puede_registrar_el_rechazo(): void
    {
        config(['filesystems.disks.firmas.key' => null]);

        $this->actingAs($this->estudiante)->get(route('consentimientos.firmar', $this->caso))
            ->assertSee('Por ahora no es posible guardar firmas.');

        $this->aceptar()->assertStatus(409)->assertJson(['message' => 'El almacenamiento de firmas no está configurado. Avisa al administrador; mientras tanto no se puede registrar la aceptación.']);
        $this->assertNadaGuardado();

        $this->aceptar(['decision' => 'Rechazado', 'firma' => null])->assertOk();
        $this->assertSame('Rechazado', ConsentimientoAceptacion::sole()->decision);
    }

    public function test_si_falla_el_almacenamiento_no_se_registra_nada(): void
    {
        $disco = Mockery::mock(Filesystem::class);
        $disco->shouldReceive('put')->andThrow(new RuntimeException('Supabase no responde'));
        Storage::shouldReceive('disk')->with('firmas')->andReturn($disco);

        $this->aceptar()->assertStatus(409)->assertJson(['message' => 'No se pudo guardar la firma. No se registró nada; intenta de nuevo.']);

        $this->assertSame(0, ConsentimientoAceptacion::count());
    }

    public function test_si_falla_la_base_se_borra_la_firma_subida(): void
    {
        DB::unprepared("CREATE TRIGGER falla_insercion BEFORE INSERT ON consentimiento_aceptaciones BEGIN SELECT RAISE(ABORT, 'falla simulada'); END;");

        $this->aceptar()->assertStatus(409)->assertJson(['message' => 'No fue posible registrar el consentimiento. No se guardó nada; intenta de nuevo.']);

        $this->assertNadaGuardado();
        $this->assertSame(0, AuditoriaExpediente::count());
    }

    public function test_un_doble_envio_no_duplica_el_registro(): void
    {
        $this->aceptar()->assertOk();
        Carbon::setTestNow(now()->addSeconds(3));
        $this->aceptar()->assertOk()->assertJson(['mensaje' => 'El consentimiento ya estaba registrado.']);

        $this->assertSame(1, ConsentimientoAceptacion::count());
        $this->assertCount(1, Storage::disk('firmas')->allFiles());
    }

    /**
     * DADO un consentimiento firmado,
     * CUANDO alguien pide ver la firma,
     * ENTONCES solo la ven el profesional del caso, quien lo remitió y el administrador (CA-04.2).
     */
    public function test_la_firma_solo_la_ven_quienes_pueden_consultar_la_evidencia(): void
    {
        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();
        $png = Storage::disk('firmas')->get($aceptacion->firma_ruta);
        $url = route('consentimientos.firma', $aceptacion);

        $this->actingAs($this->profesional)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Firma-Integra', 'si')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($png, $this->actingAs($this->profesional)->get($url)->getContent());

        $this->actingAs($this->crearUsuarioConRol('Admin'))->get($url)->assertOk();
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get($url)->assertForbidden();
        $this->actingAs($this->caso->solicitud->persona->usuario)->get($url)->assertForbidden();

        $consultas = AuditoriaExpediente::where('accion', 'firma_consultada')->get();
        $this->assertCount(3, $consultas);
        $this->assertTrue($consultas->first()->detalle['integra']);
    }

    public function test_quien_remitio_el_caso_ya_no_ve_la_firma(): void
    {
        $this->aceptar();
        $destino = $this->crearUsuarioConRol('Profesional');
        $this->actingAs($this->profesional)->post(route('remisiones.store', $this->caso), [
            'destino_remision' => 'Interna', 'id_profesional_destino' => $destino->id_usuario, 'motivo' => 'Cambio de profesional.',
        ])->assertSessionHasNoErrors();

        $url = route('consentimientos.firma', ConsentimientoAceptacion::sole());
        $this->actingAs($this->profesional)->get($url)->assertForbidden();
        $this->actingAs($destino)->get($url)->assertOk();
    }

    public function test_si_la_firma_fue_alterada_lo_indica(): void
    {
        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();
        Storage::disk('firmas')->put($aceptacion->firma_ruta, 'alterada');

        $this->actingAs($this->profesional)->get(route('consentimientos.firma', $aceptacion))->assertHeader('X-Firma-Integra', 'no');
        $this->assertFalse(AuditoriaExpediente::where('accion', 'firma_consultada')->sole()->detalle['integra']);
    }

    public function test_el_expediente_muestra_el_historial_y_la_firma_solo_a_quien_corresponde(): void
    {
        config(['sigabu.consentimiento.exigir' => true]);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('Falta el consentimiento informado firmado: el estudiante debe firmarlo desde su cuenta')
            ->assertDontSee(route('consentimientos.firmar', $this->caso), false)
            ->assertDontSee('Firmar consentimiento');

        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('✓ Consentimiento aceptado el 2026-10-08 10:00.')
            ->assertSee('Consentimiento SM-ORIENTACION · versión 1')
            ->assertSee('Ver texto y firma')
            ->assertSee('firmado por el estudiante desde su cuenta')
            ->assertSee(route('consentimientos.firma', $aceptacion), false);

        // Otro profesional solo ve el resumen del caso, sin el consentimiento
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('casos.show', $this->caso))
            ->assertSee('Este caso no está asignado a ti.')
            ->assertDontSee('Consentimiento SM-ORIENTACION')
            ->assertDontSee(route('consentimientos.firma', $aceptacion), false)
            ->assertDontSee('Ver texto y firma');
    }

    /**
     * DADO un caso que requiere consentimiento,
     * CUANDO el estudiante entra a "Mis citas",
     * ENTONCES ve el botón para firmarlo; si ya lo rechazó, puede firmarlo después.
     */
    public function test_el_estudiante_ve_el_boton_para_firmar_en_mis_citas(): void
    {
        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))
            ->assertOk()
            ->assertSee('Antes de tu atención debes leer y firmar el consentimiento informado.')
            ->assertSee(route('consentimientos.firmar', $this->caso), false);

        $this->aceptar(['decision' => 'Rechazado', 'firma' => null]);

        $this->actingAs($this->estudiante)->get(route('citas.miscitas'))
            ->assertSee('registraste que no aceptas el consentimiento informado')
            ->assertSee(route('consentimientos.firmar', $this->caso), false);
    }

    public function test_el_estudiante_ve_que_acepto_y_el_texto_pero_no_su_firma(): void
    {
        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();

        $this->actingAs($this->caso->solicitud->persona->usuario)->get(route('citas.miscitas'))
            ->assertOk()
            ->assertSee('Consentimiento informado aceptado el 2026-10-08')
            ->assertSee('(versión 1)')
            ->assertSee('de Tuluá, EPS Nueva EPS')
            ->assertDontSee(route('consentimientos.firma', $aceptacion), false)
            ->assertDontSee(route('consentimientos.firmar', $this->caso), false);
    }

    /**
     * DADO el encabezado del formato en papel (vinculación, programa, semestre y dependencia),
     * CUANDO el estudiante acepta,
     * ENTONCES lo debe llenar, y queda en la evidencia.
     */
    public function test_al_aceptar_exige_el_encabezado_del_formato(): void
    {
        DB::table('vinculacion')->insert(['id_persona' => $this->caso->solicitud->persona->id_persona, 'tipo_vinculacion' => 'Estudiante', 'facultad' => 'Ciencias de la Salud']);

        $this->actingAs($this->estudiante)->get(route('consentimientos.firmar', $this->caso))
            ->assertSee('Tu vinculación con la universidad')
            ->assertSee('<option value="Docente TCO"', false)
            ->assertSee('value="Ciencias de la Salud"', false);

        $this->aceptar(['vinculacion' => null, 'dependencia_facultad' => null])->assertUnprocessable()->assertJsonValidationErrors(['vinculacion', 'dependencia_facultad']);
        $this->aceptar(['vinculacion' => 'Rector'])->assertJsonValidationErrors(['vinculacion']);
        $this->aceptar(['programa_academico' => null, 'semestre' => null])->assertJsonValidationErrors(['programa_academico', 'semestre']);
        $this->aceptar(['semestre' => '13'])->assertJsonValidationErrors(['semestre']);
        $this->assertNadaGuardado();

        $this->aceptar()->assertOk();
        $aceptacion = ConsentimientoAceptacion::sole();
        $this->assertSame(['Estudiante', 'Psicología', 3, 'Facultad de Ciencias de la Salud'], [$aceptacion->vinculacion, $aceptacion->programa_academico, $aceptacion->semestre, $aceptacion->dependencia_facultad]);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('Vinculación: Estudiante · Psicología · semestre 3 · Facultad de Ciencias de la Salud.');
    }

    public function test_quien_no_es_estudiante_no_llena_programa_ni_semestre_y_el_rechazo_no_exige_el_encabezado(): void
    {
        $this->aceptar(['vinculacion' => 'Docente TC', 'programa_academico' => null, 'semestre' => null, 'dependencia_facultad' => 'Facultad de Ingeniería'])->assertOk();
        $this->assertSame('Docente TC', ConsentimientoAceptacion::sole()->vinculacion);

        $this->travel(1)->minutes();
        $this->aceptar(['decision' => 'Rechazado', 'firma' => null, 'vinculacion' => null, 'dependencia_facultad' => null])->assertOk();
        $this->assertSame(2, ConsentimientoAceptacion::count());
    }

    /**
     * DADO un consentimiento que aceptó el estudiante,
     * CUANDO el profesional tratante firma en el expediente,
     * ENTONCES su firma queda en el bucket, unida a esa aceptación y auditada, como en el formato en papel.
     */
    public function test_el_profesional_tratante_contrafirma_el_consentimiento(): void
    {
        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();
        $url = route('consentimientos.firmar-profesional', $aceptacion);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('Falta la firma del profesional tratante (Camila Muñoz).')
            ->assertSee($url, false)
            ->assertSee('Firmar consentimiento');

        $this->actingAs($this->profesional)->postJson($url, ['firma' => $this->firma()])
            ->assertOk()
            ->assertJson(['redirect' => route('casos.show', $this->caso)]);

        $firma = $aceptacion->fresh()->firmaProfesional;
        $this->assertSame($this->profesional->id_usuario, $firma->id_profesional);
        $this->assertSame("casos/{$this->caso->id_caso}/{$aceptacion->id_aceptacion}-profesional.png", $firma->firma_ruta);
        $this->assertSame($firma->firma_sha256, hash('sha256', Storage::disk('firmas')->get($firma->firma_ruta)));
        $this->assertSame(1, AuditoriaExpediente::where('accion', 'consentimiento_firmado_profesional')->where('id_caso', $this->caso->id_caso)->count());

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('✓ Firmado por el profesional tratante el 2026-10-08 10:00.')
            ->assertSee(route('consentimientos.firma-profesional', [$aceptacion, 'profesional']), false)
            ->assertDontSee($url, false);

        // Un segundo envío no la reemplaza: la firma es inmutable.
        $this->actingAs($this->profesional)->postJson($url, ['firma' => $this->firma()])->assertOk();
        $this->assertSame(1, DB::table('consentimiento_firmas_profesional')->count());
        $this->assertFalla(fn () => DB::table('consentimiento_firmas_profesional')->update(['firma_ruta' => 'otra.png']));
    }

    public function test_solo_el_profesional_tratante_firma_y_solo_un_consentimiento_aceptado(): void
    {
        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->postJson(route('consentimientos.firmar-profesional', $aceptacion), ['firma' => $this->firma()])->assertForbidden();

        $this->travel(1)->minutes();
        $this->aceptar(['decision' => 'Rechazado', 'firma' => null]);
        $rechazo = ConsentimientoAceptacion::where('decision', 'Rechazado')->sole();
        $this->actingAs($this->profesional)->postJson(route('consentimientos.firmar-profesional', $rechazo), ['firma' => $this->firma()])->assertForbidden();

        $this->actingAs($this->profesional)->postJson(route('consentimientos.firmar-profesional', $aceptacion), ['firma' => $this->firma(false)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['firma' => 'La firma está vacía o es muy corta.']);

        $this->assertSame(0, DB::table('consentimiento_firmas_profesional')->count());
    }

    public function test_la_firma_del_profesional_solo_la_ven_quienes_pueden_consultar_la_evidencia(): void
    {
        $this->aceptar();
        $aceptacion = ConsentimientoAceptacion::sole();
        $url = route('consentimientos.firma-profesional', [$aceptacion, 'profesional']);

        $this->actingAs($this->profesional)->get($url)->assertNotFound();

        $this->actingAs($this->profesional)->postJson(route('consentimientos.firmar-profesional', $aceptacion), ['firma' => $this->firma()]);

        $this->actingAs($this->profesional)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Firma-Integra', 'si');
        $this->actingAs($this->crearUsuarioConRol('Admin'))->get($url)->assertOk();
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get($url)->assertForbidden();

        $consulta = AuditoriaExpediente::where('accion', 'firma_consultada')->latest('id_auditoria')->first();
        $this->assertSame('profesional', $consulta->detalle['firmante']);
    }

    private function assertFalla(callable $accion): void
    {
        try {
            $accion();
        } catch (\Throwable) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Se esperaba que la base rechazara la operación.');
    }
}

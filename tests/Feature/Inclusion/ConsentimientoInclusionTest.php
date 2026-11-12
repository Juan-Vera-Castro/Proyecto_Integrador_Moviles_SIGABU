<?php

namespace Tests\Feature\Inclusion;

use App\Documentos\ConsentimientoInclusion;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Facultad;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\ModeloConsentimiento;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\Concerns\GeneraFirmaPng;
use Tests\TestCase;

/**
 * H3: consentimiento del Proceso de Inclusión en línea y en papel, con datos ficticios.
 */
class ConsentimientoInclusionTest extends TestCase
{
    use CreaEsquemaSigabu, GeneraFirmaPng, RefreshDatabase;

    private User $persona;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', Actuacion::ZONA_HORARIA));
        Storage::fake(ConsentimientoAceptacion::DISCO_FIRMAS);
        config(['filesystems.disks.firmas.key' => 'clave', 'filesystems.disks.firmas.secret' => 'secreto', 'filesystems.disks.firmas.endpoint' => 'https://ejemplo.invalid']);

        $this->crearEsquemaSigabu();
        ConsentimientoInclusion::cargar();
        Facultad::firstOrCreate(['nombre' => 'Facultad Ficticia'], ['activa' => true]);

        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Profesional Ficticia']);
        $this->profesional->forceFill(['area_profesional' => 'Inclusión'])->save();
        $this->persona = $this->crearPersona();
        $this->caso = $this->crearCaso($this->persona);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearPersona(string $nacimiento = '2000-01-15'): User
    {
        return $this->crearUsuarioConRol('Persona', [
            'nombre_completo' => 'Persona Ficticia',
            'numero_documento' => fake()->unique()->numerify('9#########'),
            'tipo_documento' => 'CC',
            'sexo' => 'F',
            'fecha_nacimiento' => $nacimiento,
        ]);
    }

    private function crearCaso(User $persona, string $servicio = 'Inclusión'): Caso
    {
        $solicitud = $persona->persona->solicitudes()->create(['tipo_servicio' => $servicio, 'descripcion' => 'Prueba']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $this->profesional->id_usuario]);

        return $caso->fresh();
    }

    private function datos(array $cambios = [], ?Caso $caso = null): array
    {
        return array_merge([
            'id_version' => ConsentimientoInclusion::versionDe($caso ?? $this->caso)->id_version,
            'nombre_persona' => 'Persona Corregida',
            'documento_persona' => '900111333',
            'numero_contacto' => '300 123-4567',
            'vinculacion' => 'Estudiante Diurno',
            'facultad' => 'Facultad Ficticia',
            'programa_academico' => 'Programa Ficticio',
            'semestre' => '3',
            'dependencia' => 'N/A',
            'decision_acompanamiento' => '1',
            'decision_datos_generales' => '1',
            'firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng()),
        ], $cambios);
    }

    private function enviar(array $cambios = [], ?User $como = null, ?Caso $caso = null)
    {
        return $this->actingAs($como ?? $this->persona)
            ->postJson(route('inclusion.consentimiento.enviar', $caso ?? $this->caso), $this->datos($cambios, $caso));
    }

    private function ultima(): ConsentimientoAceptacion
    {
        return ConsentimientoAceptacion::with('detalleInclusion')->where('id_caso', $this->caso->id_caso)->orderByDesc('fecha_hora')->firstOrFail();
    }

    private function datosPapel(array $cambios = []): array
    {
        $datos = $this->datos($cambios + [
            'archivo' => UploadedFile::fake()->createWithContent('formato.pdf', '%PDF-1.4 formato ficticio'),
            'fecha_firma_papel' => '2026-11-05',
            'motivo_papel' => 'La persona no tiene acceso a la plataforma.',
        ]);
        unset($datos['firma'], $datos['id_version']);

        return $datos;
    }

    private function registrarPapel(array $cambios = [])
    {
        return $this->actingAs($this->profesional)->post(route('inclusion.consentimiento.papel.registrar', $this->caso), $this->datosPapel($cambios));
    }

    public function test_el_flujo_actual_redirige_al_formato_de_inclusion_y_salud_mental_sigue_igual(): void
    {
        $this->actingAs($this->persona)->get(route('consentimientos.firmar', $this->caso))
            ->assertRedirect(route('inclusion.consentimiento.formulario', $this->caso));

        $casoSaludMental = $this->crearCaso($this->persona, 'Salud Mental');

        $this->actingAs($this->persona)->get(route('consentimientos.firmar', $casoSaludMental))->assertOk();
    }

    public function test_el_flujo_general_no_registra_un_consentimiento_de_este_formato(): void
    {
        $this->actingAs($this->persona)->postJson(route('consentimientos.registrar', $this->caso), [
            'decision' => 'Rechazado',
            'id_version' => ConsentimientoInclusion::versionDe($this->caso)->id_version,
        ])->assertStatus(409);

        $this->assertSame(0, ConsentimientoAceptacion::count());
    }

    public function test_un_caso_de_inclusion_con_otro_modelo_vigente_sigue_en_el_flujo_general(): void
    {
        ModeloConsentimiento::whereIn('codigo', [ConsentimientoInclusion::CODIGO_GENERAL, ConsentimientoInclusion::CODIGO_MENORES])->get()->each->update(['activo' => false]);
        $modelo = ModeloConsentimiento::create(['codigo' => 'IN-OTRO', 'nombre' => 'Otro', 'tipo_servicio' => 'Inclusión', 'poblacion' => 'General', 'activo' => true]);
        VersionConsentimiento::create(['id_modelo' => $modelo->id_modelo, 'numero_version' => 1, 'texto' => 'Texto', 'estado' => 'publicada']);

        $this->actingAs($this->persona)->get(route('consentimientos.firmar', $this->caso))->assertOk();
        $this->actingAs($this->persona)->get(route('inclusion.consentimiento.formulario', $this->caso))->assertRedirect(route('consentimientos.firmar', $this->caso));
    }

    public function test_el_formulario_precarga_los_datos_y_muestra_el_texto_antes_de_las_casillas(): void
    {
        $respuesta = $this->actingAs($this->persona)->get(route('inclusion.consentimiento.formulario', $this->caso))->assertOk();
        $html = $respuesta->getContent();

        $respuesta->assertSee('Persona Ficticia')->assertSee($this->persona->persona->numero_documento);
        $this->assertLessThan(strpos($html, 'id="decision_acompanamiento"'), strpos($html, 'id="texto-titulo"'));

        foreach (array_keys(ConsentimientoInclusion::DECISIONES) as $campo) {
            $this->assertStringContainsString("id=\"{$campo}\"", $html);
        }
    }

    public function test_un_envio_valido_guarda_aceptacion_detalle_firma_y_auditoria(): void
    {
        $this->enviar()->assertOk()->assertJsonPath('redirect', route('inclusion.consentimiento.ver', $this->caso));

        $aceptacion = $this->ultima();
        $detalle = $aceptacion->detalleInclusion;

        $this->assertSame(ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION, $aceptacion->medio);
        $this->assertSame('Estudiante', $aceptacion->vinculacion);
        $this->assertSame('Facultad Ficticia', $aceptacion->dependencia_facultad);
        $this->assertStringContainsString('Yo, Persona Corregida, con documento de identidad número 900111333', $aceptacion->texto_presentado);
        $this->assertStringContainsString('☑ ACEPTO recibir', $aceptacion->texto_presentado);
        $this->assertStringContainsString('☐ AUTORIZO DE MANERA EXPRESA', $aceptacion->texto_presentado);
        $this->assertSame(hash('sha256', $aceptacion->texto_presentado), $aceptacion->texto_sha256);
        $this->assertTrue($detalle->decision_acompanamiento);
        $this->assertFalse($detalle->decision_datos_sensibles);
        $this->assertSame('3001234567', $detalle->numero_contacto);
        $this->assertSame('Estudiante Diurno', $detalle->vinculacion_detalle);
        $this->assertSame('Persona Corregida', $detalle->nombre_persona);
        Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->assertExists($aceptacion->firma_ruta);
        $this->assertSame('Completado', $this->caso->fresh()->load('aceptacionesConsentimiento.detalleInclusion')->estadoConsentimiento());
        $this->assertSame('Persona Ficticia', $this->persona->persona->fresh()->nombre_completo, 'Corregir el formato no cambia la cuenta.');
        $this->assertTrue(AuditoriaExpediente::where('accion', AuditoriaExpediente::CONSENTIMIENTO_ACEPTADO)->exists());
    }

    public function test_sin_datos_o_sin_firma_no_se_guarda_nada_y_cada_error_va_a_su_campo(): void
    {
        $this->enviar(['numero_contacto' => 'abc', 'firma' => null, 'facultad' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero_contacto', 'firma', 'facultad']);

        $this->assertSame(0, ConsentimientoAceptacion::count());
        $this->assertSame([], Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->allFiles());
    }

    public function test_una_firma_vacia_se_rechaza(): void
    {
        $this->enviar(['firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng(false))])
            ->assertStatus(422)->assertJsonValidationErrors(['firma']);
    }

    public function test_una_version_vieja_del_texto_se_rechaza(): void
    {
        $this->enviar(['id_version' => 999999])->assertStatus(422)->assertJsonValidationErrors(['id_version']);
    }

    public function test_quien_no_es_estudiante_no_llena_facultad_y_se_guarda_na(): void
    {
        $this->enviar(['vinculacion' => 'Contratista', 'facultad' => null, 'programa_academico' => null, 'semestre' => null])->assertOk();

        $aceptacion = $this->ultima();

        $this->assertSame('Otro', $aceptacion->vinculacion);
        $this->assertSame('N/A', $aceptacion->dependencia_facultad);
        $this->assertSame('Contratista', $aceptacion->detalleInclusion->vinculacion_detalle);
    }

    public function test_si_falla_la_base_se_borra_la_firma_y_no_queda_nada(): void
    {
        ConsentimientoDetalle::creating(fn () => throw new \RuntimeException('falla de prueba'));

        $this->enviar()->assertStatus(503);

        $this->assertSame(0, ConsentimientoAceptacion::count());
        $this->assertSame([], Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->allFiles());
    }

    public function test_un_menor_debe_traer_acudiente_y_un_adulto_firma_por_si_mismo(): void
    {
        $menor = $this->crearPersona('2010-05-01');
        $caso = $this->crearCaso($menor);

        $this->enviar([], $menor, $caso)->assertStatus(422)
            ->assertJsonValidationErrors(['acudiente_nombre', 'acudiente_tipo_documento', 'acudiente_numero_documento', 'acudiente_parentesco']);

        $this->enviar([
            'acudiente_nombre' => 'Acudiente Ficticio',
            'acudiente_tipo_documento' => 'CC',
            'acudiente_numero_documento' => '900222333',
            'acudiente_parentesco' => 'Tío',
        ], $menor, $caso)->assertOk();

        $aceptacion = ConsentimientoAceptacion::where('id_caso', $caso->id_caso)->firstOrFail();
        $this->assertSame(ConsentimientoAceptacion::OTORGANTE_ACUDIENTE, $aceptacion->otorgante);
        $this->assertStringContainsString('Yo, Acudiente Ficticio', $aceptacion->texto_presentado);
        $this->assertSame(ConsentimientoInclusion::CODIGO_MENORES, $aceptacion->version->modelo->codigo);

        $this->enviar(['acudiente_nombre' => 'No Aplica'])->assertOk();
        $this->assertSame(ConsentimientoAceptacion::OTORGANTE_ESTUDIANTE, $this->ultima()->otorgante);
        $this->assertNull($this->ultima()->acudiente_nombre);
    }

    public function test_el_borrador_se_retoma_sin_firma_y_deja_de_mostrarse_al_enviar(): void
    {
        $url = route('inclusion.borradores.guardar', [$this->caso, Borrador::CONSENTIMIENTO]);

        $this->actingAs($this->persona)->putJson($url, ['datos' => ['numero_contacto' => '3009998877', 'decision_compartir' => '1', 'firma' => 'data:image/png;base64,xxx']])
            ->assertOk()->assertJsonPath('guardado', '10:00');

        $borrador = Borrador::de($this->caso, $this->persona, Borrador::CONSENTIMIENTO);
        $this->assertArrayNotHasKey('firma', $borrador->datos);

        $respuesta = $this->actingAs($this->persona)->get(route('inclusion.consentimiento.formulario', $this->caso))->assertOk();
        $respuesta->assertSee('3009998877');
        $this->assertMatchesRegularExpression('/id="decision_compartir"[^>]*checked/', $respuesta->getContent());

        $this->enviar()->assertOk();
        $this->assertNull(Borrador::de($this->caso, $this->persona, Borrador::CONSENTIMIENTO));
    }

    public function test_con_consentimiento_vigente_no_se_pide_firmar_de_nuevo(): void
    {
        $this->enviar()->assertOk();

        $this->actingAs($this->persona)->get(route('inclusion.consentimiento.formulario', $this->caso))
            ->assertRedirect(route('inclusion.consentimiento.ver', $this->caso));

        $this->enviar()->assertOk();
        $this->assertSame(1, ConsentimientoAceptacion::count());
    }

    public function test_la_persona_ve_el_suyo_y_mis_citas_lo_enlaza(): void
    {
        $this->enviar()->assertOk();

        $this->actingAs($this->persona)->get(route('inclusion.consentimiento.ver', $this->caso))
            ->assertOk()->assertSee('Persona Corregida')->assertSee('Completado')->assertSee('Revocar consentimiento');

        $this->actingAs($this->persona)->get(route('citas.miscitas'))->assertOk()->assertSee('Mi consentimiento');
    }

    public function test_solo_ven_el_consentimiento_la_persona_el_asignado_y_el_administrador(): void
    {
        $this->enviar()->assertOk();
        $url = route('inclusion.consentimiento.ver', $this->caso);

        $otraPersona = $this->crearUsuarioConRol('Persona');
        $otroProfesional = $this->crearUsuarioConRol('Profesional');
        $otroProfesional->forceFill(['area_profesional' => 'Inclusión'])->save();

        $this->actingAs($otraPersona)->get($url)->assertForbidden();
        $this->actingAs($otroProfesional)->get($url)->assertForbidden();
        $this->actingAs($this->profesional)->get($url)->assertOk()->assertDontSee('Revocar consentimiento');
        $this->actingAs($this->crearUsuarioConRol('Admin'))->get($url)->assertOk()->assertDontSee('Revocar consentimiento');
    }

    public function test_nadie_mas_diligencia_ni_revoca(): void
    {
        $this->enviar()->assertOk();
        $otraPersona = $this->crearUsuarioConRol('Persona');

        $this->actingAs($otraPersona)->get(route('inclusion.consentimiento.formulario', $this->caso))->assertForbidden();
        $this->actingAs($otraPersona)->post(route('inclusion.consentimiento.revocar', $this->caso))->assertForbidden();
        $this->enviar([], $otraPersona)->assertForbidden();

        $this->assertSame(1, ConsentimientoAceptacion::count());
    }

    public function test_revocar_agrega_una_fila_bloquea_la_atencion_y_permite_firmar_de_nuevo(): void
    {
        $this->enviar()->assertOk();
        $aceptada = $this->ultima();

        Carbon::setTestNow(now()->addMinute());

        $this->actingAs($this->persona)->post(route('inclusion.consentimiento.revocar', $this->caso))
            ->assertRedirect(route('inclusion.consentimiento.ver', $this->caso));

        $revocacion = $this->ultima();
        $caso = Caso::with('aceptacionesConsentimiento.detalleInclusion', 'aceptacionesConsentimiento.firmaProfesional', 'solicitud')->find($this->caso->id_caso);

        $this->assertSame(2, ConsentimientoAceptacion::count());
        $this->assertTrue($revocacion->detalleInclusion->es_revocacion);
        $this->assertSame(ConsentimientoAceptacion::RECHAZADO, $revocacion->decision);
        $this->assertSame($aceptada->texto_presentado, $revocacion->texto_presentado);
        $this->assertSame('Revocado', $caso->estadoConsentimiento());
        $this->assertFalse($caso->admiteAtencion());
        $this->assertStringContainsString('revocó', $caso->motivoSinAtencion());
        $this->assertTrue(AuditoriaExpediente::where('accion', AuditoriaExpediente::CONSENTIMIENTO_REVOCADO)->exists());

        $this->actingAs($this->persona)->get(route('inclusion.consentimiento.ver', $this->caso))->assertOk()->assertSee('Revocado el')->assertSee('Firmar un consentimiento nuevo');

        Carbon::setTestNow(now()->addMinute());
        $this->enviar()->assertOk();

        $caso = Caso::with('aceptacionesConsentimiento.detalleInclusion', 'solicitud')->find($this->caso->id_caso);
        $this->assertSame(3, ConsentimientoAceptacion::count());
        $this->assertTrue($caso->admiteAtencion());
    }

    public function test_no_se_revoca_dos_veces(): void
    {
        $this->enviar()->assertOk();
        Carbon::setTestNow(now()->addMinute());
        $this->actingAs($this->persona)->post(route('inclusion.consentimiento.revocar', $this->caso));
        Carbon::setTestNow(now()->addMinute());
        $this->actingAs($this->persona)->post(route('inclusion.consentimiento.revocar', $this->caso))->assertSessionHas('error');

        $this->assertSame(2, ConsentimientoAceptacion::count());
    }

    public function test_la_contrafirma_pasa_a_firmado_y_no_aplica_si_ya_se_revoco(): void
    {
        $this->enviar()->assertOk();
        $aceptacion = $this->ultima();
        $firma = ['firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng())];

        $this->actingAs($this->profesional)->postJson(route('consentimientos.firmar-profesional', $aceptacion), $firma)->assertOk();
        $caso = Caso::with('aceptacionesConsentimiento.detalleInclusion', 'aceptacionesConsentimiento.firmaProfesional')->find($this->caso->id_caso);
        $this->assertSame('Firmado', $caso->estadoConsentimiento());

        Carbon::setTestNow(now()->addMinute());
        $otro = $this->crearCaso($this->crearPersona());
        $this->enviar([], $otro->solicitud->persona->usuario, $otro)->assertOk();
        $aceptacionOtro = ConsentimientoAceptacion::where('id_caso', $otro->id_caso)->firstOrFail();
        Carbon::setTestNow(now()->addMinute());
        $this->actingAs($otro->solicitud->persona->usuario)->post(route('inclusion.consentimiento.revocar', $otro));

        $this->actingAs($this->profesional)->postJson(route('consentimientos.firmar-profesional', $aceptacionOtro), $firma)->assertForbidden();
    }

    public function test_el_registro_en_papel_exige_archivo_fecha_y_motivo(): void
    {
        $this->actingAs($this->profesional)->get(route('inclusion.consentimiento.papel', $this->caso))->assertOk()->assertSee('Registrar');

        $this->registrarPapel(['archivo' => null, 'fecha_firma_papel' => null, 'motivo_papel' => null])
            ->assertSessionHasErrors(['archivo', 'fecha_firma_papel', 'motivo_papel']);
        $this->registrarPapel(['fecha_firma_papel' => '2026-11-07'])->assertSessionHasErrors('fecha_firma_papel');

        $this->assertSame(0, ConsentimientoAceptacion::count());
    }

    public function test_el_registro_en_papel_queda_firmado_con_su_leyenda_y_sin_imagen_de_firma(): void
    {
        $this->registrarPapel()->assertRedirect(route('profesional.expediente.show', $this->caso));

        $aceptacion = $this->ultima();
        $caso = Caso::with('aceptacionesConsentimiento.detalleInclusion', 'aceptacionesConsentimiento.firmaProfesional')->find($this->caso->id_caso);

        $this->assertSame(ConsentimientoAceptacion::MEDIO_FIRMA_PAPEL, $aceptacion->medio);
        $this->assertSame('Firmado', $caso->estadoConsentimiento());
        $this->assertSame('2026-11-05', $aceptacion->detalleInclusion->fecha_firma_papel->toDateString());
        Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->assertExists($aceptacion->firma_ruta);

        $this->actingAs($this->profesional)->get(route('inclusion.consentimiento.ver', $this->caso))->assertOk()->assertSee(ConsentimientoDetalle::LEYENDA_PAPEL);
        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))->assertOk()->assertSee(ConsentimientoDetalle::LEYENDA_PAPEL);
        $this->actingAs($this->profesional)->get(route('consentimientos.firma', $aceptacion))->assertNotFound();
        $this->actingAs($this->profesional)->postJson(route('consentimientos.firmar-profesional', $aceptacion), ['firma' => 'x'])->assertForbidden();
    }

    public function test_el_archivo_en_papel_solo_lo_descargan_quienes_ven_el_consentimiento(): void
    {
        $this->registrarPapel();
        $url = route('inclusion.consentimiento.papel.archivo', $this->ultima());

        $this->actingAs($this->crearUsuarioConRol('Persona'))->get($url)->assertForbidden();
        $this->actingAs($this->persona)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->profesional)->get($url)->assertOk();
        $this->actingAs($this->crearUsuarioConRol('Admin'))->get($url)->assertOk();
        $this->assertTrue(AuditoriaExpediente::where('accion', AuditoriaExpediente::FIRMA_CONSULTADA)->exists());
    }

    public function test_otro_profesional_y_la_persona_no_registran_en_papel(): void
    {
        $otro = $this->crearUsuarioConRol('Profesional');
        $otro->forceFill(['area_profesional' => 'Inclusión'])->save();
        $url = route('inclusion.consentimiento.papel.registrar', $this->caso);

        $this->actingAs($otro)->post($url, $this->datosPapel())->assertForbidden();
        $this->actingAs($this->persona)->post($url, $this->datosPapel())->assertRedirect();

        $this->assertSame(0, ConsentimientoAceptacion::count());
    }

    public function test_con_consentimiento_vigente_no_se_registra_en_papel(): void
    {
        $this->enviar()->assertOk();

        $this->registrarPapel()->assertSessionHas('error');

        $this->assertSame(1, ConsentimientoAceptacion::count());
    }

    public function test_el_expediente_muestra_los_documentos_de_inclusion_y_sus_acciones(): void
    {
        $url = route('profesional.expediente.show', $this->caso);

        $this->actingAs($this->profesional)->get($url)->assertOk()->assertSee('Documentos de Inclusión')->assertSee('Sin diligenciar')->assertSee('Cargar en papel');

        $this->enviar()->assertOk();

        $this->actingAs($this->profesional)->get($url)->assertOk()->assertSee('Completado')->assertSee('Persona Corregida')->assertSee('Contrafirmar')->assertDontSee('Cargar en papel');
    }

    public function test_el_expediente_de_salud_mental_no_muestra_esta_seccion(): void
    {
        $this->profesional->forceFill(['area_profesional' => 'Ambas'])->save();
        $caso = $this->crearCaso($this->persona, 'Salud Mental');

        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $caso))->assertOk()->assertDontSee('Documentos de Inclusión');
    }
}

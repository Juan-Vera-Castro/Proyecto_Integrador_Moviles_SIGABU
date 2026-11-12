<?php

namespace Tests\Feature\Inclusion;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Inclusion\Bitacora;
use App\Models\Inclusion\BitacoraCambio;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\Inclusion\Constancia;
use App\Models\Inclusion\EntradaBitacora;
use App\Models\Inclusion\Evidencia;
use App\Models\Inclusion\Reapertura;
use App\Models\ModeloConsentimiento;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\Concerns\GeneraFirmaPng;
use Tests\TestCase;

/**
 * H4: bitácora, matriz, cierre con constancia y reapertura, con datos ficticios.
 */
class BitacoraInclusionTest extends TestCase
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
        Storage::fake(Evidencia::DISCO);
        config(['filesystems.disks.firmas.key' => 'clave', 'filesystems.disks.firmas.secret' => 'secreto', 'filesystems.disks.firmas.endpoint' => 'https://ejemplo.invalid']);

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearProfesional('Inclusión');
        $this->persona = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Persona Ficticia']);
        $this->caso = $this->crearCaso($this->persona);
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

    private function crearCaso(User $persona): Caso
    {
        $solicitud = $persona->persona->solicitudes()->create(['tipo_servicio' => 'Inclusión', 'descripcion' => 'Prueba']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $this->profesional->id_usuario]);

        return $caso->fresh();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'rol_institucional' => 'Estudiante Diurno',
            'programa_dependencia' => 'Programa Ficticio',
            'situacion' => 'Situación ficticia',
            'ruta_activada' => 'Ruta ficticia',
            'soportes_texto' => 'Certificado ficticio',
            'orientacion' => 'Orientación ficticia',
        ], $cambios);
    }

    private function guardar(array $cambios = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)->post(route('inclusion.bitacora.guardar', $this->caso), $this->datos($cambios));
    }

    private function entrada(string $tipo, array $cambios = [])
    {
        return $this->actingAs($this->profesional)->post(route('inclusion.entradas.store', $this->caso), array_merge([
            'fecha_hora' => '2026-11-06T09:00',
            'tipo' => $tipo,
            'descripcion' => "Descripción ficticia de {$tipo}",
        ], $cambios));
    }

    private function subir(): string
    {
        return $this->actingAs($this->profesional)
            ->postJson(route('inclusion.evidencias.store', $this->caso), ['archivo' => UploadedFile::fake()->create('soporte.pdf', 20, 'application/pdf')])
            ->assertCreated()->json('id');
    }

    private function firmarCierre()
    {
        return $this->actingAs($this->profesional)->postJson(route('inclusion.cierre', $this->caso), [
            'firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng()),
        ]);
    }

    private function dejarCerrado(): void
    {
        $this->guardar();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL);
        $this->entrada(EntradaBitacora::CIERRE);
        $this->firmarCierre()->assertOk();
    }

    /**
     * Deja la última decisión del caso como revocación, sin pasar por la pantalla.
     */
    private function revocar(): void
    {
        $modelo = ModeloConsentimiento::create(['codigo' => 'IN-PRUEBA', 'nombre' => 'Prueba', 'tipo_servicio' => 'Inclusión', 'poblacion' => 'General', 'activo' => true]);
        $version = VersionConsentimiento::create(['id_modelo' => $modelo->id_modelo, 'numero_version' => 1, 'texto' => 'Texto', 'estado' => 'publicada']);
        $id = (string) Str::uuid();

        ConsentimientoAceptacion::create([
            'id_aceptacion' => $id,
            'id_caso' => $this->caso->id_caso,
            'id_version' => $version->id_version,
            'decision' => ConsentimientoAceptacion::RECHAZADO,
            'fecha_hora' => now(Actuacion::ZONA_HORARIA),
            'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION,
            'id_profesional' => $this->profesional->id_usuario,
            'texto_presentado' => 'Texto',
            'texto_sha256' => hash('sha256', 'Texto'),
        ]);
        ConsentimientoDetalle::create(['id_aceptacion' => $id, 'es_revocacion' => true]);
    }

    public function test_los_datos_generales_salen_del_caso(): void
    {
        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))
            ->assertOk()->assertSee($this->caso->codigo_expediente)->assertSee('Persona Ficticia')->assertSee('Guardar caracterización');
    }

    public function test_guarda_la_caracterizacion_con_sus_soportes_y_termina_el_borrador(): void
    {
        $id = $this->subir();
        Borrador::guardar($this->caso, $this->profesional, Borrador::BITACORA, ['situacion' => 'Texto a medias']);

        $this->guardar(['soportes' => [$id]])->assertRedirect(route('inclusion.bitacora', $this->caso));

        $bitacora = Bitacora::firstOrFail();
        $evidencia = Evidencia::findOrFail($id);

        $this->assertSame($this->caso->id_caso, $bitacora->id_caso);
        $this->assertSame(Evidencia::VINCULADA, $evidencia->estado);
        $this->assertSame($bitacora->id_bitacora, $evidencia->id_destino);
        $this->assertNull(Borrador::de($this->caso, $this->profesional, Borrador::BITACORA));
        $this->assertSame(0, BitacoraCambio::count());
        $this->assertTrue(AuditoriaExpediente::where('accion', AuditoriaExpediente::BITACORA_REGISTRADA)->exists());
        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertSee('soporte.pdf');
    }

    public function test_exige_los_campos_obligatorios(): void
    {
        $this->guardar(['situacion' => '', 'ruta_activada' => '', 'rol_institucional' => 'Otro'])
            ->assertSessionHasErrors(['situacion', 'ruta_activada', 'rol_institucional']);

        $this->assertSame(0, Bitacora::count());
    }

    public function test_el_borrador_acepta_campos_vacios_y_se_retoma(): void
    {
        $this->actingAs($this->profesional)
            ->putJson(route('inclusion.borradores.guardar', [$this->caso, Borrador::BITACORA]), ['datos' => ['situacion' => 'Texto a medias']])
            ->assertOk();

        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertSee('Texto a medias');
    }

    public function test_cada_cambio_de_la_caracterizacion_queda_registrado_sin_valores_en_la_auditoria(): void
    {
        $this->guardar();
        $this->guardar(['situacion' => 'Situación corregida']);

        $cambio = BitacoraCambio::firstOrFail();

        $this->assertSame(1, BitacoraCambio::count());
        $this->assertSame('situacion', $cambio->campo);
        $this->assertSame('Situación ficticia', $cambio->valor_anterior);
        $this->assertSame('Situación corregida', $cambio->valor_nuevo);
        $this->assertSame($this->profesional->id_usuario, $cambio->id_profesional);
        $this->assertSame('Situación corregida', Bitacora::firstOrFail()->situacion);

        $detalle = json_encode(AuditoriaExpediente::where('accion', AuditoriaExpediente::BITACORA_ACTUALIZADA)->firstOrFail()->detalle);
        $this->assertStringNotContainsString('corregida', $detalle);

        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertSee('Historial de cambios');
    }

    public function test_avisa_si_no_hay_autorizacion_de_datos_sensibles_y_deja_continuar(): void
    {
        $modelo = ModeloConsentimiento::create(['codigo' => 'IN-PRUEBA', 'nombre' => 'Prueba', 'tipo_servicio' => 'Inclusión', 'poblacion' => 'General', 'activo' => true]);
        $version = VersionConsentimiento::create(['id_modelo' => $modelo->id_modelo, 'numero_version' => 1, 'texto' => 'Texto', 'estado' => 'publicada']);
        $id = (string) Str::uuid();

        ConsentimientoAceptacion::create([
            'id_aceptacion' => $id, 'id_caso' => $this->caso->id_caso, 'id_version' => $version->id_version, 'decision' => 'Aceptado',
            'fecha_hora' => now(Actuacion::ZONA_HORARIA), 'medio' => ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION, 'id_profesional' => $this->profesional->id_usuario,
            'vinculacion' => 'Otro', 'dependencia_facultad' => 'N/A', 'texto_presentado' => 'Texto', 'texto_sha256' => hash('sha256', 'Texto'),
            'firma_ruta' => 'casos/x.png', 'firma_sha256' => hash('sha256', 'x'),
        ]);
        ConsentimientoDetalle::create(['id_aceptacion' => $id, 'decision_acompanamiento' => true, 'vinculacion_detalle' => 'Contratista']);

        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))
            ->assertOk()->assertSee('no autorizó el tratamiento de datos sensibles')->assertSee('Contratista');
        $this->guardar()->assertSessionHasNoErrors();
    }

    public function test_sin_consentimiento_nuevo_no_hay_aviso_de_datos_sensibles(): void
    {
        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertDontSee('no autorizó el tratamiento');
    }

    public function test_las_entradas_son_consecutivas_y_la_primera_es_atencion_inicial(): void
    {
        $this->guardar();

        $this->entrada(EntradaBitacora::SEGUIMIENTO)->assertSessionHasErrors('tipo', null, 'entrada');
        $this->assertSame(0, EntradaBitacora::count());

        $this->entrada(EntradaBitacora::ATENCION_INICIAL)->assertSessionHasNoErrors();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL)->assertSessionHasErrors('tipo', null, 'entrada');
        $this->entrada(EntradaBitacora::SEGUIMIENTO)->assertSessionHasNoErrors();
        $this->entrada(EntradaBitacora::SEGUIMIENTO)->assertSessionHasNoErrors();

        $this->assertSame([1, 2, 3], EntradaBitacora::orderBy('numero')->pluck('numero')->all());
        $this->assertSame($this->profesional->id_usuario, EntradaBitacora::firstOrFail()->id_profesional);
    }

    public function test_sin_caracterizacion_no_hay_entradas(): void
    {
        $this->entrada(EntradaBitacora::ATENCION_INICIAL)->assertSessionHas('error');

        $this->assertSame(0, EntradaBitacora::count());
    }

    public function test_una_entrada_valida_fecha_y_descripcion(): void
    {
        $this->guardar();

        $this->entrada(EntradaBitacora::ATENCION_INICIAL, ['fecha_hora' => '2026-11-07T09:00', 'descripcion' => ''])
            ->assertSessionHasErrors(['fecha_hora', 'descripcion'], null, 'entrada');
    }

    public function test_las_evidencias_quedan_asociadas_a_la_entrada(): void
    {
        $this->guardar();
        $id = $this->subir();

        $this->entrada(EntradaBitacora::ATENCION_INICIAL, ['evidencias' => [$id]])->assertSessionHasNoErrors();

        $entrada = EntradaBitacora::firstOrFail();
        $this->assertSame([$id], $entrada->evidencias->pluck('id_evidencia')->all());
    }

    public function test_una_evidencia_de_otro_caso_no_se_vincula_y_no_queda_entrada(): void
    {
        $this->guardar();
        $otro = $this->crearCaso($this->crearUsuarioConRol('Persona'));
        $id = $this->actingAs($this->profesional)
            ->postJson(route('inclusion.evidencias.store', $otro), ['url' => 'https://ejemplo.invalid/a'])->json('id');

        $this->entrada(EntradaBitacora::ATENCION_INICIAL, ['evidencias' => [$id]])->assertSessionHasErrors('evidencias', null, 'entrada');

        $this->assertSame(0, EntradaBitacora::count());
    }

    public function test_una_anotacion_no_cambia_la_entrada(): void
    {
        $this->guardar();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL);
        $entrada = EntradaBitacora::firstOrFail();

        $this->actingAs($this->profesional)->post(route('inclusion.entradas.anotar', $entrada), ['texto' => 'Corrección ficticia'])->assertSessionHasNoErrors();
        $this->actingAs($this->profesional)->post(route('inclusion.entradas.anotar', $entrada), ['texto' => ''])->assertSessionHasErrors('texto', null, 'anotacion');

        $this->assertSame('Descripción ficticia de Atención inicial', $entrada->fresh()->descripcion);
        $this->assertSame(1, $entrada->anotaciones()->count());
        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertSee('Corrección ficticia');
    }

    public function test_con_revocacion_solo_se_admite_la_entrada_de_cierre(): void
    {
        $this->guardar();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL);
        $this->revocar();

        $this->guardar(['situacion' => 'Cambio'])->assertSessionHas('error');
        $this->entrada(EntradaBitacora::SEGUIMIENTO)->assertSessionHasErrors('tipo', null, 'entrada');
        $this->actingAs($this->profesional)->post(route('inclusion.entradas.anotar', EntradaBitacora::firstOrFail()), ['texto' => 'Nota'])->assertSessionHas('error');

        $this->entrada(EntradaBitacora::CIERRE)->assertSessionHasNoErrors();
        $this->firmarCierre()->assertOk();

        $this->assertSame(Caso::ESTADO_CERRADO, $this->caso->fresh()->estado_caso);
    }

    public function test_sin_entrada_de_cierre_no_se_cierra_y_lo_explica(): void
    {
        $this->guardar();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL);

        $this->firmarCierre()->assertStatus(409)->assertJsonPath('message', 'Para cerrar el caso, primero agrega una entrada de tipo Cierre en la matriz.');

        $this->assertTrue($this->caso->fresh()->estaActivo());
    }

    public function test_el_cierre_guarda_la_constancia_cierra_el_caso_y_cancela_lo_pendiente(): void
    {
        $this->guardar();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL);
        $this->entrada(EntradaBitacora::CIERRE);
        $cita = Actuacion::create([
            'id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => '2026-11-10 10:00:00', 'estado_asistencia' => Actuacion::ASISTENCIA_PENDIENTE,
        ]);

        $this->firmarCierre()->assertOk()->assertJsonPath('redirect', route('inclusion.bitacora', $this->caso));

        $constancia = Constancia::firstOrFail();
        $caso = $this->caso->fresh();

        $this->assertSame(Caso::ESTADO_CERRADO, $caso->estado_caso);
        $this->assertNotNull($caso->fecha_cierre);
        $this->assertSame(Constancia::TEXTO, $constancia->texto_presentado);
        $this->assertSame($this->profesional->persona->nombre_completo, $constancia->nombre_profesional);
        $this->assertSame(Actuacion::CANCELADA, $cita->fresh()->estado_asistencia);
        Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->assertExists($constancia->firma_ruta);
        $this->assertTrue(AuditoriaExpediente::where('accion', AuditoriaExpediente::CASO_CERRADO_CONSTANCIA)->exists());
    }

    public function test_si_falla_la_base_no_queda_firma_huerfana(): void
    {
        $this->guardar();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL);
        $this->entrada(EntradaBitacora::CIERRE);
        Constancia::creating(fn () => throw new RuntimeException('falla de prueba'));

        $this->firmarCierre()->assertStatus(503);

        $this->assertSame([], Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->allFiles());
        $this->assertTrue($this->caso->fresh()->estaActivo());
    }

    public function test_una_firma_vacia_no_cierra(): void
    {
        $this->guardar();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL);
        $this->entrada(EntradaBitacora::CIERRE);

        $this->actingAs($this->profesional)->postJson(route('inclusion.cierre', $this->caso), ['firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng(false))])
            ->assertStatus(422)->assertJsonValidationErrors('firma');
    }

    public function test_un_caso_cerrado_no_admite_nada_nuevo(): void
    {
        $this->dejarCerrado();
        $entrada = EntradaBitacora::firstOrFail();

        $this->guardar(['situacion' => 'Cambio'])->assertSessionHas('error');
        $this->entrada(EntradaBitacora::SEGUIMIENTO)->assertSessionHas('error');
        $this->actingAs($this->profesional)->post(route('inclusion.entradas.anotar', $entrada), ['texto' => 'Nota'])->assertSessionHas('error');
        $this->actingAs($this->profesional)->postJson(route('inclusion.evidencias.store', $this->caso), ['url' => 'https://ejemplo.invalid/a'])->assertStatus(409);

        $this->assertSame(2, EntradaBitacora::count());
        $this->assertSame('Situación ficticia', Bitacora::firstOrFail()->situacion);
        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertOk()->assertSee('Situación ficticia')->assertDontSee('Guardar cambios');
    }

    public function test_solo_el_asignado_del_area_ve_y_gestiona_la_bitacora(): void
    {
        $this->guardar();
        $this->entrada(EntradaBitacora::ATENCION_INICIAL);
        $entrada = EntradaBitacora::firstOrFail();
        $url = route('inclusion.bitacora', $this->caso);

        $otro = $this->crearProfesional('Inclusión');
        $psicologo = $this->crearProfesional('Salud Mental');
        $admin = $this->crearUsuarioConRol('Admin');

        foreach ([$otro, $psicologo, $admin, $this->persona] as $sinPermiso) {
            $this->actingAs($sinPermiso)->get($url)->assertDontSee('Situación ficticia');
            $this->assertNotSame(200, $this->actingAs($sinPermiso)->get($url)->getStatusCode());
            $this->assertNotSame(200, $this->actingAs($sinPermiso)->post(route('inclusion.entradas.anotar', $entrada), ['texto' => 'Intruso'])->getStatusCode());
            $this->assertNotSame(200, $this->actingAs($sinPermiso)->postJson(route('inclusion.cierre', $this->caso), [])->getStatusCode());
        }

        $this->assertSame(0, $entrada->anotaciones()->count());
    }

    public function test_al_trasladar_el_caso_el_anterior_pierde_el_acceso(): void
    {
        $this->guardar();
        $nuevo = $this->crearProfesional('Ambas');
        $this->caso->update(['id_profesional' => $nuevo->id_usuario]);

        $this->actingAs($nuevo)->get(route('inclusion.bitacora', $this->caso))->assertOk()->assertSee('Situación ficticia');
        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertForbidden();
    }

    public function test_el_expediente_lista_la_bitacora(): void
    {
        $url = route('profesional.expediente.show', $this->caso);

        $this->actingAs($this->profesional)->get($url)->assertSee('Abrir');
        $this->guardar();
        $this->actingAs($this->profesional)->get($url)->assertOk()->assertSee('Bitácora')->assertSee('Completado');
    }

    public function test_el_administrador_reabre_con_motivo_y_se_exige_nuevo_cierre(): void
    {
        $this->dejarCerrado();
        $admin = $this->crearUsuarioConRol('Admin');

        $this->actingAs($admin)->get(route('admin.casos.reapertura', ['codigo' => strtolower($this->caso->codigo_expediente)]))
            ->assertOk()->assertSee('Reabrir caso')->assertDontSee('Persona Ficticia')->assertDontSee('Situación ficticia');

        $this->actingAs($admin)->post(route('admin.casos.reabrir', $this->caso), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->actingAs($admin)->post(route('admin.casos.reabrir', $this->caso), ['motivo' => 'Motivo ficticio'])->assertSessionHas('status');

        $caso = $this->caso->fresh();
        $this->assertSame('En Seguimiento', $caso->estado_caso);
        $this->assertNull($caso->fecha_cierre);
        $this->assertSame(1, Reapertura::count());
        $this->assertSame(1, Constancia::count());
        $this->assertTrue(AuditoriaExpediente::where('accion', AuditoriaExpediente::CASO_REABIERTO)->exists());
        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertSee('Motivo ficticio');

        Carbon::setTestNow(now()->addMinute());
        $this->firmarCierre()->assertStatus(409);
        $this->entrada(EntradaBitacora::CIERRE)->assertSessionHasNoErrors();
        $this->firmarCierre()->assertOk();

        $this->assertSame(2, Constancia::count());
    }

    public function test_no_reabre_un_caso_cerrado_sin_constancia(): void
    {
        $this->caso->forceFill(['estado_caso' => Caso::ESTADO_CERRADO, 'fecha_cierre' => now()])->save();
        $admin = $this->crearUsuarioConRol('Admin');

        $this->actingAs($admin)->get(route('admin.casos.reapertura', ['codigo' => $this->caso->codigo_expediente]))->assertSee('no se cerró con constancia');
        $this->actingAs($admin)->post(route('admin.casos.reabrir', $this->caso), ['motivo' => 'Motivo'])->assertSessionHas('error');

        $this->assertSame(Caso::ESTADO_CERRADO, $this->caso->fresh()->estado_caso);
    }

    public function test_no_reabre_si_la_persona_tiene_otro_caso_sin_cerrar(): void
    {
        $this->dejarCerrado();
        $this->crearCaso($this->persona);

        $this->actingAs($this->crearUsuarioConRol('Admin'))->post(route('admin.casos.reabrir', $this->caso), ['motivo' => 'Motivo'])->assertSessionHas('error');

        $this->assertSame(0, Reapertura::count());
    }

    public function test_solo_el_administrador_reabre(): void
    {
        $this->dejarCerrado();

        $this->assertNotSame(200, $this->actingAs($this->profesional)->get(route('admin.casos.reapertura'))->getStatusCode());
        $this->actingAs($this->profesional)->post(route('admin.casos.reabrir', $this->caso), ['motivo' => 'Motivo']);

        $this->assertSame(0, Reapertura::count());
    }

    public function test_filtra_por_programa_y_fechas_solo_en_inclusion(): void
    {
        DB::table('vinculacion')->insert(['id_persona' => $this->persona->persona->id_persona, 'tipo_vinculacion' => 'Estudiante', 'programa_academico' => 'Programa Ficticio']);
        $codigo = $this->caso->codigo_expediente;

        $this->actingAs($this->profesional)->get(route('profesional.inclusion', ['programa' => 'ficticio']))->assertOk()->assertSee($codigo)->assertSee('Limpiar filtros');
        $this->actingAs($this->profesional)->get(route('profesional.inclusion', ['programa' => 'otro']))->assertOk()->assertDontSee($codigo)->assertSee('Ningún caso coincide con la búsqueda');
        $this->actingAs($this->profesional)->get(route('profesional.inclusion', ['desde' => '2099-01-01']))->assertOk()->assertDontSee($codigo);
        $this->actingAs($this->profesional)->get(route('profesional.inclusion', ['hasta' => '2099-01-01', 'programa' => 'ficticio']))->assertOk()->assertSee($codigo);
        $this->actingAs($this->profesional)->get(route('profesional.expedientes'))->assertOk()->assertDontSee('filtro-programa');
    }
}

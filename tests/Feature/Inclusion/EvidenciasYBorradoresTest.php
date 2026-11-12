<?php

namespace Tests\Feature\Inclusion;

use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\Evidencia;
use App\Models\SolicitudAtencion;
use App\Models\User;
use App\Support\AlmacenEvidencias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * T-201 a T-210: carga de evidencias, borradores y componentes de los formatos de Inclusión.
 */
class EvidenciasYBorradoresTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $persona;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaSigabu();
        Storage::fake(Evidencia::DISCO);

        $this->persona = $this->crearUsuarioConRol('Persona');
        $this->profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso = $this->crearCaso($this->persona, 'Inclusión');
    }

    private function crearCaso(User $persona, string $servicio): Caso
    {
        $solicitud = SolicitudAtencion::create(['id_persona' => $persona->persona->id_persona, 'tipo_servicio' => $servicio, 'descripcion' => 'Prueba']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->forceFill(['id_profesional' => $this->profesional->id_usuario, 'estado_caso' => Caso::ESTADO_ABIERTO])->save();

        return $caso->fresh();
    }

    private function subir(UploadedFile $archivo, ?User $como = null, ?Caso $caso = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->postJson(route('inclusion.evidencias.store', $caso ?? $this->caso), ['archivo' => $archivo]);
    }

    private function pdf(string $nombre = 'soporte.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 100, 'application/pdf');
    }

    public function test_el_profesional_sube_un_archivo_que_queda_temporal_y_privado(): void
    {
        $respuesta = $this->subir($this->pdf())->assertCreated()->assertJsonPath('nombre', 'soporte.pdf');

        $evidencia = Evidencia::findOrFail($respuesta->json('id'));

        $this->assertSame(Evidencia::TEMPORAL, $evidencia->estado);
        $this->assertSame(64, strlen($evidencia->sha256));
        $this->assertStringNotContainsString('soporte', $evidencia->ruta, 'El nombre original no va en la ruta.');
        Storage::disk(Evidencia::DISCO)->assertExists($evidencia->ruta);
    }

    public function test_rechaza_un_tipo_no_permitido(): void
    {
        $this->subir(UploadedFile::fake()->create('programa.exe', 10))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');

        $this->assertSame(0, Evidencia::count());
    }

    public function test_rechaza_un_archivo_de_mas_de_10_mb(): void
    {
        $this->subir(UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');
    }

    public function test_rechaza_un_archivo_cuyo_contenido_no_corresponde_a_su_extension(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'evidencia');
        file_put_contents($ruta, "<?php echo 'esto no es un PDF';");

        $this->subir(new UploadedFile($ruta, 'falso.pdf', null, null, true))
            ->assertUnprocessable()
            ->assertJsonPath('errors.archivo.0', 'El contenido del archivo no corresponde a su formato.');
    }

    public function test_acepta_un_link_https_y_rechaza_uno_http(): void
    {
        $this->actingAs($this->profesional)
            ->postJson(route('inclusion.evidencias.store', $this->caso), ['url' => 'https://drive.google.com/ficticio'])
            ->assertCreated();

        $this->actingAs($this->profesional)
            ->postJson(route('inclusion.evidencias.store', $this->caso), ['url' => 'http://inseguro.example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    public function test_exige_un_archivo_o_un_link(): void
    {
        $this->actingAs($this->profesional)
            ->postJson(route('inclusion.evidencias.store', $this->caso), [])
            ->assertUnprocessable();
    }

    public function test_quitar_antes_de_guardar_borra_el_objeto_y_conserva_la_fila(): void
    {
        $evidencia = Evidencia::findOrFail($this->subir($this->pdf())->json('id'));

        $this->actingAs($this->profesional)->deleteJson(route('inclusion.evidencias.quitar', $evidencia))->assertOk();

        $this->assertSame(Evidencia::DESCARTADA, $evidencia->fresh()->estado);
        Storage::disk(Evidencia::DISCO)->assertMissing($evidencia->ruta);
    }

    public function test_una_evidencia_vinculada_no_se_quita(): void
    {
        $evidencia = Evidencia::findOrFail($this->subir($this->pdf())->json('id'));
        app(AlmacenEvidencias::class)->vincular($this->caso, [$evidencia->id_evidencia], Evidencia::DESTINO_SOPORTE, (string) Str::uuid(), $this->profesional);

        $this->actingAs($this->profesional)->deleteJson(route('inclusion.evidencias.quitar', $evidencia))->assertNotFound();

        Storage::disk(Evidencia::DISCO)->assertExists($evidencia->ruta);
    }

    public function test_no_vincula_evidencias_de_otro_caso(): void
    {
        $otro = $this->crearCaso($this->crearUsuarioConRol('Persona'), 'Inclusión');
        $ajena = Evidencia::findOrFail($this->subir($this->pdf(), caso: $otro)->json('id'));

        $this->expectException(ValidationException::class);

        app(AlmacenEvidencias::class)->vincular($this->caso, [$ajena->id_evidencia], Evidencia::DESTINO_SOPORTE, (string) Str::uuid(), $this->profesional);
    }

    public function test_no_vincula_mas_del_maximo_por_registro(): void
    {
        $ids = array_map(fn () => (string) Str::uuid(), range(1, Evidencia::MAX_POR_REGISTRO + 1));

        $this->expectException(ValidationException::class);

        app(AlmacenEvidencias::class)->vincular($this->caso, $ids, Evidencia::DESTINO_SOPORTE, (string) Str::uuid(), $this->profesional);
    }

    public function test_la_descarga_es_privada_y_queda_auditada_sin_el_nombre_del_archivo(): void
    {
        $evidencia = Evidencia::findOrFail($this->subir($this->pdf('Diagnostico confidencial.pdf'))->json('id'));

        $respuesta = $this->actingAs($this->profesional)->get(route('inclusion.evidencias.archivo', $evidencia))->assertOk();

        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $respuesta->headers->get('X-Content-Type-Options'));

        $auditoria = AuditoriaExpediente::where('accion', AuditoriaExpediente::EVIDENCIA_DESCARGADA)->firstOrFail();
        $this->assertStringNotContainsString('Diagnostico', json_encode($auditoria->detalle));
    }

    public function test_nadie_mas_sube_quita_ni_descarga(): void
    {
        $evidencia = Evidencia::findOrFail($this->subir($this->pdf())->json('id'));
        $psicologo = $this->crearUsuarioConRol('Profesional');
        $psicologo->forceFill(['area_profesional' => 'Salud Mental'])->save();

        foreach ([$this->crearUsuarioConRol('Profesional'), $psicologo] as $sinPermiso) {
            $this->subir($this->pdf(), $sinPermiso)->assertForbidden();
            $this->actingAs($sinPermiso)->deleteJson(route('inclusion.evidencias.quitar', $evidencia))->assertForbidden();
            $this->actingAs($sinPermiso)->get(route('inclusion.evidencias.archivo', $evidencia))->assertForbidden();
        }

        foreach ([$this->persona, $this->crearUsuarioConRol('Admin')] as $otroRol) {
            $this->actingAs($otroRol)->get(route('inclusion.evidencias.archivo', $evidencia))->assertRedirect();
        }

        $this->assertSame(Evidencia::TEMPORAL, $evidencia->fresh()->estado);
    }

    public function test_un_caso_cerrado_no_admite_evidencias(): void
    {
        $this->caso->forceFill(['estado_caso' => Caso::ESTADO_CERRADO])->save();

        $this->subir($this->pdf())->assertStatus(409);
    }

    public function test_el_comando_descarta_solo_las_temporales_antiguas(): void
    {
        $antigua = Evidencia::findOrFail($this->subir($this->pdf('antigua.pdf'))->json('id'));
        $reciente = Evidencia::findOrFail($this->subir($this->pdf('reciente.pdf'))->json('id'));
        $antigua->update(['fecha_registro' => now()->subDays(2)]);

        $this->artisan('inclusion:limpiar-temporales')->assertSuccessful();

        $this->assertSame(Evidencia::DESCARTADA, $antigua->fresh()->estado);
        $this->assertSame(Evidencia::TEMPORAL, $reciente->fresh()->estado);
        Storage::disk(Evidencia::DISCO)->assertMissing($antigua->ruta);
    }

    private function guardarBorrador(User $como, string $documento, array $datos, ?Caso $caso = null)
    {
        return $this->actingAs($como)->putJson(route('inclusion.borradores.guardar', [$caso ?? $this->caso, $documento]), ['datos' => $datos]);
    }

    public function test_guarda_y_actualiza_un_solo_borrador_aunque_falten_obligatorios(): void
    {
        $this->guardarBorrador($this->profesional, Borrador::BITACORA, ['situacion' => 'Primer texto'])->assertOk();
        $this->guardarBorrador($this->profesional, Borrador::BITACORA, ['situacion' => 'Texto corregido'])->assertOk();

        $this->assertSame(1, Borrador::count());
        $this->assertSame('Texto corregido', Borrador::de($this->caso, $this->profesional, Borrador::BITACORA)->datos['situacion']);
    }

    public function test_el_borrador_nunca_guarda_la_firma(): void
    {
        $this->guardarBorrador($this->persona, Borrador::CONSENTIMIENTO, ['numero_contacto' => '3001234567', 'firma' => 'data:image/png;base64,AAAA'])->assertOk();

        $this->assertSame(['numero_contacto' => '3001234567'], Borrador::de($this->caso, $this->persona, Borrador::CONSENTIMIENTO)->datos);
    }

    public function test_cada_quien_guarda_solo_lo_que_le_corresponde(): void
    {
        $this->guardarBorrador($this->persona, Borrador::BITACORA, ['a' => 'b'])->assertForbidden();
        $this->guardarBorrador($this->profesional, Borrador::CONSENTIMIENTO, ['a' => 'b'])->assertForbidden();
        $this->guardarBorrador($this->crearUsuarioConRol('Persona'), Borrador::CONSENTIMIENTO, ['a' => 'b'])->assertForbidden();
        $this->guardarBorrador($this->crearUsuarioConRol('Profesional'), Borrador::PLANTILLA, ['a' => 'b'])->assertForbidden();

        $this->assertSame(0, Borrador::count());
    }

    public function test_un_borrador_de_otra_persona_no_se_ve(): void
    {
        $this->guardarBorrador($this->persona, Borrador::CONSENTIMIENTO, ['a' => 'b'])->assertOk();

        $this->assertNull(Borrador::de($this->caso, $this->profesional, Borrador::CONSENTIMIENTO));
    }

    public function test_al_terminar_el_borrador_deja_de_mostrarse_y_se_puede_iniciar_otro(): void
    {
        $this->guardarBorrador($this->profesional, Borrador::PLANTILLA, ['a' => 'b'])->assertOk();

        Borrador::terminar($this->caso, $this->profesional, Borrador::PLANTILLA);
        $this->assertNull(Borrador::de($this->caso, $this->profesional, Borrador::PLANTILLA));

        $this->guardarBorrador($this->profesional, Borrador::PLANTILLA, ['a' => 'c'])->assertOk();
        $this->assertSame(2, Borrador::count());
    }

    public function test_rechaza_un_borrador_demasiado_grande_o_en_un_caso_cerrado(): void
    {
        $this->guardarBorrador($this->profesional, Borrador::BITACORA, ['texto' => str_repeat('a', 200001)])->assertUnprocessable();

        $this->caso->forceFill(['estado_caso' => Caso::ESTADO_CERRADO])->save();
        $this->guardarBorrador($this->profesional, Borrador::BITACORA, ['a' => 'b'])->assertStatus(409);
    }

    public function test_un_documento_desconocido_no_tiene_ruta(): void
    {
        $this->actingAs($this->profesional)->putJson("/inclusion/casos/{$this->caso->id_caso}/borradores/otro", ['datos' => []])->assertNotFound();
    }

    public function test_los_componentes_se_renderizan(): void
    {
        $estado = Blade::render('<x-estado-documento estado="Revocado" />');
        $this->assertStringContainsString('Revocado', $estado);
        $this->assertStringContainsString('bg-rose-50', $estado);

        $firma = Blade::render('<x-firma-lienzo ayuda="ayuda-prueba" class="max-w-md" />');
        $this->assertStringContainsString('aria-describedby="ayuda-prueba"', $firma);
        $this->assertStringContainsString('touch-action: none', $firma);
        $this->assertStringContainsString('max-w-md', $firma);

        $evidencias = Blade::render('<x-evidencias :caso="$caso" id="soportes" etiqueta="Soportes" />', ['caso' => $this->caso]);
        $this->assertStringContainsString('for="soportes-archivo"', $evidencias);
        $this->assertStringContainsString('SIGABU no controla', $evidencias);
    }

    public function test_el_disco_de_evidencias_es_privado_y_no_es_el_del_repositorio(): void
    {
        $this->assertSame('private', config('filesystems.disks.inclusion.visibility'));
        $this->assertNotSame(config('filesystems.disks.repositorio.root'), config('filesystems.disks.inclusion.root'));
    }
}

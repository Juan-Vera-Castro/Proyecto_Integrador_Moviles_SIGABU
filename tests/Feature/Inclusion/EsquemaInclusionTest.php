<?php

namespace Tests\Feature\Inclusion;

use App\Documentos\RecursosInclusion;
use App\Models\AjusteRazonable;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Inclusion\Bitacora;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\Inclusion\Constancia;
use App\Models\Inclusion\EntradaBitacora;
use App\Models\Inclusion\EstadoDocumento;
use App\Models\Inclusion\Evidencia;
use App\Models\Inclusion\Reapertura;
use App\Models\Inclusion\Recurso;
use App\Models\SolicitudAtencion;
use App\Models\User;
use App\Policies\CasoInclusionPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * T-103 a T-109: tablas, reglas de integridad, estados y permisos de los formatos de Inclusión.
 */
class EsquemaInclusionTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $persona;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaSigabu();

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

    private function bitacora(): Bitacora
    {
        return Bitacora::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'rol_institucional' => 'Estudiante Diurno',
            'programa_dependencia' => 'Enfermería',
            'situacion' => 'Situación ficticia',
            'ruta_activada' => 'Solicitud ficticia',
            'fecha_registro' => now(),
            'fecha_actualizacion' => now(),
        ]);
    }

    private function entrada(Bitacora $bitacora, int $numero, string $tipo): EntradaBitacora
    {
        return EntradaBitacora::create([
            'id_bitacora' => $bitacora->id_bitacora,
            'numero' => $numero,
            'fecha_hora' => now(),
            'tipo' => $tipo,
            'descripcion' => 'Descripción ficticia',
            'id_profesional' => $this->profesional->id_usuario,
            'fecha_registro' => now(),
        ]);
    }

    private function constancia(Bitacora $bitacora, EntradaBitacora $cierre): Constancia
    {
        return Constancia::create([
            'id_bitacora' => $bitacora->id_bitacora,
            'id_entrada_cierre' => $cierre->id_entrada,
            'id_profesional' => $this->profesional->id_usuario,
            'nombre_profesional' => 'Profesional ficticio',
            'texto_presentado' => Constancia::TEXTO,
            'firma_ruta' => 'casos/constancia.png',
            'firma_sha256' => str_repeat('c', 64),
            'fecha_hora' => now(),
        ]);
    }

    private function evidencia(array $datos = []): Evidencia
    {
        return Evidencia::create($datos + [
            'id_caso' => $this->caso->id_caso,
            'tipo' => Evidencia::TIPO_ARCHIVO,
            'ruta' => 'casos/archivo.pdf',
            'nombre' => 'archivo.pdf',
            'sha256' => str_repeat('d', 64),
            'subido_por' => $this->profesional->id_usuario,
            'fecha_registro' => now(),
        ]);
    }

    private function decision(string $decision, string $medio, array $detalle = []): ConsentimientoAceptacion
    {
        $id = (string) Str::uuid();
        $version = DB::table('versiones_consentimiento')->value('id_version') ?? $this->crearVersion();
        $firmada = $decision === ConsentimientoAceptacion::ACEPTADO;

        DB::table('consentimiento_aceptaciones')->insert([
            'id_aceptacion' => $id,
            'id_caso' => $this->caso->id_caso,
            'id_version' => $version,
            'decision' => $decision,
            'fecha_hora' => now()->addSeconds(ConsentimientoAceptacion::count())->format('Y-m-d H:i:s.u'),
            'medio' => $medio,
            'id_profesional' => $this->profesional->id_usuario,
            'vinculacion' => $firmada ? 'Otro' : null,
            'dependencia_facultad' => $firmada ? 'N/A' : null,
            'texto_presentado' => 'Texto',
            'texto_sha256' => str_repeat('a', 64),
            'firma_ruta' => $firmada ? 'casos/firma.png' : null,
            'firma_sha256' => $firmada ? str_repeat('b', 64) : null,
        ]);

        if ($detalle) {
            ConsentimientoDetalle::create(['id_aceptacion' => $id] + $detalle);
        }

        return ConsentimientoAceptacion::findOrFail($id);
    }

    private function crearVersion(): int
    {
        $modelo = DB::table('modelos_consentimiento')->insertGetId(['codigo' => 'IN-X', 'nombre' => 'Modelo', 'tipo_servicio' => 'Inclusión', 'activo' => 1]);

        return DB::table('versiones_consentimiento')->insertGetId(['id_modelo' => $modelo, 'numero_version' => 1, 'texto' => 'Texto', 'estado' => 'publicada']);
    }

    public function test_un_caso_tiene_una_sola_bitacora(): void
    {
        $this->bitacora();

        $this->expectException(QueryException::class);

        $this->bitacora();
    }

    public function test_el_numero_de_entrada_no_se_repite(): void
    {
        $bitacora = $this->bitacora();
        $this->entrada($bitacora, 1, EntradaBitacora::ATENCION_INICIAL);

        $this->expectException(QueryException::class);

        $this->entrada($bitacora, 1, EntradaBitacora::SEGUIMIENTO);
    }

    public function test_solo_hay_una_atencion_inicial(): void
    {
        $bitacora = $this->bitacora();
        $this->entrada($bitacora, 1, EntradaBitacora::ATENCION_INICIAL);

        $this->expectException(QueryException::class);

        $this->entrada($bitacora, 2, EntradaBitacora::ATENCION_INICIAL);
    }

    public function test_una_entrada_guardada_no_se_edita_ni_en_la_base(): void
    {
        $entrada = $this->entrada($this->bitacora(), 1, EntradaBitacora::ATENCION_INICIAL);

        $this->expectException(QueryException::class);

        DB::table('inclusion_bitacora_entradas')->where('id_entrada', $entrada->id_entrada)->update(['descripcion' => 'Otra']);
    }

    public function test_una_entrada_guardada_no_se_edita_desde_el_modelo(): void
    {
        $entrada = $this->entrada($this->bitacora(), 1, EntradaBitacora::ATENCION_INICIAL);

        $this->expectException(LogicException::class);

        $entrada->update(['descripcion' => 'Otra']);
    }

    public function test_la_entrada_llega_a_su_caso_para_el_control_de_area(): void
    {
        $entrada = $this->entrada($this->bitacora(), 1, EntradaBitacora::ATENCION_INICIAL);

        $this->assertSame($this->caso->id_caso, $entrada->caso->id_caso);
    }

    public function test_una_evidencia_temporal_se_descarta_y_una_vinculada_no_cambia(): void
    {
        $temporal = $this->evidencia();
        $temporal->update(['estado' => Evidencia::DESCARTADA]);
        $this->assertSame(Evidencia::DESCARTADA, $temporal->fresh()->estado);

        $vinculada = $this->evidencia();
        $vinculada->update(['estado' => Evidencia::VINCULADA, 'destino' => Evidencia::DESTINO_SOPORTE, 'id_destino' => (string) Str::uuid()]);

        $this->expectException(QueryException::class);

        DB::table('inclusion_evidencias')->where('id_evidencia', $vinculada->id_evidencia)->update(['nombre' => 'otro.pdf']);
    }

    public function test_una_evidencia_vinculada_exige_su_destino(): void
    {
        $this->expectException(QueryException::class);

        $this->evidencia(['estado' => Evidencia::VINCULADA]);
    }

    public function test_un_link_no_necesita_archivo(): void
    {
        $link = Evidencia::create([
            'id_caso' => $this->caso->id_caso,
            'tipo' => Evidencia::TIPO_ENLACE,
            'url' => 'https://drive.example.com/archivo',
            'subido_por' => $this->profesional->id_usuario,
            'fecha_registro' => now(),
        ]);

        $this->assertFalse($link->esArchivo());
    }

    public function test_solo_hay_un_borrador_activo_por_usuario_caso_y_documento(): void
    {
        $fila = ['id_caso' => $this->caso->id_caso, 'id_usuario' => $this->persona->id_usuario, 'documento' => 'bitacora', 'datos' => '{}'];
        DB::table('inclusion_borradores')->insert(['id_borrador' => (string) Str::uuid(), 'activo' => 0] + $fila);
        DB::table('inclusion_borradores')->insert(['id_borrador' => (string) Str::uuid(), 'activo' => 1] + $fila);

        $this->expectException(QueryException::class);

        DB::table('inclusion_borradores')->insert(['id_borrador' => (string) Str::uuid(), 'activo' => 1] + $fila);
    }

    public function test_los_ajustes_anteriores_siguen_validos_y_los_de_plantilla_usan_area(): void
    {
        $base = ['id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'barrera' => 'B', 'descripcion' => 'D', 'fecha_inicio' => '2026-11-01', 'fecha_registro' => now()];

        $anterior = AjusteRazonable::create($base + ['tipo' => 'De tiempo']);
        $plantilla = AjusteRazonable::create($base + ['area' => 'Evaluación', 'observaciones' => 'Obs']);

        $this->assertNull($anterior->area);
        $this->assertNull($plantilla->tipo);

        $this->expectException(QueryException::class);

        AjusteRazonable::create($base + ['area' => 'Área inventada']);
    }

    public function test_las_acciones_de_auditoria_del_modulo_se_aceptan(): void
    {
        $this->actingAs($this->profesional);

        foreach (['CONSENTIMIENTO_REVOCADO', 'BITACORA_PDF', 'CASO_REABIERTO', 'PLANTILLA_COMPARTIR_PDF', 'EVIDENCIA_DESCARGADA'] as $accion) {
            AuditoriaExpediente::registrar(constant(AuditoriaExpediente::class.'::'.$accion), $this->caso);
        }

        $this->assertSame(5, AuditoriaExpediente::count());
    }

    public function test_el_recurso_tea_se_publica_una_vez_y_conserva_su_contenido_literal(): void
    {
        $this->assertSame([RecursosInclusion::CODIGO_TEA_N1], RecursosInclusion::cargar());
        $this->assertSame([], RecursosInclusion::cargar());

        $recurso = Recurso::activos()->firstOrFail();
        $texto = json_encode($recurso->contenido, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('5.7 Trazabilidad y registro de ajustes', $texto);
        $this->assertStringContainsString('Discapacidad Psicosocial', $texto, 'Las erratas se conservan hasta que la Unidad confirme.');
        $this->assertStringContainsString('Rigidez cognitiva', $texto);
    }

    public function test_estados_del_consentimiento(): void
    {
        $this->assertNull($this->caso->estadoConsentimiento());

        $aceptacion = $this->decision(ConsentimientoAceptacion::ACEPTADO, ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION, ['decision_acompanamiento' => true]);
        $this->assertSame(EstadoDocumento::COMPLETADO, $this->caso->fresh()->estadoConsentimiento());

        DB::table('consentimiento_firmas_profesional')->insert([
            'id_aceptacion' => $aceptacion->id_aceptacion,
            'id_profesional' => $this->profesional->id_usuario,
            'firma_ruta' => 'casos/pro.png',
            'firma_sha256' => str_repeat('e', 64),
        ]);
        $this->assertSame(EstadoDocumento::FIRMADO, $this->caso->fresh()->estadoConsentimiento());

        $this->decision(ConsentimientoAceptacion::RECHAZADO, ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION, ['es_revocacion' => true]);
        $this->assertSame(EstadoDocumento::REVOCADO, $this->caso->fresh()->estadoConsentimiento());
    }

    public function test_un_consentimiento_en_papel_queda_firmado_sin_contrafirma(): void
    {
        $this->decision(ConsentimientoAceptacion::ACEPTADO, ConsentimientoAceptacion::MEDIO_FIRMA_PAPEL, ['motivo_papel' => 'Motivo ficticio']);

        $this->assertSame(EstadoDocumento::FIRMADO, $this->caso->fresh()->estadoConsentimiento());
    }

    public function test_estados_de_la_bitacora_con_cierre_y_reapertura(): void
    {
        $bitacora = $this->bitacora();
        $this->assertSame(EstadoDocumento::COMPLETADO, $bitacora->fresh()->estadoDocumento());

        $this->entrada($bitacora, 1, EntradaBitacora::ATENCION_INICIAL);
        $constancia = $this->constancia($bitacora, $this->entrada($bitacora, 2, EntradaBitacora::CIERRE));
        $this->assertSame(EstadoDocumento::FIRMADO, $bitacora->fresh()->estadoDocumento());

        $admin = $this->crearUsuarioConRol('Admin');
        Reapertura::create(['id_bitacora' => $bitacora->id_bitacora, 'id_constancia' => $constancia->id_constancia, 'id_administrador' => $admin->id_usuario, 'motivo' => 'Motivo ficticio', 'fecha_hora' => now()]);
        $this->assertSame(EstadoDocumento::COMPLETADO, $bitacora->fresh()->estadoDocumento());
    }

    public function test_permisos_de_cada_actor(): void
    {
        $politica = new CasoInclusionPolicy;
        $admin = $this->crearUsuarioConRol('Admin');
        $otraPersona = $this->crearUsuarioConRol('Persona');
        $otroProfesional = $this->crearUsuarioConRol('Profesional');
        $psicologo = $this->crearUsuarioConRol('Profesional');
        $psicologo->forceFill(['area_profesional' => 'Salud Mental'])->save();

        $this->assertTrue($politica->gestionar($this->profesional, $this->caso));
        $this->assertFalse($politica->gestionar($otroProfesional, $this->caso));
        $this->assertFalse($politica->gestionar($this->persona, $this->caso));
        $this->assertFalse($politica->gestionar($admin, $this->caso));

        $this->assertTrue($politica->verConsentimiento($this->persona, $this->caso));
        $this->assertTrue($politica->verConsentimiento($this->profesional, $this->caso));
        $this->assertTrue($politica->verConsentimiento($admin, $this->caso));
        $this->assertFalse($politica->verConsentimiento($otraPersona, $this->caso));
        $this->assertFalse($politica->verConsentimiento($otroProfesional, $this->caso));

        $this->assertTrue($politica->diligenciarConsentimiento($this->persona, $this->caso));
        $this->assertFalse($politica->diligenciarConsentimiento($this->profesional, $this->caso));

        $this->assertTrue($politica->consultarRecursos($this->profesional));
        $this->assertFalse($politica->consultarRecursos($psicologo));
        $this->assertFalse($politica->consultarRecursos($this->persona));

        $this->assertTrue($politica->reabrir($admin, $this->caso));
        $this->assertFalse($politica->reabrir($this->profesional, $this->caso));
    }

    public function test_el_profesional_asignado_que_pierde_el_area_deja_de_gestionar(): void
    {
        $this->profesional->forceFill(['area_profesional' => 'Salud Mental'])->save();

        $this->assertFalse((new CasoInclusionPolicy)->gestionar($this->profesional->fresh(), $this->caso));
    }

    public function test_un_caso_de_salud_mental_queda_fuera_de_estos_permisos(): void
    {
        $caso = $this->crearCaso($this->crearUsuarioConRol('Persona'), 'Salud Mental');

        $this->assertFalse((new CasoInclusionPolicy)->gestionar($this->profesional, $caso));
    }
}

<?php

namespace Tests\Feature\Inclusion;

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\TextoConsentimiento;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use App\Models\SolicitudAtencion;
use App\Models\VersionConsentimiento;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * T-101 y T-102: el formato de Inclusión cabe en la evidencia actual sin debilitar la del
 * resto de servicios, y su texto se publica sin tocar lo ya firmado.
 */
class ConsentimientoInclusionEsquemaTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private Caso $caso;

    private VersionConsentimiento $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaSigabu();

        $estudiante = $this->crearUsuarioConRol('Persona');
        $profesional = $this->crearUsuarioConRol('Profesional');
        $solicitud = SolicitudAtencion::create(['id_persona' => $estudiante->persona->id_persona, 'tipo_servicio' => 'Inclusión', 'descripcion' => 'Prueba']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->forceFill(['id_profesional' => $profesional->id_usuario, 'estado_caso' => Caso::ESTADO_ABIERTO])->save();

        ConsentimientoInclusion::cargar();
        $this->version = ModeloConsentimiento::versionVigenteDe('Inclusión');
    }

    private function aceptacion(string $medio): array
    {
        return [
            'id_aceptacion' => (string) Str::uuid(),
            'id_caso' => $this->caso->id_caso,
            'id_version' => $this->version->id_version,
            'decision' => 'Aceptado',
            'medio' => $medio,
            'id_profesional' => $this->caso->id_profesional,
            'vinculacion' => 'Otro',
            'dependencia_facultad' => 'N/A',
            'texto_presentado' => 'Texto',
            'texto_sha256' => str_repeat('a', 64),
            'firma_ruta' => 'casos/firma.png',
            'firma_sha256' => str_repeat('b', 64),
        ];
    }

    public function test_los_medios_de_inclusion_no_exigen_lugar_de_expedicion_ni_eps(): void
    {
        DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION));
        DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(ConsentimientoAceptacion::MEDIO_FIRMA_PAPEL));

        $this->assertSame(2, ConsentimientoAceptacion::count());
    }

    public function test_los_demas_medios_siguen_exigiendolos(): void
    {
        $this->expectException(QueryException::class);

        DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(ConsentimientoAceptacion::MEDIO_FIRMA_ENLACE));
    }

    public function test_un_medio_desconocido_se_rechaza(): void
    {
        $this->expectException(QueryException::class);

        DB::table('consentimiento_aceptaciones')->insert($this->aceptacion('otro_medio'));
    }

    public function test_el_detalle_de_inclusion_no_se_modifica(): void
    {
        $fila = $this->aceptacion(ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION);
        DB::table('consentimiento_aceptaciones')->insert($fila);
        DB::table('inclusion_consentimientos')->insert(['id_aceptacion' => $fila['id_aceptacion'], 'decision_acompanamiento' => 1]);

        $this->expectException(QueryException::class);

        DB::table('inclusion_consentimientos')->update(['decision_compartir' => 1]);
    }

    public function test_el_modelo_nuevo_queda_vigente_por_poblacion(): void
    {
        $this->assertSame(ConsentimientoInclusion::CODIGO_GENERAL, $this->version->modelo->codigo);
        $this->assertSame(
            ConsentimientoInclusion::CODIGO_MENORES,
            ModeloConsentimiento::versionVigenteDe('Inclusión', ModeloConsentimiento::POBLACION_MENORES)->modelo->codigo,
        );
    }

    public function test_desactiva_modelos_anteriores_sin_tocar_sus_versiones_ni_otros_servicios(): void
    {
        $this->assertSame([], ConsentimientoInclusion::cargar(), 'Cargar dos veces no crea nada.');

        ModeloConsentimiento::where('codigo', ConsentimientoInclusion::CODIGO_GENERAL)->update(['codigo' => 'IN-PREVIO']);
        $previo = ModeloConsentimiento::where('codigo', 'IN-PREVIO')->firstOrFail();
        $salud = ModeloConsentimiento::create(['codigo' => 'SM-X', 'nombre' => 'SM', 'tipo_servicio' => 'Salud Mental', 'activo' => true]);

        ConsentimientoInclusion::cargar();

        $this->assertFalse($previo->fresh()->activo);
        $this->assertSame(VersionConsentimiento::PUBLICADA, $previo->versiones()->first()->estado);
        $this->assertTrue($salud->fresh()->activo);
    }

    public function test_el_texto_v1_es_el_del_formato_y_sus_marcadores_existen(): void
    {
        foreach (ConsentimientoInclusion::modelos() as $modelo) {
            $this->assertSame([], TextoConsentimiento::marcadoresDesconocidos($modelo['texto']));
            $this->assertLessThanOrEqual(VersionConsentimiento::TEXTO_MAX, mb_strlen($modelo['texto']));
            $this->assertStringContainsString('{decisiones}', $modelo['texto']);
            $this->assertStringContainsString('Política de Tratamiento de Datos Personales de la UCEVA', $modelo['texto']);
        }

        $this->assertStringContainsString('{nombre_acudiente}', ConsentimientoInclusion::modelos()[1]['texto']);
    }

    public function test_las_decisiones_salen_con_su_casilla(): void
    {
        $texto = ConsentimientoInclusion::textoDecisiones(['decision_acompanamiento' => true, 'decision_compartir' => false]);

        $this->assertStringContainsString('☑ ACEPTO recibir', $texto);
        $this->assertStringContainsString('☐ AUTORIZO compartir', $texto);
    }
}

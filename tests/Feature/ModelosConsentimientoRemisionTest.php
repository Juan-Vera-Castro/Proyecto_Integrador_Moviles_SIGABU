<?php

namespace Tests\Feature;

use App\Documentos\TextoConsentimiento;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\FormatoInstitucional;
use App\Models\ModeloConsentimiento;
use App\Models\Persona;
use App\Models\RemisionExterna;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Modelos y acceso a datos del consentimiento con firma y de la remisión externa (G2).
 */
class ModelosConsentimientoRemisionTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    /**
     * Textos A (GBU-GPGR-F-032) y B (GBU-GPGR-F-019) que se publican desde Administración.
     */
    private const TEXTO_A = <<<'TXT'
        Ciudad: {ciudad}. Fecha: {fecha}.
        Yo, {nombre_estudiante}, con documento {tipo_documento_estudiante} número {documento_estudiante} de {lugar_expedicion}, declaro que he sido informado(a) sobre los procedimientos de orientación psicológica que se realizarán y de su importancia.
        Comprendo que los resultados se utilizarán como parte del proceso que se realice y que, en caso de ser una urgencia, se me remitirá a la EPS o IPS correspondiente, {eps_ips}, y se notificará al acudiente o representante legal: {acudiente_notificacion}.
        Igualmente manifiesto que otorgo de forma libre mi consentimiento para la orientación psicológica por parte del profesional {profesional}, con documento de identificación {documento_profesional}.
        TXT;

    private const TEXTO_B = <<<'TXT'
        Ciudad: {ciudad}. Fecha: {fecha}.
        Yo, {nombre_acudiente}, con documento {tipo_documento_acudiente} número {documento_acudiente} de {lugar_expedicion_acudiente}, en calidad de {parentesco_acudiente} del menor {nombre_estudiante}, identificado con {tipo_documento_estudiante} número {documento_estudiante}, otorgo de forma libre mi consentimiento para la orientación psicológica por parte del profesional {profesional}, con documento de identificación {documento_profesional}.
        Comprendo que los resultados se utilizarán como parte del proceso que se realice y que, en caso de ser una urgencia, se remitirá al menor a la EPS o IPS correspondiente, {eps_ips}.
        TXT;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        // Jueves 8 de octubre de 2026, 10:00 am en Colombia.
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Camila Muñoz']);
        $this->caso = $this->crearCaso('Salud Mental');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(string $servicio): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Samuel Valencia']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => $servicio, 'descripcion' => 'Ansiedad.']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);

        return $caso->fresh();
    }

    private function publicar(string $servicio, string $poblacion = 'General', string $codigo = 'SM-ORIENTACION', bool $activo = true): VersionConsentimiento
    {
        $modelo = ModeloConsentimiento::create(['codigo' => $codigo, 'nombre' => $codigo, 'tipo_servicio' => $servicio, 'poblacion' => $poblacion, 'activo' => $activo]);

        return VersionConsentimiento::create(['id_modelo' => $modelo->id_modelo, 'numero_version' => 1, 'texto' => "Texto $codigo", 'estado' => 'publicada']);
    }

    private function decidir(string $decision, VersionConsentimiento $version, ?Caso $caso = null): ConsentimientoAceptacion
    {
        $acepta = $decision === ConsentimientoAceptacion::ACEPTADO;

        return ConsentimientoAceptacion::create([
            'id_caso' => ($caso ?? $this->caso)->id_caso,
            'id_version' => $version->id_version,
            'decision' => $decision,
            'fecha_hora' => now(Actuacion::ZONA_HORARIA),
            'id_profesional' => $this->profesional->id_usuario,
            'lugar_expedicion' => $acepta ? 'Tuluá' : null,
            'eps_ips' => $acepta ? 'Nueva EPS' : null,
            'contacto_urgencia' => $acepta ? 'María Pérez' : null,
            'vinculacion' => $acepta ? 'Funcionario' : null,
            'dependencia_facultad' => $acepta ? 'Bienestar Universitario' : null,
            'texto_presentado' => 'Texto',
            'texto_sha256' => hash('sha256', 'Texto'),
            'firma_ruta' => $acepta ? 'casos/x/y.png' : null,
            'firma_sha256' => $acepta ? str_repeat('b', 64) : null,
        ]);
    }

    private function casoFresco(): Caso
    {
        return Caso::with(['solicitud', 'aceptacionesConsentimiento', 'citas'])->findOrFail($this->caso->id_caso);
    }

    /**
     * DADO un estudiante que cumple 18 años hoy,
     * CUANDO se calcula su edad,
     * ENTONCES ya es mayor de edad; un día antes era menor (RN-11).
     */
    public function test_la_edad_se_calcula_en_la_fecha_indicada(): void
    {
        $persona = new Persona(['fecha_nacimiento' => '2008-10-08']);

        $this->assertSame(18, $persona->edadEn(now()));
        $this->assertFalse($persona->esMenorEn(now()));
        $this->assertSame(17, $persona->edadEn(now()->subDay()));
        $this->assertTrue($persona->esMenorEn(now()->subDay()));

        $sinFecha = new Persona;
        $this->assertNull($sinFecha->edadEn(now()));
        $this->assertNull($sinFecha->esMenorEn(now()));
    }

    public function test_los_datos_de_remision_estan_completos_solo_con_los_tres_campos(): void
    {
        $persona = $this->caso->solicitud->persona;
        $this->assertFalse($persona->datosRemisionCompletos());

        $persona->update(['tipo_documento' => 'CC', 'sexo' => 'M']);
        $this->assertFalse($persona->fresh()->datosRemisionCompletos());

        $persona->update(['fecha_nacimiento' => '2005-03-10']);
        $this->assertTrue($persona->fresh()->datosRemisionCompletos());
        $this->assertSame('2005-03-10', $persona->fresh()->fecha_nacimiento->toDateString());
    }

    public function test_la_version_vigente_se_busca_por_servicio_y_poblacion(): void
    {
        $general = $this->publicar('Salud Mental');
        $menores = $this->publicar('Salud Mental', 'Menores', 'SM-MENORES');
        $this->publicar('Salud Mental', 'Menores', 'SM-MENORES-VIEJO', activo: false);

        $this->assertSame($general->id_version, ModeloConsentimiento::versionVigenteDe('Salud Mental')->id_version);
        $this->assertSame($menores->id_version, ModeloConsentimiento::versionVigenteDe('Salud Mental', 'Menores')->id_version);
        $this->assertNull(ModeloConsentimiento::versionVigenteDe('Inclusión', 'Menores'));
        $this->assertTrue($menores->modelo->esDeMenores());
    }

    public function test_la_aceptacion_no_se_puede_editar_ni_borrar_desde_eloquent(): void
    {
        $aceptacion = $this->decidir('Aceptado', $this->publicar('Salud Mental'));

        try {
            $aceptacion->update(['decision' => 'Rechazado']);
            $this->fail('Se esperaba que no se pudiera editar');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $aceptacion->delete();
    }

    /**
     * DADO un caso de Salud Mental,
     * CUANDO solo tiene la casilla antigua o un rechazo,
     * ENTONCES no admite atención; con la firma aceptada sí (RN-01, RN-02, D-05).
     */
    public function test_el_consentimiento_vigente_es_la_ultima_decision_si_es_aceptada(): void
    {
        $this->activarExigenciaConsentimiento(publicarTexto: false);
        $version = $this->publicar('Salud Mental');

        $this->assertTrue($this->casoFresco()->requiereConsentimiento());
        $this->assertNull($this->casoFresco()->consentimientoVigente());

        $this->caso->consentimientos()->create(['aceptado' => true, 'fecha_hora_aceptacion' => now(), 'id_version' => $version->id_version, 'version_formato' => 'SM-ORIENTACION-v1']);
        $this->assertFalse($this->casoFresco()->admiteAtencion(), 'La casilla antigua no cuenta como consentimiento');

        $this->decidir('Rechazado', $version);
        $this->assertFalse($this->casoFresco()->admiteAtencion());

        Carbon::setTestNow(now()->addMinutes(5));
        $aceptado = $this->decidir('Aceptado', $version);
        $this->assertSame($aceptado->id_aceptacion, $this->casoFresco()->consentimientoVigente()->id_aceptacion);
        $this->assertTrue($this->casoFresco()->admiteAtencion());

        Carbon::setTestNow(now()->addMinutes(5));
        $this->decidir('Rechazado', $version);
        $this->assertNull($this->casoFresco()->consentimientoVigente(), 'Un rechazo posterior anula la aceptación');
    }

    public function test_dos_decisiones_del_mismo_segundo_se_ordenan_por_microsegundos(): void
    {
        $version = $this->publicar('Salud Mental');

        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00.100000', Actuacion::ZONA_HORARIA));
        $this->decidir('Aceptado', $version);
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00.900000', Actuacion::ZONA_HORARIA));
        $this->decidir('Rechazado', $version);

        $this->assertNull($this->casoFresco()->consentimientoVigente());
    }

    public function test_la_aceptacion_sigue_vigente_aunque_su_version_se_retire(): void
    {
        $version = $this->publicar('Salud Mental');
        $this->decidir('Aceptado', $version);

        VersionConsentimiento::whereKey($version->id_version)->update(['estado' => 'retirada']);

        $this->assertTrue($this->casoFresco()->admiteAtencion());
        $this->assertTrue($this->crearCaso('Inclusión')->requiereConsentimiento());
    }

    public function test_la_ultima_cita_asistida_ignora_las_futuras_y_las_no_asistidas(): void
    {
        $crear = fn (string $fecha, string $asistencia) => Actuacion::create([
            'id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => 'Consulta', 'fecha_hora' => $fecha, 'estado_asistencia' => $asistencia,
        ]);

        $this->assertNull($this->casoFresco()->ultimaCitaAsistida());

        $crear('2026-10-01 09:00:00', 'Asistió');
        $ultima = $crear('2026-10-05 14:00:00', 'Asistió');
        $crear('2026-10-07 09:00:00', 'No asistió');
        $crear('2026-10-09 09:00:00', 'Pendiente');

        $this->assertSame($ultima->id_actuacion, $this->casoFresco()->ultimaCitaAsistida()->id_actuacion);
    }

    public function test_la_remision_externa_se_relaciona_con_su_actuacion_y_es_inmutable(): void
    {
        $remision = Actuacion::create([
            'id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => 'Remisión', 'fecha_hora' => '2026-10-08 10:00:00',
            'destino_remision' => 'Externa', 'entidad_destino' => 'EPS / IPS de salud', 'descripcion' => 'Justificación.',
        ]);

        $detalle = RemisionExterna::create([
            'id_actuacion' => $remision->id_actuacion, 'categoria' => 'PRIORITARIO',
            'estudiante_nombre' => 'Samuel Valencia', 'estudiante_tipo_documento' => 'CC', 'estudiante_numero_documento' => '1112223334',
            'estudiante_sexo' => 'M', 'estudiante_fecha_nacimiento' => '2005-03-10', 'estudiante_edad' => 21,
            'fecha_hora_atencion' => '2026-10-08 10:00:00', 'profesional_nombre' => 'Camila Muñoz',
            'formato_codigo' => 'GBU-GPGR-F-016', 'formato_version' => '3', 'formato_fecha' => '2024-04-18',
        ]);

        $this->assertSame($detalle->id_actuacion, $remision->fresh()->remisionExterna->id_actuacion);
        $this->assertFalse($detalle->esUrgente());
        $this->assertSame(21, $detalle->fresh()->estudiante_edad);

        $this->expectException(LogicException::class);
        $detalle->update(['categoria' => 'URGENTE']);
    }

    public function test_el_formato_se_busca_por_clave_y_su_fecha_se_escribe_como_en_calidad(): void
    {
        $formato = FormatoInstitucional::deClave('REMISION_PSICOLOGICA');

        $this->assertSame(['GBU-GPGR-F-016', '3'], [$formato->codigo, $formato->version]);
        $this->assertSame('18/ABR/2024', FormatoInstitucional::fechaComoFormato($formato->fecha_formato));
    }

    public function test_la_auditoria_registra_usuario_caso_detalle_e_ip(): void
    {
        $this->actingAs($this->profesional);

        $registro = AuditoriaExpediente::registrar(AuditoriaExpediente::FIRMA_CONSULTADA, $this->caso, ['id_aceptacion' => 'abc']);

        $guardado = AuditoriaExpediente::findOrFail($registro->id_auditoria);
        $this->assertSame($this->profesional->id_usuario, $guardado->id_usuario);
        $this->assertSame($this->caso->id_caso, $guardado->id_caso);
        $this->assertSame(['id_aceptacion' => 'abc'], $guardado->detalle);
        $this->assertSame('127.0.0.1', $guardado->ip_origen);

        $this->expectException(LogicException::class);
        $guardado->delete();
    }

    /**
     * DADO el texto A de orientación psicológica,
     * CUANDO se renderiza con parte de los datos,
     * ENTONCES los datos aparecen y lo que falta queda como "____" (D-24).
     */
    public function test_el_texto_reemplaza_marcadores_y_detecta_los_desconocidos(): void
    {
        $this->assertSame([], TextoConsentimiento::marcadoresDesconocidos(self::TEXTO_A.self::TEXTO_B), 'Los textos A y B solo usan marcadores conocidos');

        $renderizadoB = TextoConsentimiento::renderizar(self::TEXTO_B, ['nombre_acudiente' => 'María Pérez', 'parentesco_acudiente' => 'madre', 'ciudad' => config('sigabu.ciudad')]);
        $this->assertStringContainsString('Yo, María Pérez, con documento ____', $renderizadoB);
        $this->assertStringContainsString('en calidad de madre del menor ____', $renderizadoB);
        $this->assertStringContainsString('Ciudad: Tuluá.', $renderizadoB);

        $texto = 'Yo, {nombre_estudiante}, con documento {tipo_documento_estudiante} número {documento_estudiante} de {lugar_expedicion}, ante {profesional}. {firma_digital}';

        $this->assertSame(
            'Yo, Samuel Valencia, con documento CC número 1112223334 de ____, ante Camila Muñoz. {firma_digital}',
            TextoConsentimiento::renderizar($texto, [
                'nombre_estudiante' => 'Samuel Valencia', 'tipo_documento_estudiante' => 'CC',
                'documento_estudiante' => '1112223334', 'lugar_expedicion' => '  ', 'profesional' => 'Camila Muñoz',
            ]),
        );
        $this->assertSame(['firma_digital'], TextoConsentimiento::marcadoresDesconocidos($texto.' {firma_digital}'));
        $this->assertCount(16, TextoConsentimiento::MARCADORES);
    }
}

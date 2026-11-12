<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Restricciones de la migración de consentimiento con firma y remisión externa
 * (specs/remisiones-consentimiento/design.md, 2.2), sobre la réplica SQLite del esquema.
 */
class EsquemaConsentimientoRemisionTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    private int $idVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional');

        $estudiante = $this->crearUsuarioConRol('Persona');
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad.']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);

        $idModelo = DB::table('modelos_consentimiento')->insertGetId(['codigo' => 'SM-ORIENTACION', 'nombre' => 'Orientación', 'tipo_servicio' => 'Salud Mental']);
        $this->idVersion = DB::table('versiones_consentimiento')->insertGetId(['id_modelo' => $idModelo, 'numero_version' => 1, 'texto' => 'Texto', 'estado' => 'publicada']);
    }

    private function aceptacion(array $cambios = []): array
    {
        return array_merge([
            'id_aceptacion' => (string) Str::uuid(),
            'id_caso' => $this->caso->id_caso,
            'id_version' => $this->idVersion,
            'decision' => 'Aceptado',
            'medio' => 'firma_tactil_expediente',
            'id_profesional' => $this->profesional->id_usuario,
            'otorgante' => 'Estudiante',
            'lugar_expedicion' => 'Tuluá',
            'eps_ips' => 'Nueva EPS',
            'contacto_urgencia' => 'María Pérez',
            'vinculacion' => 'Estudiante',
            'programa_academico' => 'Psicología',
            'semestre' => 3,
            'dependencia_facultad' => 'Facultad de Ciencias de la Salud',
            'texto_presentado' => 'Texto presentado',
            'texto_sha256' => str_repeat('a', 64),
            'firma_ruta' => 'casos/x/y.png',
            'firma_sha256' => str_repeat('b', 64),
        ], $cambios);
    }

    private function actuacion(string $tipo, ?string $destino = null): string
    {
        return Actuacion::create(array_filter([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => $tipo,
            'fecha_hora' => '2026-10-06 09:00:00',
            'destino_remision' => $destino,
            'entidad_destino' => $destino === 'Externa' ? 'EPS / IPS de salud' : null,
            'id_profesional_destino' => $destino === 'Interna' ? $this->crearUsuarioConRol('Profesional')->id_usuario : null,
        ]))->id_actuacion;
    }

    private function remisionExterna(string $idActuacion, array $cambios = []): array
    {
        return array_merge([
            'id_actuacion' => $idActuacion,
            'categoria' => 'PRIORITARIO',
            'estudiante_nombre' => 'Samuel Valencia',
            'estudiante_tipo_documento' => 'CC',
            'estudiante_numero_documento' => '1112223334',
            'estudiante_sexo' => 'M',
            'estudiante_fecha_nacimiento' => '2005-03-10',
            'estudiante_edad' => 21,
            'fecha_hora_atencion' => '2026-10-06 09:00:00',
            'profesional_nombre' => 'Camila Muñoz',
            'formato_codigo' => 'GBU-GPGR-F-016',
            'formato_version' => '3',
            'formato_fecha' => '2024-04-18',
        ], $cambios);
    }

    private function acompanante(): array
    {
        return [
            'acompanante_nombre' => 'María Pérez',
            'acompanante_parentesco' => 'Madre',
            'acompanante_tipo_documento' => 'CC',
            'acompanante_numero_documento' => '29876543',
            'acompanante_telefono' => '3001234567',
        ];
    }

    private function assertFalla(callable $accion, string $motivo): void
    {
        try {
            $accion();
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail("Se esperaba que la base rechazara: $motivo");
    }

    /**
     * DADO un consentimiento aceptado con su evidencia completa,
     * CUANDO se intenta editarlo o borrarlo,
     * ENTONCES la base lo impide.
     */
    public function test_la_evidencia_del_consentimiento_es_inmutable(): void
    {
        $fila = $this->aceptacion();
        DB::table('consentimiento_aceptaciones')->insert($fila);

        $this->assertSame(1, DB::table('consentimiento_aceptaciones')->count());

        $consulta = fn () => DB::table('consentimiento_aceptaciones')->where('id_aceptacion', $fila['id_aceptacion']);
        $this->assertFalla(fn () => $consulta()->update(['decision' => 'Rechazado']), 'UPDATE de una aceptación');
        $this->assertFalla(fn () => $consulta()->delete(), 'DELETE de una aceptación');
    }

    public function test_la_firma_es_obligatoria_al_aceptar_y_no_existe_al_rechazar(): void
    {
        $this->assertFalla(fn () => DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(['firma_ruta' => null, 'firma_sha256' => null])), 'aceptado sin firma');
        $this->assertFalla(fn () => DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(['decision' => 'Rechazado'])), 'rechazado con firma');

        DB::table('consentimiento_aceptaciones')->insert($this->aceptacion([
            'decision' => 'Rechazado', 'firma_ruta' => null, 'firma_sha256' => null,
            'lugar_expedicion' => null, 'eps_ips' => null, 'contacto_urgencia' => null,
        ]));

        $this->assertSame(1, DB::table('consentimiento_aceptaciones')->where('decision', 'Rechazado')->count());
    }

    public function test_los_datos_del_texto_y_del_acudiente_son_obligatorios_al_aceptar(): void
    {
        $this->assertFalla(fn () => DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(['eps_ips' => null])), 'aceptado sin EPS');
        $this->assertFalla(fn () => DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(['otorgante' => 'Acudiente'])), 'acudiente sin sus datos');
        $this->assertFalla(fn () => DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(['medio' => 'correo'])), 'medio desconocido');
        $this->assertFalla(fn () => DB::table('consentimiento_aceptaciones')->insert($this->aceptacion(['acudiente_nombre' => 'Alguien'])), 'datos de acudiente en un estudiante');

        DB::table('consentimiento_aceptaciones')->insert($this->aceptacion([
            'otorgante' => 'Acudiente', 'lugar_expedicion' => null, 'contacto_urgencia' => null,
            'acudiente_nombre' => 'María Pérez', 'acudiente_tipo_documento' => 'CC', 'acudiente_numero_documento' => '29876543',
            'acudiente_parentesco' => 'Madre', 'acudiente_lugar_expedicion' => 'Tuluá',
        ]));

        $this->assertSame(1, DB::table('consentimiento_aceptaciones')->where('otorgante', 'Acudiente')->count());
    }

    /**
     * DADO una remisión externa URGENTE,
     * CUANDO se guarda sin los datos del acompañante,
     * ENTONCES la base la rechaza; y PRIORITARIO no admite acompañante.
     */
    public function test_el_acompanante_solo_existe_y_es_obligatorio_en_remisiones_urgentes(): void
    {
        $idRemision = $this->actuacion('Remisión', 'Externa');

        $this->assertFalla(fn () => DB::table('remisiones_externas')->insert($this->remisionExterna($idRemision, ['categoria' => 'URGENTE'])), 'URGENTE sin acompañante');
        $this->assertFalla(fn () => DB::table('remisiones_externas')->insert($this->remisionExterna($idRemision, $this->acompanante())), 'PRIORITARIO con acompañante');
        $this->assertFalla(fn () => DB::table('remisiones_externas')->insert($this->remisionExterna($idRemision, ['categoria' => 'NORMAL'])), 'categoría desconocida');

        DB::table('remisiones_externas')->insert($this->remisionExterna($idRemision, ['categoria' => 'URGENTE'] + $this->acompanante()));

        $this->assertSame('URGENTE', DB::table('remisiones_externas')->value('categoria'));
    }

    public function test_la_remision_externa_solo_se_guarda_sobre_una_remision_externa_y_es_inmutable(): void
    {
        $cita = $this->actuacion('Consulta');
        $interna = $this->actuacion('Remisión', 'Interna');
        $externa = $this->actuacion('Remisión', 'Externa');

        $this->assertFalla(fn () => DB::table('remisiones_externas')->insert($this->remisionExterna($cita)), 'detalle sobre una cita');
        $this->assertFalla(fn () => DB::table('remisiones_externas')->insert($this->remisionExterna($interna)), 'detalle sobre una remisión interna');

        DB::table('remisiones_externas')->insert($this->remisionExterna($externa));

        $consulta = fn () => DB::table('remisiones_externas')->where('id_actuacion', $externa);
        $this->assertFalla(fn () => $consulta()->update(['estudiante_edad' => 30]), 'UPDATE de la copia de datos');
        $this->assertFalla(fn () => $consulta()->delete(), 'DELETE de la remisión externa');
    }

    public function test_la_auditoria_es_inmutable_y_solo_admite_acciones_conocidas(): void
    {
        DB::table('auditoria_expedientes')->insert(['id_usuario' => $this->profesional->id_usuario, 'id_caso' => $this->caso->id_caso, 'accion' => 'firma_consultada']);

        $this->assertFalla(fn () => DB::table('auditoria_expedientes')->insert(['accion' => 'borrar_todo']), 'acción desconocida');
        $this->assertFalla(fn () => DB::table('auditoria_expedientes')->update(['accion' => 'formato_actualizado']), 'UPDATE de la auditoría');
        $this->assertFalla(fn () => DB::table('auditoria_expedientes')->delete(), 'DELETE de la auditoría');
    }

    public function test_datos_nuevos_de_personas_modelos_y_formato_por_defecto(): void
    {
        $persona = $this->caso->solicitud->persona;

        DB::table('personas')->where('id_persona', $persona->id_persona)->update(['tipo_documento' => 'TI', 'sexo' => 'F', 'fecha_nacimiento' => '2009-05-01']);
        $this->assertSame('TI', DB::table('personas')->where('id_persona', $persona->id_persona)->value('tipo_documento'));

        $this->assertFalla(fn () => DB::table('personas')->where('id_persona', $persona->id_persona)->update(['sexo' => 'X']), 'sexo fuera de F/M');
        $this->assertFalla(fn () => DB::table('personas')->where('id_persona', $persona->id_persona)->update(['tipo_documento' => 'PA']), 'tipo de documento fuera de TI/CC/CE');

        $this->assertSame('General', DB::table('modelos_consentimiento')->where('codigo', 'SM-ORIENTACION')->value('poblacion'));
        $this->assertFalla(fn () => DB::table('modelos_consentimiento')->insert(['codigo' => 'X', 'nombre' => 'X', 'poblacion' => 'Adultos']), 'población desconocida');

        $formato = DB::table('formatos_institucionales')->where('clave', 'REMISION_PSICOLOGICA')->first();
        $this->assertSame(['GBU-GPGR-F-016', '3', '2024-04-18'], [$formato->codigo, $formato->version, $formato->fecha_formato]);
    }
}

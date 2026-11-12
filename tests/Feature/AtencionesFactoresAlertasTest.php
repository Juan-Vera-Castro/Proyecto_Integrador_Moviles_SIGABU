<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AlertaTemprana;
use App\Models\Atencion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Expediente: registro de atenciones, factores de riesgo y protectores, y alertas tempranas
 * (HU-026). Solo el profesional a cargo del caso; todo queda en la auditoría.
 */
class AtencionesFactoresAlertasTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private const ATENCION = [
        'tipo_atencion' => 'Sesión psicológica individual',
        'asistencia' => 'Asistió',
        'fecha_atencion' => '2026-10-22T10:00',
        'observaciones' => '  Refiere mejor descanso y menos ansiedad antes de los parciales.  ',
        'acuerdos_compromisos' => 'Llevar registro diario del sueño.',
    ];

    private const CRITICO = 'Consumo problemático de sustancias';

    private User $profesional;

    private User $otroProfesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Restrepo']);
        $this->otroProfesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Andrés Mejía']);
        $this->caso = $this->crearCaso();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(string $estado = 'En Seguimiento', string $riesgo = 'Medio', bool $conConsentimiento = true): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Ana Gómez']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad ante los parciales.']);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $this->profesional->id_usuario, 'nivel_riesgo' => $riesgo]);

        if ($conConsentimiento) {
            $this->crearConsentimientoVigente($caso);
        }

        return $caso->fresh();
    }

    private function registrarAtencion(array $cambios = [], ?User $como = null, ?Caso $caso = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->post(route('atenciones.store', $caso ?? $this->caso), array_merge(self::ATENCION, $cambios));
    }

    private function guardarFactores(array $riesgo, array $protectores = [], ?User $como = null, ?Caso $caso = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->put(route('casos.actualizar-factores', $caso ?? $this->caso), ['factores_riesgo' => $riesgo, 'factores_protectores' => $protectores]);
    }

    private function acciones(): array
    {
        return AuditoriaExpediente::orderBy('id_auditoria')->pluck('accion')->all();
    }

    /**
     * DADO un caso activo con consentimiento, CUANDO registro una atención,
     * ENTONCES queda en el expediente, a mi nombre, en hora de Colombia y en la auditoría.
     */
    public function test_registra_una_atencion_en_el_expediente(): void
    {
        $this->registrarAtencion()
            ->assertRedirect(route('profesional.expediente.show', $this->caso).'#registro-atenciones')
            ->assertSessionHas('status', 'Atención registrada en el expediente.');

        $atencion = Atencion::sole();
        $this->assertSame([$this->caso->id_caso, $this->profesional->id_usuario], [$atencion->id_caso, $atencion->id_profesional]);
        $this->assertSame('Refiere mejor descanso y menos ansiedad antes de los parciales.', $atencion->observaciones);
        $this->assertSame('2026-10-22 10:00', $atencion->fecha_atencion->format('Y-m-d H:i'));
        $this->assertSame('2026-10-23 09:00', $atencion->fecha_registro->format('Y-m-d H:i'));

        $auditoria = AuditoriaExpediente::where('accion', AuditoriaExpediente::ATENCION_REGISTRADA)->sole();
        $this->assertSame($atencion->id_atencion, $auditoria->detalle['id_atencion']);

        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $this->caso))
            ->assertOk()
            ->assertSee('Registro de atenciones')
            ->assertSee('Llevar registro diario del sueño.')
            ->assertSee('Carolina Restrepo');
    }

    public function test_la_atencion_no_se_modifica_ni_se_borra(): void
    {
        $this->registrarAtencion();
        $atencion = Atencion::sole();

        $this->expectException(LogicException::class);
        $atencion->update(['observaciones' => 'Otra cosa distinta.']);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function atencionesInvalidas(): array
    {
        return [
            'tipo fuera de la lista' => [['tipo_atencion' => 'Otra'], 'tipo_atencion'],
            'asistencia inválida' => [['asistencia' => 'asistio'], 'asistencia'],
            'fecha futura' => [['fecha_atencion' => '2026-10-24T10:00'], 'fecha_atencion'],
            'observaciones cortas' => [['observaciones' => 'Bien.'], 'observaciones'],
        ];
    }

    /**
     * @dataProvider atencionesInvalidas
     */
    public function test_rechaza_atenciones_invalidas(array $cambios, string $campo): void
    {
        $this->registrarAtencion($cambios)->assertSessionHasErrorsIn('atencion', [$campo]);
        $this->assertSame(0, Atencion::count());
    }

    public function test_no_registra_atenciones_en_casos_cerrados_ni_con_el_consentimiento_rechazado(): void
    {
        $cerrado = $this->crearCaso(estado: Caso::ESTADO_CERRADO);
        $this->registrarAtencion(caso: $cerrado)->assertSessionHas('error', "Las atenciones solo se registran en casos activos. El caso {$cerrado->codigo_expediente} está en estado \"Cerrado\".");

        $vigente = $this->caso->aceptacionesConsentimiento()->firstOrFail();
        DB::table('consentimiento_aceptaciones')->insert([
            'id_aceptacion' => (string) Str::uuid(),
            'id_caso' => $this->caso->id_caso,
            'id_version' => $vigente->id_version,
            'decision' => 'Rechazado',
            'fecha_hora' => now()->toDateTimeString(),
            'medio' => 'firma_tactil_expediente',
            'id_profesional' => $this->profesional->id_usuario,
            'texto_presentado' => 'Texto de prueba',
            'texto_sha256' => hash('sha256', 'Texto de prueba'),
        ]);
        $this->registrarAtencion()->assertSessionHas('error');

        $this->assertSame(0, Atencion::count());
        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $this->caso))
            ->assertDontSee(route('atenciones.store', $this->caso), false);
    }

    /**
     * Otro profesional, aunque sea del área, no ve ni escribe en un caso que no tiene a cargo.
     */
    public function test_solo_el_profesional_a_cargo_registra_y_marca(): void
    {
        $this->registrarAtencion(como: $this->otroProfesional)->assertNotFound();
        $this->guardarFactores([self::CRITICO], como: $this->otroProfesional)->assertNotFound();

        $this->guardarFactores([self::CRITICO]);
        $alerta = AlertaTemprana::sole();
        $this->actingAs($this->otroProfesional)->put(route('alertas.atender', $alerta->id_alerta))->assertNotFound();

        $estudiante = $this->caso->solicitud->persona->usuario;
        $this->actingAs($estudiante)->post(route('atenciones.store', $this->caso), self::ATENCION)->assertRedirect();

        $this->assertSame(0, Atencion::count());
        $this->assertTrue($alerta->fresh()->estaPendiente());
        $this->assertSame(1, AuditoriaExpediente::where('accion', AuditoriaExpediente::FACTORES_ACTUALIZADOS)->count(), 'Solo cuenta lo que guardó el profesional a cargo');
    }

    /**
     * DADO un caso en riesgo medio, CUANDO marco un factor crítico,
     * ENTONCES se guardan los factores y se genera una sola alerta pendiente, con su auditoría.
     */
    public function test_un_factor_critico_genera_una_alerta(): void
    {
        $this->guardarFactores(['Situación de vulnerabilidad socioeconómica'], ['Habilidades de afrontamiento y resiliencia'])
            ->assertSessionHas('status', 'Factores de riesgo y protectores guardados.');
        $this->assertSame(0, AlertaTemprana::count());

        $this->guardarFactores(['Situación de vulnerabilidad socioeconómica', self::CRITICO, self::CRITICO])
            ->assertRedirect(route('profesional.expediente.show', $this->caso).'#factores')
            ->assertSessionHas('status', 'Factores guardados. Se generó una alerta temprana: factor de riesgo crítico.');

        // En el orden de la lista y sin repetidos, sin importar cómo llegan.
        $this->assertSame([self::CRITICO, 'Situación de vulnerabilidad socioeconómica'], $this->caso->fresh()->factores_riesgo);

        $alerta = AlertaTemprana::sole();
        $this->assertSame(AlertaTemprana::FACTOR_CRITICO, $alerta->tipo_alerta);
        $this->assertStringContainsString(self::CRITICO, $alerta->descripcion);
        $this->assertSame($this->profesional->id_usuario, $alerta->generada_por);

        // Guardar de nuevo no repite la alerta mientras siga pendiente.
        $this->guardarFactores([self::CRITICO]);
        $this->assertSame(1, AlertaTemprana::count());

        $this->assertSame([
            AuditoriaExpediente::FACTORES_ACTUALIZADOS,
            AuditoriaExpediente::FACTORES_ACTUALIZADOS,
            AuditoriaExpediente::ALERTA_GENERADA,
            AuditoriaExpediente::FACTORES_ACTUALIZADOS,
        ], $this->acciones());
    }

    public function test_solo_acepta_factores_de_la_lista(): void
    {
        $this->guardarFactores(['Cualquier texto inventado'])->assertSessionHasErrorsIn('factores', ['factores_riesgo.0']);
        $this->guardarFactores([], ['Otro inventado'])->assertSessionHasErrorsIn('factores', ['factores_protectores.0']);

        $this->assertNull($this->caso->fresh()->factores_riesgo);
    }

    public function test_los_factores_de_un_caso_cerrado_no_se_cambian(): void
    {
        $cerrado = $this->crearCaso(estado: Caso::ESTADO_CERRADO);

        $this->guardarFactores([self::CRITICO], caso: $cerrado)->assertSessionHas('error');
        $this->assertNull($cerrado->fresh()->factores_riesgo);
        $this->assertSame(0, AlertaTemprana::count());
    }

    /**
     * DADO una alerta pendiente, CUANDO la marco como atendida,
     * ENTONCES queda quién y cuándo, en la auditoría, y una nueva situación crítica genera otra.
     */
    public function test_atender_la_alerta_queda_en_la_auditoria(): void
    {
        $this->guardarFactores([self::CRITICO]);
        $alerta = AlertaTemprana::sole();

        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $this->caso))
            ->assertSee('Alerta temprana pendiente')
            ->assertSee(route('alertas.atender', $alerta->id_alerta), false);

        $this->actingAs($this->profesional)->put(route('alertas.atender', $alerta->id_alerta))
            ->assertRedirect(route('profesional.expediente.show', $this->caso).'#alertas')
            ->assertSessionHas('status', 'Alerta temprana marcada como atendida y registrada en la auditoría del expediente.');

        $alerta->refresh();
        $this->assertSame([AlertaTemprana::ATENDIDA, $this->profesional->id_usuario, '2026-10-23 09:00'], [$alerta->estado, $alerta->atendida_por, $alerta->fecha_atencion->format('Y-m-d H:i')]);
        $this->assertSame(1, AuditoriaExpediente::where('accion', AuditoriaExpediente::ALERTA_ATENDIDA)->count());

        // Atenderla otra vez no cambia nada.
        $this->actingAs($this->profesional)->put(route('alertas.atender', $alerta->id_alerta))->assertSessionHas('error', 'Esta alerta ya estaba atendida.');
        $this->assertSame(1, AuditoriaExpediente::where('accion', AuditoriaExpediente::ALERTA_ATENDIDA)->count());

        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $this->caso))
            ->assertDontSee('Alerta temprana pendiente')
            ->assertSee('Alertas atendidas (1)')
            ->assertSee('atendida por Carolina Restrepo', false);

        // Con la anterior atendida, la situación crítica vuelve a generar una alerta.
        $this->guardarFactores([self::CRITICO]);
        $this->assertSame(1, AlertaTemprana::pendientes()->count());
    }

    /**
     * El riesgo alto también genera la alerta: al registrar una sesión en riesgo alto y al
     * abrir un caso en riesgo alto.
     */
    public function test_el_riesgo_alto_genera_la_alerta(): void
    {
        $cita = Actuacion::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => '2026-10-22 10:00:00',
            'estado_asistencia' => Actuacion::ASISTIO,
        ]);

        $this->actingAs($this->profesional)->post(route('seguimientos.store', $cita), [
            'nivel_riesgo' => 'Alto', 'valoracion_avance' => 'Retroceso', 'nota' => 'Refiere ideas de abandonar la carrera.',
        ])->assertSessionHasNoErrors();

        $alerta = AlertaTemprana::sole();
        $this->assertSame([$this->caso->id_caso, AlertaTemprana::RIESGO_ALTO], [$alerta->id_caso, $alerta->tipo_alerta]);
    }

    /**
     * Sin borrado en cascada: un caso con atenciones o alertas no se puede eliminar.
     *
     * @return array<string, array{string}>
     */
    public static function registrosDelCaso(): array
    {
        return ['atención' => ['atencion'], 'alerta' => ['alerta']];
    }

    /**
     * @dataProvider registrosDelCaso
     */
    public function test_un_caso_con_atenciones_o_alertas_no_se_borra(string $registro): void
    {
        // Sin consentimiento, para que solo lo retenga el registro de esta prueba.
        $caso = $this->crearCaso(conConsentimiento: false);

        $registro === 'atencion'
            ? Atencion::create(['id_caso' => $caso->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'tipo_atencion' => Atencion::TIPOS[0], 'asistencia' => 'Asistió', 'fecha_atencion' => '2026-10-22 10:00:00', 'observaciones' => 'Atención de prueba.', 'fecha_registro' => '2026-10-22 10:30:00'])
            : AlertaTemprana::create(['id_caso' => $caso->id_caso, 'tipo_alerta' => AlertaTemprana::RIESGO_ALTO, 'descripcion' => 'Prueba.', 'fecha_generacion' => '2026-10-22 10:00:00']);

        $this->expectException(QueryException::class);
        DB::table('casos')->where('id_caso', $caso->id_caso)->delete();
    }
}

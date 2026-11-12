<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\SeguimientoEvolucion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-015 (paso 1): registrar el seguimiento de la evolución de una cita asistida.
 */
class SeguimientoEvolucionTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private const DATOS = [
        'nivel_riesgo' => 'Medio',
        'valoracion_avance' => 'Mejora',
        'instrumento' => 'GAD-7',
        'puntaje' => '11',
        'nota' => '  Refiere menos episodios de ansiedad antes de los parciales.  ',
    ];

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso = $this->crearCaso($this->profesional);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $profesional, string $servicio = 'Salud Mental', string $estado = 'En Seguimiento'): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona');
        $solicitud = $estudiante->persona->solicitudes()->create([
            'tipo_servicio' => $servicio,
            'descripcion' => 'Ansiedad ante los parciales.',
        ]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $profesional->id_usuario, 'nivel_riesgo' => 'Alto']);
        $this->crearConsentimientoVigente($caso);

        return $caso->fresh();
    }

    private function crearCita(string $asistencia = Actuacion::ASISTIO, ?Caso $caso = null, string $fechaHora = '2026-10-22 10:00:00'): Actuacion
    {
        $caso ??= $this->caso;

        return Actuacion::create([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $caso->id_profesional,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => $fechaHora,
            'estado_asistencia' => $asistencia,
        ]);
    }

    private function registrar(Actuacion $cita, array $datos = self::DATOS, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->from(route('casos.show', $cita->id_caso))
            ->post(route('seguimientos.store', $cita), $datos + ['cita_seguimiento' => $cita->id_actuacion]);
    }

    /**
     * DADO una cita a la que el estudiante asistió y sin seguimiento,
     * CUANDO registro el riesgo, la valoración, la escala y una nota,
     * ENTONCES se guarda ligado a la cita, el riesgo del caso cambia y queda en la auditoría.
     */
    public function test_registra_el_seguimiento_de_una_cita_asistida(): void
    {
        $cita = $this->crearCita();

        $this->registrar($cita)
            ->assertRedirect(route('casos.show', $this->caso).'#citas')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Seguimiento registrado para la cita del 2026-10-22 a las 10:00 am.');

        $seguimiento = SeguimientoEvolucion::sole();
        $this->assertSame($cita->id_actuacion, $seguimiento->id_actuacion);
        $this->assertSame($this->caso->id_caso, $seguimiento->id_caso);
        $this->assertSame($this->profesional->id_usuario, $seguimiento->id_profesional);
        $this->assertSame('Medio', $seguimiento->nivel_riesgo);
        $this->assertSame('Mejora', $seguimiento->valoracion_avance);
        $this->assertSame('GAD-7', $seguimiento->instrumento);
        $this->assertSame(11, $seguimiento->puntaje);
        $this->assertSame('Moderada', $seguimiento->severidad());
        $this->assertSame('Refiere menos episodios de ansiedad antes de los parciales.', $seguimiento->nota);
        $this->assertSame('2026-10-23 09:00:00', $seguimiento->fecha_hora->format('Y-m-d H:i:s'));

        $this->assertSame('Medio', $this->caso->fresh()->nivel_riesgo);

        $auditoria = AuditoriaExpediente::sole();
        $this->assertSame(AuditoriaExpediente::SEGUIMIENTO_REGISTRADO, $auditoria->accion);
        $this->assertSame($this->caso->id_caso, $auditoria->id_caso);
        $this->assertSame($seguimiento->id_seguimiento, $auditoria->detalle['id_seguimiento']);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Seguimiento de la sesión')
            ->assertSee('GAD-7: 11/21 (Moderada)')
            ->assertDontSee(route('seguimientos.store', $cita), false);
    }

    public function test_la_escala_es_opcional(): void
    {
        $cita = $this->crearCita();

        $this->registrar($cita, ['instrumento' => '', 'puntaje' => ''] + self::DATOS)->assertSessionHasNoErrors();

        $seguimiento = SeguimientoEvolucion::sole();
        $this->assertNull($seguimiento->instrumento);
        $this->assertNull($seguimiento->puntaje);
        $this->assertNull($seguimiento->severidad());
    }

    public function test_el_expediente_ofrece_el_formulario_solo_en_citas_asistidas_sin_seguimiento(): void
    {
        $asistida = $this->crearCita();
        $noAsistida = $this->crearCita(Actuacion::NO_ASISTIO, fechaHora: '2026-10-15 10:00:00');

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee(route('seguimientos.store', $asistida), false)
            ->assertDontSee(route('seguimientos.store', $noAsistida), false)
            ->assertSee('PHQ-9 (0 a 27)')
            ->assertSee(route('casos.evolucion', $this->caso), false);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'GAD-7 fuera de rango' => [['puntaje' => '25'], 'puntaje', 'El puntaje de GAD-7 va de 0 a 21.'],
            'PHQ-9 fuera de rango' => [['instrumento' => 'PHQ-9', 'puntaje' => '28'], 'puntaje', 'El puntaje de PHQ-9 va de 0 a 27.'],
            'puntaje negativo' => [['puntaje' => '-1'], 'puntaje', 'El puntaje de GAD-7 va de 0 a 21.'],
            'escala sin puntaje' => [['puntaje' => ''], 'puntaje', 'Escribe el puntaje de GAD-7.'],
            'puntaje sin escala' => [['instrumento' => ''], 'puntaje', 'Selecciona la escala aplicada antes de escribir el puntaje.'],
            'escala desconocida' => [['instrumento' => 'BDI-II'], 'instrumento', 'Selecciona GAD-7 o PHQ-9.'],
            'sin riesgo' => [['nivel_riesgo' => ''], 'nivel_riesgo', 'Indica el nivel de riesgo del estudiante en esta sesión.'],
            'riesgo inválido' => [['nivel_riesgo' => 'Crítico'], 'nivel_riesgo', 'Indica el nivel de riesgo del estudiante en esta sesión.'],
            'sin valoración' => [['valoracion_avance' => ''], 'valoracion_avance', 'Indica cómo avanzó el estudiante respecto de la sesión anterior.'],
            'nota vacía' => [['nota' => '   '], 'nota', 'Escribe la nota de la sesión.'],
            'nota muy larga' => [['nota' => str_repeat('a', 2001)], 'nota', 'La nota no puede superar los 2000 caracteres.'],
        ];
    }

    #[DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos(array $cambios, string $campo, string $mensaje): void
    {
        $cita = $this->crearCita();

        $this->registrar($cita, $cambios + self::DATOS)
            ->assertSessionHasErrorsIn('seguimiento', [$campo => $mensaje]);

        $this->assertSame(0, SeguimientoEvolucion::count());
        $this->assertSame('Alto', $this->caso->fresh()->nivel_riesgo);
    }

    /**
     * DADO un caso de Inclusión,
     * ENTONCES el seguimiento no admite escalas clínicas y el formulario no las ofrece.
     */
    public function test_en_inclusion_no_se_aplican_escalas(): void
    {
        $caso = $this->crearCaso($this->profesional, 'Inclusión');
        $cita = $this->crearCita(caso: $caso);

        $this->registrar($cita)
            ->assertSessionHasErrorsIn('seguimiento', ['instrumento' => 'Las escalas clínicas solo se aplican en casos de Salud Mental.']);

        $this->registrar($cita, ['instrumento' => '', 'puntaje' => ''] + self::DATOS)->assertSessionHasNoErrors();
        $this->assertNull(SeguimientoEvolucion::sole()->instrumento);

        $otra = $this->crearCita(caso: $caso, fechaHora: '2026-10-15 10:00:00');
        $this->actingAs($this->profesional)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee(route('seguimientos.store', $otra), false)
            ->assertDontSee('PHQ-9 (0 a 27)');
    }

    public function test_no_registra_dos_seguimientos_para_la_misma_cita(): void
    {
        $cita = $this->crearCita();
        $this->registrar($cita)->assertSessionHasNoErrors();

        $this->registrar($cita, ['nivel_riesgo' => 'Bajo'] + self::DATOS)
            ->assertSessionHas('error', 'Esta cita ya tiene su seguimiento registrado: no se modifica.');

        $this->assertSame(1, SeguimientoEvolucion::count());
        $this->assertSame('Medio', $this->caso->fresh()->nivel_riesgo);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function asistenciasSinSeguimiento(): array
    {
        return [
            'no asistió' => [Actuacion::NO_ASISTIO],
            'cancelada' => [Actuacion::CANCELADA],
            'pendiente' => [Actuacion::ASISTENCIA_PENDIENTE],
        ];
    }

    #[DataProvider('asistenciasSinSeguimiento')]
    public function test_solo_se_registra_en_citas_asistidas(string $asistencia): void
    {
        $cita = $this->crearCita($asistencia);

        $this->registrar($cita)
            ->assertSessionHas('error', 'El seguimiento solo se registra en citas a las que el estudiante asistió.');

        $this->assertSame(0, SeguimientoEvolucion::count());
    }

    public function test_no_se_registra_en_un_caso_cerrado(): void
    {
        $cita = $this->crearCita();
        $this->caso->update(['estado_caso' => Caso::ESTADO_CERRADO]);

        $this->registrar($cita)
            ->assertSessionHas('error', "El seguimiento solo se registra en casos activos. El caso {$this->caso->codigo_expediente} está en estado \"Cerrado\".");

        $this->assertSame(0, SeguimientoEvolucion::count());
    }

    public function test_un_rechazo_del_consentimiento_bloquea_el_seguimiento(): void
    {
        $cita = $this->crearCita();
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

        $this->registrar($cita)->assertSessionHas('error');

        $this->assertSame(0, SeguimientoEvolucion::count());
    }

    /**
     * Solo el profesional asignado registra el seguimiento.
     */
    public function test_otro_profesional_no_puede_registrar_el_seguimiento(): void
    {
        $cita = $this->crearCita();
        $otro = $this->crearUsuarioConRol('Profesional');

        $this->registrar($cita, como: $otro)->assertForbidden();

        $this->assertSame(0, SeguimientoEvolucion::count());
    }

    public function test_el_estudiante_no_puede_registrar_el_seguimiento(): void
    {
        $cita = $this->crearCita();
        $estudiante = $this->caso->solicitud->persona->usuario;

        // CheckRole devuelve a cada usuario a su propio panel cuando la ruta no es de su rol.
        $this->registrar($cita, como: $estudiante)->assertRedirect($estudiante->getDashboardUrl());

        $this->assertSame(0, SeguimientoEvolucion::count());
    }

    public function test_una_remision_no_es_una_cita(): void
    {
        $remision = Actuacion::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_REMISION,
            'fecha_hora' => '2026-10-22 10:00:00',
            'destino_remision' => Actuacion::DESTINO_EXTERNA,
            'entidad_destino' => 'EPS / IPS de salud',
        ]);

        $this->registrar($remision)->assertNotFound();
    }

    public function test_el_seguimiento_no_se_modifica_ni_se_borra(): void
    {
        $this->registrar($this->crearCita())->assertSessionHasNoErrors();
        $seguimiento = SeguimientoEvolucion::sole();

        try {
            $seguimiento->update(['nota' => 'Otra nota']);
            $this->fail('El seguimiento no debe poder modificarse.');
        } catch (LogicException) {
        }

        $this->expectException(QueryException::class);
        DB::table('seguimientos_evolucion')->where('id_seguimiento', $seguimiento->id_seguimiento)->delete();
    }

    public function test_la_base_rechaza_un_seguimiento_de_una_cita_no_asistida(): void
    {
        $cita = $this->crearCita(Actuacion::NO_ASISTIO);

        $this->expectException(QueryException::class);
        SeguimientoEvolucion::create([
            'id_caso' => $this->caso->id_caso,
            'id_actuacion' => $cita->id_actuacion,
            'id_profesional' => $this->profesional->id_usuario,
            'fecha_hora' => now()->toDateTimeString(),
            'nivel_riesgo' => 'Bajo',
            'valoracion_avance' => 'Estable',
            'nota' => 'Nota',
        ]);
    }

    /**
     * @return array<string, array{?string, ?int}>
     */
    public static function puntajesInconsistentes(): array
    {
        return [
            'puntaje sin escala' => [null, 5],
            'escala sin puntaje' => ['GAD-7', null],
            'GAD-7 fuera de rango' => ['GAD-7', 22],
            'PHQ-9 fuera de rango' => ['PHQ-9', 28],
        ];
    }

    /**
     * La base protege la escala y el puntaje aunque la aplicación no los valide.
     */
    #[DataProvider('puntajesInconsistentes')]
    public function test_la_base_rechaza_puntajes_inconsistentes(?string $instrumento, ?int $puntaje): void
    {
        $cita = $this->crearCita();

        $this->expectException(QueryException::class);
        SeguimientoEvolucion::create([
            'id_caso' => $this->caso->id_caso,
            'id_actuacion' => $cita->id_actuacion,
            'id_profesional' => $this->profesional->id_usuario,
            'fecha_hora' => now()->toDateTimeString(),
            'nivel_riesgo' => 'Bajo',
            'valoracion_avance' => 'Estable',
            'instrumento' => $instrumento,
            'puntaje' => $puntaje,
            'nota' => 'Nota',
        ]);
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function severidades(): array
    {
        return [
            'GAD-7 mínima' => ['GAD-7', 4, 'Mínima'],
            'GAD-7 leve' => ['GAD-7', 5, 'Leve'],
            'GAD-7 moderada' => ['GAD-7', 14, 'Moderada'],
            'GAD-7 severa' => ['GAD-7', 15, 'Severa'],
            'PHQ-9 moderadamente severa' => ['PHQ-9', 19, 'Moderadamente severa'],
            'PHQ-9 severa' => ['PHQ-9', 20, 'Severa'],
        ];
    }

    #[DataProvider('severidades')]
    public function test_bandas_de_severidad(string $instrumento, int $puntaje, string $severidad): void
    {
        $this->assertSame($severidad, SeguimientoEvolucion::severidadDe($instrumento, $puntaje));
    }
}

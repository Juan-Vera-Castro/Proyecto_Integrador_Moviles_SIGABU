<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\SeguimientoEvolucion;
use App\Models\TareaTerapeutica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-015: Medición de evolución y efectividad del tratamiento.
 *
 * DADO un historial de atenciones y seguimientos,
 * CUANDO consulto la evolución del caso,
 * ENTONCES visualizo una línea de tiempo y un resumen del progreso.
 */
class EvolucionCasoTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $carolina;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->carolina = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Restrepo']);
        $this->caso = $this->crearCaso($this->carolina);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $profesional, string $servicio = 'Salud Mental', bool $conConsentimiento = true): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Ana Gómez']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => $servicio, 'descripcion' => 'Ansiedad.']);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => 'En Seguimiento', 'id_profesional' => $profesional->id_usuario]);
        DB::table('casos')->where('id_caso', $caso->id_caso)->update(['fecha_apertura' => '2026-07-14 08:00:00']);

        if ($conConsentimiento) {
            // Las aceptaciones son inmutables: se firma con el reloj puesto en la semana de la apertura.
            $ahora = Carbon::getTestNow();
            Carbon::setTestNow(Carbon::parse('2026-07-15 09:00:00'));
            $this->crearConsentimientoVigente($caso);
            Carbon::setTestNow($ahora);
        }

        return $caso->fresh();
    }

    private function cita(string $fechaHora, string $asistencia = Actuacion::ASISTIO, ?User $profesional = null, ?Caso $caso = null): Actuacion
    {
        return Actuacion::create([
            'id_caso' => ($caso ?? $this->caso)->id_caso,
            'id_profesional' => ($profesional ?? $this->carolina)->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => $fechaHora,
            'estado_asistencia' => $asistencia,
        ]);
    }

    private function seguimiento(string $fechaCita, string $riesgo, ?string $instrumento = null, ?int $puntaje = null, string $valoracion = 'Mejora', ?Caso $caso = null): SeguimientoEvolucion
    {
        $cita = $this->cita($fechaCita, caso: $caso);

        return SeguimientoEvolucion::create([
            'id_caso' => $cita->id_caso,
            'id_actuacion' => $cita->id_actuacion,
            'id_profesional' => $this->carolina->id_usuario,
            'fecha_hora' => Carbon::parse($fechaCita)->addHour()->toDateTimeString(),
            'nivel_riesgo' => $riesgo,
            'valoracion_avance' => $valoracion,
            'instrumento' => $instrumento,
            'puntaje' => $puntaje,
            'nota' => "Nota de la sesión del {$fechaCita}.",
        ]);
    }

    private function tarea(string $fecha, string $estado = TareaTerapeutica::ESTADO_PENDIENTE): TareaTerapeutica
    {
        return TareaTerapeutica::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->carolina->id_usuario,
            'titulo' => "Tarea del {$fecha}",
            'descripcion' => 'Registro diario de pensamientos.',
            'fecha_asignacion' => $fecha,
            'estado' => $estado,
        ]);
    }

    private function api(array $parametros = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->carolina)
            ->get(route('api.casos.evolucion', ['caso' => $this->caso->id_caso] + $parametros));
    }

    /**
     * Escenario: ver la línea de tiempo completa del caso, del evento más reciente al más antiguo.
     */
    public function test_la_linea_de_tiempo_ordena_los_eventos_y_anida_el_seguimiento(): void
    {
        $this->seguimiento('2026-08-04 09:00:00', 'Medio', 'GAD-7', 12);
        $this->cita('2026-09-01 09:00:00', Actuacion::NO_ASISTIO);
        $this->tarea('2026-08-05 10:00:00', TareaTerapeutica::ESTADO_CUMPLIDA);

        $eventos = $this->api()->assertOk()->json('eventos');

        $this->assertSame(['cita', 'tarea', 'cita', 'consentimiento', 'apertura'], array_column($eventos, 'tipo'));
        $this->assertSame('No asistió', $eventos[0]['etiqueta']);
        $this->assertNull($eventos[0]['seguimiento']);
        $this->assertSame('GAD-7', $eventos[2]['seguimiento']['instrumento']);
        $this->assertSame('Moderada', $eventos[2]['seguimiento']['severidad']);
        $this->assertSame('2026-08-04T09:00:00-05:00', $eventos[2]['fecha']);
        $this->assertSame('Carolina Restrepo', $eventos[2]['profesional']['nombre']);

        $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))
            ->assertOk()
            ->assertSeeInOrder(['Evolución del caso', 'Resumen del progreso', 'Línea de tiempo', 'Septiembre de 2026', 'Agosto de 2026', 'Julio de 2026', 'Apertura del caso'])
            ->assertSee('Nota de la sesión del 2026-08-04 09:00:00.');
    }

    /**
     * Escenario: resumen con tendencia de mejora clínicamente relevante y evolución del riesgo.
     */
    public function test_resumen_con_mejora_relevante_y_evolucion_del_riesgo(): void
    {
        $this->seguimiento('2026-07-14 09:00:00', 'Alto', 'GAD-7', 16, 'Estable');
        $this->seguimiento('2026-08-04 09:00:00', 'Medio', 'GAD-7', 12);
        $this->seguimiento('2026-10-22 10:00:00', 'Bajo', 'GAD-7', 9);

        $resumen = $this->api()->assertOk()->json('resumen');

        $gad = $resumen['escalas'][0];
        $this->assertSame('GAD-7', $gad['instrumento']);
        $this->assertSame([16, 12, 9], array_column($gad['mediciones'], 'puntaje'));
        $this->assertSame(-7, $gad['cambio_absoluto']);
        $this->assertSame(-43.8, $gad['cambio_porcentual']);
        $this->assertSame('mejora', $gad['tendencia']);
        $this->assertSame('Leve', $gad['severidad_actual']);
        $this->assertSame(['inicial' => 'Alto', 'actual' => 'Bajo', 'tendencia' => 'baja'], array_intersect_key($resumen['riesgo'], array_flip(['inicial', 'actual', 'tendencia'])));
        $this->assertSame(['Mejora' => 2, 'Estable' => 1, 'Retroceso' => 0], $resumen['valoraciones']);

        $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))
            ->assertOk()
            ->assertSee('16 → 9')
            ->assertSee('Mejora clínicamente relevante')
            ->assertSee('Alto → Bajo')
            ->assertSee('GAD-7 pasó de 16 a 9 puntos', false);
    }

    /**
     * Escenario: adherencia y cumplimiento de tareas. Canceladas, programadas y pendientes no cuentan.
     */
    public function test_adherencia_y_cumplimiento_de_tareas(): void
    {
        foreach (['2026-08-01', '2026-08-08', '2026-08-15', '2026-08-22'] as $dia) {
            $this->cita("{$dia} 09:00:00");
        }
        $this->cita('2026-08-29 09:00:00', Actuacion::NO_ASISTIO);
        $this->cita('2026-09-05 09:00:00', Actuacion::CANCELADA);
        $this->cita('2026-10-30 09:00:00', Actuacion::ASISTENCIA_PENDIENTE);
        foreach (['2026-08-01', '2026-08-08', '2026-08-15'] as $dia) {
            $this->tarea("{$dia} 10:00:00", TareaTerapeutica::ESTADO_CUMPLIDA);
        }
        $this->tarea('2026-08-22 10:00:00', TareaTerapeutica::ESTADO_NO_CUMPLIDA);
        $this->tarea('2026-10-20 10:00:00');

        $resumen = $this->api()->json('resumen');

        $this->assertSame(['asistidas' => 4, 'no_asistidas' => 1, 'canceladas' => 1, 'programadas' => 1, 'sin_registro' => 0, 'porcentaje' => 80], $resumen['adherencia']);
        $this->assertSame(75, (int) $resumen['tareas']['porcentaje_cumplimiento']);
        $this->assertSame(1, $resumen['tareas']['pendientes']);
        $this->assertSame(4, $resumen['citas_sin_seguimiento']);

        $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))
            ->assertSee('80 %')
            ->assertSee('4 de 5 citas realizadas')
            ->assertSee('75 %')
            ->assertSee('3 de 4 tareas evaluadas')
            ->assertSee('Programada');
    }

    /**
     * Escenario: el rango de fechas filtra la línea de tiempo y recalcula el resumen.
     */
    public function test_el_rango_de_fechas_filtra_eventos_y_resumen(): void
    {
        $this->seguimiento('2026-08-04 09:00:00', 'Alto', 'GAD-7', 16);
        $this->seguimiento('2026-09-08 09:00:00', 'Medio', 'GAD-7', 12);
        $this->seguimiento('2026-09-22 09:00:00', 'Bajo', 'GAD-7', 10);

        $respuesta = $this->api(['desde' => '2026-09-01', 'hasta' => '2026-09-30'])->assertOk();

        $this->assertSame(['cita', 'cita'], array_column($respuesta->json('eventos'), 'tipo'));
        $this->assertSame([12, 10], array_column($respuesta->json('resumen.escalas.0.mediciones'), 'puntaje'));
        $this->assertSame('sin_cambio', $respuesta->json('resumen.escalas.0.tendencia'));
        $this->assertSame('2026-09-01', $respuesta->json('filtros_aplicados.desde'));
    }

    /**
     * Escenario: los tipos solo filtran la línea de tiempo; el resumen no cambia.
     */
    public function test_los_tipos_filtran_solo_la_linea_de_tiempo(): void
    {
        $this->seguimiento('2026-08-04 09:00:00', 'Medio');
        $this->tarea('2026-08-05 10:00:00', TareaTerapeutica::ESTADO_CUMPLIDA);

        $completo = $this->api()->json('resumen');
        $respuesta = $this->api(['tipos' => ['tarea']])->assertOk();

        $this->assertSame(['tarea'], array_column($respuesta->json('eventos'), 'tipo'));
        $this->assertSame($completo, $respuesta->json('resumen'));
    }

    /**
     * Escenario: caso que pasó por varios profesionales; quien lo remitió lo consulta en solo lectura.
     */
    public function test_filtra_por_profesional_y_quien_remitio_ya_no_la_consulta(): void
    {
        $andres = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Andrés Mejía']);
        $this->cita('2026-08-04 09:00:00', profesional: $andres);
        Actuacion::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $andres->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_REMISION,
            'fecha_hora' => '2026-09-15 10:00:00',
            'descripcion' => 'Cambio de jornada.',
            'destino_remision' => Actuacion::DESTINO_INTERNA,
            'id_profesional_destino' => $this->carolina->id_usuario,
        ]);
        $this->seguimiento('2026-10-01 09:00:00', 'Bajo');

        $respuesta = $this->api(['profesional' => $andres->id_usuario])->assertOk();
        $this->assertSame(['remision', 'cita'], array_column($respuesta->json('eventos'), 'tipo'));
        $this->assertSame('A Carolina Restrepo. Motivo: Cambio de jornada.', $respuesta->json('eventos.0.descripcion'));

        $this->api(['profesional' => $this->crearUsuarioConRol('Profesional')->id_usuario])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profesional' => 'Selecciona un profesional que haya atendido el caso.']);

        // Quien remitió el caso solo ve su resumen (casos.show), no la evolución
        $this->api(como: $andres)->assertNotFound();
        $this->assertTrue($this->api()->json('caso.puede_registrar'));
    }

    /**
     * Escenario: caso de Inclusión, sin escalas clínicas.
     */
    public function test_inclusion_no_muestra_escalas(): void
    {
        $this->caso = $this->crearCaso($this->carolina, 'Inclusión');
        $this->seguimiento('2026-08-04 09:00:00', 'Medio', caso: $this->caso);
        $this->seguimiento('2026-09-04 09:00:00', 'Bajo', caso: $this->caso);

        $resumen = $this->api()->assertOk()->json('resumen');

        $this->assertSame([], $resumen['escalas']);
        $this->assertSame('Bajo', $resumen['riesgo']['actual']);
        $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))
            ->assertOk()
            ->assertDontSee('No se han aplicado escalas')
            ->assertSee('Medio → Bajo');
    }

    /**
     * Escenario: paginación por cursor, sin repetidos ni huecos.
     */
    public function test_paginacion_por_cursor(): void
    {
        for ($i = 0; $i < 43; $i++) {
            $this->tarea(Carbon::parse('2026-08-01 10:00:00')->addDays($i)->toDateTimeString());
        }

        $ids = [];
        $cursor = null;
        $paginas = 0;
        do {
            $respuesta = $this->api(array_filter(['cursor' => $cursor]))->assertOk();
            $ids = [...$ids, ...array_column($respuesta->json('eventos'), 'id')];
            $cursor = $respuesta->json('paginacion.siguiente_cursor');
            $paginas++;
        } while ($cursor && $paginas < 10);

        $this->assertSame(3, $paginas);
        $this->assertSame(45, $respuesta->json('paginacion.total'));
        $this->assertCount(45, $ids);
        $this->assertSame($ids, array_unique($ids));

        // Solo se audita la primera página.
        $this->assertSame(1, AuditoriaExpediente::where('accion', AuditoriaExpediente::EVOLUCION_CONSULTADA)->count());
    }

    public function test_cargar_mas_devuelve_el_fragmento_de_la_siguiente_pagina(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->tarea(Carbon::parse('2026-08-01 10:00:00')->addDays($i)->toDateTimeString());
        }

        $vista = $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))
            ->assertOk()
            ->assertSee('Cargar más');
        $siguiente = $vista->viewData('siguiente');

        $fragmento = $this->actingAs($this->carolina)->get($siguiente.'&parcial=1&mes=2026-08')->assertOk();

        $this->assertSame('7', $fragmento->headers->get('X-Cantidad'));
        $this->assertSame('', $fragmento->headers->get('X-Siguiente'));
        $this->assertStringNotContainsString('<html', $fragmento->getContent());
        $this->assertStringContainsString('Apertura del caso', $fragmento->getContent());
    }

    /**
     * Escenario: caso sin historial.
     */
    public function test_caso_sin_historial(): void
    {
        $this->caso = $this->crearCaso($this->carolina, conConsentimiento: false);

        $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))
            ->assertOk()
            ->assertSee('Apertura del caso')
            ->assertSee('Aún no hay seguimientos registrados')
            ->assertSee('Agendar primera cita');

        $resumen = $this->api()->json('resumen');
        $this->assertNull($resumen['riesgo']);
        $this->assertNull($resumen['adherencia']['porcentaje']);
        $this->assertNull($resumen['tareas']['porcentaje_cumplimiento']);
    }

    /**
     * Escenarios: una sola medición, cambio menor al umbral e instrumentos que no se mezclan.
     */
    public function test_tendencia_con_datos_insuficientes_o_sin_cambio(): void
    {
        $this->seguimiento('2026-08-04 09:00:00', 'Medio', 'PHQ-9', 14);
        $escalas = $this->api()->json('resumen.escalas');
        $this->assertSame('datos_insuficientes', $escalas[0]['tendencia']);
        $this->assertNull($escalas[0]['cambio_absoluto']);

        $this->seguimiento('2026-08-11 09:00:00', 'Medio', 'PHQ-9', 12);
        $this->assertSame('sin_cambio', $this->api()->json('resumen.escalas.0.tendencia'));

        $this->seguimiento('2026-08-18 09:00:00', 'Medio', 'GAD-7', 15);
        $escalas = $this->api()->json('resumen.escalas');
        $this->assertSame(['GAD-7', 'PHQ-9'], array_column($escalas, 'instrumento'));
        $this->assertSame('datos_insuficientes', $escalas[0]['tendencia']);

        $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))
            ->assertOk()
            ->assertSee('Datos insuficientes: se necesitan al menos 2 mediciones')
            ->assertSee('Sin cambio relevante');
    }

    public function test_seguimientos_sin_escala(): void
    {
        $this->seguimiento('2026-08-04 09:00:00', 'Medio');

        $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))
            ->assertOk()
            ->assertSee('No se han aplicado escalas en este caso.');
    }

    /**
     * Escenarios: rango sin eventos y rango inválido.
     */
    public function test_rango_sin_eventos_y_rango_invalido(): void
    {
        $this->actingAs($this->carolina)->get(route('casos.evolucion', ['caso' => $this->caso->id_caso, 'desde' => '2026-06-01', 'hasta' => '2026-06-30']))
            ->assertOk()
            ->assertSee('No hay eventos')
            ->assertSee('desde el 2026-06-01')
            ->assertSee('Quitar filtros');

        $this->api(['desde' => '2026-10-01', 'hasta' => '2026-09-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hasta' => 'La fecha final debe ser igual o posterior a la inicial.']);

        $this->actingAs($this->carolina)->get(route('casos.evolucion', ['caso' => $this->caso->id_caso, 'desde' => '2026-10-01', 'hasta' => '2026-09-01']))
            ->assertOk()
            ->assertSee('No se aplicaron los filtros')
            ->assertSee('La fecha final debe ser igual o posterior a la inicial.')
            ->assertSee('Apertura del caso');

        $this->api(['cursor' => 'basura'])->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    public function test_caso_cerrado_en_solo_lectura(): void
    {
        $this->seguimiento('2026-08-04 09:00:00', 'Bajo');
        $this->caso->update(['estado_caso' => Caso::ESTADO_CERRADO]);
        DB::table('casos')->where('id_caso', $this->caso->id_caso)->update(['fecha_cierre' => '2026-10-01 12:00:00']);

        $respuesta = $this->api()->assertOk();

        $this->assertSame('cierre', $respuesta->json('eventos.0.tipo'));
        $this->assertFalse($respuesta->json('caso.puede_registrar'));
        $this->assertSame(79, $respuesta->json('caso.dias_en_atencion'));
    }

    /**
     * Escenarios de permisos.
     */
    public function test_un_profesional_sin_relacion_con_el_caso_recibe_404(): void
    {
        $lucia = $this->crearUsuarioConRol('Profesional');

        $this->actingAs($lucia)->get(route('casos.evolucion', $this->caso))->assertNotFound();
        $this->api(como: $lucia)->assertNotFound()->assertJson(['message' => 'Caso no encontrado.']);

        $this->assertSame(0, AuditoriaExpediente::count());
    }

    public function test_el_estudiante_no_ve_la_evolucion(): void
    {
        $estudiante = $this->caso->solicitud->persona->usuario;

        $this->api(como: $estudiante)->assertForbidden()
            ->assertJson(['message' => 'No tienes permiso para consultar la evolución.']);
        $this->actingAs($estudiante)->get(route('casos.evolucion', $this->caso))
            ->assertRedirect($estudiante->getDashboardUrl());
    }

    public function test_sin_sesion_la_api_responde_401_en_json(): void
    {
        $this->get(route('api.casos.evolucion', $this->caso))
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Escenario: trazabilidad de la consulta.
     */
    public function test_la_consulta_queda_en_la_auditoria(): void
    {
        $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso))->assertOk();

        $auditoria = AuditoriaExpediente::sole();
        $this->assertSame(AuditoriaExpediente::EVOLUCION_CONSULTADA, $auditoria->accion);
        $this->assertSame($this->carolina->id_usuario, $auditoria->id_usuario);
        $this->assertSame($this->caso->id_caso, $auditoria->id_caso);
        $this->assertSame(['medio' => 'vista'], $auditoria->detalle);
    }

    public function test_el_json_no_expone_firmas_ni_documentos(): void
    {
        $this->seguimiento('2026-08-04 09:00:00', 'Bajo');

        $respuesta = $this->api()->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $json = $respuesta->getContent();

        foreach (['firma_ruta', 'firma_sha256', 'numero_documento', 'texto_presentado', 'eps_ips'] as $campo) {
            $this->assertStringNotContainsString($campo, $json);
        }
    }

    public function test_la_vista_y_el_json_dan_los_mismos_numeros(): void
    {
        $this->seguimiento('2026-08-04 09:00:00', 'Alto', 'GAD-7', 16);
        $this->seguimiento('2026-09-04 09:00:00', 'Bajo', 'GAD-7', 8);
        $this->tarea('2026-08-05 10:00:00', TareaTerapeutica::ESTADO_CUMPLIDA);

        $vista = $this->actingAs($this->carolina)->get(route('casos.evolucion', $this->caso));

        $this->assertSame($this->api()->json('resumen'), json_decode(json_encode($vista->viewData('resumen')), true));
    }

    public function test_numero_fijo_de_consultas(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $fecha = Carbon::parse('2026-08-01 09:00:00')->addDays($i * 3);
            $this->seguimiento($fecha->toDateTimeString(), 'Medio', 'GAD-7', 10);
            $this->cita($fecha->copy()->addDay()->toDateTimeString(), Actuacion::NO_ASISTIO);
            $this->tarea($fecha->copy()->addHours(2)->toDateTimeString());
        }

        DB::enableQueryLog();
        $this->api()->assertOk();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(30, $consultas, "La evolución hizo {$consultas} consultas.");
    }
}

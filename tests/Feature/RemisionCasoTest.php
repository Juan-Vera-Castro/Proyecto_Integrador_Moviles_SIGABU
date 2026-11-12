<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\TareaTerapeutica;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * HU-007: Registrar la remisión de un caso a otra instancia o profesional.
 */
class RemisionCasoTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private User $destino;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        // Martes 6 de octubre de 2026, 11:30 am en Colombia.
        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Carolina Restrepo']);
        $this->destino = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Andrés Gómez']);
        $this->caso = $this->crearCaso($this->profesional);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(User $profesional, string $estado = 'Abierto'): Caso
    {
        // El formato de remisión externa toma estos datos del registro del estudiante (D-01).
        $estudiante = $this->crearUsuarioConRol('Persona', ['tipo_documento' => 'CC', 'sexo' => 'M', 'fecha_nacimiento' => '2005-03-10']);
        $solicitud = $estudiante->persona->solicitudes()->create([
            'tipo_servicio' => 'Salud Mental',
            'descripcion' => 'Ansiedad y crisis académica.',
        ]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $profesional->id_usuario]);

        // RN-01: el caso ya tiene consentimiento firmado, así que se puede atender.
        $this->crearConsentimientoVigente($caso);

        return $caso->fresh();
    }

    private function crearCita(string $fechaHora, string $asistencia = 'Pendiente'): Actuacion
    {
        return Actuacion::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA,
            'fecha_hora' => $fechaHora,
            'estado_asistencia' => $asistencia,
        ]);
    }

    private function crearTarea(string $titulo, string $estado = TareaTerapeutica::ESTADO_PENDIENTE): TareaTerapeutica
    {
        return $this->caso->tareas()->create([
            'id_profesional' => $this->profesional->id_usuario,
            'titulo' => $titulo,
            'descripcion' => 'Actividad entre sesiones.',
            'fecha_asignacion' => '2026-10-01 10:00:00',
            'estado' => $estado,
        ]);
    }

    private function datosInterna(array $cambios = []): array
    {
        return array_merge([
            'destino_remision' => 'Interna',
            'id_profesional_destino' => $this->destino->id_usuario,
            'motivo' => 'Requiere acompañamiento especializado en duelo.',
        ], $cambios);
    }

    private function datosExterna(array $cambios = []): array
    {
        return array_merge([
            'destino_remision' => 'Externa',
            'entidad_destino' => 'EPS / IPS de salud',
            'categoria' => 'PRIORITARIO',
            'motivo' => 'Situación de riesgo que requiere protección.',
        ], $cambios);
    }

    private function remitir(array $datos, ?Caso $caso = null, ?User $como = null)
    {
        $caso ??= $this->caso;

        return $this->actingAs($como ?? $this->profesional)
            ->from(route('casos.show', $caso))
            ->post(route('remisiones.store', $caso), $datos);
    }

    private function assertCasoSinRemitir(): void
    {
        $caso = $this->caso->fresh();

        $this->assertSame($this->profesional->id_usuario, $caso->id_profesional);
        $this->assertSame('Abierto', $caso->estado_caso);
        $this->assertSame(0, Actuacion::remisiones()->count());
    }

    /**
     * DADO un caso que requiere remisión,
     * CUANDO lo remito a otro profesional,
     * ENTONCES el caso pasa a su cargo y el expediente refleja la remisión.
     */
    public function test_remite_el_caso_a_otro_profesional(): void
    {
        $this->remitir($this->datosInterna(['motivo' => '  Requiere acompañamiento especializado en duelo. ']))
            ->assertRedirect(route('casos.show', $this->caso).'#remisiones')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', "Caso {$this->caso->codigo_expediente} remitido a Andrés Gómez. Ahora está a su cargo; conservas la historia clínica hasta hoy.");

        $caso = $this->caso->fresh();
        $this->assertSame($this->destino->id_usuario, $caso->id_profesional);
        $this->assertSame('Abierto', $caso->estado_caso);
        $this->assertNull($caso->fecha_cierre);

        $remision = Actuacion::remisiones()->sole();
        $this->assertSame($this->caso->id_caso, $remision->id_caso);
        $this->assertSame($this->profesional->id_usuario, $remision->id_profesional);
        $this->assertSame('Interna', $remision->destino_remision);
        $this->assertSame($this->destino->id_usuario, $remision->id_profesional_destino);
        $this->assertNull($remision->entidad_destino);
        $this->assertSame('Requiere acompañamiento especializado en duelo.', $remision->descripcion);
        $this->assertSame('2026-10-06 11:30:00', $remision->fecha_hora->toDateTimeString());
    }

    public function test_el_nuevo_profesional_ve_el_caso_como_remision_y_sabe_de_quien_viene(): void
    {
        $this->remitir($this->datosInterna());

        $this->actingAs($this->destino)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Recibido por remisión')
            ->assertSee('Remitido por')
            ->assertSee('Carolina Restrepo')
            ->assertSee('Requiere acompañamiento especializado en duelo.')
            ->assertSee('Registrar remisión')
            ->assertSee('Guardar tarea')
            ->assertDontSee('Cerrado y remitido');
    }

    public function test_quien_remite_ve_el_caso_cerrado_y_remitido_en_solo_lectura(): void
    {
        $this->remitir($this->datosInterna());

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Remitiste este caso a')
            ->assertSee('Andrés Gómez')
            // Ve si el nuevo profesional sigue con el caso abierto
            ->assertSee($this->caso->fresh()->estado_caso)
            ->assertSee('Sigue en proceso');

        // En sus listados: el estado real del caso con el profesional actual
        $this->actingAs($this->profesional)->get(route('profesional.expedientes'))
            ->assertSee('Remitido por ti')
            ->assertSee('A cargo de Andrés Gómez')
            ->assertSee($this->caso->fresh()->estado_caso)
            ->assertDontSee('Cerrado y remitido');

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertDontSee('Registrar remisión')
            ->assertDontSee('Guardar tarea')
            ->assertDontSee(route('citas.create', ['caso' => $this->caso->id_caso]));
    }

    /**
     * DADO un caso que requiere atención fuera de la universidad,
     * CUANDO lo remito a una entidad externa,
     * ENTONCES el caso se cierra y el expediente refleja la remisión.
     */
    public function test_remite_el_caso_a_una_entidad_externa_y_lo_cierra(): void
    {
        $this->remitir($this->datosExterna())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', "Caso {$this->caso->codigo_expediente} remitido a EPS / IPS de salud. El caso queda cerrado. Descarga el documento de remisión en PDF o Word desde el historial de remisiones para imprimirlo y firmarlo.");

        $caso = $this->caso->fresh();
        $this->assertSame('Cerrado', $caso->estado_caso);
        $this->assertSame('2026-10-06 11:30:00', $caso->fecha_cierre->toDateTimeString());
        $this->assertSame($this->profesional->id_usuario, $caso->id_profesional);

        $remision = Actuacion::remisiones()->sole();
        $this->assertSame('Externa', $remision->destino_remision);
        $this->assertSame('EPS / IPS de salud', $remision->entidad_destino);
        $this->assertNull($remision->id_profesional_destino);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('Caso cerrado · remitido a una entidad externa')
            ->assertSee('EPS / IPS de salud')
            ->assertDontSee('Cerrado y remitido')
            ->assertSee('Entidad externa')
            ->assertSee('Situación de riesgo que requiere protección.')
            ->assertDontSee('Registrar remisión');
    }

    public function test_si_el_nuevo_profesional_lo_remite_a_una_entidad_externa_quien_remitio_primero_lo_ve_cerrado(): void
    {
        $this->remitir($this->datosInterna());
        Carbon::setTestNow(Carbon::parse('2026-10-08 09:15:00', Actuacion::ZONA_HORARIA));
        $this->remitir($this->datosExterna(), como: $this->destino)->assertSessionHasNoErrors();

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Remitiste este caso a')
            ->assertSee('Caso cerrado · remitido a una entidad externa')
            ->assertSee('EPS / IPS de salud')
            ->assertDontSee('Sigue en proceso')
            ->assertDontSee('Situación de riesgo que requiere protección.');
    }

    public function test_la_entidad_otra_guarda_el_nombre_escrito(): void
    {
        $this->remitir($this->datosExterna(['entidad_destino' => 'Otra', 'entidad_otra' => '  Fundación Huellas ']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Fundación Huellas', Actuacion::remisiones()->sole()->entidad_destino);
    }

    public function test_el_nuevo_profesional_puede_volver_a_remitir_y_el_historial_conserva_ambas(): void
    {
        $tercero = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Lucía Pardo']);

        $this->remitir($this->datosInterna());
        Carbon::setTestNow(Carbon::parse('2026-10-08 09:15:00', Actuacion::ZONA_HORARIA));
        $this->remitir($this->datosInterna(['id_profesional_destino' => $tercero->id_usuario, 'motivo' => 'Cambio de sede.']), como: $this->destino)
            ->assertSessionHasNoErrors();

        $this->assertSame($tercero->id_usuario, $this->caso->fresh()->id_profesional);
        $this->assertSame(2, Actuacion::remisiones()->count());

        $this->actingAs($tercero)->get(route('casos.show', $this->caso))
            ->assertSee('Recibido por remisión')
            ->assertSeeInOrder(['Remisiones', 'Andrés Gómez', 'Lucía Pardo', 'Cambio de sede.', 'Carolina Restrepo', 'Andrés Gómez']);

        $this->actingAs($this->destino)->get(route('casos.show', $this->caso))
            ->assertSee('Remitiste este caso a')
            ->assertSee('Lucía Pardo');
    }

    /**
     * DADO un caso con citas pendientes,
     * CUANDO intento remitirlo sin confirmar su cancelación,
     * ENTONCES no se registra la remisión.
     */
    public function test_con_citas_pendientes_exige_confirmar_la_cancelacion(): void
    {
        $this->crearCita('2026-10-07 09:00:00');

        $this->remitir($this->datosInterna())
            ->assertRedirect(route('casos.show', $this->caso).'#remisiones')
            ->assertSessionHasErrorsIn('remision', ['confirmar_citas']);

        $this->assertCasoSinRemitir();
        $this->assertSame('Pendiente', Actuacion::citas()->sole()->estado_asistencia);
    }

    /**
     * DADO un caso con citas próximas y pasadas sin asistencia,
     * CUANDO confirmo la cancelación y remito,
     * ENTONCES las próximas quedan canceladas, las pasadas como "No asistió" y el horario se libera.
     */
    public function test_al_confirmar_cancela_las_proximas_y_registra_las_pasadas_como_no_asistio(): void
    {
        $proxima = $this->crearCita('2026-10-07 09:00:00');
        $pasada = $this->crearCita('2026-10-05 09:00:00');
        $atendida = $this->crearCita('2026-10-02 09:00:00', 'Asistió');

        $this->remitir($this->datosInterna(['confirmar_citas' => '1']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $mensaje) => str_contains($mensaje, 'Citas próximas canceladas: 1.')
                && str_contains($mensaje, 'Citas pasadas registradas como "No asistió": 1.'));

        $remision = Actuacion::remisiones()->sole();

        $proxima->refresh();
        $this->assertSame('Cancelada', $proxima->estado_asistencia);
        $this->assertSame($remision->id_actuacion, $proxima->id_remision);

        $pasada->refresh();
        $this->assertSame('No asistió', $pasada->estado_asistencia);
        $this->assertSame(Actuacion::MOTIVO_SIN_REGISTRO, $pasada->motivo_inasistencia);
        $this->assertSame($remision->id_actuacion, $pasada->id_remision);

        $atendida->refresh();
        $this->assertSame('Asistió', $atendida->estado_asistencia);
        $this->assertNull($atendida->id_remision);

        $inicio = Actuacion::inicioDeFranja('2026-10-07', '09:00');
        $this->assertFalse(Actuacion::horarioOcupado($this->profesional->id_usuario, $inicio));
        $this->assertNotContains('09:00', Actuacion::disponibilidadDelDia($this->profesional->id_usuario, '2026-10-07')['ocupadas']);

        $this->actingAs($this->destino)->get(route('casos.show', $this->caso))
            ->assertSee('Cancelada por la remisión del caso.')
            ->assertSee(Actuacion::MOTIVO_SIN_REGISTRO);

        $this->actingAs($this->profesional)->get(route('citas.agenda'))
            ->assertSee('Cancelada por remisión');
    }

    /**
     * DADO un caso con tareas pendientes y tareas ya evaluadas,
     * CUANDO lo remito,
     * ENTONCES las pendientes se cancelan solas y el historial muestra si cada una se completó.
     */
    public function test_cancela_automaticamente_las_tareas_pendientes(): void
    {
        $pendiente = $this->crearTarea('Diario de emociones');
        $cumplida = $this->crearTarea('Respiración diafragmática', TareaTerapeutica::ESTADO_CUMPLIDA);
        $noCumplida = $this->crearTarea('Registro de sueño', TareaTerapeutica::ESTADO_NO_CUMPLIDA);

        $this->remitir($this->datosInterna())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $mensaje) => str_contains($mensaje, 'Tareas pendientes canceladas: 1.'));

        $remision = Actuacion::remisiones()->sole();

        $pendiente->refresh();
        $this->assertSame('Cancelada', $pendiente->estado);
        $this->assertSame($remision->id_actuacion, $pendiente->id_remision);
        $this->assertSame('Cumplida', $cumplida->fresh()->estado);
        $this->assertNull($cumplida->fresh()->id_remision);
        $this->assertSame('No cumplida', $noCumplida->fresh()->estado);

        $this->actingAs($this->destino)->get(route('casos.show', $this->caso))
            ->assertSee('Diario de emociones')
            ->assertSee('Cancelada por remisión (no completada)')
            ->assertSee('✓ Cumplida')
            ->assertSee('✕ No cumplida');
    }

    public function test_el_nuevo_profesional_conserva_el_historial_clinico_del_caso(): void
    {
        $this->crearCita('2026-10-02 09:00:00', 'Asistió');
        $this->crearTarea('Respiración diafragmática', TareaTerapeutica::ESTADO_CUMPLIDA);

        $this->remitir($this->datosInterna())->assertSessionHasNoErrors();

        $this->actingAs($this->destino)->get(route('casos.show', $this->caso))
            ->assertSee('2026-10-02')
            ->assertSee('✓ Asistió')
            ->assertSee('Carolina Restrepo')
            ->assertSee('Respiración diafragmática')
            ->assertSee('Ansiedad y crisis académica.');
    }

    public function test_solo_el_profesional_del_caso_puede_remitirlo(): void
    {
        $otro = $this->crearUsuarioConRol('Profesional');

        $this->remitir($this->datosInterna(), como: $otro)->assertForbidden();
        $this->remitir($this->datosInterna(), como: $this->crearUsuarioConRol('Persona'))->assertRedirect(route('estudiante.home'));

        auth()->logout();
        $this->post(route('remisiones.store', $this->caso), $this->datosInterna())->assertRedirect(route('login'));

        $this->assertCasoSinRemitir();
    }

    public function test_no_remite_casos_que_no_estan_activos(): void
    {
        foreach (['Cerrado', 'Pendiente Asignación'] as $estado) {
            $caso = $this->crearCaso($this->profesional, $estado);

            $this->remitir($this->datosInterna(), $caso)
                ->assertRedirect(route('casos.show', $caso).'#remisiones')
                ->assertSessionHas('error', "Solo se pueden remitir casos activos. El caso {$caso->codigo_expediente} está en estado \"{$estado}\".");

            $this->assertSame($this->profesional->id_usuario, $caso->fresh()->id_profesional);
        }

        $this->assertSame(0, Actuacion::remisiones()->count());
    }

    public function test_el_caso_en_seguimiento_tambien_se_puede_remitir(): void
    {
        $this->caso->update(['estado_caso' => 'En Seguimiento']);

        $this->remitir($this->datosInterna())->assertSessionHasNoErrors();

        $this->assertSame('Abierto', $this->caso->fresh()->estado_caso);
    }

    public function test_rechaza_destinos_internos_que_no_son_otro_profesional_activo(): void
    {
        $inactivo = $this->crearUsuarioConRol('Profesional');
        $inactivo->update(['estado_activo' => false]);

        $destinosInvalidos = [
            $this->profesional->id_usuario => 'No puedes remitirte el caso a ti mismo. Elige otro profesional.',
            $this->crearUsuarioConRol('Persona')->id_usuario => 'El profesional seleccionado no existe o no está activo. Elige uno de la lista.',
            $inactivo->id_usuario => 'El profesional seleccionado no existe o no está activo. Elige uno de la lista.',
            (string) Str::uuid() => 'El profesional seleccionado no existe o no está activo. Elige uno de la lista.',
            'no-es-un-id' => 'El profesional seleccionado no existe o no está activo. Elige uno de la lista.',
        ];

        foreach ($destinosInvalidos as $idDestino => $mensaje) {
            $this->remitir($this->datosInterna(['id_profesional_destino' => $idDestino]))
                ->assertSessionHasErrorsIn('remision', ['id_profesional_destino' => $mensaje]);
        }

        $this->assertCasoSinRemitir();
    }

    public function test_valida_los_datos_de_la_remision(): void
    {
        $this->remitir([])
            ->assertRedirect(route('casos.show', $this->caso).'#remisiones')
            ->assertSessionHasErrorsIn('remision', [
                'destino_remision' => 'Indica si remites el caso a otro profesional o a una entidad externa.',
            ]);

        // El traslado a otro profesional no exige justificación (revisión del 06/10/2026).
        $this->remitir($this->datosInterna(['id_profesional_destino' => '', 'motivo' => '   ']))
            ->assertSessionHasErrorsIn('remision', [
                'id_profesional_destino' => 'Selecciona el profesional que recibirá el caso.',
            ])
            ->assertSessionDoesntHaveErrors(['motivo'], null, 'remision');

        // La remisión externa sí: es la justificación del formato F-016.
        $this->remitir($this->datosExterna(['motivo' => '   ']))
            ->assertSessionHasErrorsIn('remision', ['motivo' => 'La justificación de la remisión externa es obligatoria.']);

        $this->remitir($this->datosExterna(['entidad_destino' => 'Policía de otra ciudad']))
            ->assertSessionHasErrorsIn('remision', ['entidad_destino' => 'Selecciona una de las entidades de la lista.']);

        $this->remitir($this->datosExterna(['entidad_destino' => 'Otra', 'entidad_otra' => ' ']))
            ->assertSessionHasErrorsIn('remision', ['entidad_otra' => 'Escribe el nombre de la entidad a la que remites el caso.']);

        $this->remitir($this->datosInterna(['motivo' => str_repeat('a', Actuacion::MOTIVO_REMISION_MAX + 1)]))
            ->assertSessionHasErrorsIn('remision', ['motivo']);

        $this->remitir($this->datosInterna(['destino_remision' => 'Ambos']))
            ->assertSessionHasErrorsIn('remision', ['destino_remision']);

        $this->assertCasoSinRemitir();
    }

    public function test_la_remision_interna_ignora_la_entidad_enviada_por_el_formulario(): void
    {
        $this->remitir($this->datosInterna(['entidad_destino' => 'Otra', 'entidad_otra' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Actuacion::remisiones()->sole()->entidad_destino);
    }

    public function test_despues_de_remitir_solo_el_nuevo_profesional_actua_sobre_el_caso(): void
    {
        $this->remitir($this->datosInterna())->assertSessionHasNoErrors();

        $tarea = ['titulo' => 'Diario de emociones', 'descripcion' => 'Anotar tres emociones al día.'];

        $this->remitir($this->datosExterna())->assertForbidden();
        $this->actingAs($this->profesional)->post(route('tareas.store', $this->caso), $tarea)->assertForbidden();
        $this->actingAs($this->profesional)->post(route('citas.store'), ['id_caso' => $this->caso->id_caso, 'fecha' => '2026-10-07', 'hora' => '09:00'])
            ->assertSessionHasErrors('id_caso');

        $this->actingAs($this->destino)->post(route('tareas.store', $this->caso), $tarea)->assertSessionHas('status');
        $this->actingAs($this->destino)->post(route('citas.store'), ['id_caso' => $this->caso->id_caso, 'fecha' => '2026-10-07', 'hora' => '09:00'])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->destino->id_usuario, Actuacion::citas()->sole()->id_profesional);
    }

    public function test_la_remision_no_es_una_cita_del_caso(): void
    {
        $this->remitir($this->datosInterna())->assertSessionHasNoErrors();

        $this->assertCount(0, $this->caso->fresh()->citas);
        $this->assertSame(0, Actuacion::citas()->count());
    }

    public function test_la_base_exige_el_destino_de_toda_remision(): void
    {
        $this->expectException(QueryException::class);

        Actuacion::create([
            'id_caso' => $this->caso->id_caso,
            'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_REMISION,
            'descripcion' => 'Sin destino.',
        ]);
    }

    public function test_el_formulario_lista_solo_otros_profesionales_activos_y_avisa_lo_pendiente(): void
    {
        $inactivo = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Profesional Inactivo']);
        $inactivo->update(['estado_activo' => false]);
        $this->crearCita('2026-10-07 09:00:00');
        $this->crearTarea('Diario de emociones');

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Registrar remisión')
            ->assertSee('<option value="'.$this->destino->id_usuario.'"', false)
            ->assertDontSee('<option value="'.$this->profesional->id_usuario.'"', false)
            ->assertDontSee('Profesional Inactivo')
            ->assertSee('Cita del 2026-10-07 a las 9:00 am: se cancela.')
            ->assertSee('Tarea "Diario de emociones": se cancela (no completada).', false)
            ->assertSee('Confirmo que se cancelen las citas pendientes para remitir el caso.');
    }

    /**
     * Caso de un estudiante de Ingeniería; $destino atiende Ingeniería y $otro, Salud.
     *
     * @return array{0: Caso, 1: User}
     */
    private function casoDeIngenieria(): array
    {
        $ingenieria = \App\Models\Facultad::where('nombre', 'Facultad de Ingeniería')->sole();
        $salud = \App\Models\Facultad::where('nombre', 'Facultad de Ciencias de la Salud')->sole();
        $otro = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Marta Salud']);

        foreach ([[$this->profesional, $ingenieria], [$this->destino, $ingenieria], [$otro, $salud]] as [$profesional, $facultad]) {
            \App\Models\AsignacionProfesional::create(['id_profesional' => $profesional->id_usuario, 'tipo_servicio' => 'Salud Mental', 'id_facultad' => $facultad->id_facultad, 'jornada' => 'Diurna']);
        }

        $this->caso->solicitud->update(['id_facultad' => $ingenieria->id_facultad, 'jornada' => 'Diurna', 'franja_horaria' => 'Mañana']);

        return [$this->caso->fresh(), $otro];
    }

    /**
     * DADO que el estudiante no quiere a su psicólogo,
     * CUANDO se traslada a otro psicólogo de la misma facultad,
     * ENTONCES no se pide justificación y el anterior lo ve cerrado con su historial.
     */
    public function test_el_traslado_dentro_de_la_facultad_no_pide_justificacion(): void
    {
        [$caso] = $this->casoDeIngenieria();

        $this->remitir($this->datosInterna(['motivo' => '']), $caso)->assertSessionHasNoErrors();

        $remision = Actuacion::remisiones()->sole();
        $this->assertNull($remision->descripcion);
        $this->assertNull($remision->autorizacion_traslado);
        $this->assertSame($this->destino->id_usuario, $caso->fresh()->id_profesional);

        $this->actingAs($this->profesional)->get(route('casos.show', $caso))
            ->assertSee('Remitiste este caso a')
            ->assertDontSee('Motivo:');
    }

    /**
     * DADO un psicólogo de otra facultad,
     * CUANDO se traslada el caso a él,
     * ENTONCES es una excepción: hay que indicar quién la autorizó, y queda registrado.
     */
    public function test_el_traslado_a_otra_facultad_exige_la_autorizacion(): void
    {
        [$caso, $otro] = $this->casoDeIngenieria();

        $this->actingAs($this->profesional)->get(route('casos.show', $caso))
            ->assertSee('<optgroup label="De la facultad del estudiante">', false)
            ->assertSee('<optgroup label="De otra facultad (requiere autorización)">', false)
            ->assertSee('¿Quién autorizó el traslado a otra facultad?');

        $this->remitir($this->datosInterna(['id_profesional_destino' => $otro->id_usuario, 'motivo' => '']), $caso)
            ->assertSessionHasErrorsIn('remision', ['autorizacion_traslado' => 'Ese profesional no atiende la facultad del estudiante: indica quién autorizó el traslado a otra facultad.']);
        $this->assertSame(0, Actuacion::remisiones()->count());

        $this->remitir($this->datosInterna(['id_profesional_destino' => $otro->id_usuario, 'motivo' => '', 'autorizacion_traslado' => ' Coordinación de Bienestar ']), $caso)
            ->assertSessionHasNoErrors();

        $this->assertSame('Coordinación de Bienestar', Actuacion::remisiones()->sole()->autorizacion_traslado);
        $this->assertSame($otro->id_usuario, $caso->fresh()->id_profesional);

        $this->actingAs($otro)->get(route('casos.show', $caso))
            ->assertSee('Traslado a otra facultad autorizado por: Coordinación de Bienestar');
    }

    public function test_sin_facultad_registrada_el_traslado_no_tiene_restriccion(): void
    {
        $this->assertNull($this->caso->idsProfesionalesMismaFacultad());

        $this->remitir($this->datosInterna(['motivo' => '']))->assertSessionHasNoErrors();
        $this->assertNull(Actuacion::remisiones()->sole()->autorizacion_traslado);
    }
}

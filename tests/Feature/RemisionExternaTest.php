<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\FormatoInstitucional;
use App\Models\RemisionExterna;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * RQ-2: remisión externa con categoría URGENTE/PRIORITARIO, acompañante y copia de los
 * datos del estudiante y de la atención para el formato GBU-GPGR-F-016.
 */
class RemisionExternaTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        // Jueves 8 de octubre de 2026, 10:00 am en Colombia.
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Camila Muñoz']);

        $estudiante = $this->crearUsuarioConRol('Persona', [
            'nombre_completo' => 'Samuel Valencia', 'numero_documento' => '1112223334',
            'tipo_documento' => 'CC', 'sexo' => 'M', 'fecha_nacimiento' => '2005-10-07',
        ]);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad.']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);
        $this->crearConsentimientoVigente($this->caso);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function cita(string $fechaHora, string $asistencia): Actuacion
    {
        return Actuacion::create([
            'id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA, 'fecha_hora' => $fechaHora, 'estado_asistencia' => $asistencia,
        ]);
    }

    private function acompanante(array $cambios = []): array
    {
        return array_merge([
            'acompanante_nombre' => ' María Pérez ', 'acompanante_parentesco' => 'Madre', 'acompanante_tipo_documento' => 'CC',
            'acompanante_numero_documento' => '29876543', 'acompanante_telefono' => '300 123 4567',
        ], $cambios);
    }

    private function remitir(array $datos)
    {
        return $this->actingAs($this->profesional)->from(route('casos.show', $this->caso))->post(route('remisiones.store', $this->caso), array_merge([
            'destino_remision' => 'Externa',
            'entidad_destino' => 'Servicio de urgencias',
            'categoria' => 'URGENTE',
            'motivo' => 'Ideación suicida con plan: requiere valoración inmediata.',
        ], $datos));
    }

    private function assertNadaGuardado(): void
    {
        $this->assertSame(0, Actuacion::remisiones()->count());
        $this->assertSame(0, RemisionExterna::count());
        $this->assertSame('Abierto', $this->caso->fresh()->estado_caso);
    }

    /**
     * DADO un estudiante que debe ir a urgencias,
     * CUANDO el psicólogo registra la remisión URGENTE con los datos del acompañante,
     * ENTONCES queda la copia de los datos del formato y el caso se cierra.
     */
    public function test_registra_la_remision_urgente_con_acompanante_y_copia_de_datos(): void
    {
        $this->cita('2026-09-24 09:00:00', 'Asistió');
        $ultima = $this->cita('2026-10-01 14:00:00', 'Asistió');

        $this->remitir($this->acompanante())->assertSessionHasNoErrors()
            ->assertRedirect(route('casos.show', $this->caso).'#remisiones');

        $detalle = RemisionExterna::sole();
        $remision = Actuacion::remisiones()->sole();
        $this->assertSame($remision->id_actuacion, $detalle->id_actuacion);
        $this->assertSame('URGENTE', $detalle->categoria);
        $this->assertSame(
            ['Samuel Valencia', 'CC', '1112223334', 'M', '2005-10-07'],
            [$detalle->estudiante_nombre, $detalle->estudiante_tipo_documento, $detalle->estudiante_numero_documento, $detalle->estudiante_sexo, $detalle->estudiante_fecha_nacimiento->toDateString()],
        );
        $this->assertSame($ultima->id_actuacion, $detalle->id_cita);
        $this->assertSame('2026-10-01 14:00:00', $detalle->fecha_hora_atencion->toDateTimeString());
        $this->assertSame(20, $detalle->estudiante_edad, 'La edad es a la fecha de la atención, no a la de hoy (RN-11)');
        $this->assertSame('Camila Muñoz', $detalle->profesional_nombre);
        $this->assertSame(
            ['María Pérez', 'Madre', 'CC', '29876543', '300 123 4567'],
            [$detalle->acompanante_nombre, $detalle->acompanante_parentesco, $detalle->acompanante_tipo_documento, $detalle->acompanante_numero_documento, $detalle->acompanante_telefono],
        );
        $this->assertSame(['GBU-GPGR-F-016', '3', '2024-04-18'], [$detalle->formato_codigo, $detalle->formato_version, $detalle->formato_fecha->toDateString()]);
        $this->assertSame('Servicio de urgencias', $remision->entidad_destino);
        $this->assertSame('Cerrado', $this->caso->fresh()->estado_caso);

        $auditoria = AuditoriaExpediente::where('accion', 'remision_externa_registrada')->sole();
        $this->assertSame(['id_actuacion' => $remision->id_actuacion, 'categoria' => 'URGENTE'], $auditoria->detalle);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('URGENTE')
            ->assertSee('Acompañante: María Pérez (Madre)');
    }

    public function test_la_urgente_exige_los_datos_del_acompanante(): void
    {
        $this->remitir($this->acompanante(['acompanante_nombre' => '', 'acompanante_tipo_documento' => 'NIT', 'acompanante_telefono' => 'llamar']))
            ->assertSessionHasErrorsIn('remision', [
                'acompanante_nombre' => 'En una remisión urgente escribe el nombre de la persona responsable del estudiante.',
                'acompanante_tipo_documento',
                'acompanante_telefono' => 'Escribe un número de contacto válido (entre 7 y 20 dígitos).',
            ]);

        $this->remitir([])->assertSessionHasErrorsIn('remision', ['acompanante_nombre', 'acompanante_parentesco', 'acompanante_tipo_documento', 'acompanante_numero_documento', 'acompanante_telefono']);

        $this->assertNadaGuardado();
    }

    /**
     * DADO una remisión PRIORITARIA,
     * CUANDO llegan datos de acompañante en la petición,
     * ENTONCES se descartan y la atención es la fecha de la remisión si no hubo citas (D-10).
     */
    public function test_la_prioritaria_no_guarda_acompanante_y_sin_citas_usa_la_fecha_de_la_remision(): void
    {
        $this->remitir(['categoria' => 'PRIORITARIO', 'entidad_destino' => 'EPS / IPS de salud'] + $this->acompanante())->assertSessionHasNoErrors();

        $detalle = RemisionExterna::sole();
        $this->assertSame('PRIORITARIO', $detalle->categoria);
        $this->assertNull($detalle->acompanante_nombre);
        $this->assertNull($detalle->acompanante_telefono);
        $this->assertNull($detalle->id_cita);
        $this->assertSame('2026-10-08 10:00:00', $detalle->fecha_hora_atencion->toDateTimeString());
        $this->assertSame(21, $detalle->estudiante_edad);
    }

    public function test_la_categoria_es_obligatoria_en_las_externas_y_no_aplica_a_las_internas(): void
    {
        $this->remitir(['categoria' => null])->assertSessionHasErrorsIn('remision', ['categoria' => 'Indica si la remisión es URGENTE (va a urgencias) o PRIORITARIA (pide cita en su EPS).']);
        $this->remitir(['categoria' => 'NORMAL'])->assertSessionHasErrorsIn('remision', ['categoria']);
        $this->assertNadaGuardado();

        $destino = $this->crearUsuarioConRol('Profesional');
        $this->remitir(['destino_remision' => 'Interna', 'id_profesional_destino' => $destino->id_usuario, 'categoria' => 'URGENTE'] + $this->acompanante(['acompanante_nombre' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Actuacion::remisiones()->count());
        $this->assertSame(0, RemisionExterna::count(), 'La remisión interna no lleva formato (D-04)');
    }

    public function test_sin_los_datos_del_estudiante_no_se_remite_a_una_entidad_externa(): void
    {
        $this->caso->solicitud->persona->update(['fecha_nacimiento' => null]);

        $this->remitir($this->acompanante())
            ->assertSessionHasErrorsIn('remision', ['datos_estudiante' => 'Completa el tipo de documento, el sexo y la fecha de nacimiento del estudiante antes de remitirlo a una entidad externa.']);

        $this->assertNadaGuardado();

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('Faltan el tipo de documento, el sexo o la fecha de nacimiento del estudiante.');
    }

    /**
     * DADO que las remisiones por violencia las tramita la abogada (RN-12),
     * CUANDO se intenta remitir a ICBF, Fiscalía o Comisaría,
     * ENTONCES el sistema lo rechaza, también si se escribe en "Otra".
     */
    public function test_no_se_remite_a_fiscalia_icbf_ni_comisaria(): void
    {
        foreach (['ICBF', 'Fiscalía', 'Comisaría de Familia'] as $entidad) {
            $this->remitir(['entidad_destino' => $entidad] + $this->acompanante())
                ->assertSessionHasErrorsIn('remision', ['entidad_destino' => 'Selecciona una de las entidades de la lista.']);
        }

        foreach (['Fiscalía General de la Nación', 'ICBF regional Valle', 'comisaria de familia de Tuluá'] as $otra) {
            $this->remitir(['entidad_destino' => 'Otra', 'entidad_otra' => $otra] + $this->acompanante())
                ->assertSessionHasErrorsIn('remision', ['entidad_otra' => 'Las remisiones a Comisaría, Fiscalía o ICBF las tramita la abogada con los formatos de esas entidades; no se registran aquí.']);
        }

        $this->assertNadaGuardado();

        $this->remitir(['entidad_destino' => 'Otra', 'entidad_otra' => 'Clínica San Francisco'] + $this->acompanante())->assertSessionHasNoErrors();
        $this->assertSame('Clínica San Francisco', Actuacion::remisiones()->sole()->entidad_destino);
    }

    public function test_cambiar_el_formato_no_altera_las_remisiones_registradas(): void
    {
        $this->remitir($this->acompanante())->assertSessionHasNoErrors();

        FormatoInstitucional::deClave('REMISION_PSICOLOGICA')->update(['version' => '4', 'fecha_formato' => '2026-09-01']);

        $this->assertSame('3', RemisionExterna::sole()->formato_version);
        $this->assertSame('2024-04-18', RemisionExterna::sole()->formato_fecha->toDateString());
    }

    public function test_la_justificacion_admite_texto_largo(): void
    {
        $this->remitir(['motivo' => str_repeat('Justificación detallada. ', 120)] + $this->acompanante())->assertSessionHasNoErrors();
        $this->assertGreaterThan(1000, mb_strlen(Actuacion::remisiones()->sole()->descripcion));

        $this->assertSame(Actuacion::MOTIVO_REMISION_MAX, 3000);
    }

    public function test_el_formulario_muestra_la_categoria_los_datos_del_formato_y_el_acompanante(): void
    {
        $this->cita('2026-10-01 14:00:00', 'Asistió');

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('name="categoria" value="URGENTE"', false)
            ->assertSee('name="categoria" value="PRIORITARIO"', false)
            ->assertSee('Samuel Valencia · CC 1112223334 · Sexo M')
            ->assertSee('Nació el 07-10-05 (20 años)')
            ->assertSee('Atención: 01-10-26 a las 2:00 pm')
            ->assertSee('(última cita a la que asistió)')
            ->assertSee('Datos del acompañante (remisión por urgencias)')
            ->assertSee('Servicio de urgencias')
            ->assertDontSee('<option value="ICBF"', false);
    }
}

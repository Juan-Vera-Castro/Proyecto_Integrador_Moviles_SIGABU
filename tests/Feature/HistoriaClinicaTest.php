<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\HistoriaClinica;
use App\Models\HistoriaClinicaAnotacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Historia clínica psicológica del caso de Salud Mental.
 */
class HistoriaClinicaTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private const DATOS = [
        'motivo_consulta' => '  Ansiedad ante los parciales. ',
        'historia_problema' => 'Inicia hace dos meses, con insomnio.',
        'impresion_diagnostica' => 'Síntomas ansiosos asociados a estrés académico.',
        'plan_intervencion' => 'Seis sesiones de terapia cognitivo-conductual.',
    ];

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-19 09:15:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional');
        $this->caso = $this->crearCaso();
        $this->crearConsentimientoVigente($this->caso);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCaso(string $servicio = 'Salud Mental', string $estado = 'Abierto'): Caso
    {
        $estudiante = $this->crearUsuarioConRol('Persona');
        $solicitud = $estudiante->persona->solicitudes()->create([
            'tipo_servicio' => $servicio,
            'descripcion' => 'Ansiedad y crisis académica.',
        ]);

        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => $estado, 'id_profesional' => $this->profesional->id_usuario]);

        return $caso->fresh();
    }

    private function guardar(array $datos = self::DATOS, ?Caso $caso = null, ?User $como = null)
    {
        $caso ??= $this->caso;

        return $this->actingAs($como ?? $this->profesional)
            ->from(route('casos.show', $caso))
            ->post(route('historia-clinica.store', $caso), $datos);
    }

    private function anotar(array $datos, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->from(route('casos.show', $this->caso))
            ->post(route('historia-clinica.anotar', $this->caso), $datos);
    }

    /**
     * DADO un caso de Salud Mental con consentimiento,
     * CUANDO registro la historia clínica,
     * ENTONCES queda asociada al expediente.
     */
    public function test_registra_la_historia_clinica_asociada_al_expediente(): void
    {
        $this->guardar()
            ->assertRedirect(route('casos.show', $this->caso).'#historia-clinica')
            ->assertSessionHas('status', "Historia clínica registrada en el expediente {$this->caso->codigo_expediente}.");

        $historia = HistoriaClinica::sole();
        $this->assertSame($this->caso->id_caso, $historia->id_caso);
        $this->assertSame($this->profesional->id_usuario, $historia->id_profesional);
        $this->assertSame('Ansiedad ante los parciales.', $historia->motivo_consulta);
        $this->assertNull($historia->examen_mental);
        $this->assertSame('2026-10-19 09:15', $historia->fecha_registro->format('Y-m-d H:i'));
        $this->assertTrue($this->caso->historiaClinica->is($historia));

        $auditoria = AuditoriaExpediente::sole();
        $this->assertSame(AuditoriaExpediente::HISTORIA_CLINICA_REGISTRADA, $auditoria->accion);
        $this->assertSame($this->caso->id_caso, $auditoria->id_caso);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Historia clínica psicológica')
            ->assertSee('Síntomas ansiosos asociados a estrés académico.')
            ->assertSee('Registrada el 2026-10-19 09:15')
            ->assertSee('+ Agregar anotación')
            ->assertDontSee('action="'.route('historia-clinica.store', $this->caso).'"', false)
            ->assertDontSee('Este expediente aún no tiene historia clínica registrada.');
    }

    public function test_motivo_impresion_y_plan_son_obligatorios(): void
    {
        $this->guardar(['motivo_consulta' => '   ', 'historia_problema' => 'Algo'])
            ->assertSessionHasErrorsIn('historia', [
                'motivo_consulta' => 'El apartado "Motivo de consulta" es obligatorio.',
                'impresion_diagnostica' => 'El apartado "Impresión diagnóstica" es obligatorio.',
                'plan_intervencion' => 'El apartado "Plan de intervención" es obligatorio.',
            ]);

        $this->assertSame(0, HistoriaClinica::count());
    }

    public function test_valida_el_largo_de_cada_apartado(): void
    {
        $this->guardar(['observaciones' => str_repeat('a', HistoriaClinica::TEXTO_MAX + 1)] + self::DATOS)
            ->assertSessionHasErrorsIn('historia', [
                'observaciones' => 'El apartado "Observaciones" no puede superar los 5000 caracteres.',
            ]);

        $this->assertSame(0, HistoriaClinica::count());
    }

    public function test_sin_consentimiento_aceptado_no_se_registra(): void
    {
        $caso = $this->crearCaso();

        $this->guardar(caso: $caso)
            ->assertSessionHas('error', 'Falta el consentimiento informado aceptado: el estudiante debe firmarlo desde su cuenta antes de registrar la historia clínica.');

        $this->assertSame(0, HistoriaClinica::count());

        $this->actingAs($this->profesional)->get(route('casos.show', $caso))
            ->assertSee('Falta el consentimiento informado aceptado')
            ->assertDontSee(route('historia-clinica.store', $caso), false);
    }

    public function test_un_rechazo_posterior_impide_registrarla(): void
    {
        ConsentimientoAceptacion::create([
            'id_caso' => $this->caso->id_caso, 'id_version' => $this->caso->aceptacionesConsentimiento->first()->id_version,
            'decision' => 'Rechazado', 'fecha_hora' => now(Actuacion::ZONA_HORARIA), 'id_profesional' => $this->profesional->id_usuario,
            'texto_presentado' => 'Texto', 'texto_sha256' => hash('sha256', 'Texto'),
        ]);

        $this->guardar()
            ->assertSessionHas('error', 'El estudiante no aceptó el consentimiento informado el 2026-10-19 09:15: no se puede registrar la historia clínica.');

        $this->assertSame(0, HistoriaClinica::count());
    }

    public function test_solo_en_casos_de_salud_mental(): void
    {
        $inclusion = $this->crearCaso('Inclusión');
        $this->crearConsentimientoVigente($inclusion);

        $this->guardar(caso: $inclusion)
            ->assertSessionHas('error', 'La historia clínica psicológica solo se registra en casos de Salud Mental.');

        $this->assertSame(0, HistoriaClinica::count());

        $this->actingAs($this->profesional)->get(route('casos.show', $inclusion))
            ->assertOk()
            ->assertDontSee('Historia clínica psicológica');
    }

    public function test_solo_en_casos_activos(): void
    {
        $cerrado = $this->crearCaso(estado: 'Cerrado');
        $this->crearConsentimientoVigente($cerrado);

        $this->guardar(caso: $cerrado)
            ->assertSessionHas('error', "La historia clínica solo se registra en casos activos. El caso {$cerrado->codigo_expediente} está en estado \"Cerrado\".");

        $this->assertSame(0, HistoriaClinica::count());
    }

    public function test_otro_profesional_no_la_registra_ni_la_consulta(): void
    {
        $otro = $this->crearUsuarioConRol('Profesional');

        $this->guardar(como: $otro)->assertForbidden();
        $this->assertSame(0, HistoriaClinica::count());

        $this->guardar();

        $this->actingAs($otro)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Este caso no está asignado a ti.')
            ->assertDontSee('Síntomas ansiosos asociados a estrés académico.');
    }

    public function test_quien_remitio_el_caso_la_ve_hasta_su_remision(): void
    {
        $this->guardar();

        $destino = $this->crearUsuarioConRol('Profesional');
        $this->actingAs($this->profesional)->post(route('remisiones.store', $this->caso), [
            'destino_remision' => 'Interna', 'id_profesional_destino' => $destino->id_usuario, 'motivo' => 'Cambio de profesional.',
        ])->assertSessionHasNoErrors();
        $this->assertSame($destino->id_usuario, $this->caso->fresh()->id_profesional);

        // Conserva la historia hasta su remisión, pero no lo que el nuevo profesional anote después
        Carbon::setTestNow(now()->addDay());
        $this->actingAs($destino)->post(route('historia-clinica.anotar', $this->caso), ['texto' => 'Nota del nuevo profesional.'])->assertSessionHasNoErrors();

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertOk()
            ->assertSee('Historia clínica psicológica hasta tu remisión')
            ->assertSee('Síntomas ansiosos asociados a estrés académico.')
            ->assertDontSee('Nota del nuevo profesional.')
            ->assertDontSee('+ Agregar anotación');

        $this->actingAs($destino)->get(route('casos.show', $this->caso))
            ->assertSee('Síntomas ansiosos asociados a estrés académico.')
            ->assertSee('+ Agregar anotación');
    }

    public function test_una_vez_registrada_no_se_vuelve_a_registrar_ni_se_modifica(): void
    {
        $this->guardar();

        $this->guardar(['motivo_consulta' => 'Otro motivo'] + self::DATOS)
            ->assertSessionHas('error', 'El expediente ya tiene historia clínica: no se modifica. Agrega una anotación para corregirla o complementarla.');

        $historia = HistoriaClinica::sole();
        $this->assertSame('Ansiedad ante los parciales.', $historia->motivo_consulta);
        $this->assertSame(1, AuditoriaExpediente::count());

        $this->expectException(LogicException::class);
        $historia->update(['motivo_consulta' => 'Otro motivo']);
    }

    /**
     * Res. 1995 de 1999: la corrección queda como anotación con fecha y autor.
     */
    public function test_agrega_anotaciones_con_fecha_y_autor(): void
    {
        $this->guardar();

        Carbon::setTestNow(Carbon::parse('2026-10-20 15:00:00', Actuacion::ZONA_HORARIA));

        $this->anotar(['apartado' => 'examen_mental', 'texto' => '  Orientado, afecto ansioso. '])
            ->assertRedirect(route('casos.show', $this->caso).'#historia-clinica')
            ->assertSessionHas('status', 'Anotación agregada a la historia clínica.');
        $this->anotar(['apartado' => '', 'texto' => 'El estudiante aporta valoración previa de su EPS.']);

        [$primera, $segunda] = HistoriaClinicaAnotacion::orderBy('fecha_hora')->orderBy('apartado', 'desc')->get()->all();
        $this->assertSame(HistoriaClinica::sole()->id_historia, $primera->id_historia);
        $this->assertSame('examen_mental', $primera->apartado);
        $this->assertSame('Orientado, afecto ansioso.', $primera->texto);
        $this->assertSame($this->profesional->id_usuario, $primera->id_profesional);
        $this->assertSame('2026-10-20 15:00', $primera->fecha_hora->format('Y-m-d H:i'));
        $this->assertNull($segunda->apartado);

        $auditoria = AuditoriaExpediente::where('accion', AuditoriaExpediente::HISTORIA_CLINICA_ANOTADA)->orderBy('id_auditoria')->get();
        $this->assertSame([['apartado' => 'examen_mental'], ['apartado' => null]], $auditoria->pluck('detalle')->all());

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('2026-10-20 15:00')
            ->assertSee('Examen mental')
            ->assertSee('Orientado, afecto ansioso.')
            ->assertSee('Historia en general');

        $this->expectException(LogicException::class);
        $primera->delete();
    }

    public function test_valida_la_anotacion(): void
    {
        $this->guardar();

        $this->anotar(['apartado' => 'inventado', 'texto' => '   '])
            ->assertSessionHasErrorsIn('anotacion', [
                'apartado' => 'Selecciona un apartado de la historia clínica.',
                'texto' => 'Escribe la anotación.',
            ]);

        $this->assertSame(0, HistoriaClinicaAnotacion::count());
    }

    public function test_sin_historia_no_hay_anotaciones_y_otro_profesional_no_anota(): void
    {
        $this->anotar(['texto' => 'Algo'])->assertNotFound();

        $this->guardar();
        $this->anotar(['texto' => 'Algo'], $this->crearUsuarioConRol('Profesional'))->assertForbidden();

        $this->assertSame(0, HistoriaClinicaAnotacion::count());
    }

    public function test_los_listados_indican_que_casos_tienen_historia_clinica(): void
    {
        $sinHistoria = $this->crearCaso();
        $this->guardar();

        foreach (['profesional.expedientes', 'profesional.salud-mental'] as $listado) {
            $html = $this->actingAs($this->profesional)->get(route($listado))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '✓ Historia clínica'), $listado);
            $this->assertSame(1, substr_count($html, 'Sin historia clínica'), $listado);
        }

        // Quien remitió el caso ya no consulta la historia: tampoco ve la etiqueta.
        $destino = $this->crearUsuarioConRol('Profesional');
        $this->caso->update(['id_profesional' => $destino->id_usuario]);
        $sinHistoria->update(['estado_caso' => 'Cerrado']);

        $this->actingAs($this->profesional)->get(route('profesional.expedientes'))
            ->assertDontSee('✓ Historia clínica')
            ->assertDontSee('Sin historia clínica');
    }
}

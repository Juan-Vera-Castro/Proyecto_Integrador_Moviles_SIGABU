<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * D-01 y D-20: el psicólogo del caso completa el tipo de documento, el sexo y la fecha de
 * nacimiento del estudiante, que piden el consentimiento y el formato de remisión.
 */
class DatosEstudianteTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional');

        $estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Samuel Valencia']);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad.']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function guardar(array $datos, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->profesional)
            ->from(route('casos.show', $this->caso))
            ->patch(route('estudiante.datos.update', $this->caso), $datos);
    }

    /**
     * DADO un estudiante sin datos de identificación completos,
     * CUANDO el psicólogo del caso los registra,
     * ENTONCES quedan en su registro y en la auditoría.
     */
    public function test_el_psicologo_del_caso_completa_los_datos(): void
    {
        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('Completar datos del estudiante')
            ->assertSee('Sin registrar');

        $this->guardar(['tipo_documento' => 'CC', 'sexo' => 'M', 'fecha_nacimiento' => '2005-03-10'])
            ->assertRedirect(route('casos.show', $this->caso).'#estudiante')
            ->assertSessionHas('status', 'Datos del estudiante guardados.');

        $persona = $this->caso->solicitud->persona->fresh();
        $this->assertSame(['CC', 'M', '2005-03-10'], [$persona->tipo_documento, $persona->sexo, $persona->fecha_nacimiento->toDateString()]);

        $auditoria = AuditoriaExpediente::sole();
        $this->assertSame('datos_estudiante_actualizados', $auditoria->accion);
        $this->assertEqualsCanonicalizing(['tipo_documento', 'sexo', 'fecha_nacimiento'], $auditoria->detalle['campos']);

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee('2005-03-10 · 21 años')
            ->assertSee('Corregir datos del estudiante');
    }

    /**
     * El profesional ya no toma la firma: siempre vuelve al expediente, aunque se pida otra cosa.
     */
    public function test_siempre_vuelve_al_expediente(): void
    {
        $this->guardar(['tipo_documento' => 'TI', 'sexo' => 'F', 'fecha_nacimiento' => '2010-01-15', 'volver' => 'firma'])
            ->assertRedirect(route('casos.show', $this->caso).'#estudiante');
    }

    public function test_sin_cambios_no_se_audita(): void
    {
        $datos = ['tipo_documento' => 'CC', 'sexo' => 'M', 'fecha_nacimiento' => '2005-03-10'];

        $this->guardar($datos);
        $this->guardar($datos)->assertSessionHasNoErrors();

        $this->assertSame(1, AuditoriaExpediente::count());
    }

    public function test_valida_los_datos(): void
    {
        $this->guardar(['tipo_documento' => 'PA', 'sexo' => 'X', 'fecha_nacimiento' => '2026-10-09'])
            ->assertSessionHasErrorsIn('estudiante', [
                'tipo_documento' => 'Selecciona el tipo de documento (TI, CC o CE).',
                'sexo' => 'Selecciona el sexo (F o M).',
                'fecha_nacimiento' => 'La fecha de nacimiento no puede ser posterior a hoy.',
            ]);

        $this->guardar(['fecha_nacimiento' => '10/03/2005'])
            ->assertSessionHasErrorsIn('estudiante', ['tipo_documento', 'sexo', 'fecha_nacimiento' => 'Escribe una fecha válida, por ejemplo 2005-03-10.']);

        $this->assertNull($this->caso->solicitud->persona->fresh()->fecha_nacimiento);
        $this->assertSame(0, AuditoriaExpediente::count());
    }

    public function test_solo_el_psicologo_del_caso_puede_cambiarlos(): void
    {
        $otro = $this->crearUsuarioConRol('Profesional');
        $datos = ['tipo_documento' => 'CC', 'sexo' => 'M', 'fecha_nacimiento' => '2005-03-10'];

        $this->guardar($datos, $otro)->assertForbidden();
        $this->guardar($datos, $this->caso->solicitud->persona->usuario)->assertRedirect(route('estudiante.home'));

        $this->actingAs($otro)->get(route('casos.show', $this->caso))->assertDontSee('Completar datos del estudiante');
        $this->assertNull($this->caso->solicitud->persona->fresh()->tipo_documento);
    }
}

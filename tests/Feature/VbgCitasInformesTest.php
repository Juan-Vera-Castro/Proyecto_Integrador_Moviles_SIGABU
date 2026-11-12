<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\RepositorioDocumento;
use App\Models\User;
use App\Models\Vbg\Audio;
use App\Models\Vbg\Auditoria;
use App\Models\Vbg\Cita;
use App\Models\Vbg\ConsentimientoCita;
use App\Models\Vbg\Informe;
use App\Models\Vbg\Reporte;
use App\Models\Vbg\Transcripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\Concerns\GeneraFirmaPng;
use Tests\TestCase;

/**
 * Citas, consentimiento por cita, audios, transcripciones e informes de VBG, integrados desde
 * la rama VIOLENCIA_GENERO en su propia base (conexión "vbg"), con acceso por el login de SIGABU.
 */
class VbgCitasInformesTest extends TestCase
{
    use CreaEsquemaSigabu, GeneraFirmaPng, RefreshDatabase;

    private User $vbg;

    private User $otraVbg;

    private User $estudiante;

    private Reporte $reporte;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', Actuacion::ZONA_HORARIA));
        Storage::fake('vbg');
        config(['sigabu.vbg.ia.transcripcion_comando' => null]);

        $this->crearEsquemaSigabu();
        $this->estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Sofía Ríos', 'numero_documento' => '1112223334', 'fecha_nacimiento' => '2002-05-10']);
        $this->vbg = $this->crearUsuarioConRol('VBG', ['nombre_completo' => 'Valentina López']);
        $this->otraVbg = $this->crearUsuarioConRol('VBG', ['nombre_completo' => 'Andrea Gómez']);

        $this->reporte = Reporte::registrar([
            'tipo_reportante' => Reporte::AFECTADA,
            'id_persona_reporta' => $this->estudiante->persona->id_persona,
            'relato' => 'Relato de prueba.',
            'tipo_violencia' => 'Psicológica',
            'acepta_datos' => true,
            'estado' => Reporte::EN_ATENCION,
            'nivel_riesgo' => 'Medio',
            'id_profesional' => $this->vbg->id_usuario,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function firma(bool $trazo = true): string
    {
        return 'data:image/png;base64,'.base64_encode($this->firmaPng($trazo));
    }

    private function agendar(array $cambios = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->vbg)->from(route('vbg.reportes.show', $this->reporte))
            ->post(route('vbg.citas.store', $this->reporte), array_merge([
                'fecha' => '2026-10-21',
                'hora_inicio' => '08:00',
                'hora_fin' => '09:00',
                'modalidad' => 'Presencial',
                'lugar' => 'Bloque B - Oficina 204',
                'motivo' => 'Orientación',
            ], $cambios));
    }

    private function cita(): Cita
    {
        $this->agendar()->assertSessionHasNoErrors();

        return Cita::sole();
    }

    private function consentir(Cita $cita, array $cambios = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->vbg)->postJson(route('vbg.citas.consentimiento.store', $cita), array_merge([
            'decision' => 'Aceptado',
            'firmante_nombre' => 'Sofía Ríos',
            'firmante_documento' => '1112223334',
            'firma' => $this->firma(),
        ], $cambios));
    }

    private function citaConConsentimiento(): Cita
    {
        $cita = $this->cita();
        $this->consentir($cita)->assertOk();

        return $cita->fresh();
    }

    private function subirAudio(Cita $cita, ?UploadedFile $archivo = null, array $cambios = [])
    {
        return $this->actingAs($this->vbg)->from(route('vbg.reportes.show', $this->reporte))
            ->post(route('vbg.audios.store', $cita), array_merge([
                'audio' => $archivo ?? UploadedFile::fake()->create('sesion.mp3', 500, 'audio/mpeg'),
                'duracion_segundos' => 1800,
            ], $cambios));
    }

    /**
     * Las tablas nuevas están en la base propia de VBG, no en la principal.
     */
    public function test_los_datos_nuevos_viven_en_una_base_separada(): void
    {
        foreach (['vbg_citas', 'vbg_consentimientos_cita', 'vbg_audios', 'vbg_transcripciones', 'vbg_informes', 'vbg_auditoria'] as $tabla) {
            $this->assertTrue(Schema::connection('vbg')->hasTable($tabla), "Falta $tabla en la base VBG");
            $this->assertFalse(Schema::hasTable($tabla), "$tabla no debe estar en la base principal");
        }

        $this->cita();
        $this->assertSame(1, DB::connection('vbg')->table('vbg_citas')->count());
        $this->assertSame(1, Auditoria::where('accion', Auditoria::CITA_AGENDADA)->count());
    }

    public function test_el_profesional_a_cargo_agenda_una_cita(): void
    {
        $this->agendar()->assertRedirect()->assertSessionHas('status');

        $cita = Cita::sole();
        $this->assertSame([$this->reporte->id_reporte, $this->vbg->id_usuario, 'Programada', '08:00 – 09:00'], [$cita->id_reporte, $cita->id_profesional, $cita->estado, $cita->horario()]);

        $this->actingAs($this->vbg)->get(route('vbg.reportes.show', $this->reporte))
            ->assertOk()
            ->assertSee('2026-10-21 · 08:00 – 09:00')
            ->assertSee('Falta el consentimiento informado de esta cita.')
            ->assertSee(route('vbg.citas.consentimiento', $cita), false);
    }

    public function test_la_cita_valida_fechas_horas_y_cruces(): void
    {
        $this->agendar(['fecha' => '2026-10-19'])->assertSessionHasErrorsIn('cita', ['fecha' => 'La cita no puede quedar en una fecha pasada.']);
        $this->agendar(['hora_fin' => '07:30'])->assertSessionHasErrorsIn('cita', ['hora_fin']);
        $this->agendar(['modalidad' => 'Telefónica'])->assertSessionHasErrorsIn('cita', ['modalidad']);

        $this->agendar()->assertSessionHasNoErrors();
        $this->agendar(['hora_inicio' => '08:30', 'hora_fin' => '09:30'])->assertSessionHasErrorsIn('cita', ['hora_inicio' => 'Ya tienes otra cita en ese horario.']);
        $this->agendar(['hora_inicio' => '09:00', 'hora_fin' => '10:00'])->assertSessionHasNoErrors();

        $this->assertSame(2, Cita::count());
    }

    public function test_solo_el_profesional_a_cargo_y_con_el_caso_en_atencion(): void
    {
        $this->agendar([], $this->otraVbg)->assertForbidden();
        $this->actingAs($this->estudiante)->post(route('vbg.citas.store', $this->reporte), [])->assertRedirect();
        $this->assertSame(0, Cita::count());

        $this->reporte->update(['estado' => Reporte::CERRADO]);
        $this->agendar()->assertSessionHasErrors(['reporte']);
        $this->assertSame(0, Cita::count());
    }

    public function test_la_cita_solo_se_inicia_con_el_consentimiento_aceptado(): void
    {
        $cita = $this->cita();
        $cambiar = fn (string $estado) => $this->actingAs($this->vbg)->patch(route('vbg.citas.estado', $cita), ['estado' => $estado]);

        $cambiar('En curso')->assertSessionHasErrors(['reporte' => 'Antes de iniciar la cita, registra el consentimiento informado aceptado y firmado.']);
        $cambiar('Completada')->assertSessionHasErrors(['reporte']);

        $this->consentir($cita)->assertOk();
        $cambiar('En curso')->assertSessionHasNoErrors();
        $cambiar('Completada')->assertSessionHasNoErrors();
        $cambiar('Cancelada')->assertSessionHasErrors(['reporte']);

        $this->assertSame('Completada', $cita->fresh()->estado);
        $this->assertSame(2, Auditoria::where('accion', Auditoria::CITA_ESTADO)->count());
    }

    /**
     * DADO una cita, CUANDO la persona atendida acepta y firma con el dedo,
     * ENTONCES se guardan la firma en el bucket privado de VBG, su huella y el texto presentado.
     */
    public function test_el_consentimiento_de_la_cita_se_firma_y_se_guarda(): void
    {
        $cita = $this->cita();

        $this->actingAs($this->vbg)->get(route('vbg.citas.consentimiento', $cita))
            ->assertOk()
            ->assertSee('value="Sofía Ríos"', false)
            ->assertSee(config('sigabu.vbg.consentimiento_cita.version'))
            ->assertSee('firmaTactil(', false);

        $this->consentir($cita)->assertOk()->assertJson(['redirect' => route('vbg.reportes.show', $this->reporte).'#cita-'.$cita->id_cita]);

        $consentimiento = ConsentimientoCita::sole();
        $this->assertSame(['Aceptado', false, 'VBG-CITA-1'], [$consentimiento->decision, $consentimiento->es_menor, $consentimiento->version]);
        $this->assertStringContainsString('Persona atendida: Sofía Ríos, documento 1112223334.', $consentimiento->texto_presentado);
        $this->assertSame(hash('sha256', $consentimiento->texto_presentado), $consentimiento->texto_sha256);
        $this->assertSame(hash('sha256', Storage::disk('vbg')->get($consentimiento->firma_ruta)), $consentimiento->firma_sha256);
        $this->assertNull($consentimiento->firma_menor_ruta);
        $this->assertTrue($cita->fresh()->tieneConsentimientoAceptado());
    }

    public function test_el_rechazo_no_exige_firma_y_la_firma_vacia_no_se_acepta(): void
    {
        $cita = $this->cita();

        $this->consentir($cita, ['firma' => $this->firma(trazo: false)])->assertJsonValidationErrors(['firma' => 'La firma está vacía o es muy corta. Bórrala y vuelve a firmar.']);
        $this->consentir($cita, ['firmante_nombre' => ''])->assertJsonValidationErrors(['firmante_nombre']);
        $this->assertSame(0, ConsentimientoCita::count());

        $this->consentir($cita, ['decision' => 'Rechazado', 'firma' => null])->assertOk();
        $this->assertSame(['Rechazado', null], [ConsentimientoCita::sole()->decision, ConsentimientoCita::sole()->firma_ruta]);
        $this->assertFalse($cita->fresh()->tieneConsentimientoAceptado());
        $this->assertSame([], Storage::disk('vbg')->allFiles());
    }

    /**
     * Menor de edad: firman la persona y su acudiente (nombre, teléfono, cédula y parentesco).
     */
    public function test_si_es_menor_firman_la_persona_y_su_acudiente(): void
    {
        $cita = $this->cita();
        $menor = ['es_menor' => '1', 'firma_estudiante' => $this->firma()];

        $this->consentir($cita, $menor)->assertJsonValidationErrors([
            'acudiente_nombre', 'acudiente_telefono', 'acudiente_documento', 'acudiente_parentesco',
        ]);
        $acudiente = $menor + ['acudiente_nombre' => 'María Pérez', 'acudiente_telefono' => '311 222 3344', 'acudiente_documento' => '29876543', 'acudiente_parentesco' => 'Madre'];

        $this->consentir($cita, array_merge($acudiente, ['firma_estudiante' => null]))
            ->assertJsonValidationErrors(['firma_estudiante' => 'Falta la firma de la persona atendida (menor de edad).']);
        $this->assertSame(0, ConsentimientoCita::count());

        $this->consentir($cita, $acudiente)->assertOk();

        $consentimiento = ConsentimientoCita::sole();
        $this->assertTrue($consentimiento->es_menor);
        $this->assertSame(['María Pérez', '3112223344', '29876543', 'Madre'], [$consentimiento->acudiente_nombre, $consentimiento->acudiente_telefono, $consentimiento->acudiente_documento, $consentimiento->acudiente_parentesco]);
        $this->assertStringContainsString('Otorga el consentimiento su acudiente: María Pérez, C.C. 29876543, Madre, teléfono 3112223344.', $consentimiento->texto_presentado);
        $this->assertCount(2, Storage::disk('vbg')->allFiles());
        $this->assertSame(hash('sha256', Storage::disk('vbg')->get($consentimiento->firma_menor_ruta)), $consentimiento->firma_menor_sha256);
    }

    public function test_si_falla_la_base_se_borran_las_firmas_subidas(): void
    {
        $cita = $this->cita();
        DB::connection('vbg')->unprepared("CREATE TRIGGER falla BEFORE INSERT ON vbg_consentimientos_cita BEGIN SELECT RAISE(ABORT, 'falla simulada'); END;");

        $this->consentir($cita)->assertStatus(409);

        $this->assertSame(0, ConsentimientoCita::count());
        $this->assertSame([], Storage::disk('vbg')->allFiles());
    }

    public function test_las_firmas_solo_las_ve_el_profesional_a_cargo_y_queda_auditado(): void
    {
        $cita = $this->citaConConsentimiento();
        $consentimiento = ConsentimientoCita::sole();
        $url = route('vbg.consentimientos.firma', $consentimiento);

        $this->actingAs($this->vbg)->get(route('vbg.reportes.show', $this->reporte))->assertSee($url, false);
        $this->actingAs($this->vbg)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Firma-Integra', 'si');
        $this->assertSame(1, Auditoria::where('accion', Auditoria::FIRMA_CONSULTADA)->count());

        $this->actingAs($this->otraVbg)->get($url)->assertForbidden();
        $this->actingAs($this->otraVbg)->get(route('vbg.reportes.show', $this->reporte))->assertOk()->assertDontSee($url, false);
        $this->actingAs($this->vbg)->get(route('vbg.consentimientos.firma', [$consentimiento, 'menor']))->assertNotFound();
    }

    public function test_el_audio_exige_el_consentimiento_aceptado(): void
    {
        $cita = $this->cita();

        $this->subirAudio($cita)->assertSessionHasErrorsIn('audio-'.$cita->id_cita, ['audio']);
        $this->assertSame(0, Audio::count());
    }

    /**
     * DADO una cita con consentimiento, CUANDO se sube el audio,
     * ENTONCES queda en el bucket privado con su huella, se reproduce solo para el profesional a
     * cargo (auditado) y se le escribe la transcripción.
     */
    public function test_se_sube_el_audio_se_escucha_y_se_transcribe(): void
    {
        $cita = $this->citaConConsentimiento();

        $this->subirAudio($cita, UploadedFile::fake()->create('notas.pdf', 50, 'application/pdf'))->assertSessionHasErrorsIn('audio-'.$cita->id_cita, ['audio']);
        $this->subirAudio($cita, null, ['duracion_segundos' => 7300])->assertSessionHasErrorsIn('audio-'.$cita->id_cita, ['duracion_segundos' => 'El audio supera el límite de 2 horas.']);
        $this->subirAudio($cita)->assertSessionHasNoErrors();

        $audio = Audio::sole();
        $this->assertSame(['mp3', 1800, 'sesion.mp3', '30 min 00 s'], [$audio->formato, $audio->duracion_segundos, $audio->nombre_original, $audio->duracionLegible()]);
        $this->assertTrue(Storage::disk('vbg')->exists($audio->ruta));
        $this->assertSame('2026-10-20 10:00', $audio->fresh()->creado_en->format('Y-m-d H:i'), 'Hora de Colombia, como el resto del expediente');
        $this->assertNull($audio->transcripcion, 'Sin IA configurada no queda en cola');

        $this->actingAs($this->vbg)->get(route('vbg.audios.show', $audio))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->otraVbg)->get(route('vbg.audios.show', $audio))->assertForbidden();
        $this->assertSame(1, Auditoria::where('accion', Auditoria::AUDIO_ESCUCHADO)->count());

        $this->actingAs($this->vbg)->put(route('vbg.audios.transcripcion', $audio), ['texto' => 'Texto transcrito por la profesional.'])->assertSessionHasNoErrors();
        $transcripcion = $audio->fresh()->transcripcion;
        $this->assertSame(['Completada', 'Manual', 'Texto transcrito por la profesional.'], [$transcripcion->estado, $transcripcion->origen, $transcripcion->texto]);

        $this->actingAs($this->vbg)->get(route('vbg.reportes.show', $this->reporte))
            ->assertSee(route('vbg.audios.show', $audio), false)
            ->assertSee('Texto transcrito por la profesional.');
    }

    /**
     * La transcripción automática (contemplada en la rama VIOLENCIA_GENERO) solo actúa si se configura.
     */
    public function test_la_transcripcion_automatica_procesa_la_cola_si_esta_configurada(): void
    {
        $this->artisan('vbg:transcribir')->expectsOutputToContain('no está configurada')->assertSuccessful();

        config(['sigabu.vbg.ia.transcripcion_comando' => 'python3 transcribir.py']);
        Process::fake(['*' => Process::result(output: "Texto de la sesión transcrito automáticamente.\n")]);

        $cita = $this->citaConConsentimiento();
        $this->subirAudio($cita)->assertSessionHasNoErrors();
        $this->assertSame('En cola', Audio::sole()->transcripcion->estado);

        $this->artisan('vbg:transcribir')->expectsOutputToContain('completadas: 1')->assertSuccessful();

        $transcripcion = Transcripcion::sole();
        $this->assertSame(['Completada', 'IA', 'Texto de la sesión transcrito automáticamente.'], [$transcripcion->estado, $transcripcion->origen, $transcripcion->texto]);
        Process::assertRan(fn ($proceso) => str_starts_with($proceso->command, 'python3 transcribir.py '));
    }

    public function test_si_la_transcripcion_automatica_falla_queda_con_error(): void
    {
        config(['sigabu.vbg.ia.transcripcion_comando' => 'python3 transcribir.py']);
        Process::fake(['*' => Process::result(output: '', errorOutput: 'sin GPU', exitCode: 1)]);

        $cita = $this->citaConConsentimiento();
        $this->subirAudio($cita)->assertSessionHasNoErrors();
        $this->artisan('vbg:transcribir')->expectsOutputToContain('con error: 1');

        $this->assertSame('Error', Transcripcion::sole()->estado);
    }

    /**
     * DADO un caso con una transcripción, CUANDO el profesional elabora el informe de análisis,
     * ENTONCES se guarda con marco legal, documentos del Repositorio y ruta, se descarga en PDF y
     * cada nueva versión conserva la anterior.
     */
    public function test_el_profesional_elabora_el_informe_y_lo_descarga_en_pdf(): void
    {
        $cita = $this->citaConConsentimiento();
        $this->subirAudio($cita)->assertSessionHasNoErrors();
        $audio = Audio::sole();
        $this->actingAs($this->vbg)->put(route('vbg.audios.transcripcion', $audio), ['texto' => 'Transcripción.'])->assertSessionHasNoErrors();
        $ley = RepositorioDocumento::create(['titulo' => 'Protocolo VBG UCEVA', 'categoria' => 'Protocolo', 'url' => 'https://uceva.edu.co/protocolo', 'audiencia' => 'Profesionales', 'activo' => true, 'publicado_por' => $this->vbg->id_usuario]);

        $this->actingAs($this->vbg)->get(route('vbg.informes.create', $this->reporte))
            ->assertOk()
            ->assertSee('Ley 1257 de 2008')
            ->assertSee('Protocolo VBG UCEVA')
            ->assertSee('Activar medidas de protección inmediata');

        $enviar = fn (array $cambios = []) => $this->actingAs($this->vbg)->from(route('vbg.informes.create', $this->reporte))
            ->post(route('vbg.informes.store', $this->reporte), array_merge([
                'resumen' => "Primer párrafo del análisis.\n\nSegundo párrafo.",
                'nivel_riesgo' => 'Alto',
                'tipos_violencia' => ['Psicológica', 'Digital'],
                'marco_legal' => [0, 2],
                'documentos' => [$ley->id_documento],
                'ruta_atencion' => [1],
                'ruta_otros' => "Acompañamiento a la denuncia\n",
                'id_transcripcion' => $audio->fresh()->transcripcion->id_transcripcion,
            ], $cambios));

        $enviar(['ruta_atencion' => [], 'ruta_otros' => ''])->assertSessionHasErrors(['ruta_atencion']);
        $enviar(['tipos_violencia' => ['Inventada']])->assertSessionHasErrors(['tipos_violencia.0']);
        $enviar(['documentos' => [999]])->assertSessionHasErrors(['documentos.0']);
        $this->assertSame(0, Informe::count());

        $enviar()->assertSessionHasNoErrors();
        $informe = Informe::sole();
        $this->assertSame(['Alto', ['Psicológica', 'Digital'], 'Manual'], [$informe->nivel_riesgo, $informe->tipos_violencia, $informe->origen]);
        $this->assertSame(['Ley 1257 de 2008', 'Ley 294 de 1996'], array_column($informe->marco_legal, 'titulo'));
        $this->assertSame(['Remisión a asesoría jurídica', 'Acompañamiento a la denuncia'], array_column($informe->ruta_atencion, 'titulo'));
        $this->assertSame([$ley->id_documento], $informe->documentos_repositorio);

        $this->actingAs($this->vbg)->get(route('vbg.informes.pdf', $informe))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->otraVbg)->get(route('vbg.informes.pdf', $informe))->assertForbidden();
        $this->assertSame(1, Auditoria::where('accion', Auditoria::INFORME_PDF)->count());

        $html = view('documentos.vbg-informe', [
            'informe' => $informe, 'reporte' => $this->reporte, 'documentos' => collect([$ley]), 'logo' => '', 'ciudad' => 'Tuluá', 'generado' => now(),
        ])->render();
        foreach (['Caso '.$this->reporte->codigo, 'ALTO', 'Psicológica, Digital', 'Ley 1257 de 2008', 'Protocolo VBG UCEVA', 'Acompañamiento a la denuncia', 'Segundo párrafo.'] as $esperado) {
            $this->assertStringContainsString(e($esperado), $html, "Falta en el PDF: $esperado");
        }

        Carbon::setTestNow(now()->addMinutes(5));
        $enviar(['nivel_riesgo' => 'Medio'])->assertSessionHasNoErrors();
        $this->assertSame(2, Informe::count());
        $this->actingAs($this->vbg)->get(route('vbg.reportes.show', $this->reporte))->assertSee('Riesgo medio')->assertSee('Versiones anteriores (1)');
    }

    public function test_el_panel_muestra_las_citas_de_hoy(): void
    {
        $this->agendar(['fecha' => '2026-10-20', 'hora_inicio' => '14:00', 'hora_fin' => '15:00'])->assertSessionHasNoErrors();

        $this->actingAs($this->vbg)->get(route('vbg.home'))
            ->assertOk()
            ->assertSee('Tus citas hoy')
            ->assertSee('Tu agenda de hoy')
            ->assertSee('14:00 – 15:00');
        $this->actingAs($this->otraVbg)->get(route('vbg.home'))->assertOk()->assertDontSee('Tu agenda de hoy');
    }

    /**
     * Si la base de VBG no responde, el caso se sigue viendo y lo avisa.
     */
    public function test_si_la_base_de_vbg_no_responde_el_caso_se_ve_igual(): void
    {
        // Se simula que la base de VBG falla quitándole sus tablas (la prueba se revierte al final).
        foreach (['vbg_auditoria', 'vbg_informes', 'vbg_transcripciones', 'vbg_audios', 'vbg_consentimientos_cita', 'vbg_citas'] as $tabla) {
            Schema::connection('vbg')->drop($tabla);
        }

        $this->actingAs($this->vbg)->get(route('vbg.reportes.show', $this->reporte))
            ->assertOk()
            ->assertSee('Relato de prueba.')
            ->assertSee('la base de datos de VBG no responde');
        $this->actingAs($this->vbg)->get(route('vbg.home'))->assertOk();
    }
}

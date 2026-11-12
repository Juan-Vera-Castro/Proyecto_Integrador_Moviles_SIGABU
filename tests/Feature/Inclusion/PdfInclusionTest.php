<?php

namespace Tests\Feature\Inclusion;

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\PdfInclusion;
use App\Documentos\RecursosInclusion;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Facultad;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\Inclusion\EntradaBitacora;
use App\Models\Inclusion\Evidencia;
use App\Models\Inclusion\Recurso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\Concerns\GeneraFirmaPng;
use Tests\TestCase;

/**
 * H6: PDF de los formatos de Inclusión, con datos ficticios. El contenido se revisa en el HTML
 * de cada plantilla, que es lo que DomPDF convierte; las rutas se prueban de punta a punta.
 */
class PdfInclusionTest extends TestCase
{
    use CreaEsquemaSigabu, GeneraFirmaPng, RefreshDatabase;

    private User $persona;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', Actuacion::ZONA_HORARIA));
        Storage::fake(ConsentimientoAceptacion::DISCO_FIRMAS);
        Storage::fake(Evidencia::DISCO);
        config(['filesystems.disks.firmas.key' => 'clave', 'filesystems.disks.firmas.secret' => 'secreto', 'filesystems.disks.firmas.endpoint' => 'https://ejemplo.invalid']);

        $this->crearEsquemaSigabu();
        ConsentimientoInclusion::cargar();
        RecursosInclusion::cargar();
        Facultad::firstOrCreate(['nombre' => 'Facultad Ficticia'], ['activa' => true]);

        $this->profesional = $this->crearProfesional('Inclusión', 'Profesional Ficticia');
        $this->persona = $this->crearUsuarioConRol('Persona', [
            'nombre_completo' => 'Persona Ficticia',
            'numero_documento' => '900222444',
            'tipo_documento' => 'CC',
            'sexo' => 'F',
            'fecha_nacimiento' => '2000-01-15',
        ]);

        $solicitud = $this->persona->persona->solicitudes()->create(['tipo_servicio' => 'Inclusión', 'descripcion' => 'Prueba']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->update(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $this->profesional->id_usuario]);
        $this->caso = $this->caso->fresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearProfesional(string $area, string $nombre = 'Otra Profesional'): User
    {
        $profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => $nombre]);
        $profesional->forceFill(['area_profesional' => $area])->save();

        return $profesional;
    }

    private function firmar(array $cambios = []): ConsentimientoAceptacion
    {
        $this->actingAs($this->persona)->postJson(route('inclusion.consentimiento.enviar', $this->caso), array_merge([
            'id_version' => ConsentimientoInclusion::versionDe($this->caso)->id_version,
            'nombre_persona' => 'Persona Ficticia',
            'documento_persona' => '900222444',
            'numero_contacto' => '300 000-0000',
            'vinculacion' => 'Estudiante Diurno',
            'facultad' => 'Facultad Ficticia',
            'programa_academico' => 'Programa Ficticio',
            'semestre' => '3',
            'dependencia' => 'N/A',
            'decision_acompanamiento' => '1',
            'decision_datos_generales' => '1',
            'decision_datos_sensibles' => '1',
            'firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng()),
        ], $cambios))->assertOk();

        return ConsentimientoAceptacion::where('id_caso', $this->caso->id_caso)->latest('fecha_hora')->firstOrFail();
    }

    private function html(string $vista, array $datos): string
    {
        return view($vista, $datos)->render();
    }

    private function prepararBitacora(): void
    {
        $soporte = $this->actingAs($this->profesional)
            ->postJson(route('inclusion.evidencias.store', $this->caso), ['archivo' => UploadedFile::fake()->create('certificado-clinico.pdf', 20, 'application/pdf')])
            ->json('id');

        $this->actingAs($this->profesional)->post(route('inclusion.bitacora.guardar', $this->caso), [
            'rol_institucional' => 'Estudiante Diurno',
            'programa_dependencia' => 'Programa Ficticio',
            'situacion' => 'Situación clínica ficticia',
            'ruta_activada' => 'Ruta ficticia',
            'soportes_texto' => 'Soporte ficticio',
            'orientacion' => 'Orientación ficticia',
            'soportes' => [$soporte],
        ])->assertSessionHasNoErrors();

        foreach ([EntradaBitacora::ATENCION_INICIAL, EntradaBitacora::CIERRE] as $tipo) {
            $this->actingAs($this->profesional)->post(route('inclusion.entradas.store', $this->caso), [
                'fecha_hora' => '2026-11-06T09:00', 'tipo' => $tipo, 'descripcion' => "Descripción ficticia de {$tipo}",
            ])->assertSessionHasNoErrors();
        }
    }

    private function agregarFila(): void
    {
        $archivo = $this->actingAs($this->profesional)
            ->postJson(route('inclusion.evidencias.store', $this->caso), ['archivo' => UploadedFile::fake()->create('diagnostico-tea.pdf', 20, 'application/pdf')])
            ->json('id');
        $this->actingAs($this->profesional)->postJson(route('inclusion.evidencias.store', $this->caso), ['url' => 'https://drive.invalid/rubrica']);
        $enlace = Evidencia::where('tipo', Evidencia::TIPO_ENLACE)->where('estado', Evidencia::TEMPORAL)->value('id_evidencia');

        $this->actingAs($this->profesional)->post(route('inclusion.plantilla.guardar', $this->caso), [
            'area' => 'Evaluación',
            'barrera' => 'Barrera ficticia',
            'descripcion' => 'Ajuste ficticio',
            'responsable' => 'Docente ficticio',
            'fecha_inicio' => '2026-11-06',
            'observaciones' => 'Observación ficticia',
            'id_recurso' => Recurso::firstOrFail()->id_recurso,
            'evidencias' => array_values(array_filter([$archivo, $enlace])),
        ])->assertSessionHasNoErrors();
    }

    public function test_un_png_transparente_de_firma_se_incrusta_en_el_pdf(): void
    {
        $aceptacion = $this->firmar();
        $datos = PdfInclusion::consentimiento($this->caso->fresh(), $aceptacion);

        $this->assertStringStartsWith('data:image/png;base64,', $datos['firmaPersona']['src']);
        $this->assertTrue($datos['firmaPersona']['integra']);

        $pdf = $this->actingAs($this->persona)->get(route('inclusion.consentimiento.pdf', $this->caso))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/SMask', $pdf, 'La transparencia del PNG debe llegar al PDF como máscara.');
    }

    public function test_el_pdf_del_consentimiento_lleva_datos_decisiones_y_las_dos_firmas(): void
    {
        $aceptacion = $this->firmar(['decision_compartir' => '1']);
        $this->actingAs($this->profesional)->postJson(route('consentimientos.firmar-profesional', $aceptacion), ['firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng())])->assertOk();

        $html = $this->html('documentos.consentimiento-inclusion', PdfInclusion::consentimiento($this->caso->fresh(), $aceptacion->fresh()));

        $this->assertStringContainsString('FORMATO DE CONSENTIMIENTO INFORMADO', $html);
        $this->assertStringContainsString('CÓDIGO: POR-ASIGNAR', $html);
        $this->assertStringContainsString('Persona Ficticia', $html);
        $this->assertMatchesRegularExpression('#<td>Facultad Ficticia</td>\s*<td>Programa Ficticio</td>\s*<td>3</td>\s*<td>N/A</td>#', $html);
        $this->assertStringContainsString('Estudiante Diurno', $html);
        $this->assertStringContainsString('Profesional Ficticia', $html);
        $this->assertSame(4, substr_count($html, '<span class="casilla">X</span>'));
        $this->assertSame(2, substr_count($html, 'src="data:image/png;base64,'));
        $this->assertStringNotContainsString('pendiente la firma del profesional', $html);
    }

    public function test_el_pdf_marca_solo_las_decisiones_tomadas_y_avisa_la_revocacion(): void
    {
        $this->firmar();
        Carbon::setTestNow(now()->addMinute());
        $this->actingAs($this->persona)->post(route('inclusion.consentimiento.revocar', $this->caso));

        $caso = $this->caso->fresh();
        $caso->load('aceptacionesConsentimiento');
        $aceptada = $caso->aceptacionesConsentimiento->first(fn ($fila) => $fila->esAceptado());
        $html = $this->html('documentos.consentimiento-inclusion', PdfInclusion::consentimiento($caso, $aceptada));

        $this->assertSame(3, substr_count($html, '<span class="casilla">X</span>'));
        $this->assertSame(1, substr_count($html, '<span class="casilla"></span>'));
        $this->assertStringContainsString('Consentimiento revocado el 06/11/2026 10:01', $html);
        $this->assertStringContainsString('pendiente la firma del profesional', $html);
    }

    public function test_el_pdf_de_un_consentimiento_en_papel_lleva_la_leyenda_sin_imagen_de_firma(): void
    {
        $this->actingAs($this->profesional)->post(route('inclusion.consentimiento.papel.registrar', $this->caso), [
            'archivo' => UploadedFile::fake()->createWithContent('formato.pdf', '%PDF-1.4 formato ficticio'),
            'fecha_firma_papel' => '2026-11-05',
            'motivo_papel' => 'La persona no tiene acceso a la plataforma.',
            'nombre_persona' => 'Persona Ficticia',
            'documento_persona' => '900222444',
            'numero_contacto' => '300 000-0000',
            'vinculacion' => 'Estudiante Diurno',
            'facultad' => 'Facultad Ficticia',
            'programa_academico' => 'Programa Ficticio',
            'semestre' => '3',
            'dependencia' => 'N/A',
            'decision_acompanamiento' => '1',
            'decision_datos_generales' => '1',
        ])->assertSessionHasNoErrors();

        $aceptacion = ConsentimientoAceptacion::where('id_caso', $this->caso->id_caso)->firstOrFail();
        $html = $this->html('documentos.consentimiento-inclusion', PdfInclusion::consentimiento($this->caso->fresh(), $aceptacion));

        $this->assertStringContainsString(ConsentimientoDetalle::LEYENDA_PAPEL, $html);
        $this->assertStringContainsString('La persona no tiene acceso a la plataforma.', $html);
        $this->assertStringContainsString('05/11/2026', $html);
        $this->assertStringNotContainsString('data:image/png', $html);
    }

    public function test_el_consentimiento_lo_descargan_la_persona_el_asignado_y_el_administrador_con_registro(): void
    {
        $this->firmar();
        $url = route('inclusion.consentimiento.pdf', $this->caso);

        foreach ([$this->persona, $this->profesional, $this->crearUsuarioConRol('Admin')] as $usuario) {
            $this->actingAs($usuario)->get($url)->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'attachment; filename="Consentimiento-inclusion-'.$this->caso->codigo_expediente.'-06-11-26.pdf"');
        }

        $this->assertSame(3, AuditoriaExpediente::where('accion', AuditoriaExpediente::CONSENTIMIENTO_INCLUSION_PDF)->count());
        $this->assertSame(['id_aceptacion'], array_keys(AuditoriaExpediente::where('accion', AuditoriaExpediente::CONSENTIMIENTO_INCLUSION_PDF)->firstOrFail()->detalle));

        $otraPersona = $this->crearUsuarioConRol('Persona');
        foreach ([$otraPersona, $this->crearProfesional('Inclusión'), $this->crearProfesional('Salud Mental')] as $sinPermiso) {
            $this->assertNotSame(200, $this->actingAs($sinPermiso)->get($url)->getStatusCode());
        }
        $this->assertSame(3, AuditoriaExpediente::where('accion', AuditoriaExpediente::CONSENTIMIENTO_INCLUSION_PDF)->count());
    }

    public function test_sin_consentimiento_aceptado_no_hay_pdf(): void
    {
        $this->actingAs($this->persona)->get(route('inclusion.consentimiento.pdf', $this->caso))->assertNotFound();
    }

    public function test_la_persona_y_el_expediente_enlazan_el_pdf(): void
    {
        $this->firmar();

        $this->actingAs($this->persona)->get(route('inclusion.consentimiento.ver', $this->caso))->assertSee(route('inclusion.consentimiento.pdf', $this->caso), false);
        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $this->caso))->assertSee(route('inclusion.consentimiento.pdf', $this->caso), false);
    }

    public function test_el_formato_para_imprimir_lleva_el_texto_vigente_y_los_datos_de_la_persona(): void
    {
        $this->actingAs($this->profesional)->get(route('inclusion.consentimiento.papel', $this->caso))->assertSee(route('inclusion.consentimiento.imprimible', $this->caso), false);

        $this->actingAs($this->profesional)->get(route('inclusion.consentimiento.imprimible', $this->caso))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertTrue(AuditoriaExpediente::where('accion', AuditoriaExpediente::CONSENTIMIENTO_INCLUSION_PDF)->exists());

        $html = $this->html('documentos.consentimiento-inclusion-imprimible', PdfInclusion::imprimible($this->caso->fresh()));
        $this->assertStringContainsString('Yo, Persona Ficticia, con documento de identidad número 900222444, manifiesto', $html);
        $this->assertStringContainsString('ACEPTO recibir acompañamiento', $html);
        $this->assertSame(0, substr_count($html, '<span class="casilla">X</span>'));
        $this->assertStringContainsString('Docente o responsable académico', $html);
    }

    public function test_el_formato_para_imprimir_es_solo_del_asignado_y_sin_consentimiento_vigente(): void
    {
        $url = route('inclusion.consentimiento.imprimible', $this->caso);

        foreach ([$this->persona, $this->crearProfesional('Inclusión'), $this->crearUsuarioConRol('Admin')] as $sinPermiso) {
            $this->assertNotSame(200, $this->actingAs($sinPermiso)->get($url)->getStatusCode());
        }

        $this->firmar();
        $this->actingAs($this->profesional)->get($url)->assertRedirect(route('profesional.expediente.show', $this->caso))->assertSessionHas('error');
    }

    public function test_el_pdf_de_bitacora_lleva_las_secciones_y_la_firma_de_la_constancia(): void
    {
        $this->prepararBitacora();
        $this->actingAs($this->profesional)->postJson(route('inclusion.cierre', $this->caso), ['firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng())])->assertOk();

        $html = $this->html('documentos.bitacora-inclusion', PdfInclusion::bitacora($this->caso->fresh()));

        foreach (['I. DATOS GENERALES DEL CASO', 'II. CARACTERIZACIÓN Y REGISTRO DE CASO', 'III. MATRIZ DE SEGUIMIENTO', 'IV. CONSTANCIA DE GESTIÓN', 'Situación clínica ficticia', 'certificado-clinico.pdf', 'Descripción ficticia de Cierre', '*Nota: La Unidad de Inclusión actúa', 'Nombre: Profesional Ficticia', 'size: letter landscape'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertSame(1, substr_count($html, 'src="data:image/png;base64,'));

        $this->actingAs($this->profesional)->get(route('inclusion.bitacora.pdf', $this->caso))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $auditoria = AuditoriaExpediente::where('accion', AuditoriaExpediente::BITACORA_PDF)->firstOrFail();
        $this->assertSame(['entradas' => 2], $auditoria->detalle);
    }

    public function test_sin_constancia_la_bitacora_deja_la_firma_en_blanco(): void
    {
        $this->prepararBitacora();

        $html = $this->html('documentos.bitacora-inclusion', PdfInclusion::bitacora($this->caso->fresh()));

        $this->assertStringContainsString('La constancia se firma al cerrar el caso.', $html);
        $this->assertStringNotContainsString('data:image/png', $html);
    }

    public function test_la_bitacora_y_la_plantilla_en_pdf_son_solo_del_asignado(): void
    {
        $this->prepararBitacora();
        $this->agregarFila();
        $rutas = [route('inclusion.bitacora.pdf', $this->caso), route('inclusion.plantilla.pdf', $this->caso), route('inclusion.plantilla.compartir', $this->caso)];
        $sinPermiso = [$this->persona, $this->crearProfesional('Inclusión'), $this->crearProfesional('Ambas'), $this->crearProfesional('Salud Mental'), $this->crearUsuarioConRol('Admin')];

        foreach ($rutas as $url) {
            foreach ($sinPermiso as $usuario) {
                $this->assertNotSame(200, $this->actingAs($usuario)->get($url)->getStatusCode(), $url);
            }
        }

        $this->assertSame(0, AuditoriaExpediente::whereIn('accion', [AuditoriaExpediente::BITACORA_PDF, AuditoriaExpediente::PLANTILLA_PDF, AuditoriaExpediente::PLANTILLA_COMPARTIR_PDF])->count());
    }

    public function test_el_pdf_completo_lleva_el_recurso_y_las_filas(): void
    {
        $this->agregarFila();

        $html = $this->html('documentos.recurso-plantilla-inclusion', PdfInclusion::plantilla($this->caso->fresh(), false));

        foreach (['Estrategias de Accesibilidad Académica', 'Trastorno del Espectro Autista', '1. Introducción', '5.8 PLANTILLA DE AJUSTES', 'Barrera ficticia', 'diagnostico-tea.pdf', 'https://drive.invalid/rubrica', 'solo para el expediente'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }

        $this->actingAs($this->profesional)->get(route('inclusion.plantilla.pdf', $this->caso))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(['filas' => 1], AuditoriaExpediente::where('accion', AuditoriaExpediente::PLANTILLA_PDF)->firstOrFail()->detalle);
    }

    public function test_el_pdf_para_compartir_no_lleva_recurso_condicion_ni_datos_sensibles(): void
    {
        $this->prepararBitacora();
        $this->agregarFila();

        $html = $this->html('documentos.plantilla-compartir-inclusion', PdfInclusion::plantilla($this->caso->fresh(), true));

        $this->assertStringContainsString('Barrera ficticia', $html);
        $this->assertStringContainsString('Ajuste ficticio', $html);
        $this->assertStringContainsString('https://drive.invalid/rubrica', $html);
        $this->assertStringContainsString('1 archivo en el expediente', $html);

        foreach ([Recurso::firstOrFail()->condicion, 'Espectro', 'Estrategias de Accesibilidad', '1. Introducción', 'Situación o discapacidad', 'Situación clínica ficticia', 'certificado-clinico.pdf', 'diagnostico-tea.pdf', '900222444', 'Soporte ficticio'] as $sensible) {
            $this->assertStringNotContainsString($sensible, $html);
        }
    }

    public function test_sin_la_decision_4_se_avisa_al_compartir_sin_bloquear(): void
    {
        $this->firmar();
        $this->agregarFila();

        $this->actingAs($this->profesional)->get(route('inclusion.plantilla', $this->caso))->assertOk()
            ->assertSee('Antes de compartir:')
            ->assertSee('La persona no autorizó compartir información')
            ->assertSee(route('inclusion.plantilla.compartir', $this->caso), false);

        $this->actingAs($this->profesional)->get(route('inclusion.plantilla.compartir', $this->caso))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(['filas' => 1, 'con_aviso' => true], AuditoriaExpediente::where('accion', AuditoriaExpediente::PLANTILLA_COMPARTIR_PDF)->firstOrFail()->detalle);
    }

    public function test_con_la_decision_4_no_hay_aviso(): void
    {
        $this->firmar(['decision_compartir' => '1']);
        $this->agregarFila();

        $this->assertNull(PdfInclusion::avisoCompartir($this->caso->fresh()));
        $this->actingAs($this->profesional)->get(route('inclusion.plantilla', $this->caso))->assertOk()->assertDontSee('Antes de compartir:');
    }

    public function test_el_expediente_enlaza_los_pdf_de_bitacora_y_plantilla(): void
    {
        $this->prepararBitacora();
        $this->agregarFila();

        $this->actingAs($this->profesional)->get(route('profesional.expediente.show', $this->caso))->assertOk()
            ->assertSee(route('inclusion.bitacora.pdf', $this->caso), false)
            ->assertSee(route('inclusion.plantilla.pdf', $this->caso), false);
        $this->actingAs($this->profesional)->get(route('inclusion.bitacora', $this->caso))->assertSee(route('inclusion.bitacora.pdf', $this->caso), false);
    }

    public function test_una_firma_que_no_se_puede_leer_no_rompe_el_pdf(): void
    {
        $aceptacion = $this->firmar();
        Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->delete($aceptacion->firma_ruta);

        $html = $this->html('documentos.consentimiento-inclusion', PdfInclusion::consentimiento($this->caso->fresh(), $aceptacion));

        $this->assertStringContainsString('No se pudo cargar la imagen de una firma registrada.', $html);
        $this->actingAs($this->persona)->get(route('inclusion.consentimiento.pdf', $this->caso))->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Documentos\DatosRemision;
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
use ZipArchive;

/**
 * RQ-3 y HU-RE-02: documento "Remisión psicológica" (GBU-GPGR-F-016) en PDF y Word.
 */
class DocumentoRemisionTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $profesional;

    private Caso $caso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', Actuacion::ZONA_HORARIA));

        $this->crearEsquemaSigabu();
        $this->profesional = $this->crearUsuarioConRol('Profesional', ['nombre_completo' => 'Camila Muñoz']);

        $estudiante = $this->crearUsuarioConRol('Persona', [
            'nombre_completo' => 'Samuel Valencia Ríos', 'numero_documento' => '1112223334',
            'tipo_documento' => 'CC', 'sexo' => 'M', 'fecha_nacimiento' => '2005-10-07',
        ]);
        $solicitud = $estudiante->persona->solicitudes()->create(['tipo_servicio' => 'Salud Mental', 'descripcion' => 'Ansiedad.']);
        $this->caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $this->caso->update(['estado_caso' => 'Abierto', 'id_profesional' => $this->profesional->id_usuario]);
        $this->crearConsentimientoVigente($this->caso);

        Actuacion::create([
            'id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario,
            'tipo_actuacion' => Actuacion::TIPO_CITA, 'fecha_hora' => '2026-10-01 14:00:00', 'estado_asistencia' => 'Asistió',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function remitir(string $categoria = 'URGENTE'): Actuacion
    {
        $this->actingAs($this->profesional)->post(route('remisiones.store', $this->caso), [
            'destino_remision' => 'Externa', 'entidad_destino' => 'Servicio de urgencias', 'categoria' => $categoria,
            'motivo' => "Ideación suicida con plan.\nRequiere valoración psiquiátrica inmediata & acompañamiento.",
            'acompanante_nombre' => 'María Pérez', 'acompanante_parentesco' => 'Madre', 'acompanante_tipo_documento' => 'CC',
            'acompanante_numero_documento' => '29876543', 'acompanante_telefono' => '3001234567',
        ])->assertSessionHasNoErrors();

        return Actuacion::remisiones()->sole();
    }

    private function textoWord(Actuacion $remision): string
    {
        $respuesta = $this->actingAs($this->profesional)->get(route('remisiones.documento.word', $remision))->assertOk();
        $archivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($archivo, $respuesta->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo) === true, 'El .docx debe ser un ZIP válido');
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($archivo);

        return html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function html(Actuacion $remision): string
    {
        return view('documentos.remision-psicologica', ['d' => DatosRemision::desde($remision->remisionExterna), 'logo' => 'data:image/png;base64,'])->render();
    }

    public function test_los_datos_del_documento_marcan_las_casillas_y_formatean_fechas(): void
    {
        $datos = DatosRemision::desde($this->remitir()->remisionExterna);

        $this->assertSame(['TI' => '', 'CC' => 'X', 'CE' => ''], $datos->tipoDocumento);
        $this->assertSame(['F' => '', 'M' => 'X'], $datos->sexo);
        $this->assertSame(['URGENTE' => 'X', 'PRIORITARIO' => ''], $datos->categoria);
        $this->assertSame(['01', '10', '26'], $datos->fechaAtencion);
        $this->assertSame('2:00 pm', $datos->hora);
        $this->assertSame(['07', '10', '05'], $datos->fechaNacimiento);
        $this->assertSame('20', $datos->edad);
        $this->assertSame(['CÓDIGO: GBU-GPGR-F-016', 'VERSIÓN: 3', 'FECHA: 18/ABR/2024'], [$datos->codigo, $datos->version, $datos->fecha]);
        $this->assertSame('CC 29876543', $datos->acompanante['identificacion']);
        $this->assertSame("Remision-{$this->caso->codigo_expediente}-01-10-26", $datos->nombreArchivo);
    }

    /**
     * DADO una remisión externa URGENTE,
     * CUANDO el psicólogo descarga el PDF,
     * ENTONCES obtiene el formato GBU-GPGR-F-016 con todos los datos y las firmas en blanco.
     */
    public function test_descarga_el_pdf_con_el_formato_oficial(): void
    {
        $remision = $this->remitir();

        $respuesta = $this->actingAs($this->profesional)->get(route('remisiones.documento.pdf', $remision))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Remision-'.$this->caso->codigo_expediente.'-01-10-26.pdf"');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());

        $html = $this->html($remision);
        foreach ([
            'GESTIÓN APOYO AL ESTUDIANTE', 'CÓDIGO: GBU-GPGR-F-016', 'VERSIÓN: 3', 'FECHA: 18/ABR/2024', 'REMISIÓN PSICOLÓGICA',
            'Fecha de atención (dd-mm-aa):', 'Samuel Valencia Ríos', '1112223334', 'FECHA DE NACIMIENTO (dd-mm-aa)',
            'CATEGORIA', 'REMITIDO A:', 'Servicio de urgencias', 'JUSTIFICACIÓN:', 'Requiere valoración psiquiátrica inmediata &amp; acompañamiento.',
            'EN CASO DE SER REMITIDO POR URGENCIAS', 'DATOS DEL ACOMPAÑANTE', 'María Pérez', 'Madre', 'CC 29876543', '3001234567',
            'NOMBRE PSICOLOGO (A)', 'Camila Muñoz', 'FIRMA',
        ] as $texto) {
            $this->assertStringContainsString($texto, $html, "Falta \"$texto\" en el documento");
        }
        $this->assertStringContainsString('page-break-before: always', $html);

        $this->assertSame(1, AuditoriaExpediente::where('accion', 'documento_remision_pdf')->count());
    }

    public function test_descarga_el_word_con_los_mismos_datos(): void
    {
        $remision = $this->remitir();

        $this->actingAs($this->profesional)->get(route('remisiones.documento.word', $remision))
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('Content-Disposition', 'attachment; filename="Remision-'.$this->caso->codigo_expediente.'-01-10-26.docx"');

        $texto = $this->textoWord($remision);
        foreach ([
            'CÓDIGO: GBU-GPGR-F-016', 'VERSIÓN: 3', 'FECHA: 18/ABR/2024', 'Samuel Valencia Ríos', '1112223334',
            'Servicio de urgencias', 'Requiere valoración psiquiátrica inmediata & acompañamiento.',
            'DATOS DEL ACOMPAÑANTE', 'María Pérez', 'CC 29876543', 'Camila Muñoz', 'NOMBRE PSICOLOGO (A)',
        ] as $esperado) {
            $this->assertStringContainsString($esperado, $texto, "Falta \"$esperado\" en el Word");
        }

        // Dos descargas: la de arriba y la que hace textoWord(); cada una queda auditada.
        $this->assertSame(2, AuditoriaExpediente::where('accion', 'documento_remision_word')->count());
    }

    public function test_en_la_prioritaria_la_seccion_del_acompanante_sale_vacia(): void
    {
        $remision = $this->remitir('PRIORITARIO');

        $this->assertStringContainsString('DATOS DEL ACOMPAÑANTE', $this->html($remision));
        $this->assertStringNotContainsString('María Pérez', $this->html($remision));
        $this->assertStringNotContainsString('María Pérez', $this->textoWord($remision));
        $this->assertSame(['URGENTE' => '', 'PRIORITARIO' => 'X'], DatosRemision::desde($remision->remisionExterna)->categoria);
    }

    /**
     * DADO una remisión ya registrada,
     * CUANDO después cambian el registro del estudiante o el formato de Calidad,
     * ENTONCES el documento sale igual que el día de la remisión (RN-09, RN-13).
     */
    public function test_el_documento_es_reproducible_aunque_cambien_los_datos(): void
    {
        $remision = $this->remitir();

        $this->caso->solicitud->persona->update(['nombre_completo' => 'Nombre Cambiado', 'tipo_documento' => 'TI']);
        FormatoInstitucional::deClave('REMISION_PSICOLOGICA')->update(['version' => '4', 'fecha_formato' => '2026-09-01']);

        $datos = DatosRemision::desde($remision->fresh()->remisionExterna);
        $this->assertSame('Samuel Valencia Ríos', $datos->nombre);
        $this->assertSame('X', $datos->tipoDocumento['CC']);
        $this->assertSame('VERSIÓN: 3', $datos->version);
        $this->assertSame('FECHA: 18/ABR/2024', $datos->fecha);
    }

    public function test_solo_lo_descargan_quien_remitio_y_el_profesional_del_caso(): void
    {
        $remision = $this->remitir();

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('remisiones.documento.pdf', $remision))->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('remisiones.documento.word', $remision))->assertForbidden();
        $this->actingAs($this->caso->solicitud->persona->usuario)->get(route('remisiones.documento.pdf', $remision))->assertRedirect(route('estudiante.home'));

        $this->assertSame(0, AuditoriaExpediente::where('accion', 'like', 'documento_%')->count());
    }

    public function test_las_remisiones_sin_formato_no_tienen_documento(): void
    {
        $antigua = Actuacion::create([
            'id_caso' => $this->caso->id_caso, 'id_profesional' => $this->profesional->id_usuario, 'tipo_actuacion' => 'Remisión',
            'fecha_hora' => '2026-09-01 10:00:00', 'destino_remision' => 'Externa', 'entidad_destino' => 'ICBF', 'descripcion' => 'Antes del formato.',
        ]);
        $cita = Actuacion::citas()->first();

        $this->actingAs($this->profesional)->get(route('remisiones.documento.pdf', $antigua))->assertNotFound();
        $this->actingAs($this->profesional)->get(route('remisiones.documento.word', $cita))->assertNotFound();
        $this->assertSame(0, RemisionExterna::count());
    }

    public function test_el_historial_ofrece_las_descargas(): void
    {
        $remision = $this->remitir();

        $this->actingAs($this->profesional)->get(route('casos.show', $this->caso))
            ->assertSee(route('remisiones.documento.pdf', $remision), false)
            ->assertSee(route('remisiones.documento.word', $remision), false)
            ->assertSee('Formato GBU-GPGR-F-016 v3');

        $this->actingAs($this->crearUsuarioConRol('Profesional'))->get(route('casos.show', $this->caso))
            ->assertDontSee(route('remisiones.documento.pdf', $remision), false);
    }
}

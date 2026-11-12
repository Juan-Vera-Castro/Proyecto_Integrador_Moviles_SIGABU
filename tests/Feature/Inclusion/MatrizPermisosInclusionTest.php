<?php

namespace Tests\Feature\Inclusion;

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\RecursosInclusion;
use App\Models\Actuacion;
use App\Models\AjusteRazonable;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Facultad;
use App\Models\FirmaProfesionalConsentimiento;
use App\Models\Inclusion\AnotacionBitacora;
use App\Models\Inclusion\Bitacora;
use App\Models\Inclusion\Borrador;
use App\Models\Inclusion\Constancia;
use App\Models\Inclusion\EntradaBitacora;
use App\Models\Inclusion\Evidencia;
use App\Models\Inclusion\Reapertura;
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
 * T-703: la matriz de permisos de la sección 2 de la spec, fila por fila y con cada rol, por
 * llamada directa a las rutas (incluidos PDF y archivos). Quien no tiene permiso no recibe la
 * página, no ve datos sensibles y no cambia nada.
 */
class MatrizPermisosInclusionTest extends TestCase
{
    use CreaEsquemaSigabu, GeneraFirmaPng, RefreshDatabase;

    private const SENSIBLE = 'Situación clínica ficticia';

    /** @var array<string, User> */
    private array $usuarios = [];

    private Caso $firmado;

    private Caso $enPapel;

    private Caso $sinConsentimiento;

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

        $this->usuarios = [
            'persona' => $this->crearUsuarioConRol('Persona', ['fecha_nacimiento' => '2000-01-15']),
            'asignado' => $this->profesional('Inclusión'),
            'otro_inclusion' => $this->profesional('Inclusión'),
            'otro_ambas' => $this->profesional('Ambas'),
            'psicologia' => $this->profesional('Salud Mental'),
            'admin' => $this->crearUsuarioConRol('Admin'),
            'otra_persona' => $this->crearUsuarioConRol('Persona', ['fecha_nacimiento' => '2000-01-15']),
        ];

        $this->firmado = $this->crearCaso();
        $this->enPapel = $this->crearCaso();
        $this->sinConsentimiento = $this->crearCaso();

        // Caso con consentimiento en línea, bitácora con soporte y entrada, y una fila de plantilla.
        $this->actingAs($this->usuarios['persona'])->postJson(route('inclusion.consentimiento.enviar', $this->firmado), $this->datosConsentimiento($this->firmado) + [
            'firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng()),
        ])->assertOk();

        $asignado = $this->usuarios['asignado'];
        $soporte = $this->subir($this->firmado, 'soporte.pdf');
        $this->actingAs($asignado)->post(route('inclusion.bitacora.guardar', $this->firmado), [
            'rol_institucional' => 'Estudiante Diurno', 'programa_dependencia' => 'Programa Ficticio', 'situacion' => self::SENSIBLE,
            'ruta_activada' => 'Ruta ficticia', 'soportes' => [$soporte],
        ])->assertSessionHasNoErrors();
        $this->actingAs($asignado)->post(route('inclusion.entradas.store', $this->firmado), [
            'fecha_hora' => '2026-11-06T09:00', 'tipo' => EntradaBitacora::ATENCION_INICIAL, 'descripcion' => 'Entrada ficticia',
        ])->assertSessionHasNoErrors();
        $this->actingAs($asignado)->post(route('inclusion.plantilla.guardar', $this->firmado), [
            'area' => 'Evaluación', 'barrera' => 'Barrera ficticia', 'descripcion' => 'Ajuste ficticio', 'responsable' => 'Docente ficticio', 'fecha_inicio' => '2026-11-06',
        ])->assertSessionHasNoErrors();

        // Caso con el consentimiento cargado en papel.
        $this->actingAs($asignado)->post(route('inclusion.consentimiento.papel.registrar', $this->enPapel), $this->datosConsentimiento($this->enPapel) + [
            'archivo' => UploadedFile::fake()->createWithContent('formato.pdf', '%PDF-1.4 formato ficticio'),
            'fecha_firma_papel' => '2026-11-05',
            'motivo_papel' => 'Motivo ficticio',
        ])->assertSessionHasNoErrors();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function profesional(string $area): User
    {
        $usuario = $this->crearUsuarioConRol('Profesional');
        $usuario->forceFill(['area_profesional' => $area])->save();

        return $usuario;
    }

    private function crearCaso(): Caso
    {
        $solicitud = $this->usuarios['persona']->persona->solicitudes()->create(['tipo_servicio' => 'Inclusión', 'descripcion' => 'Prueba']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
        $caso->update(['estado_caso' => Caso::ESTADO_ABIERTO, 'id_profesional' => $this->usuarios['asignado']->id_usuario]);

        return $caso->fresh();
    }

    private function datosConsentimiento(Caso $caso): array
    {
        return [
            'id_version' => ConsentimientoInclusion::versionDe($caso)->id_version,
            'nombre_persona' => 'Persona Ficticia', 'documento_persona' => '900333555', 'numero_contacto' => '300 000-0000',
            'vinculacion' => 'Estudiante Diurno', 'facultad' => 'Facultad Ficticia', 'programa_academico' => 'Programa Ficticio',
            'semestre' => '3', 'dependencia' => 'N/A', 'decision_acompanamiento' => '1', 'decision_datos_generales' => '1', 'decision_datos_sensibles' => '1',
        ];
    }

    private function subir(Caso $caso, string $nombre): string
    {
        return $this->actingAs($this->usuarios['asignado'])
            ->postJson(route('inclusion.evidencias.store', $caso), ['archivo' => UploadedFile::fake()->create($nombre, 20, 'application/pdf')])
            ->assertCreated()->json('id');
    }

    /**
     * Cada fila: [acción, método, dirección, datos, quiénes pueden]. "asignado" es el profesional
     * del caso; los demás profesionales son "otro" en la matriz.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: array, 4: list<string>}>
     */
    private function matriz(): array
    {
        $aceptacion = ConsentimientoAceptacion::where('id_caso', $this->firmado->id_caso)->firstOrFail();
        $papel = ConsentimientoAceptacion::where('id_caso', $this->enPapel->id_caso)->firstOrFail();
        $entrada = EntradaBitacora::firstOrFail();
        $soporte = Evidencia::where('destino', Evidencia::DESTINO_SOPORTE)->firstOrFail();
        $temporal = $this->subir($this->firmado, 'temporal.pdf');
        $firma = ['firma' => 'data:image/png;base64,'.base64_encode($this->firmaPng())];
        $c = $this->firmado;
        $s = $this->sinConsentimiento;

        return [
            ['Diligenciar el consentimiento', 'GET', route('inclusion.consentimiento.formulario', $s), [], ['persona']],
            ['Firmar y enviar el consentimiento', 'POST', route('inclusion.consentimiento.enviar', $s), $this->datosConsentimiento($s) + $firma, ['persona']],
            ['Borrador del consentimiento', 'PUT', route('inclusion.borradores.guardar', [$s, Borrador::CONSENTIMIENTO]), ['datos' => ['numero_contacto' => '1']], ['persona']],
            ['Ver el consentimiento', 'GET', route('inclusion.consentimiento.ver', $c), [], ['persona', 'asignado', 'admin']],
            ['Descargar el PDF del consentimiento', 'GET', route('inclusion.consentimiento.pdf', $c), [], ['persona', 'asignado', 'admin']],
            ['Descargar el consentimiento en papel', 'GET', route('inclusion.consentimiento.papel.archivo', $papel), [], ['persona', 'asignado', 'admin']],
            ['Revocar el consentimiento', 'POST', route('inclusion.consentimiento.revocar', $c), [], ['persona']],
            ['Contrafirmar', 'POST', route('consentimientos.firmar-profesional', $aceptacion), $firma, ['asignado']],
            ['Pantalla de carga en papel', 'GET', route('inclusion.consentimiento.papel', $s), [], ['asignado']],
            ['Formato para imprimir', 'GET', route('inclusion.consentimiento.imprimible', $s), [], ['asignado']],
            ['Cargar el consentimiento en papel', 'POST', route('inclusion.consentimiento.papel.registrar', $s), $this->datosConsentimiento($s) + [
                'archivo' => UploadedFile::fake()->createWithContent('formato.pdf', '%PDF-1.4 formato ficticio'), 'fecha_firma_papel' => '2026-11-05', 'motivo_papel' => 'Motivo',
            ], ['asignado']],
            ['Ver la caracterización y la bitácora', 'GET', route('inclusion.bitacora', $c), [], ['asignado']],
            ['Editar la caracterización', 'POST', route('inclusion.bitacora.guardar', $c), ['rol_institucional' => 'Otro', 'programa_dependencia' => 'X', 'situacion' => 'Cambio', 'ruta_activada' => 'X'], ['asignado']],
            ['Ver un soporte', 'GET', route('inclusion.evidencias.archivo', $soporte), [], ['asignado']],
            ['Subir una evidencia', 'POST', route('inclusion.evidencias.store', $c), ['url' => 'https://drive.invalid/x'], ['asignado']],
            ['Quitar una evidencia', 'DELETE', route('inclusion.evidencias.quitar', $temporal), [], ['asignado']],
            ['Agregar una entrada', 'POST', route('inclusion.entradas.store', $c), ['fecha_hora' => '2026-11-06T09:30', 'tipo' => EntradaBitacora::SEGUIMIENTO, 'descripcion' => 'X'], ['asignado']],
            ['Agregar una anotación', 'POST', route('inclusion.entradas.anotar', $entrada), ['texto' => 'X'], ['asignado']],
            ['Cerrar el caso y firmar la constancia', 'POST', route('inclusion.cierre', $c), $firma, ['asignado']],
            ['Consultar el catálogo de recursos', 'GET', route('inclusion.recursos.index'), [], ['asignado', 'otro_inclusion', 'otro_ambas']],
            ['Consultar el recurso', 'GET', route('inclusion.recursos.show', Recurso::firstOrFail()), [], ['asignado', 'otro_inclusion', 'otro_ambas']],
            ['Ver la plantilla', 'GET', route('inclusion.plantilla', $c), [], ['asignado']],
            ['Llenar la plantilla', 'POST', route('inclusion.plantilla.guardar', $c), ['area' => 'Evaluación', 'barrera' => 'X', 'descripcion' => 'X', 'responsable' => 'X', 'fecha_inicio' => '2026-11-06'], ['asignado']],
            ['Borrador de la plantilla', 'PUT', route('inclusion.borradores.guardar', [$c, Borrador::PLANTILLA]), ['datos' => ['barrera' => 'X']], ['asignado']],
            ['Ver el caso para reabrir', 'GET', route('admin.casos.reapertura', ['codigo' => $c->codigo_expediente]), [], ['admin']],
            ['Reabrir un caso', 'POST', route('admin.casos.reabrir', $c), ['motivo' => 'Motivo'], ['admin']],
            ['PDF de la bitácora', 'GET', route('inclusion.bitacora.pdf', $c), [], ['asignado']],
            ['PDF de recurso y plantilla', 'GET', route('inclusion.plantilla.pdf', $c), [], ['asignado']],
            ['PDF de ajustes para compartir', 'GET', route('inclusion.plantilla.compartir', $c), [], ['asignado']],
        ];
    }

    /**
     * Cuántas filas hay de todo lo que el módulo escribe, para comprobar que nada cambió.
     *
     * @return array<string, int>
     */
    private function conteos(): array
    {
        return collect([
            ConsentimientoAceptacion::class, FirmaProfesionalConsentimiento::class, Bitacora::class, EntradaBitacora::class,
            AnotacionBitacora::class, Constancia::class, Reapertura::class, AjusteRazonable::class, Evidencia::class, Borrador::class,
        ])->mapWithKeys(fn (string $modelo) => [class_basename($modelo) => $modelo::count()])->all()
            + [
                'evidencias_vigentes' => Evidencia::where('estado', '!=', Evidencia::DESCARTADA)->count(),
                'bitacora' => (string) Bitacora::value('situacion'),
                'casos_abiertos' => Caso::where('estado_caso', Caso::ESTADO_ABIERTO)->count(),
                'descargas' => AuditoriaExpediente::where('accion', 'like', '%pdf')->count(),
            ];
    }

    public function test_quien_no_tiene_permiso_no_recibe_la_pagina_ni_cambia_nada(): void
    {
        $matriz = $this->matriz();
        $antes = $this->conteos();

        foreach ($matriz as [$accion, $metodo, $url, $datos, $permitidos]) {
            foreach ($this->usuarios as $rol => $usuario) {
                if (in_array($rol, $permitidos, true)) {
                    continue;
                }

                $respuesta = $this->actingAs($usuario)->call($metodo, $url, $datos, [], [], ['HTTP_ACCEPT' => 'text/html']);

                $this->assertContains($respuesta->getStatusCode(), [302, 403, 404], "{$accion}: {$rol} recibió {$respuesta->getStatusCode()}");
                if ($respuesta->isRedirect()) {
                    $this->assertNotSame($url, $respuesta->headers->get('Location'), "{$accion}: {$rol}");
                }
                $this->assertStringNotContainsString(self::SENSIBLE, (string) $respuesta->getContent(), "{$accion}: {$rol} vio datos sensibles");
                $this->assertStringNotContainsString('%PDF', (string) $respuesta->getContent(), "{$accion}: {$rol} recibió un archivo");
            }
        }

        $this->assertSame($antes, $this->conteos());
    }

    public function test_quien_tiene_permiso_abre_cada_pagina_y_descarga(): void
    {
        foreach ($this->matriz() as [$accion, $metodo, $url, , $permitidos]) {
            if ($metodo !== 'GET') {
                continue;
            }

            foreach ($permitidos as $rol) {
                $this->actingAs($this->usuarios[$rol])->get($url)->assertOk();
            }
        }

        $this->assertTrue(true);
    }

    public function test_el_profesional_solo_de_psicologia_no_ve_nada_del_modulo(): void
    {
        $psicologia = $this->usuarios['psicologia'];

        $this->actingAs($psicologia)->get(route('profesional.salud-mental'))->assertDontSee('Recursos de inclusión');
        $this->assertNotSame(200, $this->actingAs($psicologia)->get(route('profesional.expediente.show', $this->firmado))->getStatusCode());
    }

    public function test_al_cambiar_el_profesional_el_anterior_pierde_el_acceso(): void
    {
        $nuevo = $this->usuarios['otro_inclusion'];
        $this->firmado->forceFill(['id_profesional' => $nuevo->id_usuario])->save();

        $this->actingAs($nuevo)->get(route('inclusion.bitacora', $this->firmado))->assertOk()->assertSee(self::SENSIBLE);
        $this->assertNotSame(200, $this->actingAs($this->usuarios['asignado'])->get(route('inclusion.bitacora', $this->firmado))->getStatusCode());
        $this->assertNotSame(200, $this->actingAs($this->usuarios['asignado'])->get(route('inclusion.bitacora.pdf', $this->firmado))->getStatusCode());
    }
}

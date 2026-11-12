<?php

namespace Tests\Feature;

use App\Models\RepositorioDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * Los equipos de Bienestar (profesional, VBG, Alimentos) y el administrador suben archivos al
 * repositorio. Los archivos quedan en un disco privado y se descargan a través de la app.
 */
class RepositorioArchivosTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(RepositorioDocumento::DISCO);
        $this->crearEsquemaSigabu();
    }

    private function subir(User $usuario, array $cambios = [])
    {
        return $this->actingAs($usuario)->from(route('repositorio.index'))->post(route('repositorio.store'), array_merge([
            'titulo' => 'Ruta de atención VBG',
            'categoria' => 'Protocolo',
            'audiencia' => 'Profesionales',
            'archivo' => UploadedFile::fake()->create('ruta-atencion.pdf', 300, 'application/pdf'),
        ], $cambios));
    }

    /**
     * DADO cualquiera de los cuatro roles que alimentan el repositorio,
     * CUANDO sube un archivo,
     * ENTONCES queda guardado en el disco privado con su nombre, tamaño y huella.
     */
    public function test_los_cuatro_roles_suben_archivos(): void
    {
        foreach (['Admin', 'Profesional', 'VBG', 'Alimentos'] as $rol) {
            $this->subir($this->crearUsuarioConRol($rol), ['titulo' => "Documento de {$rol}"])
                ->assertRedirect(route('repositorio.index'))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(4, RepositorioDocumento::count());
        $documento = RepositorioDocumento::first();
        $this->assertSame(['ruta-atencion.pdf', 307200], [$documento->archivo_nombre, $documento->archivo_bytes]);
        $this->assertNull($documento->url);
        $this->assertSame(64, strlen($documento->archivo_sha256));
        Storage::disk(RepositorioDocumento::DISCO)->assertExists($documento->archivo_ruta);
        $this->assertSame('300 KB', $documento->tamanoLegible());
    }

    public function test_el_estudiante_no_sube_archivos(): void
    {
        $this->subir($this->crearUsuarioConRol('Persona'))->assertRedirect(route('estudiante.home'));

        $this->assertSame(0, RepositorioDocumento::count());
        $this->assertSame([], Storage::disk(RepositorioDocumento::DISCO)->allFiles());
    }

    public function test_valida_el_archivo(): void
    {
        $vbg = $this->crearUsuarioConRol('VBG');

        $this->subir($vbg, ['archivo' => null])->assertSessionHasErrorsIn('documento', ['archivo', 'url']);
        $this->subir($vbg, ['archivo' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrorsIn('documento', ['archivo']);
        $this->subir($vbg, ['archivo' => UploadedFile::fake()->create('grande.pdf', RepositorioDocumento::ARCHIVO_MAX_KB + 1, 'application/pdf')])->assertSessionHasErrorsIn('documento', ['archivo']);

        $this->assertSame(0, RepositorioDocumento::count());

        // Un enlace sigue sirviendo sin archivo.
        $this->subir($vbg, ['archivo' => null, 'url' => 'https://uceva.edu.co/ley-1257.pdf'])->assertSessionHasNoErrors();
        $this->assertSame(1, RepositorioDocumento::count());
    }

    /**
     * Se descarga con su nombre original, solo por quien puede verlo.
     */
    public function test_descarga_el_archivo_con_permisos(): void
    {
        $alimentos = $this->crearUsuarioConRol('Alimentos');
        $this->subir($alimentos, ['archivo' => UploadedFile::fake()->createWithContent('informe.txt', 'Informe de transparencia')]);
        $documento = RepositorioDocumento::sole();
        $url = route('repositorio.archivo', $documento);

        $respuesta = $this->actingAs($this->crearUsuarioConRol('Profesional'))->get($url)->assertOk();
        $this->assertStringContainsString('informe.txt', $respuesta->headers->get('Content-Disposition'));
        $this->assertSame('Informe de transparencia', $respuesta->streamedContent());

        // Para equipos: el estudiante no lo descarga.
        $estudiante = $this->crearUsuarioConRol('Persona');
        $this->actingAs($estudiante)->get($url)->assertNotFound();

        // Publicado para todos, sí, y lo ve en Recursos.
        $this->subir($alimentos, ['titulo' => 'Guía para estudiantes', 'audiencia' => 'Todos', 'archivo' => UploadedFile::fake()->createWithContent('guia.txt', 'Guía')]);
        $paraTodos = RepositorioDocumento::where('titulo', 'Guía para estudiantes')->sole();
        $this->actingAs($estudiante)->get(route('recursos.index'))->assertSee(route('repositorio.archivo', $paraTodos), false);
        $this->actingAs($estudiante)->get(route('repositorio.archivo', $paraTodos))->assertOk();

        // Retirado, ya no se descarga (pero el archivo no se borra).
        $this->actingAs($alimentos)->patch(route('repositorio.estado', $paraTodos));
        $this->actingAs($estudiante)->get(route('repositorio.archivo', $paraTodos))->assertNotFound();
        Storage::disk(RepositorioDocumento::DISCO)->assertExists($paraTodos->archivo_ruta);
    }

    public function test_cada_uno_retira_lo_suyo_y_el_admin_cualquiera(): void
    {
        $autor = $this->crearUsuarioConRol('Profesional');
        $this->subir($autor);
        $documento = RepositorioDocumento::sole();

        $this->actingAs($this->crearUsuarioConRol('VBG'))->patch(route('repositorio.estado', $documento))->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('VBG'))->get(route('repositorio.index'))->assertDontSee('Retirar');

        $this->actingAs($autor)->patch(route('repositorio.estado', $documento))->assertRedirect(route('repositorio.index'));
        $this->assertFalse($documento->fresh()->activo);

        // El autor sigue viendo su documento retirado para volver a publicarlo; los demás no.
        $this->actingAs($autor)->get(route('repositorio.index'))->assertSee('Publicar de nuevo');
        $this->actingAs($this->crearUsuarioConRol('VBG'))->get(route('repositorio.index'))->assertDontSee('Ruta de atención VBG');

        $this->actingAs($this->crearUsuarioConRol('Admin'))->patch(route('repositorio.estado', $documento))->assertRedirect(route('repositorio.index'));
        $this->assertTrue($documento->fresh()->activo);
    }
}

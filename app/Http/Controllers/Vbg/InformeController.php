<?php

namespace App\Http\Controllers\Vbg;

use App\Documentos\PdfInclusion;
use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\RepositorioDocumento;
use App\Models\Vbg\Auditoria;
use App\Models\Vbg\Informe;
use App\Models\Vbg\Reporte;
use App\Models\Vbg\Transcripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Informe de análisis de un caso VBG (integrado desde la rama VIOLENCIA_GENERO): resumen,
 * nivel de riesgo, tipos de violencia, marco legal (catálogo y documentos del Repositorio) y
 * ruta de atención, con PDF. Lo redacta el profesional; cada versión queda en el historial.
 */
class InformeController extends Controller
{
    use ExigeCasoACargo;

    public const CATEGORIAS_NORMATIVAS = ['Ley o norma', 'Protocolo'];

    public function create(Request $request, Reporte $reporte): View
    {
        $this->exigirGestionable($request, $reporte);

        return view('vbg.informe', [
            'reporte' => $reporte,
            'anterior' => Informe::where('id_reporte', $reporte->id_reporte)->latest('creado_en')->first(),
            'transcripciones' => $this->transcripcionesDe($reporte),
            'documentos' => $this->documentosNormativos(),
            'marcoLegal' => config('sigabu.vbg.marco_legal'),
            'rutaAtencion' => config('sigabu.vbg.ruta_atencion'),
        ]);
    }

    public function store(Request $request, Reporte $reporte): RedirectResponse
    {
        $this->exigirGestionable($request, $reporte);

        $catalogoLegal = config('sigabu.vbg.marco_legal');
        $catalogoRuta = config('sigabu.vbg.ruta_atencion');
        $transcripciones = $this->transcripcionesDe($reporte)->pluck('id_transcripcion')->all();

        $datos = $request->validate([
            'resumen' => ['required', 'string', 'max:'.Informe::RESUMEN_MAX],
            'nivel_riesgo' => ['required', Rule::in(Reporte::NIVELES_RIESGO)],
            'tipos_violencia' => ['required', 'array', 'min:1'],
            'tipos_violencia.*' => [Rule::in(Reporte::TIPOS_VIOLENCIA)],
            'marco_legal' => ['nullable', 'array'],
            'marco_legal.*' => ['integer', 'between:0,'.(count($catalogoLegal) - 1)],
            'documentos' => ['nullable', 'array'],
            'documentos.*' => ['integer', Rule::in($this->documentosNormativos()->pluck('id_documento')->all())],
            'ruta_atencion' => ['nullable', 'array'],
            'ruta_atencion.*' => ['integer', 'between:0,'.(count($catalogoRuta) - 1)],
            'ruta_otros' => ['nullable', 'string', 'max:3000'],
            'id_transcripcion' => ['nullable', Rule::in($transcripciones)],
        ], [
            'resumen.required' => 'Escribe el resumen del análisis.',
            'nivel_riesgo.required' => 'Selecciona el nivel de riesgo.',
            'tipos_violencia.required' => 'Marca al menos un tipo de violencia.',
            'id_transcripcion.in' => 'Selecciona una transcripción completa de este caso.',
        ]);

        $ruta = collect($datos['ruta_atencion'] ?? [])->unique()->sort()->map(fn ($i) => $catalogoRuta[$i])
            ->merge(collect(preg_split('/\R/', (string) ($datos['ruta_otros'] ?? '')))->map(fn ($l) => trim($l))->filter()
                ->map(fn ($l) => ['titulo' => mb_substr($l, 0, 200), 'descripcion' => '']))
            ->values()->all();

        if ($ruta === []) {
            return back()->withInput()->withErrors(['ruta_atencion' => 'Indica al menos un paso de la ruta de atención.']);
        }

        $informe = Informe::create([
            'id_reporte' => $reporte->id_reporte,
            'id_transcripcion' => $datos['id_transcripcion'] ?? null,
            'resumen' => trim($datos['resumen']),
            'nivel_riesgo' => $datos['nivel_riesgo'],
            'tipos_violencia' => array_values(array_unique($datos['tipos_violencia'])),
            'marco_legal' => collect($datos['marco_legal'] ?? [])->unique()->sort()->map(fn ($i) => $catalogoLegal[$i])->values()->all(),
            'documentos_repositorio' => array_values(array_unique(array_map('intval', $datos['documentos'] ?? []))) ?: null,
            'ruta_atencion' => $ruta,
            'origen' => Informe::MANUAL,
            'editado_por_profesional' => true,
            'id_profesional' => $request->user()->id_usuario,
            'creado_en' => now(Actuacion::ZONA_HORARIA)->format('Y-m-d H:i:s.u'),
        ]);

        Auditoria::registrar(Auditoria::INFORME_REGISTRADO, $reporte->id_reporte, ['id_informe' => $informe->id_informe]);

        return $this->volverAlCaso($reporte, 'informe', 'Informe de análisis guardado.');
    }

    public function pdf(Request $request, Informe $informe): Response
    {
        $reporte = $informe->reporte;
        abort_unless($reporte, 404);
        $this->exigirACargo($request, $reporte);

        Auditoria::registrar(Auditoria::INFORME_PDF, $reporte->id_reporte, ['id_informe' => $informe->id_informe]);

        return PdfInclusion::descargar('documentos.vbg-informe', [
            'informe' => $informe->load('profesional.persona', 'transcripcion.audio.cita'),
            'reporte' => $reporte,
            'documentos' => RepositorioDocumento::whereIn('id_documento', $informe->documentos_repositorio ?? [])->orderBy('titulo')->get(),
            'logo' => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-uceva.jpg'))),
            'ciudad' => config('sigabu.ciudad'),
            'generado' => now(Actuacion::ZONA_HORARIA),
        ], 'Informe-VBG-'.$reporte->codigo.'-'.now(Actuacion::ZONA_HORARIA)->format('d-m-y').'.pdf');
    }

    /**
     * Transcripciones completas de los audios de las citas del caso.
     */
    private function transcripcionesDe(Reporte $reporte): Collection
    {
        return Transcripcion::with('audio.cita')
            ->where('estado', Transcripcion::COMPLETADA)
            ->whereHas('audio.cita', fn ($q) => $q->where('id_reporte', $reporte->id_reporte))
            ->get()
            ->filter(fn (Transcripcion $t) => $t->estaCompleta())
            ->values();
    }

    private function documentosNormativos(): Collection
    {
        return RepositorioDocumento::activos()->whereIn('categoria', self::CATEGORIAS_NORMATIVAS)->orderBy('titulo')->get();
    }
}

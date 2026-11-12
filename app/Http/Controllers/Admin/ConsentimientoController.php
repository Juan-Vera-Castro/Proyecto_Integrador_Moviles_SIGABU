<?php

namespace App\Http\Controllers\Admin;

use App\Documentos\TextoConsentimiento;
use App\Http\Controllers\Controller;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use App\Models\VersionConsentimiento;
use Closure;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * HU-009: parametrización de los modelos de consentimiento y de las versiones de su texto.
 */
class ConsentimientoController extends Controller
{
    public function index(): View
    {
        $modelos = ModeloConsentimiento::with('versionVigente')
            ->withCount('versiones')
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        return view('admin.consentimientos.index', [
            'modelos' => $modelos,
            'servicios' => ModeloConsentimiento::SERVICIOS,
            'poblaciones' => ModeloConsentimiento::POBLACIONES,
            'exigencia' => $this->estadoExigencia(),
        ]);
    }

    /**
     * Si el consentimiento firmado se exige y qué falta para que funcione (D-28): cada texto
     * por publicar y las claves del almacenamiento de firmas.
     *
     * @return array{exigir: bool, desde: ?string, faltantes: list<string>}
     */
    private function estadoExigencia(): array
    {
        $faltantes = [];

        foreach (Caso::SERVICIOS_CON_CONSENTIMIENTO as $servicio) {
            foreach (ModeloConsentimiento::POBLACIONES as $poblacion) {
                if (! ModeloConsentimiento::versionVigenteDe($servicio, $poblacion)) {
                    $faltantes[] = "texto publicado de {$servicio}".($poblacion === ModeloConsentimiento::POBLACION_MENORES ? ' para menores de edad' : '');
                }
            }
        }

        if (! ConsentimientoAceptacion::almacenamientoListo()) {
            $faltantes[] = 'claves S3 del almacenamiento de firmas (SIGABU_S3_* en el .env)';
        }

        return [
            'exigir' => (bool) config('sigabu.consentimiento.exigir'),
            'desde' => config('sigabu.consentimiento.desde'),
            'faltantes' => $faltantes,
        ];
    }

    public function show(ModeloConsentimiento $modelo): View
    {
        // Cuántos consentimientos se aceptaron con cada versión: siguen ligados a ella aunque se publique otra.
        $modelo->load([
            'versiones' => fn ($q) => $q->withCount(['aceptaciones', 'consentimientos']),
            'versiones.autor.persona',
            'versionVigente',
        ]);

        return view('admin.consentimientos.show', [
            'modelo' => $modelo,
            'borrador' => $modelo->versiones->firstWhere('estado', VersionConsentimiento::BORRADOR),
            'servicios' => ModeloConsentimiento::SERVICIOS,
            'poblaciones' => ModeloConsentimiento::POBLACIONES,
            'textoMax' => VersionConsentimiento::TEXTO_MAX,
            'marcadores' => TextoConsentimiento::MARCADORES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['codigo' => mb_strtoupper(trim((string) $request->input('codigo')))]);
        $request->mergeIfMissing(['poblacion' => ModeloConsentimiento::POBLACION_GENERAL]);

        $datos = $request->validateWithBag('modelo', [
            'codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::notIn([ModeloConsentimiento::CODIGO_LEGADO]), Rule::unique('modelos_consentimiento', 'codigo')],
            ...$this->reglasModelo(),
        ], $this->mensajes());

        // Si el servicio ya tiene un modelo activo para esa población, el nuevo nace inactivo para no reemplazarlo sin querer.
        $otro = $this->otroModeloActivo($datos['tipo_servicio'], $datos['poblacion']);
        $modelo = ModeloConsentimiento::create([...$datos, 'activo' => ! $otro]);

        $aviso = $otro
            ? "Quedó inactivo porque {$datos['tipo_servicio']} ({$datos['poblacion']}) ya usa \"{$otro->nombre}\"."
            : 'Escribe la primera versión del texto y publícala para que se use al abrir casos.';

        return redirect()->route('admin.consentimientos.show', $modelo)
            ->with('status', "Modelo {$modelo->nombre} creado. {$aviso}");
    }

    public function update(Request $request, ModeloConsentimiento $modelo): RedirectResponse
    {
        abort_if($modelo->esLegado(), 403);

        $request->mergeIfMissing(['poblacion' => $modelo->poblacion]);
        $datos = $request->validateWithBag('modelo', $this->reglasModelo(), $this->mensajes());
        $datos['activo'] = $request->boolean('activo');

        // Un servicio solo puede tener un modelo activo por población: así no hay duda de qué texto se acepta.
        if ($datos['activo'] && $otro = $this->otroModeloActivo($datos['tipo_servicio'], $datos['poblacion'], $modelo)) {
            return back()->withInput()->withErrors([
                'activo' => "El servicio {$datos['tipo_servicio']} ya tiene activo el modelo \"{$otro->nombre}\""
                    .($otro->esDeMenores() ? ' para menores de edad' : '').'. Desactívalo primero.',
            ], 'modelo');
        }

        $modelo->update($datos);

        return back()->with('status', "Modelo {$modelo->nombre} actualizado.");
    }

    /**
     * Crea el borrador de la siguiente versión. Solo puede haber un borrador por modelo.
     */
    public function storeVersion(Request $request, ModeloConsentimiento $modelo): RedirectResponse
    {
        abort_if($modelo->esLegado(), 403);

        $datos = $request->validateWithBag('version', $this->reglasTexto(), $this->mensajes());

        if ($modelo->versiones()->where('estado', VersionConsentimiento::BORRADOR)->exists()) {
            return back()->withInput()->with('error', 'Este modelo ya tiene un borrador. Edítalo o publícalo antes de crear otra versión.');
        }

        $version = $modelo->versiones()->create([
            'numero_version' => ((int) $modelo->versiones()->max('numero_version')) + 1,
            'texto' => $datos['texto'],
            'creado_por' => $request->user()->id_usuario,
        ]);

        return redirect()->route('admin.consentimientos.show', $modelo)
            ->with('status', "Borrador de la versión {$version->numero_version} guardado. Aún no se usa: publícalo cuando esté listo.");
    }

    public function updateVersion(Request $request, VersionConsentimiento $version): RedirectResponse
    {
        $volver = redirect()->route('admin.consentimientos.show', $version->id_modelo);

        if (! $version->esBorrador()) {
            return $volver->with('error', "La versión {$version->numero_version} ya fue publicada y no se puede editar. Crea una versión nueva.");
        }

        $datos = $request->validateWithBag('version', $this->reglasTexto(), $this->mensajes());

        $version->update(['texto' => $datos['texto']]);

        return $volver->with('status', "Borrador de la versión {$version->numero_version} actualizado.");
    }

    /**
     * Publica el borrador: pasa a ser la versión vigente y la anterior queda retirada.
     */
    public function publicar(VersionConsentimiento $version): RedirectResponse
    {
        $volver = redirect()->route('admin.consentimientos.show', $version->id_modelo);

        try {
            $version->publicar();
        } catch (DomainException $e) {
            return $volver->with('error', $e->getMessage());
        }

        $aviso = $version->modelo->activo
            ? 'Desde ahora es la que se acepta al abrir casos'
            : 'El modelo está inactivo: actívalo para que se use al abrir casos';

        return $volver->with('status', "Versión {$version->numero_version} publicada. {$aviso}.");
    }

    private function reglasModelo(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo_servicio' => ['required', Rule::in(ModeloConsentimiento::SERVICIOS)],
            'poblacion' => ['required', Rule::in(ModeloConsentimiento::POBLACIONES)],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function reglasTexto(): array
    {
        return [
            'texto' => ['required', 'string', 'min:20', 'max:'.VersionConsentimiento::TEXTO_MAX, function (string $atributo, mixed $valor, Closure $falla) {
                // Solo marcadores que el sistema sabe llenar (D-24): uno mal escrito se vería tal cual en pantalla.
                if ($desconocidos = TextoConsentimiento::marcadoresDesconocidos((string) $valor)) {
                    $falla('Estos marcadores no existen: {'.implode('}, {', $desconocidos).'}. Revisa la lista de marcadores disponibles.');
                }
            }],
        ];
    }

    private function otroModeloActivo(string $servicio, string $poblacion, ?ModeloConsentimiento $excepto = null): ?ModeloConsentimiento
    {
        return ModeloConsentimiento::activos()
            ->where('tipo_servicio', $servicio)
            ->where('poblacion', $poblacion)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto->id_modelo))
            ->first();
    }

    private function mensajes(): array
    {
        return [
            'codigo.required' => 'Escribe un código para el modelo, por ejemplo SM-CONSENT.',
            'codigo.max' => 'El código no puede superar los 50 caracteres.',
            'codigo.regex' => 'El código solo puede tener letras, números, guiones y guiones bajos.',
            'codigo.not_in' => 'El código LEGADO está reservado para los consentimientos anteriores.',
            'codigo.unique' => 'Ya existe un modelo con ese código.',
            'nombre.required' => 'Escribe el nombre del modelo.',
            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
            'tipo_servicio.required' => 'Selecciona el servicio que usa este consentimiento.',
            'tipo_servicio.in' => 'Selecciona un servicio de la lista.',
            'poblacion.required' => 'Indica si el consentimiento es general o para menores de edad.',
            'poblacion.in' => 'Indica si el consentimiento es general o para menores de edad.',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
            'texto.required' => 'Escribe el texto del consentimiento.',
            'texto.min' => 'El texto del consentimiento debe tener al menos 20 caracteres.',
            'texto.max' => 'El texto no puede superar los '.VersionConsentimiento::TEXTO_MAX.' caracteres.',
        ];
    }
}

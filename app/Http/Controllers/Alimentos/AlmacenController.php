<?php

namespace App\Http\Controllers\Alimentos;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarConsentimientoRequest;
use App\Models\Actuacion;
use App\Models\Alimentos\Donante;
use App\Models\Alimentos\Entrega;
use App\Models\Alimentos\Lote;
use App\Models\Alimentos\Priorizado;
use App\Models\Alimentos\Solicitud;
use App\Models\ConsentimientoAceptacion;
use App\Models\Persona;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Espacio del equipo de Donación Alimentaria "Ucevistas de Corazón":
 * 1. Gestión de Donantes y Entradas (inventario de ingreso).
 * 2. Caracterización y Solicitudes de Estudiantes (priorización y demanda).
 * 3. Control de Inventario y Despachos en Ventanilla (salidas con verificación y firma).
 * 4. Reportes, Auditoría, Analítica y Certificados Oficiales en PDF.
 */
class AlmacenController extends Controller
{
    public const PESTANAS = ['solicitudes', 'inventario', 'despachos', 'donantes', 'entregas', 'reportes'];

    public function index(Request $request): View
    {
        $pestana = in_array($request->query('tab'), self::PESTANAS, true) ? $request->query('tab') : 'solicitudes';
        $hoy = now(Actuacion::ZONA_HORARIA);

        $lotes = Lote::with('donante')->orderByDesc('fecha_ingreso')->orderByDesc('id_lote')->get();
        $despachables = Lote::despachables()->get();

        $solicitudesAbiertas = Solicitud::with(['persona', 'entrega.lote'])->abiertas()->porPrioridad()->get();
        $todasSolicitudes = Solicitud::with(['persona', 'entrega.lote'])->porPrioridad()->limit(200)->get();

        $donantes = Donante::withCount('lotes')->with('lotes')->orderByDesc('activo')->orderBy('nombre')->get();
        $entregas = Entrega::with(['solicitud.persona', 'lote', 'responsable.persona'])->orderByDesc('fecha_hora')->limit(100)->get();

        // Beneficiarios únicos que han recibido entregas
        $beneficiariosUnicos = Entrega::join('alim_solicitudes', 'alim_entregas.id_solicitud', '=', 'alim_solicitudes.id_solicitud')
            ->distinct('alim_solicitudes.id_persona')
            ->count('alim_solicitudes.id_persona');

        // Lote que debe despacharse primero según FEFO
        $proximoLoteFefo = $despachables->first();

        $resumen = [
            'mercados' => $despachables->sum('cantidad_disponible'),
            'mercadosA' => $despachables->where('tipo', 'Mercado Tipo A')->sum('cantidad_disponible'),
            'mercadosB' => $despachables->where('tipo', 'Mercado Tipo B')->sum('cantidad_disponible'),
            'elementosSueltos' => $lotes->where('tipo', 'Elemento suelto')->sum('cantidad_disponible'),
            'pendientes' => Solicitud::where('estado', Solicitud::PENDIENTE)->count(),
            'aprobadas' => Solicitud::where('estado', Solicitud::APROBADA)->count(),
            'entregadasTotal' => Entrega::count(),
            'entregasMes' => Entrega::where('fecha_hora', '>=', $hoy->copy()->startOfMonth()->toDateTimeString())->count(),
            'porVencer' => $lotes->filter(fn (Lote $lote) => $lote->venceProximamente() && ! $lote->estaVencido())->count(),
            'vencidos' => $lotes->filter(fn (Lote $lote) => $lote->estaVencido() && $lote->cantidad_disponible > 0)->count(),
            'donantesTotal' => $donantes->count(),
            'donantesActivos' => $donantes->where('activo', true)->count(),
            'coberturaEstudiantil' => $beneficiariosUnicos,
            'proximoLoteFefo' => $proximoLoteFefo,
        ];

        return view('almacen.home', [
            'pestana' => $pestana,
            'solicitudes' => $solicitudesAbiertas,
            'todasSolicitudes' => $todasSolicitudes,
            'lotes' => $lotes,
            'despachables' => $despachables,
            'donantes' => $donantes,
            'entregas' => $entregas,
            'resumen' => $resumen,
            // Documento => prioridad indicada por Trabajo Social, para marcarlos en las solicitudes.
            'priorizados' => Priorizado::pluck('prioridad', 'documento'),
        ]);
    }

    public function storeDonante(Request $request): RedirectResponse
    {
        $request->merge([
            'nombre' => trim((string) $request->input('nombre')),
            'contacto' => trim((string) $request->input('contacto')) ?: null,
        ]);

        $datos = $request->validateWithBag('donante', [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::in(Donante::TIPOS)],
            'categoria' => ['required', Rule::in(Donante::CATEGORIAS[$request->input('tipo')] ?? [])],
            'anonimo' => ['nullable', 'boolean'],
            'contacto' => ['nullable', 'string', 'max:150'],
        ], [
            'nombre.required' => 'Escribe el nombre del donante.',
            'tipo.required' => 'Indica si el donante es interno o externo.',
            'categoria.required' => 'Selecciona la categoría.',
            'categoria.in' => 'Selecciona una categoría que corresponda al tipo de donante.',
        ]);

        Donante::create($datos + [
            'anonimo' => $request->boolean('anonimo'),
            'activo' => true,
            'fecha_registro' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
        ]);

        return redirect()->route('almacen.home', ['tab' => 'donantes'])->with('status', "Donante {$datos['nombre']} registrado exitosamente.");
    }

    public function cambiarEstadoDonante(Donante $donante): RedirectResponse
    {
        $donante->update(['activo' => ! $donante->activo]);

        return redirect()->route('almacen.home', ['tab' => 'donantes'])->with('status', $donante->activo ? 'Donante activado.' : 'Donante desactivado. Sus lotes y su historial se conservan.');
    }

    /**
     * Generación y descarga del Certificado Oficial de Donación en PDF.
     */
    public function certificadoDonante(Donante $donante): Response
    {
        $lotes = $donante->lotes()->orderByDesc('fecha_ingreso')->get();
        $totalMercados = $lotes->sum('cantidad_inicial');
        $generado = now(Actuacion::ZONA_HORARIA);

        $logoPath = public_path('images/logo-uceva.jpg');
        $logo = (file_exists($logoPath) && filesize($logoPath) > 500)
            ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('documentos.certificado-donacion', [
            'donante' => $donante,
            'lotes' => $lotes,
            'totalMercados' => $totalMercados,
            'generado' => $generado,
            'generadoPor' => auth()->user()?->persona?->nombre_completo ?? 'Personal de Almacén y Donaciones',
            'ciudad' => config('sigabu.ciudad', 'Tuluá'),
            'logo' => $logo,
        ])->setPaper('letter');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Certificado-Donacion-'.str_pad($donante->id_donante, 4, '0', STR_PAD_LEFT).'-'.str($donante->nombre)->slug().'.pdf"',
        ]);
    }

    /**
     * Ingreso a bodega: un lote nuevo con su código LT-AAAA-NNN.
     */
    public function storeLote(Request $request): RedirectResponse
    {
        $request->merge(['descripcion' => trim((string) $request->input('descripcion'))]);
        $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();

        $datos = $request->validateWithBag('lote', [
            'tipo' => ['required', Rule::in(Lote::TIPOS)],
            'descripcion' => ['required', 'string', 'max:200'],
            'cantidad' => ['required', 'integer', 'between:1,10000'],
            'fecha_ingreso' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$hoy],
            'fecha_vencimiento' => ['nullable', 'date_format:Y-m-d', 'after:fecha_ingreso'],
            'id_donante' => ['nullable', 'integer', Rule::exists('alim_donantes', 'id_donante')],
        ], [
            'tipo.required' => 'Selecciona el tipo de producto.',
            'descripcion.required' => 'Describe el contenido del lote.',
            'cantidad.required' => 'Indica la cantidad que ingresa.',
            'cantidad.between' => 'La cantidad debe estar entre 1 y 10.000.',
            'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'fecha_vencimiento.after' => 'El vencimiento debe ser posterior a la fecha de ingreso.',
            'id_donante.exists' => 'Selecciona un donante de la lista.',
        ]);

        $lote = DB::transaction(function () use ($datos, $request) {
            $anio = substr($datos['fecha_ingreso'], 0, 4);
            $consecutivo = Lote::where('codigo_lote', 'like', "LT-{$anio}-%")->lockForUpdate()->pluck('codigo_lote')->count() + 1;

            return Lote::create([
                'codigo_lote' => sprintf('LT-%s-%03d', $anio, $consecutivo),
                'tipo' => $datos['tipo'],
                'descripcion' => $datos['descripcion'],
                'cantidad_inicial' => (int) $datos['cantidad'],
                'cantidad_disponible' => (int) $datos['cantidad'],
                'fecha_ingreso' => $datos['fecha_ingreso'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'id_donante' => $datos['id_donante'] ?? null,
                'registrado_por' => $request->user()->id_usuario,
            ]);
        });

        return redirect()->route('almacen.home', ['tab' => 'inventario'])->with('status', "Lote {$lote->codigo_lote} registrado: {$lote->cantidad_inicial} × {$lote->tipo}.");
    }

    public function aprobar(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->exigirEstado($solicitud, Solicitud::PENDIENTE);

        $solicitud->update([
            'estado' => Solicitud::APROBADA,
            'revisada_por' => $request->user()->id_usuario,
            'fecha_revision' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
        ]);

        return $this->volver("Solicitud de {$solicitud->persona?->nombre_completo} aprobada: ya se le puede entregar el mercado.");
    }

    public function rechazar(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->exigirEstado($solicitud, Solicitud::PENDIENTE);
        $request->merge(['motivo_rechazo' => trim((string) $request->input('motivo_rechazo'))]);

        $datos = $request->validateWithBag('revision-'.$solicitud->id_solicitud, [
            'motivo_rechazo' => ['required', 'string', 'max:'.Solicitud::MOTIVO_RECHAZO_MAX],
        ], [
            'motivo_rechazo.required' => 'Escribe el motivo; el estudiante lo verá.',
        ]);

        $solicitud->update([
            'estado' => Solicitud::RECHAZADA,
            'motivo_rechazo' => $datos['motivo_rechazo'],
            'revisada_por' => $request->user()->id_usuario,
            'fecha_revision' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
        ]);

        return $this->volver("Solicitud de {$solicitud->persona?->nombre_completo} rechazada.");
    }

    /**
     * Entrega de un mercado a una solicitud aprobada:
     * Verifica la identidad del estudiante, descuenta del lote según FEFO y registra la entrega inmutable.
     */
    public function entregar(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $bolsa = 'entrega-'.$solicitud->id_solicitud;
        $request->merge(['documento' => preg_replace('/\D/', '', (string) $request->input('documento'))]);

        $datos = $request->validateWithBag($bolsa, [
            'id_lote' => ['required', 'integer'],
            'metodo_validacion' => ['required', Rule::in(Entrega::METODOS_VALIDACION)],
            'documento' => ['required', 'string', 'max:20'],
            'firma' => ['required', 'string'],
        ], [
            'id_lote.required' => 'Selecciona el lote del que sale el mercado.',
            'metodo_validacion.required' => 'Indica cómo se verificó la identidad.',
            'documento.required' => 'Escribe el número del documento que presentó.',
            'firma.required' => 'Falta la firma de recepción del estudiante.',
        ]);

        // Firma de recepción: PNG con trazo, como las firmas de los consentimientos.
        $volverAlDespacho = route('almacen.home', ['tab' => 'despachos']).'#despacho';
        [$png, $errorFirma] = RegistrarConsentimientoRequest::leerFirma($datos['firma']);
        if ($errorFirma) {
            throw ValidationException::withMessages(['firma' => $errorFirma])->errorBag($bolsa)->redirectTo($volverAlDespacho);
        }

        // Nombre único por intento: un intento fallido nunca toca la firma de una entrega ya hecha.
        $ruta = 'entregas-alimentos/'.$solicitud->id_solicitud.'-'.Str::uuid().'.png';
        try {
            Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->put($ruta, $png);
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['firma' => 'No se pudo guardar la firma. No se entregó el mercado; intenta de nuevo.'])->errorBag($bolsa)->redirectTo($volverAlDespacho);
        }

        try {
            $this->registrarEntrega($request, $solicitud, $datos, $bolsa, $ruta, hash('sha256', $png));
        } catch (Throwable $e) {
            // Nunca queda una firma sin su entrega.
            rescue(fn () => Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->delete($ruta));
            throw $e;
        }

        return redirect()->route('almacen.home', ['tab' => 'despachos'])->with('status', "Mercado entregado a {$solicitud->persona?->nombre_completo}, con su firma de recepción. La entrega queda registrada y no se puede modificar.");
    }

    /**
     * Firma de recepción de una entrega. Solo el equipo de alimentos; no se cachea.
     */
    public function firmaEntrega(Entrega $entrega): Response
    {
        abort_unless($entrega->firma_ruta, 404);

        try {
            $png = (string) Storage::disk(ConsentimientoAceptacion::DISCO_FIRMAS)->get($entrega->firma_ruta);
        } catch (Throwable $e) {
            report($e);
            abort(503, 'No se pudo obtener la firma en este momento.');
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="firma-entrega.png"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Firma-Integra' => hash_equals((string) $entrega->firma_sha256, hash('sha256', $png)) ? 'si' : 'no',
        ]);
    }

    private function registrarEntrega(Request $request, Solicitud $solicitud, array $datos, string $bolsa, string $ruta, string $huella): void
    {
        DB::transaction(function () use ($request, $solicitud, $datos, $bolsa, $ruta, $huella) {
            $solicitud = Solicitud::whereKey($solicitud->id_solicitud)->lockForUpdate()->with('persona')->firstOrFail();
            $volver = route('almacen.home').'#solicitud-'.$solicitud->id_solicitud;

            if ($solicitud->estado !== Solicitud::APROBADA) {
                throw ValidationException::withMessages(['documento' => "La solicitud está \"{$solicitud->estado}\": solo se entregan las aprobadas."])->errorBag($bolsa)->redirectTo($volver);
            }

            if ($datos['documento'] !== preg_replace('/\D/', '', (string) $solicitud->persona?->numero_documento)) {
                throw ValidationException::withMessages(['documento' => 'El documento no coincide con el del estudiante que hizo la solicitud. No se entregó el mercado.'])->errorBag($bolsa)->redirectTo($volver);
            }

            $lote = Lote::despachables()->whereKey($datos['id_lote'])->lockForUpdate()->first();

            if (! $lote) {
                throw ValidationException::withMessages(['id_lote' => 'Ese lote ya no tiene existencias o está vencido. Elige otro.'])->errorBag($bolsa)->redirectTo($volver);
            }

            $lote->decrement('cantidad_disponible');
            $solicitud->update(['estado' => Solicitud::ENTREGADA]);

            Entrega::create([
                'id_solicitud' => $solicitud->id_solicitud,
                'id_lote' => $lote->id_lote,
                'cantidad' => 1,
                'metodo_validacion' => $datos['metodo_validacion'],
                'documento_verificado' => $datos['documento'],
                'entregado_por' => $request->user()->id_usuario,
                'fecha_hora' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
                'firma_ruta' => $ruta,
                'firma_sha256' => $huella,
            ]);
        });
    }

    /**
     * Cargue de los estudiantes priorizados por Trabajo Social (archivo CSV). No crea solicitudes:
     * guarda la prioridad indicada para cada documento y sube la de las solicitudes pendientes de
     * esos estudiantes; las que hagan después quedan con esa prioridad.
     * Columnas: codigo, documento, estrato, prioridad (con o sin fila de encabezado; coma o punto y coma).
     */
    public function cargueMasivo(Request $request): RedirectResponse
    {
        $request->validateWithBag('cargue', [
            'archivo_csv' => ['required', 'file', 'extensions:csv,txt', 'max:1024'],
        ], [
            'archivo_csv.required' => 'Selecciona el archivo CSV de Trabajo Social.',
            'archivo_csv.extensions' => 'El archivo debe ser .csv o .txt.',
            'archivo_csv.max' => 'El archivo no puede superar 1 MB.',
        ]);

        [$filas, $errores] = $this->leerPriorizados((string) file_get_contents($request->file('archivo_csv')->getRealPath()));

        if ($filas === [] && $errores === []) {
            $errores[] = 'El archivo no tiene filas con estudiantes.';
        }

        if (count($filas) > Priorizado::MAX_FILAS) {
            $errores = ['El archivo tiene más de '.Priorizado::MAX_FILAS.' estudiantes. Divídelo en partes.'];
            $filas = [];
        }

        $nuevos = $actualizados = $subieron = 0;
        $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();

        DB::transaction(function () use ($request, $filas, $ahora, &$nuevos, &$actualizados, &$subieron) {
            foreach ($filas as $fila) {
                $priorizado = Priorizado::updateOrCreate(['documento' => $fila['documento']], $fila + [
                    'cargado_por' => $request->user()->id_usuario,
                    'fecha_cargue' => $ahora,
                ]);
                $priorizado->wasRecentlyCreated ? $nuevos++ : $actualizados++;
            }

            // Las solicitudes pendientes de estos estudiantes suben a la prioridad indicada.
            $prioridades = collect($filas)->pluck('prioridad', 'documento');
            Solicitud::with('persona')->where('estado', Solicitud::PENDIENTE)
                ->whereHas('persona', fn ($q) => $q->whereIn('numero_documento', $prioridades->keys()))
                ->get()
                ->each(function (Solicitud $solicitud) use ($prioridades, &$subieron) {
                    $indicada = $prioridades[Priorizado::normalizarDocumento($solicitud->persona?->numero_documento)] ?? null;
                    if ($indicada && Priorizado::mayor($indicada, $solicitud->prioridad) !== $solicitud->prioridad) {
                        $solicitud->update(['prioridad' => $indicada]);
                        $subieron++;
                    }
                });
        });

        $conCuenta = Persona::whereIn('numero_documento', array_column($filas, 'documento'))->count();
        $mensaje = $filas === []
            ? 'No se cargó ningún estudiante.'
            : 'Priorizados de Trabajo Social cargados: '.count($filas)." ({$nuevos} nuevos, {$actualizados} actualizados). "
                ."{$conCuenta} tienen cuenta en SIGABU; {$subieron} solicitudes pendientes subieron de prioridad. "
                .'Los demás quedarán priorizados cuando soliciten.';

        return redirect()->route('almacen.home', ['tab' => 'solicitudes'])
            ->with($filas === [] ? 'error' : 'status', $mensaje)
            ->with('erroresCargue', array_slice($errores, 0, 20));
    }

    /**
     * Filas válidas del CSV (normalizadas) y los errores por fila.
     *
     * @return array{0: list<array{documento: string, codigo: ?string, estrato: ?int, prioridad: string}>, 1: list<string>}
     */
    private function leerPriorizados(string $texto): array
    {
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
        $lineas = array_values(array_filter(preg_split('/\R/', $texto), fn ($l) => trim($l) !== ''));
        if ($lineas === []) {
            return [[], []];
        }

        $separador = substr_count($lineas[0], ';') > substr_count($lineas[0], ',') ? ';' : ',';
        $primera = array_map(fn ($c) => mb_strtolower(trim($c)), str_getcsv($lineas[0], $separador));
        $primera = array_map(fn ($c) => str_replace('ó', 'o', $c), $primera);
        $conEncabezado = in_array('documento', $primera, true);
        // Sin encabezado, el orden del formato del módulo: codigo, documento, estrato, prioridad.
        $columnas = $conEncabezado ? $primera : ['codigo', 'documento', 'estrato', 'prioridad'];

        $filas = [];
        $errores = [];
        foreach (array_slice($lineas, $conEncabezado ? 1 : 0) as $i => $linea) {
            $numero = $i + ($conEncabezado ? 2 : 1);
            $valores = array_map('trim', str_getcsv($linea, $separador));
            $dato = fn (string $columna) => ($pos = array_search($columna, $columnas, true)) !== false ? ($valores[$pos] ?? '') : '';

            $documento = Priorizado::normalizarDocumento($dato('documento'));
            $estrato = $dato('estrato');
            $prioridad = collect(Solicitud::PRIORIDADES)->first(fn ($p) => mb_strtolower($p) === mb_strtolower($dato('prioridad'))) ?? ($dato('prioridad') === '' ? 'Alta' : null);

            $error = match (true) {
                $documento === null || strlen($documento) < 5 || strlen($documento) > 20 => 'falta el documento o no es válido',
                $estrato !== '' && (! ctype_digit($estrato) || $estrato < 1 || $estrato > 6) => 'el estrato debe ser un número de 1 a 6',
                $prioridad === null => 'la prioridad debe ser Alta, Media o Baja',
                default => null,
            };

            if ($error) {
                $errores[] = "Fila {$numero}: {$error}.";

                continue;
            }

            $filas[$documento] = [
                'documento' => $documento,
                'codigo' => mb_substr($dato('codigo'), 0, 30) ?: null,
                'estrato' => $estrato === '' ? null : (int) $estrato,
                'prioridad' => $prioridad,
            ];
        }

        return [array_values($filas), $errores];
    }

    /**
     * Generación y descarga del Informe Oficial de Transparencia y Control en PDF.
     */
    public function informeTransparenciaPdf(): Response
    {
        $generado = now(Actuacion::ZONA_HORARIA);
        $lotes = Lote::with('donante')->orderBy('fecha_ingreso')->get();
        $despachables = Lote::despachables()->get();
        $donantes = Donante::withCount('lotes')->orderBy('nombre')->get();
        $entregas = Entrega::with(['solicitud.persona', 'lote', 'responsable.persona'])->orderByDesc('fecha_hora')->limit(100)->get();

        $beneficiariosUnicos = Entrega::join('alim_solicitudes', 'alim_entregas.id_solicitud', '=', 'alim_solicitudes.id_solicitud')
            ->distinct('alim_solicitudes.id_persona')
            ->count('alim_solicitudes.id_persona');

        $logoPath = public_path('images/logo-uceva.jpg');
        $logo = (file_exists($logoPath) && filesize($logoPath) > 500)
            ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $resumen = [
            'mercadosDisponibles' => $despachables->sum('cantidad_disponible'),
            'mercadosEntregados' => Entrega::count(),
            'totalIngresados' => $lotes->sum('cantidad_inicial'),
            'solicitudesAtendidas' => Solicitud::whereIn('estado', [Solicitud::APROBADA, Solicitud::ENTREGADA])->count(),
            'donantesRegistrados' => $donantes->count(),
            'coberturaEstudiantil' => $beneficiariosUnicos,
        ];

        $pdf = Pdf::loadView('documentos.informe-transparencia-alimentos', [
            'resumen' => $resumen,
            'lotes' => $lotes,
            'entregas' => $entregas,
            'donantes' => $donantes,
            'generado' => $generado,
            'generadoPor' => auth()->user()?->persona?->nombre_completo ?? 'Personal de Almacén y Control de Insumos',
            'ciudad' => config('sigabu.ciudad', 'Tuluá'),
            'logo' => $logo,
        ])->setPaper('letter');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Informe-Transparencia-Donaciones-'.$generado->format('Ymd').'.pdf"',
        ]);
    }

    private function exigirEstado(Solicitud $solicitud, string $estado): void
    {
        if ($solicitud->estado !== $estado) {
            throw ValidationException::withMessages(['solicitud' => "La solicitud ya está \"{$solicitud->estado}\". Actualiza la página."])
                ->redirectTo(route('almacen.home'));
        }
    }

    private function volver(string $mensaje): RedirectResponse
    {
        return redirect()->route('almacen.home', ['tab' => 'solicitudes'])->with('status', $mensaje);
    }
}

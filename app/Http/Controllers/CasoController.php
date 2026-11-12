<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCasoRequest;
use App\Models\Actuacion;
use App\Models\AlertaTemprana;
use App\Models\Caso;
use App\Models\ModeloConsentimiento;
use App\Models\Persona;
use App\Models\PropuestaCita;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CasoController extends Controller
{
    /**
     * Casos de Salud Mental del profesional: los que tiene asignados y los que remitió.
     */
    public function index(Request $request): View
    {
        return $this->listar($request, 'Salud Mental', 'salud-mental');
    }

    /**
     * Casos de Inclusión Universitaria del profesional: misma pantalla que Salud Mental.
     */
    public function inclusion(Request $request): View
    {
        return $this->listar($request, 'Inclusión', 'salud-mental');
    }

    /**
     * Expedientes del profesional en todos los servicios.
     */
    public function expedientes(Request $request): View
    {
        return $this->listar($request, null, 'profesional.expedientes');
    }

    /**
     * Arma el listado con su resumen y aplica la búsqueda y los filtros. El filtro de estado
     * usa el estado que ve el profesional ("Cerrado y remitido" no existe en la base), por eso
     * se aplica sobre la colección; cada profesional maneja pocos casos.
     */
    private function listar(Request $request, ?string $servicio, string $vista): View
    {
        $profesional = $request->user();

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(Caso::ESTADOS_LISTADO)],
            'riesgo' => ['nullable', Rule::in(Caso::NIVELES_RIESGO)],
        ] + ($servicio === 'Inclusión' ? [
            'programa' => ['nullable', 'string', 'max:150'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ] : []));

        $casos = Caso::delProfesional($profesional)
            ->when($servicio, fn ($query) => $query->whereHas('solicitud', fn ($solicitud) => $solicitud->where('tipo_servicio', $servicio)))
            ->with(['solicitud.persona', 'profesional.persona', 'citas', 'remisiones'])
            ->withExists('historiaClinica')
            ->orderByDesc('fecha_apertura')
            ->get();

        $busqueda = mb_strtolower(trim($filtros['q'] ?? ''));

        $filtrados = $casos->filter(function (Caso $caso) use ($filtros, $busqueda, $profesional) {
            $persona = $caso->solicitud?->persona;
            $texto = mb_strtolower(implode(' ', [$caso->codigo_expediente, $persona?->nombre_completo, $persona?->numero_documento]));

            return ($busqueda === '' || str_contains($texto, $busqueda))
                && (empty($filtros['estado']) || ($filtros['estado'] === Caso::FILTRO_REMITIDOS
                    ? $caso->remisionHechaPor($profesional) !== null
                    : $caso->estado_caso === $filtros['estado']))
                && (empty($filtros['riesgo']) || $caso->nivel_riesgo === $filtros['riesgo'])
                && (empty($filtros['desde']) || $caso->fecha_apertura?->toDateString() >= $filtros['desde'])
                && (empty($filtros['hasta']) || $caso->fecha_apertura?->toDateString() <= $filtros['hasta'])
                && (empty($filtros['programa']) || str_contains(mb_strtolower((string) ($persona?->datosAcademicos()['programa'] ?? '')), mb_strtolower(trim($filtros['programa']))));
        })->sortByDesc(fn (Caso $caso) => $caso->estaActivo() && $caso->esEmergencia())->values();

        $aCargo = $casos->filter(fn (Caso $caso) => $caso->estaActivo() && $caso->id_profesional === $profesional->id_usuario);

        return view($vista, [
            'casos' => $filtrados,
            'filtros' => $filtros,
            'hayFiltros' => $busqueda !== '' || ! empty($filtros['estado']) || ! empty($filtros['riesgo']) || ! empty($filtros['programa']) || ! empty($filtros['desde']) || ! empty($filtros['hasta']),
            'filtrosInclusion' => $servicio === 'Inclusión',
            'resumen' => [
                'total' => $casos->count(),
                'activos' => $aCargo->count(),
                'riesgoAlto' => $aCargo->where('nivel_riesgo', 'Alto')->count(),
                'seguimiento' => $aCargo->where('estado_caso', 'En Seguimiento')->count(),
                'remitidos' => $casos->filter(fn (Caso $caso) => $caso->remisionHechaPor($profesional) !== null)->count(),
            ],
            'estados' => Caso::ESTADOS_LISTADO,
            'niveles' => Caso::NIVELES_RIESGO,
            'servicio' => $servicio,
        ]);
    }

    /**
     * Buscar paciente (HU-004): el profesional busca al estudiante registrado y ve cuántas citas
     * lleva y desde cuándo está en proceso. Si tiene un caso activo a su cargo le envía una
     * solicitud de cita (el estudiante la acepta o la rechaza); si no, le abre el caso con sus
     * datos ya registrados. Quien aún no está registrado se ingresa a mano (?nuevo=1).
     */
    public function create(Request $request): View
    {
        $servicios = $request->user()->serviciosQueAtiende();
        $servicio = in_array($request->query('servicio'), $servicios, true)
            ? $request->query('servicio')
            : ($servicios[0] ?? Caso::SERVICIO_SALUD_MENTAL);

        $busqueda = trim((string) $request->query('q', ''));
        $paciente = $request->filled('paciente') && Str::isUuid($request->query('paciente'))
            ? $this->estudiantes()->whereKey($request->query('paciente'))->first()
            : null;

        $casosPaciente = $paciente
            ? $paciente->casos()->deAreaDe($request->user())->with(['solicitud', 'profesional.persona', 'citas', 'propuestasVigentes'])->orderByDesc('fecha_apertura')->get()
            : collect();

        return view('casos.create', [
            'busqueda' => $busqueda,
            'resultados' => mb_strlen($busqueda) >= 3 ? $this->buscarEstudiantes($busqueda, $request->user()) : collect(),
            'paciente' => $paciente,
            'casosPaciente' => $casosPaciente,
            // Última respuesta del estudiante a una solicitud de cita, por caso.
            'respuestas' => PropuestaCita::whereIn('id_caso', $casosPaciente->pluck('id_caso'))
                ->whereIn('estado', [PropuestaCita::ELEGIDA, PropuestaCita::RECHAZADA])
                ->orderByDesc('fecha_respuesta')->get()->unique('id_caso')->keyBy('id_caso'),
            // Sin paciente elegido, el formulario manual queda para quien aún no está registrado.
            'registroManual' => ! $paciente && ($request->boolean('nuevo') || $request->session()->hasOldInput('numero_documento')),
            'servicioInicial' => $servicio,
            'servicios' => $servicios,
            'consentimientos' => collect($servicios)
                ->mapWithKeys(fn (string $tipo) => [$tipo => ModeloConsentimiento::versionVigenteDe($tipo)])
                ->filter(),
            'nivelesRiesgo' => Caso::NIVELES_RIESGO,
            'motivoMax' => StoreCasoRequest::MOTIVO_MAX,
        ]);
    }

    /**
     * Registra la solicitud de atención; el trigger de la base crea el caso con
     * su código de expediente y aquí queda "Abierto" y asignado al profesional.
     * Todo ocurre en una transacción: si algo falla no queda nada guardado.
     */
    public function store(StoreCasoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $caso = DB::transaction(function () use ($datos, $request) {
                $persona = $this->resolverSolicitante($datos);

                $solicitud = $persona->solicitudes()->create([
                    'tipo_servicio' => $datos['tipo_servicio'],
                    'descripcion' => $datos['motivo'],
                ]);

                $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();

                $caso->update([
                    'estado_caso' => Caso::ESTADO_ABIERTO,
                    'nivel_riesgo' => $datos['nivel_riesgo'] ?? null,
                    'id_profesional' => $request->user()->id_usuario,
                ]);

                // Un caso que se abre en riesgo alto nace con su alerta temprana (HU-026).
                AlertaTemprana::evaluar($caso);

                // Queda la versión exacta del consentimiento que aceptó el solicitante (HU-009).
                if ($version = $request->versionConsentimiento()) {
                    $caso->consentimientos()->create([
                        'aceptado' => true,
                        'fecha_hora_aceptacion' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
                        'id_version' => $version->id_version,
                        'version_formato' => $version->etiqueta(),
                    ]);
                }

                return $caso;
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No fue posible abrir el caso en este momento. No se guardó ningún dato; intenta de nuevo en unos minutos.');
        }

        return redirect()
            ->route('casos.show', $caso)
            ->with('status', "Caso {$caso->codigo_expediente} abierto correctamente.");
    }

    /**
     * Detalle del caso con sus citas agendadas, sus tareas terapéuticas y sus remisiones.
     */
    public function show(Request $request, Caso $caso): View
    {
        // Solo el profesional asignado ve el caso completo. Los demás profesionales ven un
        // resumen: datos del estudiante, profesional a cargo, fechas de apertura y cierre, y estado.
        // Quien remitió el caso a otro profesional conserva además la historia clínica hasta su remisión.
        if ($caso->id_profesional !== $request->user()->id_usuario) {
            $caso->load(['solicitud.persona', 'solicitud.facultad', 'profesional.persona', 'remisiones.profesionalDestino.persona']);
            $remisionHecha = $caso->remisionHechaPor($request->user());

            if ($remisionHecha) {
                $caso->load(['historiaClinica.profesional.persona', 'historiaClinica.anotaciones.profesional.persona']);
            }

            return view('casos.resumen', [
                'caso' => $caso,
                'remisionHecha' => $remisionHecha,
                'historia' => $remisionHecha ? $caso->historiaClinicaAlRemitir($remisionHecha) : null,
                'remisionExterna' => $caso->remisionExternaDeCierre(),
            ]);
        }

        $caso->load([
            'solicitud.persona', 'solicitud.facultad', 'profesional.persona', 'citas.profesional.persona', 'citas.seguimiento.profesional.persona', 'desistimientos', 'propuestasVigentes', 'tareas', 'historiaClinica.profesional.persona', 'historiaClinica.anotaciones.profesional.persona',
            'consentimientos.version.modelo', 'remisiones.profesional.persona', 'remisiones.profesionalDestino.persona', 'remisiones.remisionExterna',
            'aceptacionesConsentimiento.version.modelo', 'aceptacionesConsentimiento.profesional.persona', 'aceptacionesConsentimiento.firmaProfesional', 'aceptacionesConsentimiento.detalleInclusion',
        ]);

        // Ajustes razonables (HU-018/019): solo los casos de Inclusión los tienen.
        if ($caso->esDeInclusion()) {
            $caso->load('ajustes.seguimientos.profesional.persona', 'ajustes.evidencias');
        }

        $puedeRemitir = $caso->estaActivo() && $caso->id_profesional === $request->user()->id_usuario;

        return view('casos.show', [
            'caso' => $caso,
            'remisionHecha' => $caso->remisionHechaPor($request->user()),
            'remisionRecibida' => $caso->remisionRecibida(),
            'puedeVerEvidencia' => $caso->puedeConsultarEvidencia($request->user()),
            'profesionalesDestino' => $puedeRemitir
                ? User::profesionalesActivos()
                    ->queAtienden($caso->solicitud?->tipo_servicio)
                    ->whereKeyNot($request->user()->id_usuario)
                    ->with('persona')
                    ->get()
                    ->sortBy(fn (User $profesional) => $profesional->persona?->nombre_completo)
                    ->values()
                : collect(),
            // Traslado: sin trámite a uno de la misma facultad; a otra facultad, con autorización.
            'idsMismaFacultad' => $puedeRemitir ? $caso->idsProfesionalesMismaFacultad() : null,
        ]);
    }

    /**
     * Reutiliza la persona con esa cédula o, si no existe, le crea su cuenta
     * (rol Persona) y su registro en personas, ya que personas.id_usuario es obligatorio.
     * La contraseña es aleatoria; se asigna después con `php artisan usuario:password`.
     */
    /**
     * Personas con cuenta de estudiante.
     */
    private function estudiantes()
    {
        return Persona::whereHas('usuario', fn ($q) => $q->where('id_rol', Role::where('nombre_rol', 'Persona')->value('id_rol')));
    }

    /**
     * Estudiantes por nombre, documento, correo o código de expediente (máximo 10).
     */
    private function buscarEstudiantes(string $texto, User $profesional)
    {
        $patron = '%'.mb_strtolower($texto).'%';
        $digitos = preg_replace('/\D/', '', $texto);

        return $this->estudiantes()
            ->where(fn ($q) => $q->whereRaw('lower(nombre_completo) like ?', [$patron])
                ->when(strlen($digitos) >= 3, fn ($q) => $q->orWhere('numero_documento', 'like', "%{$digitos}%"))
                ->orWhereRaw('lower(correo_inst) like ?', [$patron])
                ->orWhereHas('casos', fn ($c) => $c->deAreaDe($profesional)->whereRaw('lower(codigo_expediente) like ?', [$patron])))
            ->orderBy('nombre_completo')
            ->limit(10)
            ->get();
    }

    private function resolverSolicitante(array $datos): Persona
    {
        $persona = Persona::where('numero_documento', $datos['numero_documento'])->first();

        if ($persona) {
            return $persona;
        }

        $usuario = User::create([
            'codigo_unico' => $datos['numero_documento'],
            'contrasena_hash' => Str::random(40),
            'id_rol' => Role::where('nombre_rol', 'Persona')->value('id_rol'),
            'estado_activo' => true,
        ]);

        return Persona::create([
            'id_usuario' => $usuario->id_usuario,
            'numero_documento' => $datos['numero_documento'],
            'nombre_completo' => $datos['nombre_completo'],
            'correo_inst' => $datos['correo_inst'],
        ]);
    }
}

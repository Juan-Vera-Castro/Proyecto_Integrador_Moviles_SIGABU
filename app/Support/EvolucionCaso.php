<?php

namespace App\Support;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\Consentimiento;
use App\Models\ConsentimientoAceptacion;
use App\Models\Desistimiento;
use App\Models\HistoriaClinicaAnotacion;
use App\Models\SeguimientoEvolucion;
use App\Models\TareaTerapeutica;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Evolución de un caso (HU-015): la línea de tiempo con todo lo que ha pasado en él y el
 * resumen del progreso (escalas, riesgo, adherencia a las citas y cumplimiento de tareas).
 * La usan la vista y el endpoint JSON, para que los dos muestren siempre lo mismo.
 *
 * Un caso tiene a lo sumo unos cientos de eventos: se cargan con un número fijo de consultas
 * y se ordenan, filtran y paginan en memoria, con un cursor estable.
 */
class EvolucionCaso
{
    /**
     * Tipos de evento de la línea de tiempo, con su nombre para el filtro.
     */
    public const TIPOS = [
        'apertura' => 'Apertura',
        'consentimiento' => 'Consentimiento',
        'historia_clinica' => 'Historia clínica',
        'anotacion' => 'Anotaciones de la historia',
        'cita' => 'Citas y seguimientos',
        'tarea' => 'Tareas terapéuticas',
        'remision' => 'Remisiones',
        'desistimiento' => 'Desistimiento',
        'cierre' => 'Cierre',
    ];

    public const POR_PAGINA = 20;

    public const POR_PAGINA_MAX = 50;

    /**
     * Largo máximo de los textos de la historia clínica y de los motivos en la línea de tiempo.
     */
    public const RECORTE = 280;

    public const MENSAJES = [
        'desde.date_format' => 'La fecha inicial no es válida.',
        'hasta.date_format' => 'La fecha final no es válida.',
        'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        'tipos.array' => 'Los tipos de evento no son válidos.',
        'tipos.*.in' => 'Selecciona tipos de evento de la lista.',
        'profesional.in' => 'Selecciona un profesional que haya atendido el caso.',
        'por_pagina.between' => 'Se muestran entre 5 y 50 eventos por página.',
    ];

    private const ORDEN_RIESGO = ['Bajo' => 1, 'Medio' => 2, 'Alto' => 3];

    /**
     * Todos los eventos del caso, del más reciente al más antiguo.
     *
     * @var Collection<int, array<string, mixed>>
     */
    private Collection $todos;

    /**
     * @var array{desde?: ?string, hasta?: ?string, tipos?: list<string>, profesional?: ?string}
     */
    private array $filtros = [];

    public function __construct(private Caso $caso)
    {
        $caso->loadMissing([
            'solicitud.persona', 'profesional.persona',
            'citas.profesional.persona', 'citas.seguimiento.profesional.persona',
            'tareas.profesional.persona', 'desistimientos.profesional.persona',
            'remisiones.profesional.persona', 'remisiones.profesionalDestino.persona',
            'historiaClinica.profesional.persona', 'historiaClinica.anotaciones.profesional.persona',
            'aceptacionesConsentimiento.profesional.persona', 'aceptacionesConsentimiento.detalleInclusion', 'consentimientos',
        ]);

        $this->todos = $this->armarEventos()->sort(fn (array $a, array $b) => self::comparar(self::clave($a), self::clave($b)))->values();
    }

    /**
     * Reglas de los filtros. El profesional debe ser uno de los que registraron eventos.
     */
    public function reglas(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'tipos' => ['nullable', 'array'],
            'tipos.*' => [Rule::in(array_keys(self::TIPOS))],
            'profesional' => ['nullable', Rule::in(array_keys($this->profesionales()))],
            'cursor' => ['nullable', 'string', function (string $atributo, mixed $valor, \Closure $falla) {
                if (! is_string($valor) || self::decodificarCursor($valor) === null) {
                    $falla('El cursor de la página no es válido.');
                }
            }],
            'por_pagina' => ['nullable', 'integer', 'between:5,'.self::POR_PAGINA_MAX],
        ];
    }

    /**
     * Aplica los filtros validados. El rango de fechas afecta la línea de tiempo y el
     * resumen; los tipos y el profesional, solo la línea de tiempo.
     */
    public function filtrar(array $filtros): static
    {
        $this->filtros = [
            'desde' => $filtros['desde'] ?? null,
            'hasta' => $filtros['hasta'] ?? null,
            'tipos' => array_values($filtros['tipos'] ?? []),
            'profesional' => $filtros['profesional'] ?? null,
        ];

        return $this;
    }

    public function filtros(): array
    {
        return $this->filtros + ['desde' => null, 'hasta' => null, 'tipos' => [], 'profesional' => null];
    }

    public function hayFiltros(): bool
    {
        return array_filter($this->filtros()) !== [];
    }

    /**
     * Profesionales que registraron algo en el caso: id => nombre.
     *
     * @return array<string, string>
     */
    public function profesionales(): array
    {
        return $this->todos
            ->pluck('profesional')
            ->filter()
            ->unique('id')
            ->sortBy('nombre')
            ->mapWithKeys(fn (array $profesional) => [$profesional['id'] => $profesional['nombre']])
            ->all();
    }

    /**
     * Si el caso no tiene más actividad que su apertura.
     */
    public function sinActividad(): bool
    {
        return $this->todos->every(fn (array $evento) => $evento['tipo'] === 'apertura');
    }

    /**
     * Una página de eventos filtrados, a partir del cursor.
     *
     * @return array{eventos: list<array<string, mixed>>, siguiente_cursor: ?string, total: int}
     */
    public function eventos(?string $cursor = null, ?int $porPagina = null): array
    {
        $porPagina = min($porPagina ?? self::POR_PAGINA, self::POR_PAGINA_MAX);
        $desde = $cursor !== null ? self::decodificarCursor($cursor) : null;

        $filtrados = $this->todos->filter(fn (array $evento) => $this->pasaFiltros($evento));
        $restantes = $desde ? $filtrados->filter(fn (array $evento) => self::comparar(self::clave($evento), $desde) > 0) : $filtrados;

        $pagina = $restantes->take($porPagina + 1)->values();
        $hayMas = $pagina->count() > $porPagina;
        $pagina = $pagina->take($porPagina);

        return [
            'eventos' => $pagina->all(),
            'siguiente_cursor' => $hayMas ? self::codificarCursor(self::clave($pagina->last())) : null,
            'total' => $filtrados->count(),
        ];
    }

    /**
     * Resumen del progreso dentro del rango de fechas.
     */
    public function resumen(): array
    {
        $citas = $this->caso->citas->filter(fn (Actuacion $cita) => $this->enRango($cita->fecha_hora));
        $tareas = $this->caso->tareas->filter(fn (TareaTerapeutica $tarea) => $this->enRango($tarea->fecha_asignacion));

        // La fecha de un seguimiento es la de su cita: cuándo ocurrió la sesión.
        $seguimientos = $citas
            ->filter(fn (Actuacion $cita) => $cita->seguimiento !== null)
            ->sortBy('fecha_hora')
            ->map(fn (Actuacion $cita) => ['fecha' => $cita->fecha_hora, 'seguimiento' => $cita->seguimiento])
            ->values();

        $asistidas = $citas->where('estado_asistencia', Actuacion::ASISTIO)->count();
        $noAsistidas = $citas->where('estado_asistencia', Actuacion::NO_ASISTIO)->count();
        $pendientes = $citas->filter(fn (Actuacion $cita) => $cita->asistenciaPendiente());
        $cumplidas = $tareas->where('estado', TareaTerapeutica::ESTADO_CUMPLIDA)->count();
        $noCumplidas = $tareas->where('estado', TareaTerapeutica::ESTADO_NO_CUMPLIDA)->count();

        $serieRiesgo = $seguimientos->map(fn (array $s) => ['fecha' => self::iso($s['fecha']), 'nivel' => $s['seguimiento']->nivel_riesgo]);

        return [
            'seguimientos_registrados' => $seguimientos->count(),
            'citas_sin_seguimiento' => $asistidas - $seguimientos->count(),
            'escalas' => $this->caso->esDeSaludMental() ? $this->escalas($seguimientos) : [],
            'riesgo' => $serieRiesgo->isEmpty() ? null : [
                'inicial' => $serieRiesgo->first()['nivel'],
                'actual' => $serieRiesgo->last()['nivel'],
                'tendencia' => self::tendenciaRiesgo($serieRiesgo->first()['nivel'], $serieRiesgo->last()['nivel']),
                'serie' => $serieRiesgo->all(),
            ],
            'valoraciones' => collect(SeguimientoEvolucion::VALORACIONES)
                ->mapWithKeys(fn (string $valoracion) => [$valoracion => $seguimientos->where('seguimiento.valoracion_avance', $valoracion)->count()])
                ->all(),
            'adherencia' => [
                'asistidas' => $asistidas,
                'no_asistidas' => $noAsistidas,
                'canceladas' => $citas->where('estado_asistencia', Actuacion::CANCELADA)->count(),
                'programadas' => $pendientes->filter(fn (Actuacion $cita) => $cita->esProxima())->count(),
                'sin_registro' => $pendientes->reject(fn (Actuacion $cita) => $cita->esProxima())->count(),
                'porcentaje' => self::porcentaje($asistidas, $asistidas + $noAsistidas),
            ],
            'tareas' => [
                'cumplidas' => $cumplidas,
                'no_cumplidas' => $noCumplidas,
                'pendientes' => $tareas->where('estado', TareaTerapeutica::ESTADO_PENDIENTE)->count(),
                'canceladas' => $tareas->where('estado', TareaTerapeutica::ESTADO_CANCELADA)->count(),
                'porcentaje_cumplimiento' => self::porcentaje($cumplidas, $cumplidas + $noCumplidas),
            ],
        ];
    }

    /**
     * Datos del caso para la cabecera, vistos por $usuario.
     */
    public function caso(User $usuario): array
    {
        $caso = $this->caso;
        // Días entre fechas locales: las columnas guardan la hora de Bogotá sin zona horaria.
        $fin = Carbon::parse(($caso->fecha_cierre ?? now(Actuacion::ZONA_HORARIA))->format('Y-m-d'));
        $dias = $caso->fecha_apertura ? (int) Carbon::parse($caso->fecha_apertura->format('Y-m-d'))->diffInDays($fin) : null;

        return [
            'id' => $caso->id_caso,
            'codigo_expediente' => $caso->codigo_expediente,
            'servicio' => $caso->solicitud?->tipo_servicio,
            'estado' => $caso->estado_caso,
            'nivel_riesgo_actual' => $caso->nivel_riesgo,
            'fecha_apertura' => self::iso($caso->fecha_apertura),
            'fecha_cierre' => self::iso($caso->fecha_cierre),
            'dias_en_atencion' => $dias,
            'estudiante' => ['nombre' => $caso->solicitud?->persona?->nombre_completo],
            'profesional_asignado' => self::persona($caso->profesional),
            'puede_registrar' => $this->puedeRegistrar($usuario),
        ];
    }

    /**
     * Solo el profesional asignado registra seguimientos, en un caso activo que admite atención.
     */
    public function puedeRegistrar(User $usuario): bool
    {
        return $this->caso->id_profesional === $usuario->id_usuario && $this->caso->estaActivo() && $this->caso->admiteAtencion();
    }

    /**
     * Un evento tal como lo entrega el endpoint JSON.
     */
    public function aJson(array $evento, User $usuario): array
    {
        $json = [
            'id' => $evento['id'],
            'tipo' => $evento['tipo'],
            'fecha' => self::iso($evento['fecha']),
            'titulo' => $evento['titulo'],
            'etiqueta' => $evento['etiqueta'],
            'descripcion' => $evento['descripcion'],
            'profesional' => $evento['profesional'],
            'detalle' => $evento['detalle'],
        ];

        if ($evento['tipo'] === 'cita') {
            $seguimiento = $evento['seguimiento'];
            $json['seguimiento'] = $seguimiento ? [
                'id' => $seguimiento->id_seguimiento,
                'nivel_riesgo' => $seguimiento->nivel_riesgo,
                'valoracion_avance' => $seguimiento->valoracion_avance,
                'instrumento' => $seguimiento->instrumento,
                'puntaje' => $seguimiento->puntaje,
                'severidad' => $seguimiento->severidad(),
                'nota' => $seguimiento->nota,
                'registrado_por' => self::persona($seguimiento->profesional),
                'registrado_en' => self::iso($seguimiento->fecha_hora),
            ] : null;
            $json['acciones'] = ['registrar_seguimiento' => ! $seguimiento && $evento['detalle']['estado_asistencia'] === Actuacion::ASISTIO && $this->puedeRegistrar($usuario)];
        }

        return $json;
    }

    public static function iso(?Carbon $fecha): ?string
    {
        // Las columnas guardan la hora local de la universidad, sin zona horaria.
        return $fecha?->copy()->shiftTimezone(Actuacion::ZONA_HORARIA)->toIso8601String();
    }

    public static function decodificarCursor(string $cursor): ?array
    {
        $datos = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true);

        return is_array($datos) && count($datos) === 3 && array_is_list($datos) && array_filter($datos, 'is_string') === $datos
            && isset(self::TIPOS[$datos[1]]) && strtotime($datos[0]) !== false
            ? $datos
            : null;
    }

    private static function codificarCursor(array $clave): string
    {
        return rtrim(strtr(base64_encode(json_encode($clave)), '+/', '-_'), '=');
    }

    /**
     * Clave de orden de un evento: fecha (descendente), tipo e id.
     *
     * @return array{string, string, string}
     */
    private static function clave(array $evento): array
    {
        return [$evento['fecha']->format('Y-m-d H:i:s'), $evento['tipo'], $evento['id']];
    }

    private static function comparar(array $a, array $b): int
    {
        return strcmp($b[0], $a[0]) ?: strcmp($a[1], $b[1]) ?: strcmp($a[2], $b[2]);
    }

    private function pasaFiltros(array $evento): bool
    {
        $filtros = $this->filtros();

        return $this->enRango($evento['fecha'])
            && ($filtros['tipos'] === [] || in_array($evento['tipo'], $filtros['tipos'], true))
            && ($filtros['profesional'] === null || ($evento['profesional']['id'] ?? null) === $filtros['profesional']);
    }

    private function enRango(?Carbon $fecha): bool
    {
        if (! $fecha) {
            return false;
        }

        $dia = $fecha->format('Y-m-d');
        $filtros = $this->filtros();

        return ($filtros['desde'] === null || $dia >= $filtros['desde'])
            && ($filtros['hasta'] === null || $dia <= $filtros['hasta']);
    }

    /**
     * Series por escala: cada instrumento por separado, porque sus puntajes no se comparan.
     */
    private function escalas(Collection $seguimientos): array
    {
        $umbrales = config('sigabu.evolucion.umbrales', []);

        return collect(SeguimientoEvolucion::INSTRUMENTOS)
            ->map(function (int $maximo, string $instrumento) use ($seguimientos, $umbrales) {
                $mediciones = $seguimientos
                    ->filter(fn (array $s) => $s['seguimiento']->instrumento === $instrumento)
                    ->map(fn (array $s) => [
                        'fecha' => self::iso($s['fecha']),
                        'puntaje' => $s['seguimiento']->puntaje,
                        'severidad' => $s['seguimiento']->severidad(),
                    ])
                    ->values();

                if ($mediciones->isEmpty()) {
                    return null;
                }

                $inicial = $mediciones->first()['puntaje'];
                $actual = $mediciones->last()['puntaje'];
                $suficientes = $mediciones->count() >= 2;
                $cambio = $actual - $inicial;
                $umbral = (int) ($umbrales[$instrumento] ?? 5);

                return [
                    'instrumento' => $instrumento,
                    'puntaje_maximo' => $maximo,
                    'mediciones' => $mediciones->all(),
                    'inicial' => $inicial,
                    'actual' => $actual,
                    'severidad_actual' => $mediciones->last()['severidad'],
                    'cambio_absoluto' => $suficientes ? $cambio : null,
                    'cambio_porcentual' => $suficientes && $inicial > 0 ? round($cambio / $inicial * 100, 1) : null,
                    'umbral_relevante' => $umbral,
                    // En estas escalas un puntaje menor es mejor.
                    'tendencia' => match (true) {
                        ! $suficientes => 'datos_insuficientes',
                        $cambio <= -$umbral => 'mejora',
                        $cambio >= $umbral => 'empeora',
                        default => 'sin_cambio',
                    },
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function tendenciaRiesgo(string $inicial, string $actual): string
    {
        return match (true) {
            self::ORDEN_RIESGO[$actual] < self::ORDEN_RIESGO[$inicial] => 'baja',
            self::ORDEN_RIESGO[$actual] > self::ORDEN_RIESGO[$inicial] => 'sube',
            default => 'igual',
        };
    }

    private static function porcentaje(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : null;
    }

    private static function persona(?User $usuario): ?array
    {
        return $usuario ? ['id' => $usuario->id_usuario, 'nombre' => $usuario->persona?->nombre_completo ?? 'Profesional'] : null;
    }

    private static function recortar(?string $texto): ?string
    {
        return $texto === null ? null : Str::limit(trim($texto), self::RECORTE);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function armarEventos(): Collection
    {
        $caso = $this->caso;
        $eventos = collect();
        $evento = fn (string $tipo, string $id, ?Carbon $fecha, string $titulo, ?string $etiqueta, ?string $descripcion, ?User $profesional, array $detalle = [], ?string $seccion = null) => $fecha ? [
            'id' => "{$tipo}:{$id}",
            'tipo' => $tipo,
            'fecha' => $fecha,
            'titulo' => $titulo,
            'etiqueta' => $etiqueta,
            'descripcion' => $descripcion,
            'profesional' => self::persona($profesional),
            'detalle' => $detalle,
            'seguimiento' => null,
            'enlace' => $seccion ? route('casos.show', $caso).'#'.$seccion : null,
        ] : null;

        $eventos->push($evento('apertura', $caso->id_caso, $caso->fecha_apertura, 'Apertura del caso', null,
            "Servicio de {$caso->solicitud?->tipo_servicio}.", null, ['servicio' => $caso->solicitud?->tipo_servicio]));

        foreach ($caso->aceptacionesConsentimiento as $aceptacion) {
            /** @var ConsentimientoAceptacion $aceptacion */
            $quien = $aceptacion->loOtorgaAcudiente() ? 'el acudiente' : 'el estudiante';
            $eventos->push($evento('consentimiento', $aceptacion->id_aceptacion, $aceptacion->fecha_hora, 'Consentimiento informado',
                $aceptacion->etiquetaDecision(), match (true) {
                    $aceptacion->esRevocacion() => "Revocado por {$quien}.",
                    $aceptacion->esEnPapel() => 'Firmado en papel y registrado por el profesional.',
                    default => ($aceptacion->esAceptado() ? 'Aceptado' : 'No aceptado')." y firmado por {$quien}.",
                }, $aceptacion->profesional,
                ['decision' => $aceptacion->decision, 'otorgante' => $aceptacion->otorgante], 'consentimiento'));
        }

        foreach ($caso->consentimientos as $casilla) {
            /** @var Consentimiento $casilla */
            $eventos->push($evento('consentimiento', (string) $casilla->id_consentimiento, $casilla->fecha_hora_aceptacion ? Carbon::parse($casilla->fecha_hora_aceptacion) : null,
                'Consentimiento informado', $casilla->aceptado ? 'Aceptado' : 'No aceptado', 'Registrado con la casilla de aceptación (sin firma).', null,
                ['decision' => $casilla->aceptado ? 'Aceptado' : 'Rechazado', 'otorgante' => null], 'consentimiento'));
        }

        if ($historia = $caso->historiaClinica) {
            $eventos->push($evento('historia_clinica', $historia->id_historia, $historia->fecha_registro, 'Historia clínica registrada', null,
                'Motivo: '.self::recortar($historia->motivo_consulta), $historia->profesional, [
                    'motivo_consulta' => self::recortar($historia->motivo_consulta),
                    'impresion_diagnostica' => self::recortar($historia->impresion_diagnostica),
                    'plan_intervencion' => self::recortar($historia->plan_intervencion),
                ], 'historia-clinica'));

            foreach ($historia->anotaciones as $anotacion) {
                /** @var HistoriaClinicaAnotacion $anotacion */
                $eventos->push($evento('anotacion', $anotacion->id_anotacion, $anotacion->fecha_hora, 'Anotación en la historia clínica',
                    $anotacion->etiquetaApartado() ?? 'General', self::recortar($anotacion->texto), $anotacion->profesional,
                    ['apartado' => $anotacion->apartado], 'historia-clinica'));
            }
        }

        foreach ($caso->citas as $cita) {
            $etiqueta = match (true) {
                $cita->asistenciaPendiente() && $cita->esProxima() => 'Programada',
                $cita->asistenciaPendiente() => 'Sin registro de asistencia',
                default => $cita->estado_asistencia,
            };
            $item = $evento('cita', $cita->id_actuacion, $cita->fecha_hora, 'Cita de asesoría psicológica', $etiqueta,
                $cita->motivo_inasistencia ? "Motivo: {$cita->motivo_inasistencia}" : null, $cita->profesional,
                ['estado_asistencia' => $cita->estado_asistencia, 'motivo_inasistencia' => $cita->motivo_inasistencia, 'es_proxima' => $cita->esProxima()], 'citas');
            $item['seguimiento'] = $cita->seguimiento;
            $eventos->push($item);
        }

        foreach ($caso->tareas as $tarea) {
            $eventos->push($evento('tarea', $tarea->id_tarea, $tarea->fecha_asignacion, $tarea->titulo, $tarea->estado,
                $tarea->fecha_limite ? "Fecha límite: {$tarea->fecha_limite->format('Y-m-d')}." : null, $tarea->profesional,
                ['estado' => $tarea->estado, 'fecha_limite' => $tarea->fecha_limite?->format('Y-m-d')], 'tareas'));
        }

        foreach ($caso->remisiones as $remision) {
            $interna = $remision->esRemisionInterna();
            $eventos->push($evento('remision', $remision->id_actuacion, $remision->fecha_hora, $remision->esTrasladoAdministrativo() ? 'Traslado administrativo' : ($interna ? 'Remisión interna' : 'Remisión externa'),
                $interna ? 'Interna' : 'Externa', 'A '.$remision->etiquetaDestino().'.'.($remision->descripcion ? ' Motivo: '.self::recortar($remision->descripcion) : ''),
                $remision->adminTraslado ?? $remision->profesional, ['destino' => $remision->destino_remision, 'a' => $remision->etiquetaDestino()], 'remisiones'));
        }

        foreach ($caso->desistimientos as $desistimiento) {
            /** @var Desistimiento $desistimiento */
            $eventos->push($evento('desistimiento', $desistimiento->id_desistimiento, $desistimiento->fecha_hora, 'Desistimiento de la atención',
                null, $desistimiento->motivo.($desistimiento->observaciones ? ': '.self::recortar($desistimiento->observaciones) : '.'),
                $desistimiento->profesional, ['motivo' => $desistimiento->motivo]));
        }

        $eventos->push($evento('cierre', $caso->id_caso, $caso->fecha_cierre, 'Cierre del caso', $caso->estado_caso, null, null,
            ['estado' => $caso->estado_caso]));

        return $eventos->filter()->values();
    }
}

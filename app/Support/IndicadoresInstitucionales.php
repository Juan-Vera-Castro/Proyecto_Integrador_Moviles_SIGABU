<?php

namespace App\Support;

use App\Models\Actuacion;
use App\Models\Alimentos\Entrega;
use App\Models\Alimentos\Solicitud as SolicitudAlimentaria;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\SolicitudAtencion;
use App\Models\Vbg\Reporte;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Indicadores institucionales agregados y anonimizados (HU-032). Solo devuelve conteos: ningún
 * nombre, documento ni código de expediente. En los desgloses por grupo, los grupos con menos de
 * `sigabu.indicadores.minimo_por_grupo` casos se suprimen (valor null, se muestran como "<N"),
 * para que nadie pueda deducir de quién se trata.
 */
class IndicadoresInstitucionales
{
    public const PERIODOS = [3, 6, 12];

    public const SERVICIOS = ['Todos', 'Salud Mental', 'Inclusión'];

    private readonly Carbon $desde;

    private readonly Carbon $hoy;

    public function __construct(
        public readonly int $meses = 6,
        public readonly string $servicio = 'Todos',
    ) {
        $this->hoy = now(Actuacion::ZONA_HORARIA);
        $this->desde = $this->hoy->copy()->startOfMonth()->subMonths($meses - 1);
    }

    public static function minimo(): int
    {
        return max(1, (int) config('sigabu.indicadores.minimo_por_grupo', 5));
    }

    /**
     * Valor visible de un grupo: el conteo, o null si es pequeño (1 a mínimo-1) y debe suprimirse.
     */
    public static function suprimir(int $n): ?int
    {
        return $n > 0 && $n < self::minimo() ? null : $n;
    }

    public function desde(): Carbon
    {
        return $this->desde;
    }

    /**
     * @return array<string, mixed>
     */
    public function todo(): array
    {
        $solicitudes = $this->solicitudes();
        $citas = $this->citasDelPeriodo();
        $asistio = $citas->where('estado_asistencia', Actuacion::ASISTIO)->count();
        $noAsistio = $citas->where('estado_asistencia', Actuacion::NO_ASISTIO)->count();

        return [
            'kpis' => [
                'solicitudes' => $solicitudes->count(),
                'activos' => $this->casosActivos()->count(),
                'sesiones' => $asistio,
                'asistencia' => $asistio + $noAsistio > 0 ? (int) round(100 * $asistio / ($asistio + $noAsistio)) : null,
                'reportesVbg' => $this->servicio === 'Todos' ? Reporte::where('fecha_reporte', '>=', $this->desde->toDateTimeString())->count() : null,
                'entregas' => $this->servicio === 'Todos' ? Entrega::where('fecha_hora', '>=', $this->desde->toDateTimeString())->count() : null,
            ],
            'meses' => $this->meses(),
            'tendencia' => $this->tendencia($solicitudes),
            'porFacultad' => $this->porFacultad($solicitudes),
            'porRiesgo' => $this->grupos($this->casosActivos()->countBy(fn (Caso $c) => $c->nivel_riesgo ?? 'Sin valorar'), ['Alto', 'Medio', 'Bajo', 'Sin valorar']),
            'asistencia' => $this->grupos($citas->countBy('estado_asistencia'), [Actuacion::ASISTIO, Actuacion::NO_ASISTIO, Actuacion::CANCELADA]),
            'vbgPorTipo' => $this->servicio === 'Todos'
                ? $this->grupos(Reporte::where('fecha_reporte', '>=', $this->desde->toDateTimeString())->get(['tipo_violencia'])->countBy(fn ($r) => $r->tipo_violencia ?? 'Sin clasificar'), [...Reporte::TIPOS_VIOLENCIA, 'Sin clasificar'])
                : null,
            'alimentosPorSituacion' => $this->servicio === 'Todos'
                ? $this->grupos(SolicitudAlimentaria::where('fecha_solicitud', '>=', $this->desde->toDateTimeString())->get(['situacion'])->countBy('situacion'), SolicitudAlimentaria::SITUACIONES)
                : null,
        ];
    }

    /**
     * @return Collection<int, SolicitudAtencion>
     */
    private function solicitudes(): Collection
    {
        return SolicitudAtencion::query()
            ->where('fecha_solicitud', '>=', $this->desde->toDateTimeString())
            ->when($this->servicio !== 'Todos', fn ($q) => $q->where('tipo_servicio', $this->servicio))
            ->get(['id_solicitud', 'tipo_servicio', 'id_facultad', 'fecha_solicitud']);
    }

    /**
     * @return Collection<int, Caso>
     */
    private function casosActivos(): Collection
    {
        return Caso::activos()
            ->when($this->servicio !== 'Todos', fn ($q) => $q->whereHas('solicitud', fn ($s) => $s->where('tipo_servicio', $this->servicio)))
            ->get(['id_caso', 'nivel_riesgo']);
    }

    /**
     * Citas cuya hora ya pasó dentro del periodo (las futuras no tienen asistencia todavía).
     *
     * @return Collection<int, Actuacion>
     */
    private function citasDelPeriodo(): Collection
    {
        return Actuacion::citas()
            ->whereBetween('fecha_hora', [$this->desde->toDateTimeString(), $this->hoy->toDateTimeString()])
            ->when($this->servicio !== 'Todos', fn ($q) => $q->whereHas('caso.solicitud', fn ($s) => $s->where('tipo_servicio', $this->servicio)))
            ->get(['id_actuacion', 'estado_asistencia']);
    }

    /**
     * @return list<string>  Meses del periodo en formato Y-m.
     */
    private function meses(): array
    {
        return collect(range(0, $this->meses - 1))->map(fn (int $i) => $this->desde->copy()->addMonths($i)->format('Y-m'))->all();
    }

    /**
     * Solicitudes por mes y servicio. Es un total institucional por mes, sin desglose por persona
     * ni grupo pequeño, así que no se suprime.
     *
     * @return array<string, array<string, int>>
     */
    private function tendencia(Collection $solicitudes): array
    {
        $servicios = $this->servicio === 'Todos' ? SolicitudAtencion::SERVICIOS : [$this->servicio];

        return collect($servicios)->mapWithKeys(fn (string $servicio) => [$servicio => collect($this->meses())->mapWithKeys(
            fn (string $mes) => [$mes => $solicitudes->where('tipo_servicio', $servicio)->filter(fn ($s) => Carbon::parse($s->fecha_solicitud)->format('Y-m') === $mes)->count()]
        )->all()])->all();
    }

    /**
     * Solicitudes por facultad, de mayor a menor, con supresión de grupos pequeños.
     *
     * @return list<array{etiqueta: string, valor: ?int}>
     */
    private function porFacultad(Collection $solicitudes): array
    {
        $nombres = Facultad::pluck('nombre', 'id_facultad');
        $conteo = $solicitudes->countBy(fn ($s) => $s->id_facultad ? ($nombres[$s->id_facultad] ?? 'Otra') : 'Sin facultad registrada');

        return $conteo->sortDesc()->map(fn (int $n, string $facultad) => ['etiqueta' => preg_replace('/^Facultad de /', '', $facultad), 'valor' => self::suprimir($n)])->values()->all();
    }

    /**
     * Conteos de categorías fijas (en ese orden), con supresión de grupos pequeños.
     *
     * @param  list<string>  $orden
     * @return list<array{etiqueta: string, valor: ?int}>
     */
    private function grupos(Collection $conteo, array $orden): array
    {
        return collect($orden)
            ->map(fn (string $categoria) => ['etiqueta' => $categoria, 'valor' => self::suprimir((int) ($conteo[$categoria] ?? 0))])
            ->all();
    }
}

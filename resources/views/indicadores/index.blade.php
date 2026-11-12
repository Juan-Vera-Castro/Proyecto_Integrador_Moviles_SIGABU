@php
    use App\Support\IndicadoresInstitucionales;

    $k = $datos['kpis'];
    $input = 'rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#007F2F] focus:ring-[#007F2F]';
    // Categóricos validados (dataviz): azul y naranja, en ese orden fijo.
    $colorServicio = ['Salud Mental' => '#2a78d6', 'Inclusión' => '#eb6834'];
    // Estados: siempre con ícono y etiqueta, nunca solo el color.
    $colorRiesgo = ['Alto' => ['#d03b3b', '▲'], 'Medio' => ['#ec835a', '●'], 'Bajo' => ['#0ca30c', '▼'], 'Sin valorar' => ['#a8a29e', '○']];
    $tarjetas = array_filter([
        ['Solicitudes de atención', $k['solicitudes'], 'en el periodo'],
        ['Casos activos', $k['activos'], 'hoy'],
        ['Sesiones realizadas', $k['sesiones'], 'citas con asistencia'],
        ['Asistencia a citas', $k['asistencia'] === null ? '—' : $k['asistencia'].'%', 'asistió / (asistió + no asistió)'],
        $k['reportesVbg'] !== null ? ['Reportes de VBG', $k['reportesVbg'], 'en el periodo'] : null,
        $k['entregas'] !== null ? ['Mercados entregados', $k['entregas'], 'en el periodo'] : null,
    ]);
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Indicadores institucionales</h1>
                <p class="text-gray-500 text-sm">Datos agregados y anonimizados de Bienestar Universitario, para apoyar la toma de decisiones</p>
            </div>
        </div>

        <div class="p-4 sm:p-8 max-w-[1400px] mx-auto w-full flex flex-col gap-6">

            <!-- Filtros, en una fila sobre los gráficos -->
            <form method="GET" action="{{ route('indicadores.index') }}" class="flex flex-wrap items-end gap-3" aria-label="Filtros">
                <div>
                    <label for="meses" class="block text-xs font-medium text-gray-600 mb-1">Periodo</label>
                    <select id="meses" name="meses" class="{{ $input }}" onchange="this.form.submit()">
                        @foreach (IndicadoresInstitucionales::PERIODOS as $opcion)
                            <option value="{{ $opcion }}" @selected($meses === $opcion)>Últimos {{ $opcion }} meses</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="servicio" class="block text-xs font-medium text-gray-600 mb-1">Servicio</label>
                    <select id="servicio" name="servicio" class="{{ $input }}" onchange="this.form.submit()">
                        @foreach (IndicadoresInstitucionales::SERVICIOS as $opcion)
                            <option value="{{ $opcion }}" @selected($servicio === $opcion)>{{ $opcion === 'Todos' ? 'Todos los servicios' : $opcion }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="px-4 py-2 bg-gray-100 text-sm font-bold rounded-xl">Aplicar</button></noscript>
                <p class="text-xs text-gray-500 pb-2">Desde el {{ $desde->format('Y-m-d') }} hasta hoy.</p>
            </form>

            <!-- Indicadores clave -->
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                @foreach ($tarjetas as [$etiqueta, $valor, $nota])
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <p class="text-xs text-gray-500 font-medium">{{ $etiqueta }}</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1 tabular-nums">{{ $valor }}</p>
                        <p class="text-[11px] text-gray-400">{{ $nota }}</p>
                    </div>
                @endforeach
            </div>

            @include('indicadores.partials.lineas', [
                'titulo' => 'Solicitudes de atención por mes',
                'series' => $datos['tendencia'],
                'colores' => $colorServicio,
                'meses' => $datos['meses'],
            ])

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @include('indicadores.partials.barras', ['titulo' => 'Solicitudes por facultad', 'filas' => $datos['porFacultad'], 'minimo' => $minimo])
                @include('indicadores.partials.barras', ['titulo' => 'Nivel de riesgo de los casos activos', 'filas' => $datos['porRiesgo'], 'minimo' => $minimo, 'colores' => $colorRiesgo])
                @include('indicadores.partials.barras', ['titulo' => 'Asistencia a citas en el periodo', 'filas' => $datos['asistencia'], 'minimo' => $minimo])
                @if ($datos['vbgPorTipo'] !== null)
                    @include('indicadores.partials.barras', ['titulo' => 'Reportes de VBG por tipo de violencia', 'filas' => $datos['vbgPorTipo'], 'minimo' => $minimo])
                @endif
                @if ($datos['alimentosPorSituacion'] !== null)
                    @include('indicadores.partials.barras', ['titulo' => 'Solicitudes de apoyo alimentario por situación', 'filas' => $datos['alimentosPorSituacion'], 'minimo' => $minimo])
                @endif
            </div>

            <p class="text-xs text-gray-500">
                Este tablero muestra solo conteos agregados: ningún nombre, documento ni código de expediente. En los desgloses por grupo,
                los grupos con menos de {{ $minimo }} casos se muestran como "&lt;{{ $minimo }}" para que no se pueda deducir de quién se trata.
            </p>
        </div>
    </div>
</x-app-layout>

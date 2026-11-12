@php
    $colorEstado = [
        'Abierto' => 'bg-emerald-500',
        'En Seguimiento' => 'bg-amber-400',
    ];
    $colorRiesgo = [
        'Alto' => 'bg-rose-500',
        'Medio' => 'bg-amber-400',
        'Bajo' => 'bg-emerald-500',
        'Sin definir' => 'bg-gray-300',
    ];
    $colorServicio = [
        'Salud Mental' => 'bg-pink-400',
        'Inclusión' => 'bg-blue-400',
    ];
    $colorAntiguedad = ['bg-[#9FD8B3]', 'bg-[#3FA866]', 'bg-[#00592A]'];
    $colorAsistencia = [
        'Asistió' => 'bg-emerald-500',
        'No asistió' => 'bg-rose-500',
        'Pendiente' => 'bg-sky-400',
        'Cancelada' => 'bg-gray-300',
    ];
    $insigniaRiesgo = [
        'Alto' => 'bg-rose-50 border-rose-200 text-rose-600',
        'Medio' => 'bg-amber-50 border-amber-200 text-amber-600',
        'Bajo' => 'bg-emerald-50 border-emerald-200 text-emerald-600',
    ];
    $insigniaEstado = [
        'Abierto' => 'bg-emerald-50 text-emerald-700',
        'En Seguimiento' => 'bg-amber-50 text-amber-700',
    ];
    $totalCitas = array_sum($asistencia);
    $citasCerradas = $asistencia['Asistió'] + $asistencia['No asistió'];
    $tasaAsistencia = $citasCerradas > 0 ? (int) round($asistencia['Asistió'] * 100 / $citasCerradas) : null;
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

    <!-- 1. FRANJA BLANCA SUPERIOR -->
    <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
        <div>
            <h1 class="text-2xl font-bold text-[#007F2F] tracking-tight">Analítica de casos activos</h1>
            <p class="text-xs text-gray-500 font-medium mt-0.5">
                Indicadores de los casos a tu cargo · corte al {{ now(\App\Models\Actuacion::ZONA_HORARIA)->format('Y-m-d') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('profesional.expedientes') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition shadow-sm flex items-center gap-1.5">
                <span aria-hidden="true">📂</span> Expedientes
            </a>
            <a href="{{ route('profesional.home') }}"
                class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm"
                title="Volver al inicio" aria-label="Volver al inicio">
                ✕
            </a>
        </div>
    </div>

    <div class="p-6 sm:p-8 space-y-6 max-w-[1600px] w-full mx-auto">

        <!-- 2. INDICADORES PRINCIPALES -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach ([
                ['🧠', 'bg-pink-100 text-pink-500', $resumen['activos'], 'Casos activos'],
                ['⚠️', 'bg-rose-100 text-rose-500', $resumen['riesgoAlto'], 'Activos en riesgo alto'],
                ['⏱️', 'bg-blue-100 text-blue-500', $resumen['promedioDias'].' d', 'Antigüedad promedio'],
                ['🗓️', 'bg-amber-100 text-amber-500', $resumen['sesiones'], 'Sesiones realizadas'],
                ['✅', 'bg-emerald-100 text-emerald-600', $resumen['finalizados'], 'Casos finalizados'],
            ] as [$icono, $color, $valor, $etiqueta])
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl {{ $color }} flex items-center justify-center text-xl shrink-0 font-bold" aria-hidden="true">{{ $icono }}</div>
                    <div class="min-w-0">
                        <span class="text-2xl font-bold text-gray-900 block leading-tight tabular-nums">{{ $valor }}</span>
                        <span class="text-xs text-gray-500 font-medium">{{ $etiqueta }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($resumen['activos'] === 0)
            <div class="max-w-2xl mx-auto bg-white rounded-2xl p-10 sm:p-14 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center">
                <div class="text-5xl mb-4 p-4 bg-gray-50 rounded-2xl border border-gray-100 shadow-inner" aria-hidden="true">📊</div>
                <h2 class="text-xl font-bold text-gray-800 mb-1">No tienes casos activos</h2>
                <p class="text-gray-500 text-sm max-w-sm">Cuando tengas casos abiertos o en seguimiento verás aquí su estadística.</p>
            </div>
        @else

        <!-- 3. COMPOSICIÓN POR ESTADO -->
        <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                <h2 class="text-sm font-bold text-gray-900">Estado de los casos activos</h2>
                <p class="text-xs text-gray-400 font-medium">{{ $resumen['activos'] }} {{ $resumen['activos'] === 1 ? 'caso' : 'casos' }}</p>
            </div>
            <div class="flex h-4 w-full gap-[2px] rounded-full overflow-hidden bg-gray-100" role="img"
                 aria-label="@foreach ($porEstado as $estado => $n){{ $estado }}: {{ $n }}. @endforeach">
                @foreach ($porEstado as $estado => $n)
                    @if ($n > 0)
                        <div class="{{ $colorEstado[$estado] }} h-full hover:opacity-80 transition" style="width: {{ $n * 100 / $resumen['activos'] }}%"
                             title="{{ $estado }}: {{ $n }} ({{ round($n * 100 / $resumen['activos']) }}%)"></div>
                    @endif
                @endforeach
            </div>
            <ul class="flex flex-wrap gap-x-6 gap-y-2 mt-3">
                @foreach ($porEstado as $estado => $n)
                    <li class="flex items-center gap-2 text-xs text-gray-600 font-medium">
                        <span class="w-2.5 h-2.5 rounded-sm {{ $colorEstado[$estado] }}" aria-hidden="true"></span>
                        {{ $estado }}
                        <span class="font-bold text-gray-900 tabular-nums">{{ $n }}</span>
                        <span class="text-gray-400 tabular-nums">({{ round($n * 100 / $resumen['activos']) }}%)</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <!-- 4. DISTRIBUCIONES -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ([
                ['Nivel de riesgo', 'Casos activos según el riesgo valorado', $porRiesgo, $colorRiesgo, $resumen['activos']],
                ['Servicio', 'Casos activos por línea de atención', $porServicio, $colorServicio, $resumen['activos']],
                ['Antigüedad', 'Días transcurridos desde la apertura', $porAntiguedad, array_combine(array_keys($porAntiguedad), $colorAntiguedad), $resumen['activos']],
                ['Asistencia a citas', $tasaAsistencia === null ? 'Aún no hay citas con asistencia registrada' : "Tasa de asistencia: {$tasaAsistencia}% de las citas atendidas", $asistencia, $colorAsistencia, max($totalCitas, 1)],
            ] as [$titulo, $subtitulo, $datos, $colores, $total])
                @php($maximo = max(max($datos), 1))
                <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                    <h2 class="text-sm font-bold text-gray-900">{{ $titulo }}</h2>
                    <p class="text-xs text-gray-400 font-medium mt-0.5 mb-5">{{ $subtitulo }}</p>
                    <ul class="space-y-3.5">
                        @foreach ($datos as $categoria => $n)
                            <li class="grid grid-cols-[7.5rem_1fr_3.5rem] items-center gap-3 group" title="{{ $categoria }}: {{ $n }} ({{ round($n * 100 / $total) }}%)">
                                <span class="text-xs text-gray-600 font-medium truncate">{{ $categoria }}</span>
                                <span class="h-3 bg-gray-100 rounded-r-full overflow-hidden">
                                    <span class="block h-full rounded-r-full {{ $colores[$categoria] }} group-hover:opacity-80 transition" style="width: {{ $n * 100 / $maximo }}%"></span>
                                </span>
                                <span class="text-xs text-right tabular-nums"><span class="font-bold text-gray-900">{{ $n }}</span> <span class="text-gray-400">{{ round($n * 100 / $total) }}%</span></span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <!-- 5. DETALLE DE CASOS ACTIVOS -->
        <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden">
            <div class="px-6 pt-6 pb-4">
                <h2 class="text-sm font-bold text-gray-900">Detalle de casos activos</h2>
                <p class="text-xs text-gray-400 font-medium mt-0.5">Los datos de las gráficas, caso por caso</p>
            </div>
            <div class="relative overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px]">
                        <tr>
                            <th scope="col" class="text-left font-bold px-6 py-3">Expediente</th>
                            <th scope="col" class="text-left font-bold px-3 py-3">Solicitante</th>
                            <th scope="col" class="text-left font-bold px-3 py-3">Servicio</th>
                            <th scope="col" class="text-left font-bold px-3 py-3">Riesgo</th>
                            <th scope="col" class="text-left font-bold px-3 py-3">Estado</th>
                            <th scope="col" class="text-right font-bold px-3 py-3">Días abierto</th>
                            <th scope="col" class="text-right font-bold px-6 py-3">Sesiones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($activos as $caso)
                            <tr class="hover:bg-gray-50/80">
                                <td class="px-6 py-3"><a href="{{ route('casos.show', $caso) }}" class="font-bold text-[#007F2F] hover:underline">{{ $caso->codigo_expediente }}</a></td>
                                <td class="px-3 py-3 text-gray-900 font-medium">{{ $caso->solicitud?->persona?->nombre_completo ?? 'Solicitante sin registrar' }}</td>
                                <td class="px-3 py-3 text-gray-600">{{ $caso->solicitud?->tipo_servicio }}</td>
                                <td class="px-3 py-3">
                                    @if ($caso->nivel_riesgo)
                                        <span class="px-2 py-0.5 rounded-full border text-[11px] font-bold {{ $insigniaRiesgo[$caso->nivel_riesgo] }}">● {{ mb_strtolower($caso->nivel_riesgo) }}</span>
                                    @else
                                        <span class="text-gray-400">Sin definir</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-lg text-[11px] font-bold {{ $insigniaEstado[$caso->estado_caso] }}">{{ $caso->estado_caso }}</span></td>
                                <td class="px-3 py-3 text-right tabular-nums text-gray-900">{{ $diasAbierto($caso) }}</td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-900">{{ $caso->sesionesRealizadas() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        <!-- 6. CASOS FINALIZADOS -->
        @if ($finalizados->isNotEmpty())
            <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                <h2 class="text-sm font-bold text-gray-900">Casos finalizados</h2>
                <p class="text-xs text-gray-400 font-medium mt-0.5 mb-4">Casos que cerraste; no cuentan en la estadística de activos</p>
                <ul class="divide-y divide-gray-100">
                    @foreach ($finalizados as $caso)
                        <li class="py-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div>
                                <a href="{{ route('casos.show', $caso) }}" class="font-bold text-[#007F2F] hover:underline">{{ $caso->codigo_expediente }}</a>
                                <span class="text-gray-900 font-medium ml-2">{{ $caso->solicitud?->persona?->nombre_completo }}</span>
                            </div>
                            <span class="text-gray-500">
                                Abierto el {{ $caso->fecha_apertura?->format('Y-m-d') }}
                                @if ($caso->fecha_cierre)
                                    · cerrado el {{ $caso->fecha_cierre->format('Y-m-d') }} ({{ (int) $caso->fecha_apertura->diffInDays($caso->fecha_cierre) }} días)
                                @endif
                                · {{ $caso->sesionesRealizadas() }} {{ $caso->sesionesRealizadas() === 1 ? 'sesión' : 'sesiones' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

    </div>
</div>
</x-app-layout>

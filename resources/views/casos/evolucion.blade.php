@php
    use App\Models\SeguimientoEvolucion;
    use App\Support\EvolucionCaso;
    use Illuminate\Support\Carbon;

    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $riesgo = [
        'Alto' => 'bg-rose-50 border-rose-200 text-rose-600',
        'Medio' => 'bg-amber-50 border-amber-200 text-amber-600',
        'Bajo' => 'bg-emerald-50 border-emerald-200 text-emerald-600',
    ];
    $flechaRiesgo = ['baja' => ['▼', 'bajó'], 'sube' => ['▲', 'subió'], 'igual' => ['▬', 'se mantiene']];
    $tendencias = [
        'mejora' => ['Mejora clínicamente relevante', 'text-emerald-700'],
        'empeora' => ['Empeoramiento clínicamente relevante', 'text-rose-600'],
        'sin_cambio' => ['Sin cambio relevante', 'text-gray-700'],
        'datos_insuficientes' => ['Datos insuficientes: se necesitan al menos 2 mediciones', 'text-gray-500'],
    ];
    $pct = fn (?float $valor) => $valor === null ? null : rtrim(rtrim(number_format($valor, 1, ',', ''), '0'), ',').' %';
    $hoy = now(\App\Models\Actuacion::ZONA_HORARIA);
    $atajos = [
        'Último mes' => $hoy->copy()->subMonth()->format('Y-m-d'),
        'Últimos 3 meses' => $hoy->copy()->subMonths(3)->format('Y-m-d'),
    ];
    $esSaludMental = $caso->esDeSaludMental();
    $ultimoMes = collect($pagina['eventos'])->last()['fecha'] ?? null;
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <!-- Franja superior -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div class="min-w-0">
                <nav aria-label="Ruta" class="text-[11px] text-gray-500 font-medium">
                    <a href="{{ $esSaludMental ? route('profesional.salud-mental') : route('profesional.inclusion') }}" class="hover:underline">{{ $datos['servicio'] }}</a>
                    › <a href="{{ route('casos.show', $caso) }}" class="hover:underline">{{ $datos['codigo_expediente'] }}</a>
                    › <span aria-current="page">Evolución</span>
                </nav>
                <h1 class="text-xl sm:text-2xl font-bold text-[#007F2F] tracking-tight">Evolución del caso {{ $datos['codigo_expediente'] }}</h1>
            </div>
            <a href="{{ route('casos.show', $caso) }}"
               class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
               title="Volver al expediente" aria-label="Cerrar y volver al expediente">✕</a>
        </div>

        <div class="flex-1 p-4 sm:p-8">
            <div class="w-full max-w-6xl mx-auto space-y-4">

                <!-- Cabecera del caso -->
                <section class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-wrap items-center justify-between gap-3" aria-label="Datos del caso">
                    <div class="text-sm min-w-0">
                        <p class="font-bold text-gray-900 break-words">{{ $datos['estudiante']['nombre'] }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $datos['servicio'] }} · Abierto el {{ $caso->fecha_apertura?->format('Y-m-d') }}
                            @if ($datos['dias_en_atencion'] !== null) · {{ $datos['dias_en_atencion'] }} {{ $datos['dias_en_atencion'] === 1 ? 'día' : 'días' }} en atención @endif
                            @if ($datos['profesional_asignado']) · {{ $datos['profesional_asignado']['nombre'] }} @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-100 text-gray-700">{{ $datos['estado'] }}</span>
                        @if ($datos['nivel_riesgo_actual'])
                            <span class="px-2.5 py-0.5 rounded-full border text-[11px] font-bold {{ $riesgo[$datos['nivel_riesgo_actual']] ?? '' }}">
                                @if ($resumen['riesgo'] && $resumen['riesgo']['serie'] && count($resumen['riesgo']['serie']) > 1)
                                    @php [$simbolo, $texto] = $flechaRiesgo[$resumen['riesgo']['tendencia']]; @endphp
                                    <span aria-hidden="true">{{ $simbolo }}</span><span class="sr-only">Riesgo {{ $texto }}:</span>
                                @endif
                                Riesgo {{ mb_strtolower($datos['nivel_riesgo_actual']) }}
                            </span>
                        @endif
                    </div>
                </section>

                <!-- Filtros -->
                <section class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100" aria-labelledby="filtros-titulo">
                    <h2 id="filtros-titulo" class="sr-only">Filtros</h2>
                    <form method="GET" action="{{ route('casos.evolucion', $caso) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                        <div>
                            <label for="desde" class="block text-xs font-semibold text-gray-700 mb-1">Desde</label>
                            <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] }}" class="{{ $input }} py-1.5"
                                   @if ($erroresFiltro->has('desde')) aria-invalid="true" @endif>
                        </div>
                        <div>
                            <label for="hasta" class="block text-xs font-semibold text-gray-700 mb-1">Hasta</label>
                            <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] }}" class="{{ $input }} py-1.5"
                                   @if ($erroresFiltro->has('hasta')) aria-invalid="true" @endif>
                        </div>
                        <div>
                            <label for="profesional" class="block text-xs font-semibold text-gray-700 mb-1">Profesional</label>
                            <select id="profesional" name="profesional" class="{{ $input }} py-1.5">
                                <option value="">Todos</option>
                                @foreach ($profesionales as $id => $nombre)
                                    <option value="{{ $id }}" @selected($filtros['profesional'] === $id)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <details class="relative" @if ($filtros['tipos']) open @endif>
                            <summary class="cursor-pointer px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-800 bg-white">
                                Tipos de evento @if ($filtros['tipos']) ({{ count($filtros['tipos']) }}) @endif
                            </summary>
                            <fieldset class="mt-2 p-3 border border-gray-200 rounded-lg bg-white space-y-1.5">
                                <legend class="sr-only">Tipos de evento</legend>
                                @foreach (EvolucionCaso::TIPOS as $tipo => $nombre)
                                    <label class="flex items-center gap-2 text-xs text-gray-800">
                                        <input type="checkbox" name="tipos[]" value="{{ $tipo }}" @checked(in_array($tipo, $filtros['tipos'], true))
                                               class="rounded text-[#007F2F] focus:ring-[#007F2F]">
                                        {{ $nombre }}
                                    </label>
                                @endforeach
                            </fieldset>
                        </details>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                Aplicar
                            </button>
                            @if ($hayFiltros)
                                <a href="{{ route('casos.evolucion', $caso) }}" class="px-3 py-2 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 text-center">Quitar filtros</a>
                            @endif
                        </div>
                    </form>
                    <p class="mt-3 flex flex-wrap gap-2 text-[11px]">
                        @foreach ($atajos as $nombre => $desde)
                            <a href="{{ route('casos.evolucion', ['caso' => $caso->id_caso, 'desde' => $desde]) }}" class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">{{ $nombre }}</a>
                        @endforeach
                        <a href="{{ route('casos.evolucion', $caso) }}" class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Todo</a>
                    </p>
                    @if ($erroresFiltro->any())
                        <div class="mt-3 bg-rose-50 border border-rose-200 rounded-xl p-3" role="alert">
                            <p class="text-xs font-bold text-rose-700">No se aplicaron los filtros:</p>
                            <ul class="text-xs text-rose-700 list-disc pl-5">
                                @foreach ($erroresFiltro->all() as $mensaje)
                                    <li>{{ $mensaje }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

                    <!-- Resumen del progreso -->
                    <section class="lg:col-span-1 space-y-4" aria-labelledby="resumen-titulo">
                        <h2 id="resumen-titulo" class="text-base font-bold text-gray-900">Resumen del progreso</h2>

                        @if ($resumen['seguimientos_registrados'] === 0)
                            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 text-sm text-gray-600">
                                Aún no hay seguimientos registrados{{ $hayFiltros ? ' en este rango' : '' }}. La evolución se mide con la nota de seguimiento de cada cita a la que el estudiante asiste.
                            </div>
                        @endif

                        <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
                            @if ($esSaludMental)
                                @forelse ($resumen['escalas'] as $escala)
                                    @php [$textoTendencia, $colorTendencia] = $tendencias[$escala['tendencia']]; @endphp
                                    <div class="col-span-2 lg:col-span-1 bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                                        <p class="text-xs font-semibold text-gray-500">{{ $escala['instrumento'] }}</p>
                                        <p class="text-lg font-bold text-gray-900">
                                            @if ($escala['cambio_absoluto'] !== null)
                                                {{ $escala['inicial'] }} → {{ $escala['actual'] }}
                                                <span class="text-sm font-semibold text-gray-600">({{ $escala['cambio_absoluto'] > 0 ? '+' : '' }}{{ $escala['cambio_absoluto'] }} puntos{{ $escala['cambio_porcentual'] !== null ? ', '.($escala['cambio_porcentual'] > 0 ? '+' : '').$pct($escala['cambio_porcentual']) : '' }})</span>
                                            @else
                                                {{ $escala['actual'] }} / {{ $escala['puntaje_maximo'] }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-700">Severidad actual: <strong>{{ $escala['severidad_actual'] }}</strong></p>
                                        <p class="text-xs font-semibold {{ $colorTendencia }}">{{ $textoTendencia }}</p>
                                        <div class="mt-3">
                                            @include('casos.partials.evolucion-grafico', ['escala' => $escala])
                                        </div>
                                    </div>
                                @empty
                                    @if ($resumen['seguimientos_registrados'] > 0)
                                        <div class="col-span-2 lg:col-span-1 bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-xs text-gray-600">
                                            No se han aplicado escalas en este caso.
                                        </div>
                                    @endif
                                @endforelse
                            @endif

                            <!-- Riesgo -->
                            <div class="col-span-2 lg:col-span-1 bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                                <p class="text-xs font-semibold text-gray-500">Nivel de riesgo</p>
                                @if ($resumen['riesgo'])
                                    <p class="text-lg font-bold text-gray-900">{{ $resumen['riesgo']['inicial'] }} → {{ $resumen['riesgo']['actual'] }}</p>
                                    <ol class="mt-2 flex flex-wrap gap-1.5" aria-label="Riesgo en cada seguimiento">
                                        @foreach ($resumen['riesgo']['serie'] as $punto)
                                            <li class="px-2 py-0.5 rounded-full border text-[11px] font-bold {{ $riesgo[$punto['nivel']] ?? '' }}"
                                                title="{{ Carbon::parse($punto['fecha'])->format('Y-m-d') }}">
                                                {{ Carbon::parse($punto['fecha'])->format('d/m') }} · {{ $punto['nivel'] }}
                                            </li>
                                        @endforeach
                                    </ol>
                                @else
                                    <p class="text-lg font-bold text-gray-400">—</p>
                                    <p class="text-xs text-gray-500">Sin seguimientos que lo registren.</p>
                                @endif
                            </div>

                            <!-- Adherencia -->
                            @php $adherencia = $resumen['adherencia']; @endphp
                            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                                <p class="text-xs font-semibold text-gray-500">Adherencia a las citas</p>
                                @if ($adherencia['porcentaje'] !== null)
                                    <p class="text-lg font-bold text-gray-900">{{ $pct($adherencia['porcentaje']) }}</p>
                                    <p class="text-xs text-gray-600">{{ $adherencia['asistidas'] }} de {{ $adherencia['asistidas'] + $adherencia['no_asistidas'] }} citas realizadas</p>
                                @else
                                    <p class="text-lg font-bold text-gray-400">—</p>
                                    <p class="text-xs text-gray-500">Aún no hay citas con asistencia registrada.</p>
                                @endif
                                @if ($adherencia['programadas'] || $adherencia['canceladas'] || $adherencia['sin_registro'])
                                    <p class="text-[11px] text-gray-500 mt-1">
                                        {{ collect(['programadas' => 'programada(s)', 'canceladas' => 'cancelada(s)', 'sin_registro' => 'sin registro'])->filter(fn ($t, $k) => $adherencia[$k])->map(fn ($t, $k) => $adherencia[$k].' '.$t)->implode(' · ') }}
                                    </p>
                                @endif
                            </div>

                            <!-- Tareas -->
                            @php $tareas = $resumen['tareas']; @endphp
                            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                                <p class="text-xs font-semibold text-gray-500">Tareas cumplidas</p>
                                @if ($tareas['porcentaje_cumplimiento'] !== null)
                                    <p class="text-lg font-bold text-gray-900">{{ $pct($tareas['porcentaje_cumplimiento']) }}</p>
                                    <p class="text-xs text-gray-600">{{ $tareas['cumplidas'] }} de {{ $tareas['cumplidas'] + $tareas['no_cumplidas'] }} tareas evaluadas</p>
                                @else
                                    <p class="text-lg font-bold text-gray-400">—</p>
                                    <p class="text-xs text-gray-500">Aún no hay tareas evaluadas.</p>
                                @endif
                                @if ($tareas['pendientes'])
                                    <p class="text-[11px] text-gray-500 mt-1">{{ $tareas['pendientes'] }} pendiente(s) de evaluación</p>
                                @endif
                            </div>
                        </div>

                        @if ($resumen['seguimientos_registrados'] > 0)
                            <p class="text-[11px] text-gray-500">
                                Valoración del avance: {{ collect($resumen['valoraciones'])->map(fn ($n, $v) => "{$v} {$n}")->implode(' · ') }}.
                                @if ($resumen['citas_sin_seguimiento'] > 0)
                                    {{ $resumen['citas_sin_seguimiento'] }} cita(s) asistida(s) sin seguimiento.
                                @endif
                            </p>
                        @endif
                    </section>

                    <!-- Línea de tiempo -->
                    <section class="lg:col-span-2" aria-labelledby="linea-titulo"
                             x-data="{
                                 url: @js($siguiente),
                                 mes: @js($ultimoMes?->format('Y-m')),
                                 cargando: false,
                                 error: false,
                                 anuncio: '',
                                 async cargar() {
                                     if (! this.url || this.cargando) return;
                                     this.cargando = true;
                                     this.error = false;
                                     try {
                                         const destino = new URL(this.url, window.location.origin);
                                         destino.searchParams.set('parcial', '1');
                                         if (this.mes) destino.searchParams.set('mes', this.mes);
                                         const respuesta = await fetch(destino, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                                         if (! respuesta.ok) throw new Error(respuesta.status);
                                         const lista = this.$refs.lista;
                                         const antes = lista.querySelectorAll('[data-evento]').length;
                                         lista.insertAdjacentHTML('beforeend', await respuesta.text());
                                         const nuevos = lista.querySelectorAll('[data-evento]');
                                         const cantidad = nuevos.length - antes;
                                         if (nuevos[antes]) nuevos[antes].focus();
                                         const ultimo = lista.querySelector('li:last-child');
                                         if (ultimo) this.mes = ultimo.dataset.mes;
                                         this.url = respuesta.headers.get('X-Siguiente') || null;
                                         this.anuncio = cantidad + ' eventos más cargados.';
                                     } catch (e) {
                                         this.error = true;
                                     } finally {
                                         this.cargando = false;
                                     }
                                 },
                             }">
                        <div class="flex items-baseline justify-between gap-2 mb-3">
                            <h2 id="linea-titulo" class="text-base font-bold text-gray-900">Línea de tiempo</h2>
                            <p class="text-xs text-gray-500">{{ $pagina['total'] }} {{ $pagina['total'] === 1 ? 'evento' : 'eventos' }}</p>
                        </div>

                        @if ($sinActividad)
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center space-y-3 mb-4">
                                <p class="text-3xl" aria-hidden="true">🌱</p>
                                <p class="text-sm text-gray-700">Aún no hay seguimientos registrados. La evolución se construye con las citas y sus notas de seguimiento.</p>
                                @if ($puedeRegistrar)
                                    <a href="{{ route('citas.create', ['caso' => $caso->id_caso]) }}"
                                       class="inline-block px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm">Agendar primera cita</a>
                                @endif
                            </div>
                        @endif

                        @if ($pagina['eventos'] === [])
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center space-y-3">
                                <p class="text-sm text-gray-700">
                                    No hay eventos
                                    @if ($filtros['desde'] || $filtros['hasta'])
                                        {{ $filtros['desde'] ? 'desde el '.$filtros['desde'] : '' }} {{ $filtros['hasta'] ? 'hasta el '.$filtros['hasta'] : '' }}
                                    @endif
                                    con los filtros elegidos.
                                </p>
                                <a href="{{ route('casos.evolucion', $caso) }}" class="inline-block px-4 py-2 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50">Quitar filtros</a>
                            </div>
                        @else
                            <ol x-ref="lista" class="relative space-y-3 before:absolute before:left-3.5 before:top-2 before:bottom-2 before:w-px before:bg-gray-200" :aria-busy="cargando.toString()">
                                @include('casos.partials.evolucion-eventos', ['eventos' => $pagina['eventos'], 'mesPrevio' => null, 'puedeRegistrar' => $puedeRegistrar])
                            </ol>

                            <div x-show="cargando" x-cloak class="mt-3 space-y-3" aria-hidden="true">
                                <div class="h-20 rounded-xl bg-gray-200 animate-pulse"></div>
                                <div class="h-20 rounded-xl bg-gray-200 animate-pulse"></div>
                            </div>

                            <div x-show="error" x-cloak class="mt-3 bg-rose-50 border border-rose-200 rounded-xl p-3 flex items-center justify-between gap-3" role="alert">
                                <p class="text-xs text-rose-700 font-medium">No se pudo cargar la evolución.</p>
                                <button type="button" @click="cargar()" class="px-3 py-1.5 bg-white border border-rose-300 text-rose-700 text-xs font-bold rounded-lg">Reintentar</button>
                            </div>

                            <p class="sr-only" aria-live="polite" x-text="anuncio"></p>

                            @if ($siguiente)
                                <div class="mt-4 text-center" x-show="url">
                                    <a href="{{ $siguiente }}" @click.prevent="cargar()" :aria-disabled="cargando.toString()"
                                       class="inline-block px-5 py-2 bg-white border border-[#007F2F] text-[#007F2F] hover:bg-emerald-50 text-xs font-bold rounded-xl transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                        Cargar más
                                    </a>
                                </div>
                            @endif
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

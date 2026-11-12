@php
    use App\Models\Caso;

    $profesional = auth()->user();
    $colorEstado = [
        'Abierto' => 'bg-emerald-50 text-emerald-700',
        'En Seguimiento' => 'bg-amber-50 text-amber-700',
        'Pendiente Asignación' => 'bg-gray-100 text-gray-600',
        'Cerrado' => 'bg-gray-100 text-gray-600',
    ];
    $colorRiesgo = [
        'Alto' => 'bg-rose-50 border-rose-200 text-rose-600',
        'Medio' => 'bg-amber-50 border-amber-200 text-amber-600',
        'Bajo' => 'bg-emerald-50 border-emerald-200 text-emerald-600',
    ];
    // La misma pantalla lista los casos de Salud Mental o de Inclusión.
    $esInclusion = ($servicio ?? 'Salud Mental') === 'Inclusión';
    $titulo = $esInclusion ? 'Inclusión Universitaria' : 'Salud Mental y Apoyo Psicosocial';
    $rutaListado = $esInclusion ? 'profesional.inclusion' : 'profesional.salud-mental';
    $servicioCaso = $esInclusion ? 'Inclusión' : 'Salud Mental';
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

    <!-- 1. FRANJA BLANCA SUPERIOR -->
    <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
        <div>
            <!-- Título en negrita -->
            <h1 class="text-2xl font-bold text-[#007F2F] tracking-tight">
                {{ $titulo }}
            </h1>
            <p class="text-xs text-gray-500 font-medium mt-0.5">
                {{ $resumen['total'] }} {{ $resumen['total'] === 1 ? 'caso' : 'casos' }} · {{ $resumen['activos'] }} {{ $resumen['activos'] === 1 ? 'activo' : 'activos' }} a tu cargo
            </p>
        </div>

        <!-- Botones de Acción (Sin campana de notificaciones) -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Botón Desistimiento -->
            @php $activosPropios = $casos->filter(fn ($c) => $c->estaActivo() && $c->id_profesional === $profesional->id_usuario); @endphp
            <div class="relative" x-data="{ abierto: false }" @keydown.escape.window="abierto = false" @click.outside="abierto = false">
                <button type="button" @click="abierto = ! abierto" :aria-expanded="abierto"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition shadow-sm">
                    Registrar desistimiento
                </button>
                <div x-show="abierto" x-cloak class="absolute right-0 mt-2 w-72 bg-white border border-gray-200 rounded-xl shadow-lg z-20 p-2">
                    <p class="px-2 py-1.5 text-[11px] text-gray-500">Elige el caso en el que la persona desiste:</p>
                    @forelse ($activosPropios as $activo)
                        <a href="{{ route('casos.show', $activo) }}#desistimiento" class="block px-2 py-1.5 rounded-lg text-xs hover:bg-gray-50">
                            <strong>{{ $activo->codigo_expediente }}</strong> · {{ $activo->solicitud?->persona?->nombre_completo }}
                        </a>
                    @empty
                        <p class="px-2 py-1.5 text-xs text-gray-500">No tienes casos activos.</p>
                    @endforelse
                </div>
            </div>

            <!-- Botón Agenda (HU-011) -->
            <a href="{{ route('citas.agenda') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition shadow-sm flex items-center gap-1.5">
                <span aria-hidden="true">📅</span> Mi agenda
            </a>

            <!-- Botón + Nuevo Caso -->
            <a href="{{ route('casos.create', ['servicio' => $servicioCaso]) }}" class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5">
                <span>+</span> Nuevo caso
            </a>

            <!-- Botón X -->
            <a href="{{ route('profesional.home') }}"
                class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm"
                title="Volver al inicio" aria-label="Volver al inicio">
                ✕
            </a>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="p-6 sm:p-8 space-y-6 max-w-[1600px] w-full mx-auto">

        <!-- 2. TARJETAS DE INDICADORES (KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ([
                ['🧠', 'bg-pink-100 text-pink-500', $resumen['activos'], 'Casos activos a tu cargo'],
                ['⚠️', 'bg-amber-100 text-amber-500', $resumen['riesgoAlto'], 'Riesgo alto'],
                ['📈', 'bg-blue-100 text-blue-500', $resumen['seguimiento'], 'En seguimiento'],
                ['↪️', 'bg-indigo-100 text-indigo-500', $resumen['remitidos'], 'Remitidos por ti'],
            ] as [$icono, $color, $valor, $etiqueta])
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl {{ $color }} flex items-center justify-center text-xl shrink-0 font-bold" aria-hidden="true">{{ $icono }}</div>
                    <div>
                        <span class="text-2xl font-bold text-gray-900 block leading-tight">{{ $valor }}</span>
                        <span class="text-xs text-gray-500 font-medium">{{ $etiqueta }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        @if (session('status'))
            <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 flex gap-3 items-center" role="status">
                <span class="text-xl shrink-0" aria-hidden="true">✅</span>
                <p class="text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">{{ session('status') }}</p>
            </div>
        @endif

        <!-- 3. BARRA DE BÚSQUEDA Y FILTROS -->
        @include('casos.partials.filtros', ['ruta' => $rutaListado])

        <!-- 4. GRID DE TARJETAS DE CASOS -->
        @if ($casos->isEmpty())
            <div class="max-w-2xl mx-auto bg-white rounded-2xl p-10 sm:p-14 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center">
                <div class="text-5xl mb-4 p-4 bg-gray-50 rounded-2xl border border-gray-100 shadow-inner" aria-hidden="true">🗂️</div>
                <h2 class="text-xl font-bold text-gray-800 mb-1">
                    {{ $hayFiltros ? 'Ningún caso coincide con la búsqueda' : 'Aún no tienes casos de '.$servicioCaso }}
                </h2>
                <p class="text-gray-500 text-sm mb-6 max-w-sm">
                    {{ $hayFiltros ? 'Prueba con otro nombre o código, o quita los filtros.' : 'Abre un caso para empezar a registrar la atención.' }}
                </p>
                @if ($hayFiltros)
                    <a href="{{ route($rutaListado) }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold text-sm transition">Limpiar filtros</a>
                @else
                    <a href="{{ route('casos.create', ['servicio' => $servicioCaso]) }}" class="bg-[#007F2F] hover:bg-[#006324] text-white px-6 py-3 rounded-xl font-semibold text-sm transition shadow-md">+ Nuevo caso</a>
                @endif
            </div>
        @else
            <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($casos as $caso)
                    @php
                        $persona = $caso->solicitud?->persona;
                        $estado = $caso->estado_caso;
                        $remitidaPorMi = $caso->remisionHechaPor($profesional);
                        $proxima = $caso->proximaCita();
                        $sesiones = $caso->sesionesRealizadas();
                    @endphp
                    <li>
                        <a href="{{ route('casos.show', $caso) }}"
                           class="h-full bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm hover:shadow-md hover:border-[#007F2F] transition flex flex-col justify-between space-y-4 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-xs font-bold text-[#007F2F]">{{ $caso->codigo_expediente }} @include('casos.partials.emergencia', ['caso' => $caso])</span>
                                    @if ($caso->nivel_riesgo)
                                        <span class="px-2.5 py-0.5 rounded-full border text-[11px] font-bold {{ $colorRiesgo[$caso->nivel_riesgo] ?? '' }}">● {{ mb_strtolower($caso->nivel_riesgo) }}</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full border border-gray-200 bg-gray-50 text-gray-500 text-[11px] font-bold">Riesgo sin definir</span>
                                    @endif
                                </div>
                                <h3 class="text-sm font-bold text-gray-900 break-words">{{ $persona?->nombre_completo ?? 'Solicitante sin registrar' }}</h3>
                                <p class="text-[11px] text-gray-500 font-medium">Doc. {{ $persona?->numero_documento }} · Abierto el {{ $caso->fecha_apertura?->format('Y-m-d') }}</p>

                                {{-- Quien remitió el caso solo ve su resumen: sin la descripción de la solicitud --}}
                                @if ($caso->id_profesional === $profesional->id_usuario)
                                    <div class="mt-3 pl-3 border-l-2 border-gray-200 text-xs text-gray-600 font-medium py-0.5 line-clamp-2 break-words">
                                        {{ $caso->solicitud?->descripcion }}
                                    </div>
                                @endif
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-4">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $colorEstado[$estado] ?? 'bg-gray-100 text-gray-600' }}">{{ $estado }}</span>
                                    @if ($remitidaPorMi)
                                        <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold">Remitido por ti</span>
                                    @elseif ($caso->remisionRecibida())
                                        <span class="px-2.5 py-1 bg-sky-50 text-sky-700 rounded-lg text-xs font-bold">Remisión</span>
                                    @endif
                                    @include('casos.partials.historia-clinica-etiqueta', ['forma' => 'text-xs rounded-lg'])
                                    <span class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium flex items-center gap-1"><span aria-hidden="true">🗓️</span> {{ $sesiones }} {{ $sesiones === 1 ? 'sesión' : 'sesiones' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 text-[11px] text-gray-500 font-medium pt-3 border-t border-gray-100">
                                    <span class="truncate">{{ $remitidaPorMi && ! $remitidaPorMi->esRemisionInterna() ? $remitidaPorMi->entidad_destino : ($caso->profesional?->persona?->nombre_completo ?? 'Sin asignar') }}</span>
                                    <span class="shrink-0">{{ $proxima ? 'Cita: '.$proxima->fecha_hora->format('Y-m-d g:i a') : 'Sin cita próxima' }}</span>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

    </div>
</div>
</x-app-layout>

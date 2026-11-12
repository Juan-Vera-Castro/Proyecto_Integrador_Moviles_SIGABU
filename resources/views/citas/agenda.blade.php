@php
    use Illuminate\Support\Carbon;

    $estadoCaso = [
        'Abierto' => 'bg-emerald-50 text-emerald-700',
        'En Seguimiento' => 'bg-amber-50 text-amber-700',
        'Pendiente Asignación' => 'bg-gray-100 text-gray-600',
        'Cerrado' => 'bg-gray-100 text-gray-600',
    ];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-[#007F2F] tracking-tight">Mi agenda</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">
                    {{ $totalCitas }} {{ $totalCitas === 1 ? 'cita' : 'citas' }}
                    {{ $fecha ? 'el '.$fecha : 'desde hoy' }} · Asesoría psicológica
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('citas.create') }}" class="px-4 py-2 bg-[#22C55E] hover:bg-[#16a34a] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                    <span aria-hidden="true">+</span> Agendar cita
                </a>

                <!-- Botón X -->
                <a href="{{ auth()->user()->urlCasos() }}"
                   class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
                   title="Volver a Salud Mental" aria-label="Cerrar y volver a Salud Mental">
                    ✕
                </a>
            </div>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="p-4 sm:p-8 space-y-6 max-w-5xl w-full mx-auto">

            @if (session('status'))
                <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 flex gap-3 items-center" role="status">
                    <span class="text-xl shrink-0" aria-hidden="true">✅</span>
                    <p class="text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">{{ session('status') }}</p>
                </div>
            @endif

            @if ($solicitudesPendientes > 0)
                <a href="{{ route('solicitudes.index') }}"
                   class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-wrap gap-3 items-center justify-between hover:bg-amber-100 transition focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2">
                    <span class="text-xs sm:text-sm text-amber-800 font-medium">
                        📬 Tienes {{ $solicitudesPendientes }} {{ $solicitudesPendientes === 1 ? 'solicitud' : 'solicitudes' }} por atender: casos nuevos sin primera cita o sin profesional asignado.
                    </span>
                    <span class="text-xs font-bold text-amber-800">Revisar →</span>
                </a>
            @endif

            <!-- Casos por reagendar: los pacientes ya tuvieron su cita y el caso sigue abierto -->
            @if ($porReagendar->isNotEmpty())
                <section id="por-reagendar" aria-labelledby="por-reagendar-titulo" class="bg-white p-4 sm:p-5 rounded-2xl border border-sky-200 shadow-sm space-y-3">
                    <div>
                        <h2 id="por-reagendar-titulo" class="text-base font-bold text-gray-900">
                            Pacientes por reagendar <span class="ms-1 px-2 py-0.5 bg-sky-50 text-sky-700 rounded-full text-xs">{{ $porReagendar->count() }}</span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Ya tuvieron su cita y el caso sigue abierto. Selecciona un paciente para asignarle una nueva fecha y hora.</p>
                    </div>

                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl">
                        @foreach ($porReagendar as $casoPorReagendar)
                            @php($ultima = $casoPorReagendar->ultimaCitaRealizada())
                            <li class="p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-bold text-gray-900 text-sm break-words">{{ $casoPorReagendar->solicitud?->persona?->nombre_completo }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ $casoPorReagendar->codigo_expediente }} · {{ $casoPorReagendar->estado_caso }}
                                        · {{ $casoPorReagendar->sesionesRealizadas() }} {{ $casoPorReagendar->sesionesRealizadas() === 1 ? 'sesión' : 'sesiones' }}
                                        · Última cita: {{ $ultima->fecha_hora->format('Y-m-d') }} a las {{ $ultima->fecha_hora->format('g:i a') }} ({{ $ultima->estado_asistencia }})
                                    </p>
                                </div>
                                <a href="{{ route('citas.create', ['caso' => $casoPorReagendar->id_caso]) }}"
                                   class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm text-center shrink-0 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    Asignar nueva cita <span class="sr-only">a {{ $casoPorReagendar->solicitud?->persona?->nombre_completo }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <!-- Filtro por fecha -->
            <form method="GET" action="{{ route('citas.agenda') }}"
                  class="bg-white p-3 rounded-2xl border border-gray-200/80 shadow-sm flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[180px]">
                    <label for="filtro-fecha" class="block text-xs font-medium text-gray-500 mb-1">Ver citas de una fecha</label>
                    <input id="filtro-fecha" name="fecha" type="date" value="{{ $fecha }}"
                           class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:outline-none focus:border-[#007F2F] focus:ring-[#007F2F]">
                </div>
                <button type="submit" class="px-3.5 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl shadow-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                    Filtrar
                </button>
                @if ($fecha)
                    <a href="{{ route('citas.agenda') }}" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-medium rounded-xl transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        Ver próximas citas
                    </a>
                @endif
            </form>

            @forelse ($citasPorDia as $dia => $citas)
                <section class="space-y-3" aria-labelledby="dia-{{ $dia }}">
                    <h2 id="dia-{{ $dia }}" class="text-xs font-black text-gray-700 uppercase tracking-wider">
                        {{ Carbon::parse($dia)->locale('es')->translatedFormat('l j \d\e F \d\e Y') }}
                        @if ($dia === $hoy)
                            <span class="ms-1 px-2 py-0.5 bg-[#EAF7EF] text-[#007F2F] rounded-full text-[10px] normal-case tracking-normal">Hoy</span>
                        @endif
                    </h2>

                    <ul class="space-y-3">
                        @foreach ($citas as $cita)
                            @php($persona = $cita->caso?->solicitud?->persona)
                            <li class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                <div class="flex items-start gap-4 min-w-0">
                                    <div class="w-16 shrink-0 rounded-xl bg-purple-50 text-purple-700 flex flex-col items-center justify-center py-2">
                                        <span class="text-sm font-bold leading-none">{{ $cita->fecha_hora->format('g:i') }}</span>
                                        <span class="text-[10px] font-semibold mt-0.5">{{ $cita->fecha_hora->format('a') }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="font-bold text-gray-900 text-base break-words">{{ $persona?->nombre_completo }}</h3>
                                            @if ($cita->estaCancelada())
                                                <span class="px-2.5 py-0.5 bg-gray-100 text-gray-600 text-[11px] font-semibold rounded-full">⊘ Cancelada por remisión</span>
                                            @elseif ($cita->esProxima())
                                                <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-700 text-[11px] font-semibold rounded-full">Próxima</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            {{ $cita->caso?->codigo_expediente }} · Asesoría psicológica · {{ \App\Models\Actuacion::DURACION_MINUTOS }} min
                                        </p>
                                        @if ($cita->descripcion)
                                            <p class="text-xs font-medium text-gray-600 mt-1 break-words">{{ $cita->descripcion }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex sm:flex-col items-center sm:items-end gap-2 shrink-0">
                                    @if ($cita->caso)
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $estadoCaso[$cita->caso->estado_caso] ?? 'bg-gray-100 text-gray-600' }}">{{ $cita->caso->estado_caso }}</span>
                                        <a href="{{ route('casos.show', $cita->caso) }}"
                                           class="text-xs font-bold text-[#007F2F] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2 rounded">
                                            Ver caso <span class="sr-only">{{ $cita->caso->codigo_expediente }}</span> →
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <!-- Estado vacío -->
                <div class="max-w-2xl mx-auto bg-white rounded-2xl p-10 sm:p-14 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center">
                    <div class="text-5xl mb-4 p-4 bg-gray-50 rounded-2xl border border-gray-100 shadow-inner" aria-hidden="true">🗓️</div>
                    <h2 class="text-xl font-bold text-gray-800 mb-1">
                        {{ $fecha ? 'No tienes citas el '.$fecha : 'No tienes citas próximas' }}
                    </h2>
                    <p class="text-gray-500 text-sm mb-6 max-w-sm">Agenda una cita de asesoría psicológica para uno de tus casos activos.</p>
                    <a href="{{ route('citas.create') }}"
                       class="bg-[#007F2F] hover:bg-[#006324] text-white px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-200 shadow-md flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        Agendar cita
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>

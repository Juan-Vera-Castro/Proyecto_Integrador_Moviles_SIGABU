@php
    use App\Models\Actuacion;

    $servicioEtiqueta = ['Salud Mental' => '🧠 Atención Psicológica', 'Inclusión' => '♿ Inclusión Universitaria'];
    $errores = $errors->getBag('respuesta');
    $total = $nuevos->count() + $sinAsignar->count();
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-[#007F2F] tracking-tight">Solicitudes de cita</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">
                    {{ $total }} {{ $total === 1 ? 'solicitud por atender' : 'solicitudes por atender' }}
                </p>
            </div>

            <a href="{{ route('citas.agenda') }}"
               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                Ver mi agenda
            </a>
        </div>

        <div class="p-4 sm:p-8 space-y-8 max-w-4xl w-full mx-auto">

            @if (session('status'))
                <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 flex gap-3 items-center" role="status">
                    <span class="text-xl shrink-0" aria-hidden="true">✅</span>
                    <p class="text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">{{ session('status') }}</p>
                </div>
            @endif

            @if (session('error') || $errores->has('solicitud'))
                <div class="bg-red-50 border border-red-200 text-red-600 text-sm font-medium rounded-xl px-4 py-3" role="alert">
                    {{ session('error') ?? $errores->first('solicitud') }}
                </div>
            @endif

            <!-- Casos nuevos: llegaron por la facultad y jornada del estudiante y aún no tienen cita -->
            <section aria-labelledby="nuevos-titulo" class="space-y-4">
                <div>
                    <h2 id="nuevos-titulo" class="text-lg font-bold text-gray-900">Casos nuevos a tu cargo</h2>
                    <p class="text-xs text-gray-500">Te llegaron por la facultad y la jornada del estudiante. Envíale varios horarios dentro de su franja para que elija uno, o agenda la cita si ya la acordaron.</p>
                </div>

                @forelse ($nuevos as $caso)
                    @php
                        $solicitud = $caso->solicitud;
                        $persona = $solicitud?->persona;
                        $conErrores = old('caso') === $caso->id_caso && $errores->hasAny(['fecha', 'hora']);
                        $consentimiento = $caso->consentimientos->last();
                    @endphp
                    <article id="solicitud-{{ $caso->id_caso }}" class="bg-white rounded-2xl border {{ $caso->esEmergencia() ? 'border-rose-300 ring-1 ring-rose-200' : 'border-gray-100' }} shadow-sm p-5 sm:p-6 space-y-4 scroll-mt-6">
                        <header>
                            <h3 class="font-bold text-gray-900 text-base">{{ $persona?->nombre_completo }} @include('casos.partials.emergencia', ['caso' => $caso])</h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $caso->codigo_expediente }} · {{ $servicioEtiqueta[$solicitud?->tipo_servicio] ?? $solicitud?->tipo_servicio }}
                                · solicitada el {{ $solicitud?->fecha_solicitud?->format('Y-m-d') ?? $caso->fecha_apertura?->format('Y-m-d') }}
                            </p>
                        </header>

                        @include('citas.partials.datos-solicitud', ['solicitud' => $solicitud, 'persona' => $persona, 'consentimiento' => $consentimiento])

                        @include('citas.partials.proponer-horarios', ['caso' => $caso])

                        <p class="text-xs text-gray-500">O, si ya acordaron la hora, agéndala directamente:</p>
                        <form method="POST" action="{{ route('solicitudes.agendar', $caso) }}"
                              class="bg-[#F3FBF6] border border-[#BFE6CE] rounded-xl p-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="caso" value="{{ $caso->id_caso }}">

                            <div class="min-w-[150px]">
                                <label for="fecha-{{ $caso->id_caso }}" class="block text-xs font-medium text-gray-600 mb-1">Fecha de la primera cita</label>
                                <input id="fecha-{{ $caso->id_caso }}" name="fecha" type="date" min="{{ $hoy }}" value="{{ $conErrores ? old('fecha') : '' }}" required
                                       class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:outline-none focus:border-[#007F2F] focus:ring-[#007F2F]">
                            </div>
                            <div class="min-w-[130px]">
                                <label for="hora-{{ $caso->id_caso }}" class="block text-xs font-medium text-gray-600 mb-1">Hora</label>
                                <select id="hora-{{ $caso->id_caso }}" name="hora" required
                                        class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:outline-none focus:border-[#007F2F] focus:ring-[#007F2F]">
                                    <option value="">Selecciona</option>
                                    @foreach ($horarios as $hora)
                                        <option value="{{ $hora }}" @selected($conErrores && old('hora') === $hora)>{{ Actuacion::etiquetaHora($hora) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit"
                                    class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl shadow-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                Agendar primera cita
                            </button>
                            <a href="{{ route('casos.show', $caso) }}" class="px-4 py-2 text-xs font-bold text-[#007F2F] hover:underline">Ver expediente</a>

                            @if ($conErrores)
                                <p class="w-full text-xs text-red-600 font-medium" role="alert">{{ $errores->first('fecha') ?: $errores->first('hora') }}</p>
                            @endif
                        </form>
                    </article>
                @empty
                    <p class="bg-white rounded-2xl p-6 border border-gray-100 text-sm text-gray-500 text-center">No tienes casos nuevos sin primera cita.</p>
                @endforelse
            </section>

            <!-- Sin profesional: nadie atiende ese servicio en esa facultad y jornada -->
            @if ($sinAsignar->isNotEmpty())
                <section aria-labelledby="sin-asignar-titulo" class="space-y-4">
                    <div>
                        <h2 id="sin-asignar-titulo" class="text-lg font-bold text-gray-900">Solicitudes sin profesional asignado</h2>
                        <p class="text-xs text-gray-500">Llegaron sin profesional porque nadie tiene asignado ese servicio. Si puedes atenderla, asígnatela; el administrador configura las asignaciones en "Asignaciones".</p>
                    </div>

                    @foreach ($sinAsignar as $caso)
                        @php
                            $solicitud = $caso->solicitud;
                            $persona = $solicitud?->persona;
                        @endphp
                        <article id="solicitud-{{ $caso->id_caso }}" class="bg-white rounded-2xl border {{ $caso->esEmergencia() ? 'border-rose-300 ring-1 ring-rose-200' : 'border-amber-100' }} shadow-sm p-5 sm:p-6 space-y-4 scroll-mt-6">
                            <header class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold text-gray-900 text-base">{{ $persona?->nombre_completo }} @include('casos.partials.emergencia', ['caso' => $caso])</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $caso->codigo_expediente }} · {{ $servicioEtiqueta[$solicitud?->tipo_servicio] ?? $solicitud?->tipo_servicio }}
                                        · solicitada el {{ $solicitud?->fecha_solicitud?->format('Y-m-d') ?? $caso->fecha_apertura?->format('Y-m-d') }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('solicitudes.tomar', $caso) }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl shadow-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                        Asignármela
                                    </button>
                                </form>
                            </header>

                            @include('citas.partials.datos-solicitud', ['solicitud' => $solicitud, 'persona' => $persona, 'consentimiento' => $caso->consentimientos->last()])
                        </article>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
</x-app-layout>

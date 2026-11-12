{{-- Alertas tempranas del caso (HU-026): la pendiente arriba, con su botón; luego las ya atendidas. --}}
@php
    $alertaPendiente = $caso->alertas->first(fn ($alerta) => $alerta->estaPendiente());
    $alertasAtendidas = $caso->alertas->reject(fn ($alerta) => $alerta->estaPendiente());
@endphp

@if ($caso->alertas->isNotEmpty())
    <section id="alertas" aria-labelledby="alertas-titulo" class="space-y-2">
        <h2 id="alertas-titulo" class="sr-only">Alertas tempranas</h2>

        @if ($alertaPendiente)
            <div class="bg-rose-50 border-l-4 border-rose-600 rounded-r-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3" role="alert">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="text-2xl shrink-0" aria-hidden="true">🚨</span>
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-bold text-rose-800">
                            Alerta temprana pendiente
                            <span class="px-2 py-0.5 bg-rose-100 text-rose-800 text-xs font-semibold rounded-full whitespace-nowrap">{{ $alertaPendiente->tipo_alerta }}</span>
                        </p>
                        <p class="text-xs sm:text-sm text-rose-700 mt-1 break-words">{{ $alertaPendiente->descripcion }}</p>
                        <p class="text-xs text-rose-700 mt-0.5">Generada el {{ $alertaPendiente->fecha_generacion->format('Y-m-d H:i') }}.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('alertas.atender', $alertaPendiente->id_alerta) }}" class="shrink-0">
                    @csrf
                    @method('PUT')
                    <button type="submit"
                            class="w-full sm:w-auto px-4 py-2 bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-700 focus:ring-offset-2">
                        Marcar como atendida
                    </button>
                </form>
            </div>
        @endif

        @if ($alertasAtendidas->isNotEmpty())
            <details class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4 text-sm">
                <summary class="cursor-pointer font-semibold text-gray-800">Alertas atendidas ({{ $alertasAtendidas->count() }})</summary>
                <ul class="mt-2 space-y-1.5">
                    @foreach ($alertasAtendidas as $alerta)
                        <li class="text-xs text-gray-600 break-words">
                            <span class="font-semibold text-gray-800">{{ $alerta->tipo_alerta }}</span>
                            · generada el {{ $alerta->fecha_generacion->format('Y-m-d H:i') }}
                            · atendida por {{ $alerta->usuarioAtendio?->persona?->nombre_completo ?? 'Profesional' }}
                            el {{ $alerta->fecha_atencion?->format('Y-m-d H:i') }}
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>
@endif

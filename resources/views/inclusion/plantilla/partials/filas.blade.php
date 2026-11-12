{{-- Filas de la plantilla: tarjetas apiladas en celular y filas desde md. Variable: $ajustes. --}}
@php
    $columnas = 'md:grid-cols-[8rem_minmax(0,1fr)_minmax(0,1fr)_7rem_5.5rem_8rem_minmax(0,1fr)]';
@endphp

@if ($ajustes->isEmpty())
    <p class="text-sm text-gray-600">Aún no hay filas en la plantilla.</p>
@else
    <div class="hidden md:grid {{ $columnas }} gap-3 px-3 text-xs font-semibold text-gray-500" aria-hidden="true">
        <span>Área / Barrera</span><span>Descripción de la barrera</span><span>Ajuste o estrategia</span><span>Responsable</span><span>Fecha</span><span>Evidencia</span><span>Observaciones</span>
    </div>

    <div class="space-y-2">
        @foreach ($ajustes as $ajuste)
            <article class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-sm" aria-label="Ajuste de {{ $ajuste->etiqueta() }}">
                <div class="grid grid-cols-1 {{ $columnas }} gap-1.5 md:gap-3">
                    <p class="font-semibold text-gray-900 break-words">{{ $ajuste->etiqueta() }}</p>
                    <p class="text-gray-800 whitespace-pre-line break-words"><span class="md:hidden text-xs text-gray-500 block">Barrera</span>{{ $ajuste->barrera }}</p>
                    <p class="text-gray-800 whitespace-pre-line break-words"><span class="md:hidden text-xs text-gray-500 block">Ajuste</span>{{ $ajuste->descripcion }}</p>
                    <p class="text-xs text-gray-700 break-words"><span class="md:hidden text-gray-500">Responsable: </span>{{ $ajuste->responsable ?: '—' }}</p>
                    <p class="text-xs text-gray-700"><span class="md:hidden text-gray-500">Fecha: </span>{{ $ajuste->fecha_inicio->format('d/m/Y') }}</p>
                    <div class="min-w-0">
                        @if ($ajuste->evidencias->isNotEmpty())
                            @include('inclusion.bitacora.partials.evidencias', ['evidencias' => $ajuste->evidencias])
                        @else
                            <span class="text-xs text-gray-500">Sin evidencia</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-700 whitespace-pre-line break-words"><span class="md:hidden text-gray-500 block">Observaciones</span>{{ $ajuste->observaciones ?: '—' }}</p>
                </div>
                <a href="{{ route('casos.show', $ajuste->id_caso) }}#ajuste-{{ $ajuste->id_ajuste }}" class="inline-block mt-2 text-xs font-bold text-[#006324] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">Ver seguimiento · {{ $ajuste->estado }}</a>
            </article>
        @endforeach
    </div>
@endif

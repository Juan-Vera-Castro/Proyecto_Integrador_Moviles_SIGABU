{{-- Barras horizontales (HTML/CSS) de un desglose anonimizado. Un grupo pequeño (valor null) se
     muestra como "<N" sin barra. Recibe $titulo, $filas (lista de [etiqueta, valor|null]),
     $minimo y, opcional, $colores (etiqueta => [hex, icono]) para estados como el riesgo. --}}
@php
    $colores ??= [];
    $visibles = collect($filas)->pluck('valor')->filter(fn ($v) => $v !== null);
    $maximo = max(1, $visibles->max() ?? 1);
    $haySuprimidos = collect($filas)->contains(fn ($f) => $f['valor'] === null);
    $vacio = collect($filas)->every(fn ($f) => $f['valor'] === 0);
@endphp

<figure class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col" aria-label="{{ $titulo }}">
    <figcaption class="text-base font-bold text-gray-900">{{ $titulo }}</figcaption>

    @if ($vacio)
        <p class="text-sm text-gray-500 mt-3">Sin datos en el periodo.</p>
    @else
        <dl class="mt-3 space-y-2.5">
            @foreach ($filas as $fila)
                @php [$color, $icono] = $colores[$fila['etiqueta']] ?? ['#2a78d6', null]; @endphp
                <div class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-3 text-sm"
                     title="{{ $fila['etiqueta'] }}: {{ $fila['valor'] === null ? 'menos de '.$minimo.' (oculto para proteger la identidad)' : $fila['valor'] }}">
                    <dt class="text-gray-700 truncate">@if ($icono) <span aria-hidden="true">{{ $icono }}</span> @endif{{ $fila['etiqueta'] }}</dt>
                    <dd class="h-3 bg-gray-100 rounded-sm overflow-hidden" aria-hidden="true">
                        @if ($fila['valor'])
                            <div class="h-full rounded-r" style="width: {{ max(2, round(100 * $fila['valor'] / $maximo)) }}%; background: {{ $color }}"></div>
                        @endif
                    </dd>
                    <dd class="tabular-nums text-right w-10 {{ $fila['valor'] === null ? 'text-gray-400' : 'text-gray-900 font-semibold' }}">
                        {{ $fila['valor'] === null ? '<'.$minimo : $fila['valor'] }}
                    </dd>
                </div>
            @endforeach
        </dl>
        @if ($haySuprimidos)
            <p class="text-[11px] text-gray-400 mt-3">"&lt;{{ $minimo }}": grupo con menos de {{ $minimo }} casos, oculto para proteger la identidad.</p>
        @endif
    @endif
</figure>

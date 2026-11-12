{{-- Contenido de un recurso: bloques de datos, nunca HTML guardado. Variables: $bloques, $prefijo. --}}
<div class="space-y-3 text-[15px] sm:text-sm text-gray-800 leading-relaxed break-words">
    @foreach ($bloques as $indice => $bloque)
        @switch($bloque['tipo'])
            @case('titulo')
                <h2 id="{{ $prefijo }}-{{ $indice }}" class="text-lg font-bold text-[#006324] pt-3 scroll-mt-20">{{ $bloque['texto'] }}</h2>
                @break

            @case('subtitulo')
                <h3 class="text-base font-bold text-gray-900 pt-2">{{ $bloque['texto'] }}</h3>
                @break

            @case('parrafo')
                <p>{{ $bloque['texto'] }}</p>
                @break

            @case('lista')
                <{{ empty($bloque['ordenada']) ? 'ul' : 'ol' }} class="pl-5 space-y-1 {{ empty($bloque['ordenada']) ? 'list-disc' : 'list-decimal' }}">
                    @foreach ($bloque['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </{{ empty($bloque['ordenada']) ? 'ul' : 'ol' }}>
                @break

            @case('destacado')
                <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4">
                    <p class="font-bold text-[#1F5134]">{{ $bloque['titulo'] }}</p>
                    <p>{{ $bloque['texto'] }}</p>
                </div>
                @break

            @case('tabla')
                {{-- Desde md es una tabla; por debajo, una tarjeta por fila. --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-xs border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                @foreach ($bloque['encabezados'] as $encabezado)
                                    <th scope="col" class="p-2 text-left font-semibold text-gray-700 border-b border-gray-200">{{ $encabezado }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bloque['filas'] as $fila)
                                <tr class="border-b border-gray-100 align-top">
                                    @foreach ($fila as $celda)
                                        <td class="p-2">{{ $celda }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="md:hidden space-y-2">
                    @foreach ($bloque['filas'] as $fila)
                        <dl class="border border-gray-200 rounded-xl p-3 space-y-1.5">
                            @foreach ($fila as $posicion => $celda)
                                <div>
                                    <dt class="text-xs text-gray-500">{{ $bloque['encabezados'][$posicion] ?? '' }}</dt>
                                    <dd>{{ $celda }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endforeach
                </div>
                @break
        @endswitch
    @endforeach
</div>

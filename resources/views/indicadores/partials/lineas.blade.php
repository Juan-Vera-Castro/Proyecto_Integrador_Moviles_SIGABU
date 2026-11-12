{{-- Gráfico de líneas (SVG) con varias series sobre los mismos meses, con leyenda, etiqueta directa,
     texto al pasar el cursor y tabla equivalente. Recibe $titulo, $series (nombre => [mes => valor]),
     $colores (nombre => hex) y $meses (lista Y-m). --}}
@php
    use Illuminate\Support\Carbon;

    $ancho = 640; $alto = 220;
    [$izq, $der, $arriba, $abajo] = [36, 96, 14, 28];
    $anchoUtil = $ancho - $izq - $der; $altoUtil = $alto - $arriba - $abajo;
    $n = count($meses);
    $maximo = max(1, collect($series)->flatten()->max());
    // Escala con números redondos: 4 divisiones.
    $paso = max(1, (int) ceil($maximo / 4));
    $tope = $paso * 4;
    $x = fn (int $i) => round($izq + ($n === 1 ? $anchoUtil / 2 : $i * $anchoUtil / ($n - 1)), 1);
    $y = fn (int $v) => round($arriba + $altoUtil * (1 - $v / $tope), 1);
    $mesCorto = fn (string $mes) => ucfirst(Carbon::createFromFormat('Y-m', $mes)->locale('es')->translatedFormat('M y'));
    $idTitulo = 'g-'.Str::slug($titulo);
@endphp

<figure class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5" aria-labelledby="{{ $idTitulo }}">
    <figcaption id="{{ $idTitulo }}" class="text-base font-bold text-gray-900">{{ $titulo }}</figcaption>

    @if (count($series) > 1)
        <ul class="flex flex-wrap gap-4 mt-2 text-xs text-gray-600" aria-label="Leyenda">
            @foreach ($series as $nombre => $valores)
                <li class="flex items-center gap-1.5"><span class="inline-block w-3 h-0.5 rounded" style="background: {{ $colores[$nombre] }}"></span>{{ $nombre }}</li>
            @endforeach
        </ul>
    @endif

    <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" class="w-full h-auto mt-3" role="img" aria-label="{{ $titulo }}. La tabla de abajo tiene los valores.">
        {{-- Cuadrícula y eje Y, discretos --}}
        @foreach (range(0, 4) as $i)
            @php $v = $paso * $i; @endphp
            <line x1="{{ $izq }}" x2="{{ $ancho - $der }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" stroke="{{ $i === 0 ? '#a8a29e' : '#e7e5e4' }}" stroke-width="1" />
            <text x="{{ $izq - 6 }}" y="{{ $y($v) + 3.5 }}" text-anchor="end" font-size="10" fill="#78716c">{{ $v }}</text>
        @endforeach
        @foreach ($meses as $i => $mes)
            <text x="{{ $x($i) }}" y="{{ $alto - 8 }}" text-anchor="middle" font-size="10" fill="#78716c">{{ $mesCorto($mes) }}</text>
        @endforeach

        @foreach ($series as $nombre => $valores)
            @php
                $puntos = collect(array_values($valores))->map(fn ($v, $i) => [$x($i), $y((int) $v), (int) $v]);
                $ultimo = $puntos->last();
            @endphp
            <polyline points="{{ $puntos->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}" fill="none" stroke="{{ $colores[$nombre] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
            @foreach ($puntos as $i => [$px, $py, $v])
                <g>
                    <title>{{ $nombre }} · {{ $mesCorto($meses[$i]) }}: {{ $v }}</title>
                    {{-- Zona de toque más grande que el punto --}}
                    <circle cx="{{ $px }}" cy="{{ $py }}" r="10" fill="transparent" />
                    <circle cx="{{ $px }}" cy="{{ $py }}" r="4" fill="{{ $colores[$nombre] }}" stroke="#ffffff" stroke-width="2" />
                </g>
            @endforeach
            {{-- Etiqueta directa al final de la línea, en tinta de texto --}}
            <text x="{{ $ultimo[0] + 8 }}" y="{{ $ultimo[1] + 3.5 }}" font-size="11" fill="#44403c">{{ $nombre }} · {{ $ultimo[2] }}</text>
        @endforeach
    </svg>

    <details class="mt-2">
        <summary class="cursor-pointer text-xs font-bold text-[#007F2F] select-none">Ver tabla</summary>
        <table class="w-full text-xs mt-2">
            <thead><tr class="text-left text-gray-500">
                <th scope="col" class="py-1 pr-2">Mes</th>
                @foreach ($series as $nombre => $valores) <th scope="col" class="py-1 pr-2 text-right">{{ $nombre }}</th> @endforeach
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($meses as $mes)
                    <tr>
                        <th scope="row" class="py-1 pr-2 text-left font-medium text-gray-700">{{ $mesCorto($mes) }}</th>
                        @foreach ($series as $valores) <td class="py-1 pr-2 text-right tabular-nums">{{ $valores[$mes] ?? 0 }}</td> @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>

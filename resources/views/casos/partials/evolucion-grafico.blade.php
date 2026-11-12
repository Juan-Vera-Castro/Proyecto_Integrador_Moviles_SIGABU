{{-- Gráfico SVG de una escala (GAD-7 o PHQ-9) con sus bandas de severidad, más su tabla equivalente. --}}
@php
    use App\Models\SeguimientoEvolucion;
    use Illuminate\Support\Carbon;

    $ancho = 600;
    $alto = 220;
    [$izq, $der, $arriba, $abajo] = [34, 14, 12, 30];
    $anchoUtil = $ancho - $izq - $der;
    $altoUtil = $alto - $arriba - $abajo;
    $maximo = $escala['puntaje_maximo'];
    $mediciones = $escala['mediciones'];
    $n = count($mediciones);

    $y = fn (float $puntaje) => round($arriba + $altoUtil * (1 - $puntaje / $maximo), 1);
    $x = fn (int $i) => round($izq + ($n === 1 ? $anchoUtil / 2 : $i * $anchoUtil / ($n - 1)), 1);

    $fondos = ['#ecfdf5', '#f7fee7', '#fefce8', '#fff7ed', '#fef2f2'];
    $bandas = [];
    $inicios = array_keys(SeguimientoEvolucion::SEVERIDADES[$escala['instrumento']]);
    foreach ($inicios as $i => $desde) {
        $hasta = $inicios[$i + 1] ?? $maximo + 1;
        $bandas[] = [
            'nombre' => SeguimientoEvolucion::SEVERIDADES[$escala['instrumento']][$desde],
            'y' => $y(min($hasta, $maximo)),
            'alto' => $y($desde) - $y(min($hasta, $maximo)),
            'color' => $fondos[$i] ?? '#f9fafb',
            'desde' => $desde,
        ];
    }

    $puntos = collect($mediciones)->values()->map(fn ($m, $i) => [
        'x' => $x($i),
        'y' => $y($m['puntaje']),
        'puntaje' => $m['puntaje'],
        'severidad' => $m['severidad'],
        'fecha' => Carbon::parse($m['fecha']),
    ]);
    $cadaCuanto = max(1, (int) ceil($n / 8));
    $primera = $puntos->first();
    $ultima = $puntos->last();
    $descripcion = $n === 1
        ? "{$escala['instrumento']}: una medición de {$primera['puntaje']} puntos ({$primera['severidad']}) el {$primera['fecha']->format('Y-m-d')}."
        : "{$escala['instrumento']} pasó de {$primera['puntaje']} a {$ultima['puntaje']} puntos entre el {$primera['fecha']->format('Y-m-d')} y el {$ultima['fecha']->format('Y-m-d')}, en {$n} mediciones.";
    $color = $escala['instrumento'] === SeguimientoEvolucion::GAD7 ? '#007F2F' : '#2563eb';
@endphp

<figure class="space-y-2">
    <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" class="w-full h-auto" role="img" aria-label="{{ $descripcion }}">
        @foreach ($bandas as $banda)
            <rect x="{{ $izq }}" y="{{ $banda['y'] }}" width="{{ $anchoUtil }}" height="{{ $banda['alto'] }}" fill="{{ $banda['color'] }}" />
            <text x="{{ $izq + $anchoUtil - 4 }}" y="{{ $banda['y'] + 11 }}" text-anchor="end" font-size="10" fill="#6b7280">{{ $banda['nombre'] }}</text>
            <text x="{{ $izq - 6 }}" y="{{ $y($banda['desde']) + 3 }}" text-anchor="end" font-size="10" fill="#6b7280">{{ $banda['desde'] }}</text>
        @endforeach
        <text x="{{ $izq - 6 }}" y="{{ $y($maximo) + 3 }}" text-anchor="end" font-size="10" fill="#6b7280">{{ $maximo }}</text>
        <line x1="{{ $izq }}" y1="{{ $arriba + $altoUtil }}" x2="{{ $izq + $anchoUtil }}" y2="{{ $arriba + $altoUtil }}" stroke="#d1d5db" />

        @if ($n > 1)
            <polyline points="{{ $puntos->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}" fill="none" stroke="{{ $color }}" stroke-width="2.5" stroke-linejoin="round" />
        @endif
        @foreach ($puntos as $i => $punto)
            <circle cx="{{ $punto['x'] }}" cy="{{ $punto['y'] }}" r="5" fill="{{ $color }}" stroke="#fff" stroke-width="2">
                <title>{{ $punto['fecha']->format('Y-m-d') }}: {{ $punto['puntaje'] }} puntos ({{ $punto['severidad'] }})</title>
            </circle>
            @if ($i % $cadaCuanto === 0 || $i === $n - 1)
                <text x="{{ $punto['x'] }}" y="{{ $alto - 10 }}" text-anchor="middle" font-size="10" fill="#374151">{{ $punto['fecha']->format('d/m') }}</text>
            @endif
        @endforeach
    </svg>

    <details class="text-xs">
        <summary class="cursor-pointer font-semibold text-gray-600">Ver datos de {{ $escala['instrumento'] }}</summary>
        <table class="mt-2 w-full text-left">
            <caption class="sr-only">Mediciones de {{ $escala['instrumento'] }}</caption>
            <thead>
                <tr class="text-gray-500">
                    <th scope="col" class="py-1 font-semibold">Fecha</th>
                    <th scope="col" class="py-1 font-semibold">Puntaje</th>
                    <th scope="col" class="py-1 font-semibold">Severidad</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($puntos as $punto)
                    <tr>
                        <td class="py-1">{{ $punto['fecha']->format('Y-m-d') }}</td>
                        <td class="py-1">{{ $punto['puntaje'] }} / {{ $maximo }}</td>
                        <td class="py-1">{{ $punto['severidad'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>

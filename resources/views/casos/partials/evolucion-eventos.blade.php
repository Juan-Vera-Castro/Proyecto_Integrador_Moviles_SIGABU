{{-- Eventos de la línea de tiempo de la evolución (HU-015). Se usa en la carga inicial y en "Cargar más". --}}
@php
    use App\Models\Actuacion;
    use App\Models\SeguimientoEvolucion;
    use App\Support\EvolucionCaso;

    $iconos = [
        'apertura' => '📂', 'consentimiento' => '✍️', 'historia_clinica' => '📋', 'anotacion' => '📝',
        'cita' => '🗓️', 'tarea' => '✅', 'remision' => '↪️', 'desistimiento' => '⏹️', 'cierre' => '🔒',
    ];
    $colores = [
        'Asistió' => 'bg-emerald-50 text-emerald-700', 'Cumplida' => 'bg-emerald-50 text-emerald-700', 'Aceptado' => 'bg-emerald-50 text-emerald-700',
        'No asistió' => 'bg-rose-50 text-rose-700', 'No cumplida' => 'bg-rose-50 text-rose-700', 'Rechazado' => 'bg-rose-50 text-rose-700', 'No aceptado' => 'bg-rose-50 text-rose-700',
        'Programada' => 'bg-amber-50 text-amber-700', 'Sin registro de asistencia' => 'bg-amber-50 text-amber-700', 'Pendiente de evaluación' => 'bg-amber-50 text-amber-700',
        'Interna' => 'bg-sky-50 text-sky-700', 'Externa' => 'bg-sky-50 text-sky-700',
    ];
    $riesgo = [
        'Alto' => 'bg-rose-50 border-rose-200 text-rose-600',
        'Medio' => 'bg-amber-50 border-amber-200 text-amber-600',
        'Bajo' => 'bg-emerald-50 border-emerald-200 text-emerald-600',
    ];
    $valoracion = [
        SeguimientoEvolucion::MEJORA => ['↑', 'bg-emerald-50 text-emerald-700'],
        SeguimientoEvolucion::ESTABLE => ['→', 'bg-sky-50 text-sky-700'],
        SeguimientoEvolucion::RETROCESO => ['↓', 'bg-rose-50 text-rose-700'],
    ];
    $mesAnterior = $mesPrevio ?? null;
@endphp

@foreach ($eventos as $evento)
    @php
        $mes = $evento['fecha']->format('Y-m');
        $seguimiento = $evento['seguimiento'];
        $sinSeguimiento = $evento['tipo'] === 'cita' && ! $seguimiento && ($evento['detalle']['estado_asistencia'] ?? null) === Actuacion::ASISTIO;
    @endphp

    @if ($mes !== $mesAnterior)
        <li class="pt-2 first:pt-0" data-mes="{{ $mes }}" role="presentation">
            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ ucfirst($evento['fecha']->copy()->locale('es')->translatedFormat('F \d\e Y')) }}</h3>
        </li>
        @php $mesAnterior = $mes; @endphp
    @endif

    <li class="relative pl-9" data-mes="{{ $mes }}" data-evento tabindex="-1">
        <span class="absolute left-0 top-3 w-7 h-7 rounded-full bg-white border border-gray-200 flex items-center justify-center text-sm" aria-hidden="true">{{ $iconos[$evento['tipo']] ?? '•' }}</span>

        <article class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm space-y-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <h4 class="text-sm font-bold text-gray-900 break-words">{{ $evento['titulo'] }}</h4>
                    <p class="text-[11px] text-gray-500">
                        <time datetime="{{ EvolucionCaso::iso($evento['fecha']) }}">{{ $evento['fecha']->format('Y-m-d') }} · {{ $evento['fecha']->format('g:i a') }}</time>
                        @if ($evento['profesional'])
                            · {{ $evento['profesional']['nombre'] }}
                        @endif
                    </p>
                </div>
                @if ($evento['etiqueta'])
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0 {{ $colores[$evento['etiqueta']] ?? 'bg-gray-100 text-gray-600' }}">{{ $evento['etiqueta'] }}</span>
                @endif
            </div>

            @if ($evento['descripcion'])
                <p class="text-xs text-gray-700 whitespace-pre-line break-words">{{ $evento['descripcion'] }}</p>
            @endif

            @if ($evento['tipo'] === 'historia_clinica')
                <dl class="text-xs text-gray-700 space-y-1">
                    <div><dt class="inline font-semibold">Impresión diagnóstica:</dt> <dd class="inline break-words">{{ $evento['detalle']['impresion_diagnostica'] }}</dd></div>
                    <div><dt class="inline font-semibold">Plan:</dt> <dd class="inline break-words">{{ $evento['detalle']['plan_intervencion'] }}</dd></div>
                </dl>
            @endif

            @if ($seguimiento)
                @php [$flecha, $colorValoracion] = $valoracion[$seguimiento->valoracion_avance] ?? ['', '']; @endphp
                <div class="bg-gray-50 border border-gray-100 rounded-lg p-3 space-y-2">
                    <p class="text-[11px] font-semibold text-gray-500">Seguimiento de la sesión</p>
                    <p class="flex flex-wrap items-center gap-1.5">
                        <span class="px-2 py-0.5 rounded-full border text-[11px] font-bold {{ $riesgo[$seguimiento->nivel_riesgo] ?? '' }}">Riesgo {{ mb_strtolower($seguimiento->nivel_riesgo) }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $colorValoracion }}">{{ $flecha }} {{ $seguimiento->valoracion_avance }}</span>
                        @if ($seguimiento->instrumento)
                            <span class="px-2 py-0.5 bg-white border border-gray-200 text-gray-700 rounded-full text-[11px] font-semibold">
                                {{ $seguimiento->instrumento }}: {{ $seguimiento->puntaje }}/{{ SeguimientoEvolucion::INSTRUMENTOS[$seguimiento->instrumento] }} ({{ $seguimiento->severidad() }})
                            </span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-700 whitespace-pre-line break-words">{{ $seguimiento->nota }}</p>
                </div>
            @elseif ($sinSeguimiento)
                <p class="text-xs text-amber-700 font-medium">
                    Cita sin seguimiento registrado.
                    @if ($puedeRegistrar)
                        <a href="{{ $evento['enlace'] }}" class="underline font-bold text-[#007F2F] hover:text-[#006324]">Registrar seguimiento</a>
                    @endif
                </p>
            @endif

            @if ($evento['enlace'])
                <a href="{{ $evento['enlace'] }}" class="inline-block text-[11px] font-semibold text-[#007F2F] hover:underline">Ver en el expediente →</a>
            @endif
        </article>
    </li>
@endforeach

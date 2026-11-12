@php
    use App\Models\Vbg\Reporte;

    $colorEstado = [Reporte::RECIBIDO => 'bg-amber-50 text-amber-700', Reporte::EN_ATENCION => 'bg-sky-50 text-sky-700', Reporte::CERRADO => 'bg-gray-100 text-gray-600'];
    $colorRiesgo = ['Alto' => 'bg-rose-50 text-rose-700', 'Medio' => 'bg-amber-50 text-amber-700', 'Bajo' => 'bg-emerald-50 text-emerald-700'];
    $indicadores = [
        ['📥', 'Por tomar', $resumen['recibidos']],
        ['🗂️', 'En atención', $resumen['enAtencion']],
        ['🚨', 'Riesgo alto abiertos', $resumen['riesgoAlto']],
        ['👤', 'A tu cargo', $resumen['mios']],
        ['📅', 'Tus citas hoy', $citasHoy?->count() ?? '—'],
    ];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#7E22CE]">Violencias de Género · SIGABU</h1>
                <p class="text-gray-500 text-sm">Reportes de la comunidad universitaria y gestión de cada caso</p>
            </div>
        </div>

        <div class="p-4 sm:p-8 max-w-[1400px] mx-auto w-full flex flex-col gap-6">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                @foreach ($indicadores as [$icono, $etiqueta, $valor])
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <p class="text-2xl" aria-hidden="true">{{ $icono }}</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $valor }}</p>
                        <p class="text-xs text-gray-500 font-medium">{{ $etiqueta }}</p>
                    </div>
                @endforeach
            </div>

            @if ($citasHoy?->isNotEmpty())
                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm" aria-labelledby="agenda-hoy">
                    <h2 id="agenda-hoy" class="px-5 py-4 border-b border-gray-100 text-base font-bold text-gray-900">Tu agenda de hoy</h2>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($citasHoy as $cita)
                            <li>
                                <a href="{{ route('vbg.reportes.show', $cita->id_reporte) }}#cita-{{ $cita->id_cita }}" class="px-5 py-3 flex flex-wrap items-center justify-between gap-2 hover:bg-gray-50 transition">
                                    <span class="font-semibold text-gray-900">{{ $cita->horario() }} · {{ $cita->modalidad }}@if ($cita->lugar) · {{ $cita->lugar }}@endif</span>
                                    <span class="text-xs font-semibold text-gray-500">{{ $cita->estado }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm" aria-labelledby="bandeja">
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="bandeja" class="text-base font-bold text-gray-900">Reportes</h2>
                    <nav class="flex flex-wrap gap-2 text-xs font-bold" aria-label="Filtrar por estado">
                        @foreach (array_merge([null], Reporte::ESTADOS) as $opcion)
                            <a href="{{ route('vbg.home', $opcion ? ['estado' => $opcion] : []) }}"
                               @class(['px-3 py-1.5 rounded-full border transition', 'bg-[#7E22CE] text-white border-[#7E22CE]' => $estado === $opcion, 'border-gray-200 text-gray-600 hover:bg-gray-50' => $estado !== $opcion])>
                                {{ $opcion ?? 'Todos' }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                @if ($reportes->isEmpty())
                    <p class="p-8 text-center text-sm text-gray-500">No hay reportes {{ $estado ? 'en estado "'.$estado.'"' : 'registrados' }}.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($reportes as $reporte)
                            <li>
                                <a href="{{ route('vbg.reportes.show', $reporte) }}" class="px-5 py-4 flex flex-wrap items-center justify-between gap-3 hover:bg-gray-50 transition">
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-900">{{ $reporte->codigo }} · {{ $reporte->etiquetaAfectado() }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            {{ $reporte->fecha_reporte->format('Y-m-d H:i') }}
                                            · {{ $reporte->tipo_reportante === Reporte::ANONIMO ? 'anónimo' : ($reporte->tipo_reportante === Reporte::TERCERO ? 'reportado por un tercero' : 'reportado por la persona afectada') }}
                                            @if ($reporte->profesional) · a cargo de {{ $reporte->profesional->persona?->nombre_completo }} @endif
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                                        @if ($reporte->tipo_violencia)
                                            <span class="px-2.5 py-1 rounded-full bg-purple-50 text-[#7E22CE]">{{ $reporte->tipo_violencia }}</span>
                                        @endif
                                        @if ($reporte->nivel_riesgo)
                                            <span class="px-2.5 py-1 rounded-full {{ $colorRiesgo[$reporte->nivel_riesgo] }}">Riesgo {{ mb_strtolower($reporte->nivel_riesgo) }}</span>
                                        @endif
                                        <span class="px-2.5 py-1 rounded-full {{ $colorEstado[$reporte->estado] }}">{{ $reporte->estado }}</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>

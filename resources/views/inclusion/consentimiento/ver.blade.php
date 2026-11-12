{{-- P2: el consentimiento registrado. Lo ven la persona, el profesional asignado y el administrador. --}}
@php
    use App\Models\Inclusion\ConsentimientoDetalle;

    $detalle = $aceptacion?->detalleInclusion;
    $volver = match (true) {
        $esDueno => route('citas.miscitas').'#caso-'.$caso->id_caso,
        auth()->user()->role === 'profesional' => route('profesional.expediente.show', $caso),
        default => auth()->user()->getDashboardUrl(),
    };
    $revocacion = $caso->aceptacionesConsentimiento->first()?->esRevocacion() ? $caso->aceptacionesConsentimiento->first() : null;
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-[#007F2F] tracking-tight">{{ $esDueno ? 'Mi consentimiento' : 'Consentimiento informado' }}</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Caso {{ $caso->codigo_expediente }} · Proceso de Inclusión Universitaria</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if ($estado)
                    <x-estado-documento :estado="$estado" />
                @endif
                <a href="{{ $volver }}" class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm"
                   title="Volver" aria-label="Volver">✕</a>
            </div>
        </div>

        <div class="flex-1 p-3 sm:p-8 flex justify-center items-start">
            <div class="w-full max-w-5xl space-y-4">

                @if (session('status'))
                    <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-sm text-[#1F5134] font-medium" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-700 font-medium" role="alert">{{ session('error') }}</div>
                @endif

                @if ($revocacion)
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-800 font-medium" role="status">
                        Revocado el {{ $revocacion->fecha_hora->format('d/m/Y H:i') }}. La atención queda suspendida hasta que se firme un consentimiento nuevo.
                    </div>
                @endif

                @if (! $aceptacion)
                    <section class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-gray-100 text-sm text-gray-600">
                        Aún no hay un consentimiento firmado en este caso.
                    </section>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 items-start">
                        <section class="lg:col-span-3 bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="texto-titulo">
                            <h2 id="texto-titulo" class="text-base font-bold text-gray-900 mb-1">Texto aceptado</h2>
                            <p class="text-xs text-gray-500 mb-3">
                                {{ $aceptacion->version?->modelo?->nombre }} · versión {{ $aceptacion->version?->numero_version }} · {{ $aceptacion->fecha_hora->format('d/m/Y H:i') }}
                            </p>
                            <div class="text-[15px] sm:text-sm text-gray-800 leading-relaxed whitespace-pre-line break-words max-h-[28rem] overflow-y-auto focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded-lg" tabindex="0" role="region" aria-labelledby="texto-titulo">{{ $aceptacion->texto_presentado }}</div>
                        </section>

                        <div class="lg:col-span-2 space-y-4">
                            @if ($detalle)
                                <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="decisiones-titulo">
                                    <h2 id="decisiones-titulo" class="text-base font-bold text-gray-900 mb-3">Decisiones</h2>
                                    <ul class="space-y-2 text-sm text-gray-800">
                                        @foreach ($detalle->decisiones() as $texto => $marcada)
                                            <li class="flex items-start gap-2">
                                                <span aria-hidden="true" @class(['font-bold', 'text-emerald-700' => $marcada, 'text-gray-400' => ! $marcada])>{{ $marcada ? '☑' : '☐' }}</span>
                                                <span><span class="sr-only">{{ $marcada ? 'Marcada: ' : 'Sin marcar: ' }}</span>{{ $texto }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif

                            @if ($aceptacion->esEnPapel())
                                <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 text-sm text-gray-800 space-y-2" aria-labelledby="papel-titulo">
                                    <h2 id="papel-titulo" class="text-base font-bold text-gray-900">{{ ConsentimientoDetalle::LEYENDA_PAPEL }}</h2>
                                    <p>Fecha de la firma: {{ $detalle?->fecha_firma_papel?->format('d/m/Y') }}</p>
                                    <p class="break-words">Motivo: {{ $detalle?->motivo_papel }}</p>
                                    <a href="{{ route('inclusion.consentimiento.papel.archivo', $aceptacion) }}" class="inline-block font-bold text-[#006324] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">Descargar formato firmado</a>
                                </section>
                            @endif

                            <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="historial-titulo">
                                <h2 id="historial-titulo" class="text-base font-bold text-gray-900 mb-3">Historial</h2>
                                <ul class="space-y-2 text-sm">
                                    @foreach ($caso->aceptacionesConsentimiento as $fila)
                                        <li class="flex items-center justify-between gap-3">
                                            <span class="text-gray-800">{{ $fila->etiquetaDecision() }}{{ $fila->esEnPapel() ? ' (en papel)' : '' }}</span>
                                            <span class="text-xs text-gray-500 shrink-0">{{ $fila->fecha_hora->format('d/m/Y H:i') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        </div>
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pb-8">
                    @if ($aceptacion)
                        <a href="{{ route('inclusion.consentimiento.pdf', $caso) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-[#007F2F] text-[#006324] hover:bg-emerald-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2 py-3"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>Descargar PDF</a>
                    @endif
                    @if ($puedeFirmar)
                        <a href="{{ route('inclusion.consentimiento.formulario', $caso) }}"
                           class="px-6 py-3 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm text-center transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                            Firmar un consentimiento nuevo
                        </a>
                    @endif
                    @if ($puedeRevocar)
                        <form method="POST" action="{{ route('inclusion.consentimiento.revocar', $caso) }}"
                              onsubmit="return confirm('¿Confirmas que quieres revocar tu consentimiento? Se suspenderá la atención hasta que firmes uno nuevo. El historial se conserva.')">
                            @csrf
                            <button type="submit" class="w-full sm:w-auto px-5 py-3 bg-white border-2 border-rose-300 text-rose-700 hover:bg-rose-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                                Revocar consentimiento
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

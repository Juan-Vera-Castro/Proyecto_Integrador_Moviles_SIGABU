@php
    use App\Models\Caso;

    $profesional = auth()->user();
    $colorEstado = [
        'Abierto' => 'bg-emerald-100 text-emerald-700',
        'En Seguimiento' => 'bg-amber-100 text-amber-700',
        'Pendiente Asignación' => 'bg-gray-100 text-gray-600',
        'Cerrado' => 'bg-gray-100 text-gray-600',
    ];
    $colorRiesgo = [
        'Alto' => ['text-rose-600', 'bg-rose-600'],
        'Medio' => ['text-amber-600', 'bg-amber-500'],
        'Bajo' => ['text-emerald-600', 'bg-emerald-500'],
    ];
@endphp

<x-app-layout>
    <div class="p-4 sm:p-6 space-y-4">

        <!-- Franja blanca superior: Encabezado y botón X -->
        <div class="flex items-center justify-between gap-4 bg-white p-4 sm:px-6 rounded-2xl shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-[#007F2F]">Expedientes digitales</h1>
                <p class="text-xs sm:text-sm text-gray-400 mt-0.5">
                    {{ $resumen['total'] }} {{ $resumen['total'] === 1 ? 'expediente' : 'expedientes' }} a tu cargo o remitidos por ti
                </p>
            </div>

            <!-- Únicamente el Botón X de Cierre que regresa al Inicio -->
            <a href="{{ route('profesional.home') }}"
               class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition-colors shadow-sm text-sm"
               title="Volver al inicio" aria-label="Volver al inicio">
                ✕
            </a>
        </div>

        @include('casos.partials.filtros', ['ruta' => 'profesional.expedientes'])

        <!-- Lista acumulativa de Expedientes (Soporta scroll vertical) -->
        @if ($casos->isEmpty())
            <div class="bg-white rounded-2xl p-10 shadow-sm border border-gray-100 text-center">
                <p class="text-base font-bold text-gray-800">{{ $hayFiltros ? 'Ningún expediente coincide con la búsqueda' : 'Aún no tienes expedientes' }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ $hayFiltros ? 'Prueba con otro nombre o código, o quita los filtros.' : 'Los casos que abras o recibas por remisión aparecerán aquí.' }}</p>
            </div>
        @else
            <ul class="space-y-3 pb-8">
                @foreach ($casos as $caso)
                    @php
                        $persona = $caso->solicitud?->persona;
                        $estado = $caso->estado_caso;
                        $remitidaPorMi = $caso->remisionHechaPor($profesional);
                        // Quien remitió el caso ya no es el asignado: solo abre el resumen.
                        $esSuCaso = $caso->id_profesional === $profesional->id_usuario;
                        $sesiones = $caso->sesionesRealizadas();
                        [$textoRiesgo, $puntoRiesgo] = $colorRiesgo[$caso->nivel_riesgo] ?? ['text-gray-400', 'bg-gray-300'];
                    @endphp
                    <li>
                        <a href="{{ $esSuCaso ? route('profesional.expediente.show', $caso) : route('casos.show', $caso) }}"
                           class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-[#007F2F] transition flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                            <div class="flex items-start gap-4 min-w-0">
                                <div class="w-11 h-11 rounded-xl bg-purple-50 flex items-center justify-center text-xl shrink-0" aria-hidden="true">
                                    {{ $caso->solicitud?->tipo_servicio === 'Inclusión' ? '♿' : '🧠' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-bold text-gray-900 text-base break-words">{{ $persona?->nombre_completo ?? 'Solicitante sin registrar' }}</h3>
                                        <span class="px-2.5 py-0.5 text-[11px] font-semibold rounded-full {{ $colorEstado[$estado] ?? 'bg-gray-100 text-gray-600' }}">{{ $estado }}</span>
                                        @if ($remitidaPorMi)
                                            <span class="px-2.5 py-0.5 bg-indigo-50 text-indigo-700 text-[11px] font-semibold rounded-full">Remitido por ti</span>
                                        @elseif ($caso->remisionRecibida())
                                            <span class="px-2.5 py-0.5 bg-sky-50 text-sky-700 text-[11px] font-semibold rounded-full">Remisión</span>
                                        @endif
                                        @include('casos.partials.historia-clinica-etiqueta', ['forma' => 'text-[11px] rounded-full'])
                                    </div>
                                    <p class="text-xs text-gray-400 mt-0.5">@include('casos.partials.emergencia', ['caso' => $caso]) {{ $caso->codigo_expediente }} · {{ $caso->solicitud?->tipo_servicio }} · Doc. {{ $persona?->numero_documento }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $remitidaPorMi && ! $remitidaPorMi->esRemisionInterna() ? 'Remitido a '.$remitidaPorMi->entidad_destino : 'A cargo de '.($caso->profesional?->persona?->nombre_completo ?? 'nadie aún') }}</p>
                                    @if ($esSuCaso)
                                        <p class="text-xs font-medium text-gray-600 mt-1 line-clamp-1 break-words">{{ $caso->solicitud?->descripcion }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="text-left sm:text-right shrink-0">
                                <span class="text-xs font-bold {{ $textoRiesgo }} flex items-center gap-1 sm:justify-end">
                                    <span class="w-2 h-2 rounded-full {{ $puntoRiesgo }}" aria-hidden="true"></span> {{ $caso->nivel_riesgo ? mb_strtolower($caso->nivel_riesgo) : 'riesgo sin definir' }}
                                </span>
                                <p class="text-xs text-gray-400 mt-1">{{ $caso->fecha_apertura?->format('Y-m-d') }}</p>
                                <p class="text-xs text-gray-400">{{ $sesiones }} {{ $sesiones === 1 ? 'sesión' : 'sesiones' }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>

{{-- Resumen del caso para un profesional que no lo tiene asignado: datos del estudiante, profesional
     a cargo, fechas de apertura y cierre y el estado real del caso. Quien lo remitió a otro profesional
     ve además la historia clínica hasta su remisión. Recibe $caso, $remisionHecha, $historia y $remisionExterna. --}}
@php
    use App\Models\Caso;
    use App\Models\HistoriaClinica;

    $persona = $caso->solicitud?->persona;
    $cerrado = $remisionExterna || $caso->fecha_cierre !== null || ! in_array($caso->estado_caso, [...Caso::ESTADOS_ACTIVOS, Caso::ESTADO_PENDIENTE], true);
    $asignado = $caso->profesional?->persona?->nombre_completo;
    $remitioAProfesional = $remisionHecha?->esRemisionInterna();
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-[#007F2F] tracking-tight">Caso {{ $caso->codigo_expediente }}</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Resumen · Bienestar Universitario</p>
            </div>

            <a href="{{ auth()->user()->urlCasos() }}"
               class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
               title="Volver a Salud Mental" aria-label="Cerrar y volver a Salud Mental">
                ✕
            </a>
        </div>

        <div class="flex-1 p-4 sm:p-8 flex justify-center items-start">
            <div class="w-full max-w-3xl space-y-4">

                @if (session('status'))
                    <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 flex gap-3 items-center" role="status">
                        <span class="text-xl shrink-0" aria-hidden="true">✅</span>
                        <p class="text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">{{ session('status') }}</p>
                    </div>
                @endif

                @if (session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex gap-3 items-center" role="alert">
                        <span class="text-xl shrink-0" aria-hidden="true">⚠️</span>
                        <p class="text-xs sm:text-sm text-rose-700 font-medium leading-relaxed">{{ session('error') }}</p>
                    </div>
                @endif

                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex gap-3 items-center" role="note">
                    <span class="text-xl shrink-0" aria-hidden="true">🔒</span>
                    <p class="text-xs sm:text-sm text-amber-800 font-medium leading-relaxed">
                        @if ($remitioAProfesional)
                            {{ $remisionHecha->esTrasladoAdministrativo() ? 'La administración trasladó este caso a' : 'Remitiste este caso a' }} {{ $remisionHecha->etiquetaDestino() }} el {{ $remisionHecha->fecha_hora->format('Y-m-d H:i') }}.
                            Conservas la historia clínica hasta esa fecha.
                        @else
                            Este caso no está asignado a ti.
                        @endif
                        Solo el profesional a cargo puede ver su contenido completo.
                    </p>
                </div>

                @if ($remisionExterna)
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm" role="note">
                        <p class="font-bold text-gray-800">Caso cerrado · remitido a una entidad externa</p>
                        <p class="text-xs text-gray-600 mt-1 break-words">
                            Remitido a <strong>{{ $remisionExterna->entidad_destino }}</strong> el {{ $remisionExterna->fecha_hora->format('Y-m-d H:i') }}.
                        </p>
                    </div>
                @endif

                <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100 space-y-6">

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-xs font-bold text-[#007F2F]">Expediente {{ $caso->codigo_expediente }}</span>
                        <span @class(['px-2.5 py-1 rounded-lg text-xs font-bold', 'bg-emerald-50 text-emerald-700' => ! $cerrado, 'bg-gray-100 text-gray-600' => $cerrado])>
                            {{ $remisionExterna ? Caso::ESTADO_CERRADO : $caso->estado_caso }}
                        </span>
                    </div>

                    <section aria-labelledby="titulo-estudiante">
                        <h2 id="titulo-estudiante" class="text-sm font-bold text-gray-900 mb-3">Estudiante</h2>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-gray-500 font-medium">Nombre</dt>
                                <dd class="text-gray-900 font-semibold">{{ $persona?->nombre_completo ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 font-medium">Documento</dt>
                                <dd class="text-gray-900">{{ trim(($persona?->tipo_documento ?? '').' '.($persona?->numero_documento ?? '')) ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 font-medium">Correo institucional</dt>
                                <dd class="text-gray-900 break-all">{{ $persona?->correo_inst ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 font-medium">Facultad</dt>
                                <dd class="text-gray-900">{{ $caso->solicitud?->facultad?->nombre ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section aria-labelledby="titulo-caso" class="border-t border-gray-100 pt-6">
                        <h2 id="titulo-caso" class="text-sm font-bold text-gray-900 mb-3">Caso</h2>
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-gray-500 font-medium">{{ $remisionExterna ? 'Remitido a' : 'Profesional asignado' }}</dt>
                                <dd class="text-gray-900 font-semibold">{{ $remisionExterna ? $remisionExterna->entidad_destino : ($asignado ?? 'Sin asignar') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 font-medium">Inicio del caso</dt>
                                <dd class="text-gray-900">{{ $caso->fecha_apertura?->format('Y-m-d H:i') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 font-medium">Cierre del caso</dt>
                                <dd class="text-gray-900">{{ $caso->fecha_cierre?->format('Y-m-d H:i') ?? ($cerrado ? 'Cerrado' : 'Sigue en proceso') }}</dd>
                            </div>
                        </dl>
                    </section>

                    @if ($remitioAProfesional)
                        <section aria-labelledby="titulo-historia" class="border-t border-gray-100 pt-6">
                            <h2 id="titulo-historia" class="text-sm font-bold text-gray-900">Historia clínica psicológica hasta tu remisión</h2>

                            @if ($historia)
                                <p class="text-[11px] text-gray-400 font-medium mt-0.5 mb-3">
                                    Registrada el {{ $historia->fecha_registro?->format('Y-m-d H:i') }} por {{ $historia->profesional?->persona?->nombre_completo ?? 'el profesional del caso' }}.
                                    Lo registrado después del {{ $remisionHecha->fecha_hora->format('Y-m-d H:i') }} solo lo ve el profesional a cargo.
                                </p>

                                <dl class="space-y-3 text-sm mb-4">
                                    @foreach (HistoriaClinica::APARTADOS as $campo => [$etiqueta])
                                        @if (filled($historia->{$campo}))
                                            <div>
                                                <dt class="text-xs text-gray-500 mb-1">{{ $etiqueta }}</dt>
                                                <dd class="pl-3 border-l-2 border-gray-200 text-gray-700 font-medium py-0.5 whitespace-pre-line break-words">{{ $historia->{$campo} }}</dd>
                                            </div>
                                        @endif
                                    @endforeach
                                </dl>

                                @if ($historia->anotaciones->isNotEmpty())
                                    <h3 class="text-sm font-bold text-gray-900 mb-2">Anotaciones</h3>
                                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl">
                                        @foreach ($historia->anotaciones as $anotacion)
                                            <li class="p-4 text-sm">
                                                <p class="text-[11px] text-gray-400 font-medium">
                                                    {{ $anotacion->fecha_hora?->format('Y-m-d H:i') }} · {{ $anotacion->profesional?->persona?->nombre_completo ?? 'Profesional' }}
                                                    · {{ $anotacion->etiquetaApartado() ?? 'Historia en general' }}
                                                </p>
                                                <p class="mt-1 text-gray-700 whitespace-pre-line break-words">{{ $anotacion->texto }}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @else
                                <p class="text-xs text-gray-500 mt-1">El caso no tenía historia clínica registrada cuando lo remitiste.</p>
                            @endif
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@php
    use App\Models\Actuacion;
    use App\Models\Caso;

    $colorEstado = [
        Caso::CITA_PENDIENTE => 'bg-amber-50 text-amber-700 border-amber-200',
        Caso::ELIGE_HORARIO => 'bg-sky-50 text-sky-700 border-sky-200',
        'Rechazada' => 'bg-rose-50 text-rose-700 border-rose-200',
        'En atención' => 'bg-green-50 text-[#007F2F] border-green-200',
        'Finalizado' => 'bg-gray-100 text-gray-600 border-gray-200',
    ];
    $colorAsistencia = [
        Actuacion::ASISTIO => 'text-[#007F2F]',
        Actuacion::NO_ASISTIO => 'text-rose-600',
        Actuacion::ASISTENCIA_PENDIENTE => 'text-gray-400',
    ];
    $servicioEtiqueta = ['Salud Mental' => '🧠 Atención Psicológica', 'Inclusión' => '♿ Inclusión Universitaria'];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col">

        <!-- 1. FRANJA BLANCA SUPERIOR DE ORILLA A ORILLA (HEADER) -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-4 flex items-center justify-between gap-3 w-full shrink-0">
            <!-- Lado Izquierdo: Título y Subtítulo -->
            <div>
                <h1 class="text-lg font-bold text-gray-900 leading-tight">Mis citas</h1>
                <p class="text-xs text-gray-500">Historial y estado de tus solicitudes de atención</p>
            </div>

            <!-- Lado Derecho: Botón de Cierre (X) -->
            <a href="{{ route('estudiante.home') }}"
               class="bg-pink-50 hover:bg-pink-100 text-pink-600 p-2.5 rounded-xl transition-colors flex items-center justify-center shadow-xs shrink-0"
               title="Volver al inicio" aria-label="Volver al inicio">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </a>
        </div>

        <div class="w-full max-w-3xl mx-auto p-6 sm:p-8 space-y-6">

            @if (session('status'))
                <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 flex gap-3 items-start" role="status">
                    <span class="text-xl shrink-0" aria-hidden="true">✅</span>
                    <p class="text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">{{ session('status') }}</p>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex gap-3 items-start" role="alert">
                    <span class="text-xl shrink-0" aria-hidden="true">⚠️</span>
                    <p class="text-xs sm:text-sm text-rose-700 font-medium leading-relaxed">{{ session('error') }}</p>
                </div>
            @endif

            @if ($casos->isEmpty())
                <!-- Estado Vacío (Sin Citas) -->
                <div class="bg-white rounded-2xl p-10 sm:p-14 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center">
                    <div class="text-5xl mb-4 p-4 bg-gray-50 rounded-2xl border border-gray-100 shadow-inner" aria-hidden="true">🗓️</div>
                    <h2 class="text-xl font-bold text-gray-800 mb-1">Aún no tienes citas</h2>
                    <p class="text-gray-500 text-sm mb-6 max-w-sm">Solicita tu primera sesión de acompañamiento</p>
                    <a href="{{ route('citas.solicitar') }}"
                       class="bg-[#007F2F] hover:bg-[#006324] text-white px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-200 shadow-md flex items-center gap-2">
                        Solicitar Nueva Cita
                    </a>
                </div>
            @else
                <div class="flex justify-end">
                    <a href="{{ route('citas.solicitar') }}"
                       class="bg-[#007F2F] hover:bg-[#006324] text-white px-5 py-2.5 rounded-xl font-semibold text-sm transition-colors shadow-sm">
                        + Solicitar nueva cita
                    </a>
                </div>

                <!-- Próximas citas confirmadas -->
                <section aria-labelledby="proximas-titulo" class="space-y-3">
                    <h2 id="proximas-titulo" class="text-base font-bold text-gray-900">Próximas citas</h2>

                    @forelse ($proximas as $cita)
                        @php $fecha = $cita->fecha_hora; @endphp
                        <article class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex gap-4 items-start">
                            <div class="shrink-0 w-14 text-center rounded-xl bg-[#007F2F] text-white py-2">
                                <div class="text-[10px] font-bold uppercase tracking-wide">{{ $fecha->locale('es')->translatedFormat('M') }}</div>
                                <div class="text-xl font-bold leading-none mt-0.5">{{ $fecha->format('j') }}</div>
                            </div>
                            <div class="min-w-0 text-sm">
                                <p class="font-bold text-gray-900 first-letter:uppercase">
                                    {{ $fecha->locale('es')->translatedFormat('l j \d\e F') }} · {{ $fecha->format('g:i a') }}
                                </p>
                                <p class="text-gray-600 mt-0.5">
                                    {{ $servicioEtiqueta[$cita->caso->solicitud?->tipo_servicio] ?? 'Cita de acompañamiento' }}
                                    @if ($cita->profesional?->persona)
                                        · con {{ $cita->profesional->persona->nombre_completo }}
                                    @endif
                                </p>
                                @if ($cita->descripcion)
                                    <p class="text-xs text-gray-500 mt-2 whitespace-pre-line break-words">{{ $cita->descripcion }}</p>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-sm text-gray-500">
                            No tienes citas confirmadas próximamente. Cuando tu profesional agende una, aparecerá aquí.
                        </p>
                    @endforelse
                </section>

                <!-- Solicitudes y casos -->
                <section aria-labelledby="solicitudes-titulo" class="space-y-3">
                    <h2 id="solicitudes-titulo" class="text-base font-bold text-gray-900">Mis solicitudes</h2>

                    @foreach ($casos as $caso)
                        @php
                            $solicitud = $caso->solicitud;
                            $estado = $caso->etiquetaParaEstudiante();
                            $historial = $caso->citas->reject(fn ($cita) => $cita->esProxima())->sortByDesc('fecha_hora');
                            $consentimiento = $caso->consentimientos->last();
                            $firmado = $caso->consentimientoVigente();
                        @endphp
                        <article id="caso-{{ $caso->id_caso }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4 scroll-mt-4">
                            <header class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold text-gray-900">{{ $servicioEtiqueta[$solicitud?->tipo_servicio] ?? $solicitud?->tipo_servicio }}</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $caso->codigo_expediente }} · solicitada el {{ $solicitud?->fecha_solicitud?->format('Y-m-d') ?? $caso->fecha_apertura?->format('Y-m-d') }}
                                    </p>
                                </div>
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full border {{ $colorEstado[$estado] }}">{{ $estado }}</span>
                            </header>

                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                @if ($solicitud?->etiquetaAtencion())
                                    <div>
                                        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Facultad y horario</dt>
                                        <dd class="text-gray-900 mt-0.5">{{ $solicitud->etiquetaAtencion() }}</dd>
                                    </div>
                                @elseif ($solicitud?->fecha_preferida)
                                    <div>
                                        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Preferencia</dt>
                                        <dd class="text-gray-900 mt-0.5">{{ collect([
                                            $solicitud->fecha_preferida->format('Y-m-d'),
                                            $solicitud->hora_preferida ? Actuacion::etiquetaHora($solicitud->hora_preferida) : null,
                                            $solicitud->modalidad,
                                        ])->filter()->implode(' · ') }}</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Profesional</dt>
                                    <dd class="text-gray-900 mt-0.5">
                                        @if ($caso->profesional?->persona)
                                            {{ $caso->profesional->persona->nombre_completo }}
                                        @elseif ($solicitud?->fueRechazada() && $solicitud->profesionalPreferido?->persona)
                                            {{ $solicitud->profesionalPreferido->persona->nombre_completo }} no pudo atender tu solicitud
                                        @elseif ($solicitud?->profesionalPreferido?->persona)
                                            Por asignar · pediste a {{ $solicitud->profesionalPreferido->persona->nombre_completo }}
                                        @else
                                            Por asignar
                                        @endif
                                    </dd>
                                </div>
                                @if ($caso->estaActivo() || $caso->estado_caso === Caso::ESTADO_PENDIENTE)
                                    @php $proxima = $caso->proximaCita(); @endphp
                                    <div>
                                        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Día y hora de la cita</dt>
                                        <dd class="mt-0.5 {{ $proxima ? 'text-gray-900' : 'text-amber-700 font-semibold' }}">
                                            @if ($proxima)
                                                {{ $proxima->fecha_hora->format('Y-m-d') }} · {{ $proxima->fecha_hora->format('g:i a') }}
                                            @else
                                                {{ $caso->propuestasVigentes->isNotEmpty() ? 'Pendiente: responde la solicitud de cita' : 'Pendiente' }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                                @if ($solicitud?->descripcion)
                                    <div class="sm:col-span-2">
                                        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Motivo</dt>
                                        <dd class="text-gray-700 mt-0.5 whitespace-pre-line break-words">{{ $solicitud->descripcion }}</dd>
                                    </div>
                                @endif
                            </dl>

                            {{-- Solicitud de cita del psicólogo: el estudiante acepta uno de los horarios o la rechaza. --}}
                            @if ($caso->estaActivo() && ! $caso->proximaCita() && $caso->propuestasVigentes->isNotEmpty())
                                @php $unica = $caso->propuestasVigentes->count() === 1; @endphp
                                <div class="bg-sky-50 border border-sky-200 rounded-xl p-4 space-y-3">
                                    <div>
                                        <p class="text-sm font-bold text-sky-900">Solicitud de cita</p>
                                        <p class="text-xs text-sky-800">
                                            {{ $caso->profesional?->persona?->nombre_completo ?? 'Tu profesional' }}
                                            {{ $unica ? 'te propone tu próxima cita. ¿La aceptas?' : 'te propone estos horarios. Acepta el que mejor te quede.' }}
                                        </p>
                                    </div>
                                    @if ($errors->getBag('propuestas')->has('propuesta'))
                                        <p class="text-xs text-red-600 font-medium" role="alert">{{ $errors->getBag('propuestas')->first('propuesta') }}</p>
                                    @endif
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach ($caso->propuestasVigentes as $propuesta)
                                            <form method="POST" action="{{ route('propuestas.elegir', $propuesta) }}">
                                                @csrf
                                                <button type="submit"
                                                        onclick="return confirm(@js('¿Aceptas tu cita el '.$propuesta->etiqueta().'?'))"
                                                        class="w-full px-3 py-2.5 bg-white border border-gray-200 hover:border-[#007F2F] hover:bg-green-50/50 rounded-xl text-sm text-gray-800 text-left transition focus:outline-none focus:ring-2 focus:ring-[#007F2F]">
                                                    📅 {{ $propuesta->etiqueta() }}
                                                    <span class="block text-xs font-bold text-[#007F2F] mt-0.5">✓ Aceptar</span>
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                    <form method="POST" action="{{ route('propuestas.rechazar', $caso) }}">
                                        @csrf
                                        <button type="submit"
                                                onclick="return confirm(@js($unica ? '¿Rechazas esta solicitud de cita?' : '¿Ninguno de estos horarios te sirve? Se rechazará la solicitud.'))"
                                                class="px-3 py-2 bg-white border border-rose-200 hover:bg-rose-50 text-rose-700 text-xs font-bold rounded-xl transition focus:outline-none focus:ring-2 focus:ring-rose-400">
                                            ✕ {{ $unica ? 'Rechazar la cita' : 'Ninguno me sirve: rechazar' }}
                                        </button>
                                    </form>
                                </div>
                            @endif

                            @if ($caso->estado_caso === Caso::ESTADO_PENDIENTE)
                                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">
                                    Bienestar Universitario te asignará un profesional, que te contactará para acordar la fecha y la hora de tu cita.
                                </p>
                            @elseif ($caso->estaActivo() && $caso->citas->isEmpty() && $caso->profesional?->persona)
                                <p class="text-xs text-sky-900 bg-sky-50 border border-sky-200 rounded-xl p-3">
                                    Tu caso quedó a cargo de {{ $caso->profesional->persona->nombre_completo }}, que te contactará para acordar la fecha y la hora de tu primera cita.
                                </p>
                            @elseif ($solicitud?->fueRechazada())
                                <div class="text-xs text-rose-800 bg-rose-50 border border-rose-200 rounded-xl p-3 space-y-1">
                                    <p class="font-bold">Solicitud rechazada{{ $solicitud->fecha_respuesta ? ' el '.$solicitud->fecha_respuesta->format('Y-m-d') : '' }}</p>
                                    <p class="whitespace-pre-line break-words">{{ $solicitud->motivo_rechazo }}</p>
                                    <p>Puedes <a href="{{ route('citas.solicitar') }}" class="font-bold underline">solicitar una nueva cita</a> con otro profesional.</p>
                                </div>
                            @endif

                            @if ($historial->isNotEmpty())
                                <div>
                                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Citas anteriores</h4>
                                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl text-sm">
                                        @foreach ($historial as $cita)
                                            <li class="px-4 py-2.5 flex flex-wrap justify-between gap-2">
                                                <span class="text-gray-800">{{ $cita->fecha_hora->format('Y-m-d') }} · {{ $cita->fecha_hora->format('g:i a') }}</span>
                                                <span class="font-medium {{ $colorAsistencia[$cita->estado_asistencia] ?? 'text-gray-500' }}">
                                                    {{ $cita->asistenciaPendiente() ? 'Asistencia por registrar' : $cita->estado_asistencia }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- El estudiante firma su consentimiento (RQ-1) desde aquí; el profesional ya no lo toma en el expediente. --}}
                            @if ($caso->requiereConsentimiento() && $caso->estaActivo() && ! $firmado)
                                @php $ultimaDecision = $caso->aceptacionesConsentimiento->first(); @endphp
                                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3" role="status">
                                    <p class="text-xs sm:text-sm text-amber-900 font-medium leading-relaxed">
                                        @if ($ultimaDecision?->esRevocacion())
                                            El {{ $ultimaDecision->fecha_hora->format('Y-m-d') }} revocaste tu consentimiento informado. Sin él no se puede prestar la atención; puedes firmar uno nuevo si cambias de opinión.
                                        @elseif ($ultimaDecision)
                                            El {{ $ultimaDecision->fecha_hora->format('Y-m-d') }} registraste que no aceptas el consentimiento informado. Sin él no se puede prestar la atención; puedes firmarlo si cambias de opinión.
                                        @else
                                            Antes de tu atención debes leer y firmar el consentimiento informado.
                                        @endif
                                    </p>
                                    <a href="{{ route('consentimientos.firmar', $caso) }}"
                                       class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm text-center shrink-0 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                        Firmar consentimiento
                                    </a>
                                </div>
                            @endif

                            @if ($caso->esDeInclusion() && $caso->aceptacionesConsentimiento->isNotEmpty())
                                <a href="{{ route('inclusion.consentimiento.ver', $caso) }}" class="inline-block text-xs font-bold text-[#006324] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">Mi consentimiento</a>
                            @endif

                            @if ($firmado)
                                {{-- Consentimiento firmado (RQ-1): el texto que se le leyó, sin la imagen de la firma (D-13). --}}
                                <details class="group text-sm">
                                    <summary class="cursor-pointer text-xs font-medium text-[#007F2F] hover:underline">
                                        Consentimiento informado aceptado el {{ $firmado->fecha_hora->format('Y-m-d') }}
                                        @if ($firmado->version) (versión {{ $firmado->version->numero_version }}) @endif
                                    </summary>
                                    <div class="mt-2 bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs text-gray-700 leading-relaxed whitespace-pre-line break-words max-h-56 overflow-y-auto" tabindex="0">{{ $firmado->texto_presentado }}</div>
                                </details>
                            @elseif ($consentimiento?->aceptado)
                                <details class="group text-sm">
                                    <summary class="cursor-pointer text-xs font-medium text-[#007F2F] hover:underline">
                                        Consentimiento informado aceptado el {{ $consentimiento->fecha_hora_aceptacion->format('Y-m-d') }}
                                        @if ($consentimiento->version) (versión {{ $consentimiento->version->numero_version }}) @endif
                                    </summary>
                                    @if ($consentimiento->version)
                                        <div class="mt-2 bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs text-gray-700 leading-relaxed whitespace-pre-line break-words max-h-56 overflow-y-auto" tabindex="0">{{ $consentimiento->version->texto }}</div>
                                    @else
                                        <p class="mt-2 text-xs text-gray-500">Formato {{ $consentimiento->version_formato }}.</p>
                                    @endif
                                </details>
                            @endif
                        </article>
                    @endforeach
                </section>
            @endif
        </div>

    </div>
</x-app-layout>

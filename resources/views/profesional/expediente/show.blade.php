{{-- Expediente digital único (HU-005): todo lo registrado en el caso, en una sola vista. Aquí también se registran las atenciones, los factores de riesgo y protectores, y se atienden las alertas tempranas (HU-026). --}}
@php
    use App\Models\Actuacion;
    use App\Models\SeguimientoEvolucion;

    $persona = $caso->solicitud?->persona;
    $yo = auth()->id();
    $colorEstado = [
        'Abierto' => 'bg-emerald-100 text-emerald-700',
        'En Seguimiento' => 'bg-amber-100 text-amber-700',
    ];
    $colorRiesgo = ['Alto' => 'text-rose-600', 'Medio' => 'text-amber-600', 'Bajo' => 'text-emerald-600'];
    $asistencia = [
        Actuacion::ASISTIO => 'bg-emerald-50 text-emerald-700',
        Actuacion::NO_ASISTIO => 'bg-rose-50 text-rose-700',
        Actuacion::CANCELADA => 'bg-gray-100 text-gray-600',
    ];
    $valoracion = [
        SeguimientoEvolucion::MEJORA => 'text-emerald-700',
        SeguimientoEvolucion::ESTABLE => 'text-sky-700',
        SeguimientoEvolucion::RETROCESO => 'text-rose-600',
    ];
    $remisionesExternas = $caso->remisiones->filter(fn ($remision) => $remision->remisionExterna && in_array($yo, [$remision->id_profesional, $caso->id_profesional], true));
    $desistimientos = $caso->desistimientos->filter(fn ($desistimiento) => in_array($yo, [$desistimiento->id_profesional, $caso->id_profesional], true));
    $ajustes = $caso->relationLoaded('ajustes') ? $caso->ajustes : collect();
    $totalDocumentos = $caso->aceptacionesConsentimiento->count() + $remisionesExternas->count() + $desistimientos->count() + ($ajustes->isNotEmpty() ? 1 : 0);
@endphp

<x-app-layout>
    <div class="p-4 sm:p-6 space-y-4">

        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 bg-white p-4 sm:px-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-[#007F2F]">Expediente {{ $caso->codigo_expediente }} @include('casos.partials.emergencia', ['caso' => $caso])</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">{{ $caso->solicitud?->tipo_servicio }} · Expediente digital único</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $colorEstado[$estado] ?? 'bg-gray-100 text-gray-600' }}">{{ $estado }}</span>
                <a href="{{ route('casos.show', $caso) }}"
                   class="px-3 sm:px-4 py-2 bg-white border border-[#007F2F] text-[#007F2F] hover:bg-emerald-50 text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                    Gestionar caso
                </a>
                <a href="{{ route('profesional.expedientes') }}"
                   class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition-colors shadow-sm text-sm"
                   title="Volver a expedientes" aria-label="Volver a expedientes">
                    ✕
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-3 text-sm text-[#1F5134] font-medium" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-sm text-rose-700 font-medium" role="alert">{{ session('error') }}</div>
        @endif

        @include('profesional.expediente.partials.alertas')

        @if ($caso->esDeInclusion())
            @include('profesional.expediente.partials.documentos-inclusion')
        @endif

        <!-- Información general -->
        <section aria-labelledby="general-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
            <h2 id="general-titulo" class="text-base font-bold text-gray-900 mb-4">Información general del caso</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">Solicitante</dt>
                    <dd class="font-semibold text-gray-800 break-words">{{ $persona?->nombre_completo ?? 'Solicitante sin registrar' }}</dd>
                    <dd class="text-xs text-gray-500">Doc. {{ $persona?->numero_documento ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Facultad</dt>
                    <dd class="font-semibold text-gray-800">{{ $caso->solicitud?->facultad?->nombre ?? 'Sin facultad registrada' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Profesional a cargo</dt>
                    <dd class="font-semibold text-gray-800">{{ $caso->profesional?->persona?->nombre_completo ?? 'Sin asignar' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Apertura</dt>
                    <dd class="font-semibold text-gray-800">{{ $caso->fecha_apertura?->format('Y-m-d') ?? 'No registrada' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Cierre</dt>
                    <dd class="font-semibold text-gray-800">{{ $caso->fecha_cierre?->format('Y-m-d') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Nivel de riesgo</dt>
                    <dd class="font-semibold {{ $colorRiesgo[$caso->nivel_riesgo] ?? 'text-gray-500' }}">{{ $caso->nivel_riesgo ?? 'Sin valorar' }}</dd>
                </div>
                @if ($caso->solicitud?->descripcion)
                    <div class="sm:col-span-3">
                        <dt class="text-xs text-gray-500">Motivo de la solicitud</dt>
                        <dd class="text-gray-800 whitespace-pre-line break-words">{{ $caso->solicitud->descripcion }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            <!-- Consentimientos informados -->
            <section aria-labelledby="consentimientos-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <h2 id="consentimientos-titulo" class="text-base font-bold text-gray-900">Consentimientos informados</h2>
                    <span class="px-2 py-0.5 bg-emerald-50 text-[#007F2F] text-xs font-bold rounded-full">{{ $caso->aceptacionesConsentimiento->count() + $caso->consentimientos->count() }}</span>
                </div>
                <ul class="space-y-2">
                    @foreach ($caso->aceptacionesConsentimiento as $aceptacion)
                        <li class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-between gap-2 text-sm">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 break-words">{{ $aceptacion->version?->modelo?->nombre ?? 'Consentimiento informado' }} · v{{ $aceptacion->version?->numero_version }}</p>
                                <p class="text-xs text-gray-500">{{ $aceptacion->fecha_hora->format('Y-m-d H:i') }} · {{ $aceptacion->otorgante }}{{ $aceptacion->esAceptado() && $aceptacion->faltaFirmaProfesional() ? ' · falta la firma del profesional' : '' }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full shrink-0 {{ $aceptacion->esAceptado() ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $aceptacion->etiquetaDecision() }}</span>
                        </li>
                    @endforeach
                    {{-- Aceptaciones registradas antes de la firma táctil --}}
                    @foreach ($caso->consentimientos as $consentimiento)
                        <li class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-between gap-2 text-sm">
                            <div>
                                <p class="font-semibold text-gray-800">Versión {{ $consentimiento->version?->numero_version ?? $consentimiento->version_formato }}</p>
                                <p class="text-xs text-gray-500">{{ $consentimiento->fecha_hora_aceptacion?->format('Y-m-d H:i') }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $consentimiento->aceptado ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $consentimiento->aceptado ? 'Aceptado' : 'No aceptado' }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($caso->aceptacionesConsentimiento->isEmpty() && $caso->consentimientos->isEmpty())
                    <p class="text-sm text-gray-500">No se han registrado consentimientos informados.</p>
                @endif
            </section>

            <!-- Historial de atenciones -->
            <section aria-labelledby="atenciones-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <h2 id="atenciones-titulo" class="text-base font-bold text-gray-900">Historial de atenciones</h2>
                    <span class="px-2 py-0.5 bg-emerald-50 text-[#007F2F] text-xs font-bold rounded-full">{{ $caso->citas->count() }}</span>
                </div>
                @forelse ($caso->citas->sortByDesc('fecha_hora') as $cita)
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 mb-2 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-gray-800">{{ $cita->fecha_hora->format('Y-m-d H:i') }}</span>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $asistencia[$cita->estado_asistencia] ?? 'bg-amber-50 text-amber-700' }}">{{ $cita->esProxima() && $cita->asistenciaPendiente() ? 'Próxima' : $cita->estado_asistencia }}</span>
                        </div>
                        <p class="text-xs text-gray-500">{{ $cita->profesional?->persona?->nombre_completo }}</p>
                        @if ($cita->descripcion)
                            <p class="text-xs text-gray-600 mt-1 whitespace-pre-line break-words">{{ $cita->descripcion }}</p>
                        @endif
                        @if ($cita->motivo_inasistencia)
                            <p class="text-xs text-gray-600 mt-1 break-words">Motivo: {{ $cita->motivo_inasistencia }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No hay atenciones registradas para este caso.</p>
                @endforelse
            </section>

            <!-- Documentos del expediente -->
            <section aria-labelledby="documentos-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <h2 id="documentos-titulo" class="text-base font-bold text-gray-900">Documentos</h2>
                    <span class="px-2 py-0.5 bg-emerald-50 text-[#007F2F] text-xs font-bold rounded-full">{{ $totalDocumentos }}</span>
                </div>
                <ul class="space-y-2 text-sm">
                    @foreach ($caso->aceptacionesConsentimiento as $aceptacion)
                        <li class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800">Consentimiento informado firmado</p>
                                <p class="text-xs text-gray-500">{{ $aceptacion->fecha_hora->format('Y-m-d') }} · {{ $aceptacion->etiquetaDecision() }} · huella {{ substr($aceptacion->texto_sha256, 0, 12) }}…</p>
                            </div>
                            <span class="flex gap-2 shrink-0">
                                <a href="{{ route('casos.show', $caso) }}#consentimiento" class="text-xs font-bold text-[#007F2F] hover:underline">Ver firma</a>
                                @if (\App\Documentos\PdfConsentimiento::aplicaA($aceptacion) && $caso->puedeConsultarEvidencia(auth()->user()))
                                    <a href="{{ route('consentimientos.pdf', $aceptacion) }}" class="text-xs font-bold text-[#007F2F] hover:underline">PDF</a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                    @foreach ($remisionesExternas as $remision)
                        <li class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800">Remisión a {{ $remision->etiquetaDestino() }}</p>
                                <p class="text-xs text-gray-500">{{ $remision->fecha_hora->format('Y-m-d') }} · {{ $remision->remisionExterna->categoria }} · formato {{ $remision->remisionExterna->formato_codigo }} v{{ $remision->remisionExterna->formato_version }}</p>
                            </div>
                            <span class="flex gap-2 shrink-0">
                                <a href="{{ route('remisiones.documento.pdf', $remision) }}" class="text-xs font-bold text-[#007F2F] hover:underline">PDF</a>
                                <a href="{{ route('remisiones.documento.word', $remision) }}" class="text-xs font-bold text-[#007F2F] hover:underline">Word</a>
                            </span>
                        </li>
                    @endforeach
                    @if ($ajustes->isNotEmpty())
                        <li class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800">Reporte de seguimiento de Inclusión</p>
                                <p class="text-xs text-gray-500">Ajustes razonables e historial de seguimiento, al día de hoy</p>
                            </div>
                            <a href="{{ route('casos.reporte-inclusion', $caso) }}" class="text-xs font-bold text-[#007F2F] hover:underline shrink-0">PDF</a>
                        </li>
                    @endif
                    @foreach ($desistimientos as $desistimiento)
                        <li class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800">Desistimiento de la atención</p>
                                <p class="text-xs text-gray-500">{{ $desistimiento->fecha_hora->format('Y-m-d') }} · {{ $desistimiento->motivo }}</p>
                            </div>
                            <a href="{{ route('desistimientos.pdf', $desistimiento) }}" class="text-xs font-bold text-[#007F2F] hover:underline shrink-0">PDF</a>
                        </li>
                    @endforeach
                </ul>
                @if ($totalDocumentos === 0)
                    <p class="text-sm text-gray-500">Este expediente aún no tiene documentos.</p>
                @endif
            </section>

            <!-- Seguimientos y evolución -->
            <section aria-labelledby="seguimientos-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <h2 id="seguimientos-titulo" class="text-base font-bold text-gray-900">Seguimientos y evolución</h2>
                    <a href="{{ route('casos.evolucion', $caso) }}" class="text-xs font-bold text-[#007F2F] hover:underline">Ver evolución</a>
                </div>
                @forelse ($caso->seguimientos->sortByDesc('fecha_hora') as $seguimiento)
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 mb-2 text-sm">
                        <p class="text-xs text-gray-500">
                            {{ $seguimiento->fecha_hora->format('Y-m-d') }} ·
                            <span class="font-semibold {{ $valoracion[$seguimiento->valoracion_avance] ?? '' }}">{{ $seguimiento->valoracion_avance }}</span>
                            · riesgo {{ mb_strtolower($seguimiento->nivel_riesgo) }}
                            @if ($seguimiento->instrumento) · {{ $seguimiento->instrumento }} {{ $seguimiento->puntaje }} @endif
                        </p>
                        @if ($seguimiento->nota)
                            <p class="text-gray-700 mt-1 whitespace-pre-line break-words">{{ $seguimiento->nota }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 mb-2">No se han registrado seguimientos de las sesiones.</p>
                @endforelse

                @if ($ajustes->isNotEmpty())
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mt-4 mb-2">Ajustes razonables</h3>
                    @foreach ($ajustes as $ajuste)
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 mb-2 text-sm">
                            <p class="font-semibold text-gray-800">{{ $ajuste->etiqueta() }} · {{ $ajuste->estado }}</p>
                            <p class="text-xs text-gray-500">{{ $ajuste->seguimientos->count() }} {{ $ajuste->seguimientos->count() === 1 ? 'seguimiento' : 'seguimientos' }}{{ $ajuste->seguimientos->last() ? ' · último: '.$ajuste->seguimientos->last()->fecha->format('Y-m-d').' ('.$ajuste->seguimientos->last()->cumplimiento.')' : '' }}</p>
                        </div>
                    @endforeach
                @endif
            </section>
        </div>

        @include('profesional.expediente.partials.factores')

        <!-- Historia clínica y remisiones -->
        <section aria-labelledby="otros-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
            <h2 id="otros-titulo" class="text-base font-bold text-gray-900 mb-3">Historia clínica y remisiones</h2>
            <ul class="space-y-2 text-sm">
                @if ($caso->historiaClinica)
                    <li class="flex items-center justify-between gap-2">
                        <span class="text-gray-800">Historia clínica registrada el {{ $caso->historiaClinica->fecha_registro?->format('Y-m-d') }} por {{ $caso->historiaClinica->profesional?->persona?->nombre_completo }}</span>
                        <a href="{{ route('casos.show', $caso) }}#historia-clinica" class="text-xs font-bold text-[#007F2F] hover:underline shrink-0">Ver</a>
                    </li>
                @elseif ($caso->esDeSaludMental())
                    <li class="text-gray-500">Sin historia clínica registrada.</li>
                @endif
                @forelse ($caso->remisiones as $remision)
                    <li class="text-gray-800">
                        {{ $remision->fecha_hora->format('Y-m-d') }} · {{ $remision->esTrasladoAdministrativo() ? 'Traslado administrativo' : 'Remisión '.mb_strtolower($remision->destino_remision) }} a {{ $remision->etiquetaDestino() }}
                        <span class="text-xs text-gray-500">· {{ $remision->esTrasladoAdministrativo() ? 'desde' : 'por' }} {{ $remision->profesional?->persona?->nombre_completo }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">El caso no tiene remisiones.</li>
                @endforelse
            </ul>
        </section>

        @include('profesional.expediente.partials.registro-atenciones')
    </div>
</x-app-layout>

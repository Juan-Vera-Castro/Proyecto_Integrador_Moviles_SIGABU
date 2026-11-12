@php
    use App\Models\Actuacion;
    use App\Models\HistoriaClinica;
    use App\Models\RemisionExterna;
    use App\Models\SeguimientoEvolucion;
    use App\Models\TareaTerapeutica;

    $persona = $caso->solicitud?->persona;
    $esSuCaso = $caso->id_profesional === auth()->id();
    $puedeAgendar = $caso->estaActivo() && $esSuCaso;
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $erroresTarea = $errors->getBag('tarea');
    $erroresAsistencia = $errors->getBag('asistencia');
    $erroresRemision = $errors->getBag('remision');
    $erroresSeguimiento = $errors->getBag('seguimiento');
    $valoracion = [
        SeguimientoEvolucion::MEJORA => ['↑', 'bg-emerald-50 text-emerald-700'],
        SeguimientoEvolucion::ESTABLE => ['→', 'bg-sky-50 text-sky-700'],
        SeguimientoEvolucion::RETROCESO => ['↓', 'bg-rose-50 text-rose-700'],
    ];
    $asistencia = [
        Actuacion::ASISTIO => ['✓', 'bg-emerald-50 text-emerald-700'],
        Actuacion::NO_ASISTIO => ['✕', 'bg-rose-50 text-rose-700'],
        Actuacion::CANCELADA => ['⊘', 'bg-gray-100 text-gray-600'],
    ];
    $estadoTarea = [
        TareaTerapeutica::ESTADO_PENDIENTE => ['', 'bg-amber-50 text-amber-700', TareaTerapeutica::ESTADO_PENDIENTE],
        TareaTerapeutica::ESTADO_CUMPLIDA => ['✓', 'bg-emerald-50 text-emerald-700', 'Cumplida'],
        TareaTerapeutica::ESTADO_NO_CUMPLIDA => ['✕', 'bg-rose-50 text-rose-700', 'No cumplida'],
        TareaTerapeutica::ESTADO_CANCELADA => ['⊘', 'bg-gray-100 text-gray-600', 'Cancelada por remisión (no completada)'],
    ];
    // HU-007: aquí solo llega el profesional asignado; si remitió a una entidad externa, el caso queda "Cerrado".
    $estadoVisible = $caso->estado_caso;
    $citasPendientes = $caso->citas->filter(fn ($cita) => $cita->asistenciaPendiente());
    $citasPorCancelar = $citasPendientes->filter(fn ($cita) => $cita->esProxima());
    $citasSinAsistencia = $citasPendientes->reject(fn ($cita) => $cita->esProxima());
    $tareasPorCancelar = $caso->tareas->filter(fn ($tarea) => $tarea->estaPendiente());
    // RN-01: sin consentimiento firmado no se registra "Asistió", ni tareas, ni remisiones.
    $sinAtencion = $caso->estaActivo() ? $caso->motivoSinAtencion() : null;
    // Formato de remisión externa (D-10): la atención es la última cita a la que asistió, o la remisión.
    $ultimaAtendida = $caso->ultimaCitaAsistida();
    $fechaAtencion = $ultimaAtendida?->fecha_hora ?? now(Actuacion::ZONA_HORARIA);
    $riesgo = [
        'Alto' => 'bg-rose-50 border-rose-200 text-rose-600',
        'Medio' => 'bg-amber-50 border-amber-200 text-amber-600',
        'Bajo' => 'bg-emerald-50 border-emerald-200 text-emerald-600',
    ];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-3 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-[#007F2F] tracking-tight">Caso {{ $caso->codigo_expediente }} @include('casos.partials.emergencia', ['caso' => $caso])</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">{{ $caso->solicitud?->tipo_servicio }} · Bienestar Universitario</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
            <!-- Expediente digital único (HU-005) -->
            <a href="{{ route('profesional.expediente.show', $caso) }}"
               class="px-3 sm:px-4 py-2 bg-white border border-[#007F2F] text-[#007F2F] hover:bg-emerald-50 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                <span aria-hidden="true">🗂️</span> Expediente
            </a>

            @if ($caso->esDeInclusion() && Gate::allows('inclusion.gestionar', $caso))
                <a href="{{ route('inclusion.bitacora', $caso) }}"
                   class="px-3 sm:px-4 py-2 bg-white border border-[#007F2F] text-[#007F2F] hover:bg-emerald-50 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                    <span aria-hidden="true">📝</span> Bitácora
                </a>
            @endif

            <!-- Evolución del caso (HU-015) -->
            <a href="{{ route('casos.evolucion', $caso) }}"
               class="px-3 sm:px-4 py-2 bg-white border border-[#007F2F] text-[#007F2F] hover:bg-emerald-50 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                <span aria-hidden="true">📈</span> Ver evolución
            </a>

            <!-- Botón X -->
            <a href="{{ auth()->user()->urlCasos() }}"
               class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
               title="Volver a Salud Mental" aria-label="Cerrar y volver a Salud Mental">
                ✕
            </a>
            </div>
        </div>

        <!-- 2. CONTENIDO PRINCIPAL -->
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

                <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100 space-y-6">

                    <!-- Encabezado del expediente -->
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold text-[#007F2F]">Expediente {{ $caso->codigo_expediente }}</span>
                            <p class="text-[11px] text-gray-500 font-medium">Abierto el {{ $caso->fecha_apertura?->format('Y-m-d H:i') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class(['px-2.5 py-1 rounded-lg text-xs font-bold', 'bg-emerald-50 text-emerald-700' => $caso->estaActivo() && ! $remisionHecha, 'bg-gray-100 text-gray-600' => ! $caso->estaActivo() || $remisionHecha])>{{ $estadoVisible }}</span>
                            @if ($remisionRecibida)
                                <span class="px-2.5 py-1 bg-sky-50 text-sky-700 rounded-lg text-xs font-bold">Remisión</span>
                            @endif
                            @if ($caso->nivel_riesgo)
                                <span class="px-2.5 py-0.5 rounded-full border text-[11px] font-bold {{ $riesgo[$caso->nivel_riesgo] ?? '' }}">● {{ mb_strtolower($caso->nivel_riesgo) }}</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full border border-gray-200 bg-gray-50 text-gray-500 text-[11px] font-bold">Riesgo sin definir</span>
                            @endif
                        </div>
                    </div>

                    <!-- Bloqueo de la atención sin consentimiento firmado (RN-01) -->
                    @if ($sinAtencion && $esSuCaso)
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3" role="alert">
                            <div class="text-sm">
                                <p class="font-bold text-amber-900">Atención bloqueada</p>
                                <p class="text-xs text-amber-900 mt-1">{{ $sinAtencion }} Puedes agendar citas, pero no registrar asistencia, asignar tareas ni remitir.</p>
                            </div>
                            <a href="#consentimiento"
                               class="shrink-0 px-4 py-2 bg-white border border-amber-300 text-amber-900 hover:bg-amber-100 text-xs font-bold rounded-xl text-center transition focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2">
                                Ver estado del consentimiento
                            </a>
                        </div>
                    @endif

                    <!-- Remisión (HU-007): de dónde viene el caso o a dónde se fue -->
                    @if ($remisionHecha && ! $remisionHecha->esRemisionInterna())
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm" role="note">
                            <p class="font-bold text-gray-800">Caso cerrado · remitido a una entidad externa</p>
                            <p class="text-xs text-gray-600 mt-1 break-words">
                                Remitiste este caso a <strong>{{ $remisionHecha->entidad_destino }}</strong>
                                el {{ $remisionHecha->fecha_hora->format('Y-m-d H:i') }}. El expediente queda en solo lectura.
                            </p>
                        </div>
                    @elseif ($remisionHecha)
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm" role="note">
                            <p class="font-bold text-gray-800">Cerrado y remitido</p>
                            <p class="text-xs text-gray-600 mt-1 break-words">
                                Remitiste este caso a <strong>{{ $remisionHecha->etiquetaDestino() }}</strong>
                                el {{ $remisionHecha->fecha_hora->format('Y-m-d H:i') }}. El expediente queda en solo lectura para ti.
                            </p>
                        </div>
                    @elseif ($remisionRecibida)
                        <div class="bg-sky-50 border border-sky-200 rounded-xl p-4 text-sm" role="note">
                            <p class="font-bold text-sky-800">{{ $remisionRecibida->esTrasladoAdministrativo() ? 'Recibido por traslado administrativo' : 'Recibido por remisión' }}</p>
                            <p class="text-xs text-sky-900 mt-1 break-words">
                                {{ $remisionRecibida->esTrasladoAdministrativo() ? 'La administración lo trasladó desde' : 'Remitido por' }} <strong>{{ $remisionRecibida->profesional?->persona?->nombre_completo ?? 'otro profesional' }}</strong>
                                el {{ $remisionRecibida->fecha_hora->format('Y-m-d H:i') }}.
                            </p>
                            @if ($remisionRecibida->descripcion)
                                <p class="text-xs text-sky-900 mt-1 whitespace-pre-line break-words">Motivo: {{ $remisionRecibida->descripcion }}</p>
                            @endif
                            @if ($remisionRecibida->autorizacion_traslado)
                                <p class="text-xs text-sky-900 mt-1 break-words">{{ $remisionRecibida->etiquetaAutorizacion() }}: {{ $remisionRecibida->autorizacion_traslado }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="h-px bg-gray-100"></div>

                    <!-- Solicitante -->
                    <section id="estudiante">
                        <h2 class="text-base font-bold text-gray-900 mb-3">Solicitante</h2>
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                            <div>
                                <dt class="text-xs text-gray-500">Nombre completo</dt>
                                <dd class="font-semibold text-gray-900 break-words">{{ $persona?->nombre_completo }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Documento de identidad</dt>
                                <dd class="font-semibold text-gray-900">{{ $persona?->tipo_documento }} {{ $persona?->numero_documento }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Correo institucional</dt>
                                <dd class="font-semibold text-gray-900 break-all">{{ $persona?->correo_inst }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Sexo</dt>
                                <dd class="font-semibold text-gray-900">{{ $persona?->sexo ?? 'Sin registrar' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Fecha de nacimiento</dt>
                                <dd class="font-semibold text-gray-900">
                                    @if ($persona?->fecha_nacimiento)
                                        {{ $persona->fecha_nacimiento->format('Y-m-d') }} · {{ $persona->edadEn(now(Actuacion::ZONA_HORARIA)) }} años
                                    @else
                                        Sin registrar
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        @if ($persona && $esSuCaso)
                            <details class="mt-3 border border-gray-100 rounded-xl" @if ($errors->getBag('estudiante')->any() || ! $persona->datosRemisionCompletos()) open @endif>
                                <summary class="px-4 py-2.5 cursor-pointer text-sm font-bold text-[#007F2F] select-none">
                                    {{ $persona->datosRemisionCompletos() ? 'Corregir datos del estudiante' : 'Completar datos del estudiante' }}
                                </summary>
                                <div class="px-4 pb-4 pt-1">
                                    @unless ($persona->datosRemisionCompletos())
                                        <p class="text-xs text-gray-500 mb-3">Se necesitan para el consentimiento informado y para el formato de remisión.</p>
                                    @endunless
                                    @include('casos.partials.datos-estudiante', ['caso' => $caso, 'persona' => $persona, 'volver' => 'expediente'])
                                </div>
                            </details>
                        @endif
                    </section>

                    <!-- Caso -->
                    <section>
                        <h2 class="text-base font-bold text-gray-900 mb-3">Información del caso</h2>
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm mb-4">
                            <div>
                                <dt class="text-xs text-gray-500">Servicio</dt>
                                <dd class="font-semibold text-gray-900">{{ $caso->solicitud?->tipo_servicio }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Profesional asignado</dt>
                                <dd class="font-semibold text-gray-900">{{ $caso->profesional?->persona?->nombre_completo ?? 'Sin asignar' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Estado</dt>
                                <dd class="font-semibold text-gray-900">{{ $estadoVisible }}</dd>
                            </div>
                        </dl>
                        <p class="text-xs text-gray-500 mb-1">Motivo de consulta</p>
                        <div class="pl-3 border-l-2 border-gray-200 text-sm text-gray-700 font-medium py-0.5 whitespace-pre-line break-words">{{ $caso->solicitud?->descripcion }}</div>
                    </section>

                    <div class="h-px bg-gray-100"></div>

                    <!-- Consentimiento informado con firma (RQ-1) -->
                    <section id="consentimiento" aria-labelledby="consentimiento-titulo">
                        @php $vigente = $caso->consentimientoVigente(); $ultima = $caso->aceptacionesConsentimiento->first(); @endphp
                        <div class="mb-3">
                            <h2 id="consentimiento-titulo" class="text-base font-bold text-gray-900">Consentimiento informado</h2>
                            @if ($caso->requiereConsentimiento())
                                <p class="text-xs text-gray-500 mt-0.5">Lo firma el estudiante (o su acudiente, si es menor de edad) desde su cuenta, en "Mis citas".</p>
                            @endif
                        </div>

                        @if ($vigente)
                            <p class="text-sm font-semibold text-emerald-700 mb-3">✓ Consentimiento aceptado el {{ $vigente->fecha_hora->format('Y-m-d H:i') }}.</p>
                        @elseif ($ultima?->esRevocacion())
                            <p class="text-sm font-semibold text-rose-700 mb-3">✕ La persona revocó el consentimiento informado el {{ $ultima->fecha_hora->format('Y-m-d H:i') }}. No se puede prestar la atención.</p>
                        @elseif ($ultima)
                            <p class="text-sm font-semibold text-rose-700 mb-3">✕ El {{ $ultima->loOtorgaAcudiente() ? 'acudiente' : 'estudiante' }} no aceptó el consentimiento informado el {{ $ultima->fecha_hora->format('Y-m-d H:i') }}. No se puede prestar la atención.</p>
                        @elseif ($caso->exigeConsentimiento())
                            <p class="text-sm font-semibold text-amber-700 mb-3">Falta el consentimiento informado firmado: el estudiante debe firmarlo desde su cuenta antes de la atención.</p>
                        @elseif ($caso->requiereConsentimiento())
                            {{-- D-28 y D-29: por qué hoy no se exige la firma en este caso. --}}
                            <p class="text-sm text-gray-600 mb-3">
                                @if (! config('sigabu.consentimiento.exigir'))
                                    El consentimiento firmado todavía no es obligatorio en el sistema. El estudiante ya puede firmarlo desde su cuenta.
                                @elseif ($caso->eximidoPorFecha())
                                    Este caso se abrió antes de que se exigiera el consentimiento firmado: no es obligatorio, pero el estudiante puede firmarlo desde su cuenta.
                                @else
                                    La firma aún no se exige en este caso porque falta lo siguiente: {{ implode('; ', $caso->faltantesParaFirmar()) }}. Avisa al administrador.
                                @endif
                            </p>
                        @endif

                        @if ($caso->aceptacionesConsentimiento->isNotEmpty())
                            <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl">
                                @foreach ($caso->aceptacionesConsentimiento as $aceptacion)
                                    <li class="p-4 text-sm space-y-1">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span @class(['px-2 py-0.5 rounded-full text-[11px] font-semibold', 'bg-emerald-50 text-emerald-700' => $aceptacion->esAceptado(), 'bg-rose-50 text-rose-700' => ! $aceptacion->esAceptado()])>
                                                {{ $aceptacion->esAceptado() ? '✓ Aceptado' : ($aceptacion->esRevocacion() ? '✕ Revocado' : '✕ No aceptado') }}
                                            </span>
                                            <span class="text-xs text-gray-500">{{ $aceptacion->fecha_hora->format('Y-m-d H:i') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-600">
                                            {{ $aceptacion->version?->modelo?->nombre }} · versión {{ $aceptacion->version?->numero_version }}{{ $aceptacion->version?->estado === 'retirada' ? ' (retirada)' : '' }}
                                            · {{ match ($aceptacion->medio) {
                                                \App\Models\ConsentimientoAceptacion::MEDIO_FIRMA_ENLACE, \App\Models\ConsentimientoAceptacion::MEDIO_FIRMA_INCLUSION => 'firmado por el estudiante desde su cuenta',
                                                \App\Models\ConsentimientoAceptacion::MEDIO_FIRMA_PAPEL => 'firmado en papel',
                                                default => 'firma táctil en el expediente',
                                            } }}
                                            · profesional: {{ $aceptacion->profesional?->persona?->nombre_completo }}
                                        </p>
                                        @if ($aceptacion->loOtorgaAcudiente())
                                            <p class="text-xs text-gray-600">Otorgado por el acudiente {{ $aceptacion->acudiente_nombre }} ({{ $aceptacion->acudiente_parentesco }}, {{ $aceptacion->acudiente_tipo_documento }} {{ $aceptacion->acudiente_numero_documento }}).</p>
                                        @endif
                                        @if ($aceptacion->encabezado())
                                            <p class="text-xs text-gray-600">Vinculación: {{ $aceptacion->encabezado() }}.</p>
                                        @endif
                                        @if ($aceptacion->esEnPapel())
                                            <p class="text-xs text-gray-700 font-semibold">{{ \App\Models\Inclusion\ConsentimientoDetalle::LEYENDA_PAPEL }}. Motivo: {{ $aceptacion->detalleInclusion?->motivo_papel }}</p>
                                        @elseif ($aceptacion->esAceptado())
                                            @if ($aceptacion->firmaProfesional)
                                                <p class="text-xs text-emerald-700 font-semibold">✓ Firmado por el profesional tratante el {{ $aceptacion->firmaProfesional->fecha_hora->format('Y-m-d H:i') }}.</p>
                                            @else
                                                <p class="text-xs text-amber-700 font-semibold">Falta la firma del profesional tratante ({{ $aceptacion->profesional?->persona?->nombre_completo ?? 'profesional del caso' }}).</p>
                                            @endif
                                        @endif
                                        @if ($puedeVerEvidencia && \App\Documentos\PdfConsentimiento::aplicaA($aceptacion))
                                            <a href="{{ route('consentimientos.pdf', $aceptacion) }}"
                                               class="inline-flex items-center gap-1 pt-1 text-xs font-bold text-[#007F2F] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2 rounded">
                                                ↓ Descargar consentimiento (PDF)
                                            </a>
                                        @endif
                                        @if ($puedeVerEvidencia)
                                            <details class="pt-1">
                                                <summary class="cursor-pointer text-xs font-bold text-[#007F2F] select-none">Ver texto{{ $aceptacion->firma_ruta && ! $aceptacion->esEnPapel() ? ' y firmas' : '' }}</summary>
                                                <div class="mt-2 pl-3 border-l-2 border-gray-200 text-xs text-gray-600 whitespace-pre-line break-words">{{ $aceptacion->texto_presentado }}</div>
                                                @if ($aceptacion->firma_ruta && ! $aceptacion->esEnPapel())
                                                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                        <figure>
                                                            <img src="{{ route('consentimientos.firma', $aceptacion) }}" alt="Firma registrada el {{ $aceptacion->fecha_hora->format('Y-m-d H:i') }}"
                                                                 loading="lazy" class="w-full max-w-xs border border-gray-200 rounded-lg bg-white">
                                                            <figcaption class="mt-1 text-[11px] text-gray-500">Firma {{ $aceptacion->loOtorgaAcudiente() ? 'del acudiente' : 'del estudiante' }}</figcaption>
                                                        </figure>
                                                        @if ($aceptacion->firma_estudiante_ruta)
                                                            <figure>
                                                                <img src="{{ route('consentimientos.firma-profesional', [$aceptacion, 'estudiante']) }}" alt="Firma del estudiante menor de edad registrada el {{ $aceptacion->fecha_hora->format('Y-m-d H:i') }}"
                                                                     loading="lazy" class="w-full max-w-xs border border-gray-200 rounded-lg bg-white">
                                                                <figcaption class="mt-1 text-[11px] text-gray-500">Firma del estudiante (menor de edad)</figcaption>
                                                            </figure>
                                                        @endif
                                                        @if ($aceptacion->firmaProfesional)
                                                            <figure>
                                                                <img src="{{ route('consentimientos.firma-profesional', [$aceptacion, 'profesional']) }}" alt="Firma del profesional tratante registrada el {{ $aceptacion->firmaProfesional->fecha_hora->format('Y-m-d H:i') }}"
                                                                     loading="lazy" class="w-full max-w-xs border border-gray-200 rounded-lg bg-white">
                                                                <figcaption class="mt-1 text-[11px] text-gray-500">Firma del profesional tratante</figcaption>
                                                            </figure>
                                                        @endif
                                                    </div>
                                                @endif
                                            </details>
                                        @endif
                                        @if ($aceptacion->faltaFirmaProfesional() && $aceptacion->id_profesional === auth()->id() && (! $caso->esDeInclusion() || $vigente?->is($aceptacion)))
                                            {{-- Contrafirma del profesional tratante, como en el formato en papel. --}}
                                            <form method="POST" action="{{ route('consentimientos.firmar-profesional', $aceptacion) }}"
                                                  x-data="firmaTactil({})" @submit.prevent class="mt-3 p-3 bg-amber-50/60 border border-amber-200 rounded-xl space-y-2">
                                                @csrf
                                                <div class="flex items-center justify-between gap-3">
                                                    <p id="firma-profesional-ayuda-{{ $aceptacion->id_aceptacion }}" class="text-xs font-semibold text-gray-700">Firma como profesional tratante, con el dedo o el mouse.</p>
                                                    <button type="button" @click="borrar()" :disabled="enviando"
                                                            class="px-3 py-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                                        Borrar
                                                    </button>
                                                </div>
                                                <x-firma-lienzo :ayuda="'firma-profesional-ayuda-'.$aceptacion->id_aceptacion" etiqueta="Área de firma del profesional" class="max-w-md" />
                                                <x-input-error :messages="$errors->getBag('firmaProfesional')->get('firma')" />
                                                <p class="text-sm text-red-600" x-show="error('firma') || mensaje" x-text="error('firma') || mensaje" style="display: none;"></p>
                                                <button type="button" @click="enviar('{{ \App\Models\ConsentimientoAceptacion::ACEPTADO }}')" :disabled="enviando || ! hayTrazo"
                                                        class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-lg transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                                    <span x-show="! enviando">Firmar consentimiento</span>
                                                    <span x-show="enviando" style="display: none;">Guardando…</span>
                                                </button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @foreach ($caso->consentimientos as $consentimiento)
                            <details class="text-sm mt-2">
                                <summary class="cursor-pointer text-gray-500 text-xs">
                                    Casilla (antes de la firma digital): {{ $consentimiento->aceptado ? 'aceptado' : 'no aceptado' }}
                                    el {{ $consentimiento->fecha_hora_aceptacion?->format('Y-m-d H:i') }}
                                    · {{ $consentimiento->version?->modelo?->nombre ?? 'Versión' }} ({{ $consentimiento->version_formato }})
                                </summary>
                                @if ($consentimiento->version)
                                    <div class="mt-2 pl-3 border-l-2 border-gray-200 text-xs text-gray-600 whitespace-pre-line break-words">{{ $consentimiento->version->texto }}</div>
                                @endif
                            </details>
                        @endforeach
                    </section>

                    @if ($caso->esDeSaludMental())
                        <div class="h-px bg-gray-100"></div>

                        <!-- Historia clínica psicológica -->
                        <section id="historia-clinica" aria-labelledby="historia-clinica-titulo">
                            @php
                                $historia = $caso->historiaClinica;
                                $erroresHistoria = $errors->getBag('historia');
                                $erroresAnotacion = $errors->getBag('anotacion');
                                $sinHistoria = $caso->motivoSinHistoriaClinica();
                            @endphp
                            <div class="mb-3">
                                <h2 id="historia-clinica-titulo" class="text-base font-bold text-gray-900">Historia clínica psicológica</h2>
                                @if ($historia && $esSuCaso)
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Registrada el {{ $historia->fecha_registro?->format('Y-m-d H:i') }} por {{ $historia->profesional?->persona?->nombre_completo ?? 'el profesional del caso' }}.
                                        No se modifica: las correcciones y los complementos van como anotaciones.
                                    </p>
                                @endif
                            </div>

                            {{-- Reserva de la historia clínica: solo la consulta el profesional asignado al caso. --}}
                            @if (! $esSuCaso)
                                <p class="text-xs text-gray-500">La historia clínica solo la consulta el profesional asignado al caso.</p>
                            @elseif ($historia)
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
                                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl mb-4">
                                        @foreach ($historia->anotaciones as $anotacion)
                                            <li class="p-4 text-sm">
                                                <p class="text-[11px] text-gray-500 font-medium">
                                                    {{ $anotacion->fecha_hora?->format('Y-m-d H:i') }} · {{ $anotacion->profesional?->persona?->nombre_completo ?? 'Profesional' }}
                                                    · {{ $anotacion->etiquetaApartado() ?? 'Historia en general' }}
                                                </p>
                                                <p class="mt-1 text-gray-700 whitespace-pre-line break-words">{{ $anotacion->texto }}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($esSuCaso && ! $sinHistoria)
                                    <details class="border border-gray-100 rounded-xl" @if ($erroresAnotacion->any()) open @endif>
                                        <summary class="px-4 py-2.5 cursor-pointer text-sm font-bold text-[#007F2F] select-none">+ Agregar anotación</summary>
                                        <form method="POST" action="{{ route('historia-clinica.anotar', $caso) }}" class="px-4 pb-4 pt-1 space-y-3">
                                            @csrf
                                            <div>
                                                <label for="anotacion-apartado" class="block text-sm font-medium text-gray-700 mb-1.5">Apartado <span class="text-gray-500 font-normal">(opcional)</span></label>
                                                <select id="anotacion-apartado" name="apartado"
                                                        @if ($erroresAnotacion->has('apartado')) aria-invalid="true" aria-describedby="anotacion-apartado-error" @endif
                                                        @class([$input, 'sm:w-80', 'border-red-500' => $erroresAnotacion->has('apartado'), 'border-gray-300' => ! $erroresAnotacion->has('apartado')])>
                                                    <option value="">Historia en general</option>
                                                    @foreach (HistoriaClinica::APARTADOS as $campo => [$etiqueta])
                                                        <option value="{{ $campo }}" @selected(old('apartado') === $campo)>{{ $etiqueta }}</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error id="anotacion-apartado-error" :messages="$erroresAnotacion->get('apartado')" class="mt-2" />
                                            </div>
                                            <div>
                                                <label for="anotacion-texto" class="block text-sm font-medium text-gray-700 mb-1.5">Anotación</label>
                                                <textarea id="anotacion-texto" name="texto" rows="3" required maxlength="{{ HistoriaClinica::TEXTO_MAX }}"
                                                          placeholder="Corrección o información nueva para la historia…"
                                                          @if ($erroresAnotacion->has('texto')) aria-invalid="true" aria-describedby="anotacion-texto-error" @endif
                                                          @class([$input, 'resize-y', 'border-red-500' => $erroresAnotacion->has('texto'), 'border-gray-300' => ! $erroresAnotacion->has('texto')])>{{ old('texto') }}</textarea>
                                                <x-input-error id="anotacion-texto-error" :messages="$erroresAnotacion->get('texto')" class="mt-2" />
                                            </div>
                                            <div class="flex justify-end">
                                                <button type="submit"
                                                        class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                                    Guardar anotación
                                                </button>
                                            </div>
                                        </form>
                                    </details>
                                @elseif ($esSuCaso)
                                    <p class="text-xs text-amber-700">{{ $sinHistoria }} Por eso no se pueden agregar anotaciones.</p>
                                @endif
                            @else
                                <div class="py-8 px-5 mb-3 text-center text-sm text-gray-500 bg-gray-50 rounded-xl">
                                    Este expediente aún no tiene historia clínica registrada.
                                </div>

                                @if ($esSuCaso && ! $sinHistoria)
                                    <details class="border border-gray-100 rounded-xl" @if ($erroresHistoria->any()) open @endif>
                                        <summary class="px-4 py-2.5 cursor-pointer text-sm font-bold text-[#007F2F] select-none">+ Registrar historia clínica</summary>
                                        <form method="POST" action="{{ route('historia-clinica.store', $caso) }}" class="px-4 pb-4 pt-1 space-y-3">
                                            @csrf
                                            <p class="text-xs text-amber-700">Revísala antes de guardar: una vez registrada no se modifica, solo admite anotaciones.</p>
                                            @foreach (HistoriaClinica::APARTADOS as $campo => [$etiqueta, $obligatorio])
                                                <div>
                                                    <label for="hc-{{ $campo }}" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                        {{ $etiqueta }}
                                                        @unless ($obligatorio)<span class="text-gray-500 font-normal">(opcional)</span>@endunless
                                                    </label>
                                                    <textarea id="hc-{{ $campo }}" name="{{ $campo }}" rows="3" maxlength="{{ HistoriaClinica::TEXTO_MAX }}" @if ($obligatorio) required @endif
                                                              @if ($erroresHistoria->has($campo)) aria-invalid="true" aria-describedby="hc-{{ $campo }}-error" @endif
                                                              @class([$input, 'resize-y', 'border-red-500' => $erroresHistoria->has($campo), 'border-gray-300' => ! $erroresHistoria->has($campo)])>{{ old($campo) }}</textarea>
                                                    <x-input-error id="hc-{{ $campo }}-error" :messages="$erroresHistoria->get($campo)" class="mt-2" />
                                                </div>
                                            @endforeach
                                            <div class="flex justify-end">
                                                <button type="submit"
                                                        class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                                    Guardar historia clínica
                                                </button>
                                            </div>
                                        </form>
                                    </details>
                                @elseif ($esSuCaso)
                                    <p class="text-xs text-amber-700">{{ $sinHistoria }}</p>
                                @endif
                            @endif
                        </section>
                    @endif

                    <div class="h-px bg-gray-100"></div>

                    <!-- Citas (HU-011) -->
                    <section id="citas" aria-labelledby="citas-titulo">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <h2 id="citas-titulo" class="text-base font-bold text-gray-900">Citas</h2>
                            @if ($puedeAgendar)
                                <a href="{{ route('citas.create', ['caso' => $caso->id_caso]) }}"
                                   class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    <span aria-hidden="true">+</span> Agendar cita
                                </a>
                            @endif
                        </div>

                        @unless ($caso->estaActivo())
                            <p class="text-xs text-gray-500 mb-3">El caso está en estado "{{ $caso->estado_caso }}": solo se pueden agendar citas para casos activos.</p>
                        @endunless

                        @if ($puedeAgendar && $caso->necesitaNuevaCita())
                            @php $ultimaCita = $caso->ultimaCitaRealizada(); @endphp
                            <div class="mb-3 bg-sky-50 border border-sky-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3" role="status">
                                <p class="text-xs sm:text-sm text-sky-800 font-medium leading-relaxed">
                                    La última cita ({{ $ultimaCita->fecha_hora->format('Y-m-d') }} a las {{ $ultimaCita->fecha_hora->format('g:i a') }}) ya se realizó y el caso sigue abierto.
                                    Asígnale al paciente una nueva fecha y hora.
                                </p>
                                <a href="{{ route('citas.create', ['caso' => $caso->id_caso]) }}"
                                   class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm text-center shrink-0 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    Asignar nueva cita
                                </a>
                            </div>
                        @endif

                        {{-- Sin cita próxima: el psicólogo puede enviar horarios para que el estudiante elija. --}}
                        @if ($puedeAgendar && ! $caso->proximaCita())
                            <div class="mb-3">
                                @include('citas.partials.proponer-horarios', ['caso' => $caso])
                            </div>
                        @endif

                        @if ($caso->citas->isEmpty())
                            <div class="py-8 px-5 text-center text-sm text-gray-500 bg-gray-50 rounded-xl">
                                No hay citas registradas para este expediente.
                            </div>
                        @else
                            <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl">
                                @foreach ($caso->citas as $cita)
                                    <li class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-gray-500">Fecha</p>
                                            <p class="font-semibold text-gray-900">{{ $cita->fecha_hora->format('Y-m-d') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Hora</p>
                                            <p class="font-semibold text-gray-900">{{ $cita->fecha_hora->format('g:i a') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Profesional</p>
                                            <p class="font-semibold text-gray-900 break-words">{{ $cita->profesional?->persona?->nombre_completo ?? 'Sin asignar' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Tipo</p>
                                            <p class="flex flex-wrap gap-1.5 mt-0.5">
                                                <span class="px-2 py-0.5 bg-[#e6f4ec] text-[#007F2F] rounded-full text-[11px] font-semibold">Asesoría psicológica</span>
                                                @if ($cita->esProxima() && ! $cita->estaCancelada())
                                                    <span class="px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full text-[11px] font-semibold">Próxima</span>
                                                @endif
                                            </p>
                                        </div>
                                        @if ($cita->descripcion)
                                            <div class="col-span-2 sm:col-span-4 pl-3 border-l-2 border-gray-200 text-xs text-gray-600 font-medium py-0.5 whitespace-pre-line break-words">{{ $cita->descripcion }}</div>
                                        @endif

                                        <!-- Asistencia (HU-012) -->
                                        <div class="col-span-2 sm:col-span-4">
                                            <p class="text-xs text-gray-500 mb-1">Asistencia</p>
                                            @if (! $cita->asistenciaPendiente())
                                                @php [$icono, $color] = $asistencia[$cita->estado_asistencia] ?? ['', '']; @endphp
                                                <p class="flex flex-wrap items-center gap-2">
                                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $color }}">{{ $icono }} {{ $cita->estado_asistencia }}</span>
                                                    @if ($cita->estaCancelada())
                                                        <span class="text-xs text-gray-600">Cancelada por la remisión del caso.</span>
                                                    @endif
                                                    @if ($cita->motivo_inasistencia)
                                                        <span class="text-xs text-gray-600 break-words">Motivo: {{ $cita->motivo_inasistencia }}</span>
                                                    @endif
                                                </p>
                                            @elseif ($cita->esProxima())
                                                <p class="text-xs text-gray-500">Cita futura: la asistencia se registra a partir de la hora de la cita.</p>
                                            @elseif ($esSuCaso)
                                                @php $conError = old('id_actuacion') === $cita->id_actuacion && $erroresAsistencia->any(); @endphp
                                                <form method="POST" action="{{ route('citas.asistencia', $cita) }}" class="flex flex-col sm:flex-row sm:items-start gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="id_actuacion" value="{{ $cita->id_actuacion }}">
                                                    <div class="flex-1">
                                                        <label for="motivo-{{ $cita->id_actuacion }}" class="sr-only">Motivo de inasistencia (opcional)</label>
                                                        <input type="text" id="motivo-{{ $cita->id_actuacion }}" name="motivo_inasistencia"
                                                               maxlength="{{ Actuacion::MOTIVO_MAX }}" placeholder="Motivo si no asistió (opcional)"
                                                               value="{{ $conError ? old('motivo_inasistencia') : '' }}"
                                                               @if ($conError) aria-invalid="true" aria-describedby="motivo-{{ $cita->id_actuacion }}-error" @endif
                                                               @class([$input, 'py-1.5', 'border-red-500' => $conError, 'border-gray-300' => ! $conError])>
                                                        @if ($conError)
                                                            <x-input-error id="motivo-{{ $cita->id_actuacion }}-error" :messages="$erroresAsistencia->all()" class="mt-1" />
                                                        @endif
                                                    </div>
                                                    <div class="flex gap-2 shrink-0">
                                                        <button type="submit" name="estado_asistencia" value="{{ Actuacion::ASISTIO }}"
                                                                @if ($sinAtencion) disabled title="{{ $sinAtencion }}" @endif
                                                                class="px-3 py-1.5 rounded-full border-2 border-[#007F2F] bg-emerald-50 text-[#007F2F] text-xs font-bold hover:bg-emerald-100 disabled:opacity-40 disabled:cursor-not-allowed transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                                            ✓ Asistió
                                                        </button>
                                                        <button type="submit" name="estado_asistencia" value="{{ Actuacion::NO_ASISTIO }}"
                                                                class="px-3 py-1.5 rounded-full border-2 border-rose-500 bg-rose-50 text-rose-700 text-xs font-bold hover:bg-rose-100 transition focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                                                            ✕ No asistió
                                                        </button>
                                                    </div>
                                                </form>
                                            @else
                                                <p class="text-xs text-gray-500">Pendiente de registro por el profesional del caso.</p>
                                            @endif
                                        </div>

                                        <!-- Seguimiento de la evolución (HU-015) -->
                                        @if ($cita->estado_asistencia === Actuacion::ASISTIO)
                                            <div class="col-span-2 sm:col-span-4">
                                                @if ($seguimiento = $cita->seguimiento)
                                                    @php [$flecha, $colorValoracion] = $valoracion[$seguimiento->valoracion_avance] ?? ['', '']; @endphp
                                                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 space-y-2">
                                                        <p class="text-xs text-gray-500">Seguimiento de la sesión</p>
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
                                                        <p class="text-[11px] text-gray-500">
                                                            Registrado por {{ $seguimiento->profesional?->persona?->nombre_completo ?? 'el profesional' }} el {{ $seguimiento->fecha_hora->format('Y-m-d H:i') }}
                                                        </p>
                                                    </div>
                                                @elseif ($puedeAgendar && ! $sinAtencion)
                                                    @php
                                                        $conErrorSeg = old('cita_seguimiento') === $cita->id_actuacion && $erroresSeguimiento->any();
                                                        $sufijo = $cita->id_actuacion;
                                                    @endphp
                                                    <details @if ($conErrorSeg) open @endif class="border border-dashed border-[#007F2F]/40 rounded-xl">
                                                        <summary class="px-3 py-2 text-xs font-bold text-[#007F2F] cursor-pointer select-none rounded-xl hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-[#007F2F]">
                                                            + Registrar seguimiento de la sesión
                                                        </summary>
                                                        <form method="POST" action="{{ route('seguimientos.store', $cita) }}" class="p-3 pt-1 space-y-3"
                                                              x-data="{ instrumento: @js($conErrorSeg ? old('instrumento', '') : ''), maximos: @js(SeguimientoEvolucion::INSTRUMENTOS) }">
                                                            @csrf
                                                            <input type="hidden" name="cita_seguimiento" value="{{ $cita->id_actuacion }}">

                                                            <fieldset>
                                                                <legend class="text-xs font-semibold text-gray-700 mb-1">Nivel de riesgo en esta sesión <span class="text-rose-600">*</span></legend>
                                                                <div class="flex flex-wrap gap-3">
                                                                    @foreach (\App\Models\Caso::NIVELES_RIESGO as $nivel)
                                                                        <label class="flex items-center gap-1.5 text-xs text-gray-800">
                                                                            <input type="radio" name="nivel_riesgo" value="{{ $nivel }}" required
                                                                                   @checked($conErrorSeg && old('nivel_riesgo') === $nivel)
                                                                                   class="text-[#007F2F] focus:ring-[#007F2F]">
                                                                            {{ $nivel }}
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                                @if ($conErrorSeg)
                                                                    <x-input-error :messages="$erroresSeguimiento->get('nivel_riesgo')" class="mt-1" />
                                                                @endif
                                                            </fieldset>

                                                            <fieldset>
                                                                <legend class="text-xs font-semibold text-gray-700 mb-1">Avance respecto de la sesión anterior <span class="text-rose-600">*</span></legend>
                                                                <div class="flex flex-wrap gap-3">
                                                                    @foreach (SeguimientoEvolucion::VALORACIONES as $opcion)
                                                                        <label class="flex items-center gap-1.5 text-xs text-gray-800">
                                                                            <input type="radio" name="valoracion_avance" value="{{ $opcion }}" required
                                                                                   @checked($conErrorSeg && old('valoracion_avance') === $opcion)
                                                                                   class="text-[#007F2F] focus:ring-[#007F2F]">
                                                                            {{ $valoracion[$opcion][0] }} {{ $opcion }}
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                                @if ($conErrorSeg)
                                                                    <x-input-error :messages="$erroresSeguimiento->get('valoracion_avance')" class="mt-1" />
                                                                @endif
                                                            </fieldset>

                                                            @if ($caso->esDeSaludMental())
                                                                @php
                                                                    $errInstrumento = $conErrorSeg && $erroresSeguimiento->has('instrumento');
                                                                    $errPuntaje = $conErrorSeg && $erroresSeguimiento->has('puntaje');
                                                                @endphp
                                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                    <div>
                                                                        <label for="instrumento-{{ $sufijo }}" class="block text-xs font-semibold text-gray-700 mb-1">Escala aplicada (opcional)</label>
                                                                        <select id="instrumento-{{ $sufijo }}" name="instrumento" x-model="instrumento"
                                                                                @class([$input, 'py-1.5', 'border-red-500' => $errInstrumento, 'border-gray-300' => ! $errInstrumento])>
                                                                            <option value="">Ninguna</option>
                                                                            @foreach (SeguimientoEvolucion::INSTRUMENTOS as $escala => $maximo)
                                                                                <option value="{{ $escala }}" @selected($conErrorSeg && old('instrumento') === $escala)>{{ $escala }} (0 a {{ $maximo }})</option>
                                                                            @endforeach
                                                                        </select>
                                                                        @if ($conErrorSeg)
                                                                            <x-input-error :messages="$erroresSeguimiento->get('instrumento')" class="mt-1" />
                                                                        @endif
                                                                    </div>
                                                                    <div>
                                                                        <label for="puntaje-{{ $sufijo }}" class="block text-xs font-semibold text-gray-700 mb-1">Puntaje</label>
                                                                        <input type="number" id="puntaje-{{ $sufijo }}" name="puntaje" min="0" step="1" inputmode="numeric"
                                                                               value="{{ $conErrorSeg ? old('puntaje') : '' }}"
                                                                               :max="maximos[instrumento] ?? null" :disabled="! instrumento" :required="!! instrumento"
                                                                               @if ($errPuntaje) aria-invalid="true" aria-describedby="puntaje-{{ $sufijo }}-error" @endif
                                                                               @class([$input, 'py-1.5 disabled:bg-gray-100', 'border-red-500' => $errPuntaje, 'border-gray-300' => ! $errPuntaje])>
                                                                        @if ($conErrorSeg)
                                                                            <x-input-error id="puntaje-{{ $sufijo }}-error" :messages="$erroresSeguimiento->get('puntaje')" class="mt-1" />
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            @php $errNota = $conErrorSeg && $erroresSeguimiento->has('nota'); @endphp
                                                            <div>
                                                                <label for="nota-{{ $sufijo }}" class="block text-xs font-semibold text-gray-700 mb-1">Nota de la sesión <span class="text-rose-600">*</span></label>
                                                                <textarea id="nota-{{ $sufijo }}" name="nota" rows="3" required maxlength="{{ SeguimientoEvolucion::NOTA_MAX }}"
                                                                          placeholder="Resumen de la sesión, avances y acuerdos."
                                                                          @if ($errNota) aria-invalid="true" aria-describedby="nota-{{ $sufijo }}-error" @endif
                                                                          @class([$input, 'border-red-500' => $errNota, 'border-gray-300' => ! $errNota])>{{ $conErrorSeg ? old('nota') : '' }}</textarea>
                                                                @if ($conErrorSeg)
                                                                    <x-input-error id="nota-{{ $sufijo }}-error" :messages="$erroresSeguimiento->get('nota')" class="mt-1" />
                                                                @endif
                                                            </div>

                                                            <p class="text-[11px] text-gray-500">El seguimiento no se puede modificar después de guardarlo. El nivel de riesgo pasa a ser el riesgo vigente del caso.</p>

                                                            <button type="submit"
                                                                    class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                                                Guardar seguimiento
                                                            </button>
                                                        </form>
                                                    </details>
                                                @elseif ($esSuCaso && $caso->estaActivo())
                                                    <p class="text-xs text-gray-500">Sin seguimiento registrado.</p>
                                                @endif
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <div class="h-px bg-gray-100"></div>

                    {{-- Ajustes razonables de Inclusión (HU-018) y su seguimiento (HU-019) --}}
                    @if ($caso->esDeInclusion())
                        @include('casos.partials.ajustes-razonables', ['caso' => $caso, 'esSuCaso' => $esSuCaso, 'input' => $input])

                        <div class="h-px bg-gray-100"></div>
                    @endif

                    <!-- Tareas terapéuticas (HU-013) -->
                    <section id="tareas" aria-labelledby="tareas-titulo">
                        <h2 id="tareas-titulo" class="text-base font-bold text-gray-900 mb-3">Tareas terapéuticas</h2>

                        @if ($puedeAgendar && ! $sinAtencion)
                            <details class="mb-4 border border-gray-100 rounded-xl" @if ($erroresTarea->any()) open @endif>
                                <summary class="px-4 py-2.5 cursor-pointer text-sm font-bold text-[#007F2F] select-none">+ Asignar tarea</summary>
                                <form method="POST" action="{{ route('tareas.store', $caso) }}" class="px-4 pb-4 pt-1 space-y-3">
                                    @csrf
                                    <div>
                                        <label for="titulo" class="block text-sm font-medium text-gray-700 mb-1.5">Título de la actividad</label>
                                        <input type="text" id="titulo" name="titulo" required maxlength="{{ TareaTerapeutica::TITULO_MAX }}"
                                               value="{{ old('titulo') }}" placeholder="Ej. Diario de emociones"
                                               @if ($erroresTarea->has('titulo')) aria-invalid="true" aria-describedby="titulo-error" @endif
                                               @class([$input, 'border-red-500' => $erroresTarea->has('titulo'), 'border-gray-300' => ! $erroresTarea->has('titulo')])>
                                        <x-input-error id="titulo-error" :messages="$erroresTarea->get('titulo')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1.5">Descripción de la actividad</label>
                                        <textarea id="descripcion" name="descripcion" rows="3" required maxlength="{{ TareaTerapeutica::DESCRIPCION_MAX }}"
                                                  placeholder="Describe qué debe hacer el estudiante entre sesiones…"
                                                  @if ($erroresTarea->has('descripcion')) aria-invalid="true" aria-describedby="descripcion-error" @endif
                                                  @class([$input, 'resize-y', 'border-red-500' => $erroresTarea->has('descripcion'), 'border-gray-300' => ! $erroresTarea->has('descripcion')])>{{ old('descripcion') }}</textarea>
                                        <x-input-error id="descripcion-error" :messages="$erroresTarea->get('descripcion')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="fecha_limite" class="block text-sm font-medium text-gray-700 mb-1.5">Fecha límite <span class="text-gray-500 font-normal">(opcional)</span></label>
                                        <input type="date" id="fecha_limite" name="fecha_limite" value="{{ old('fecha_limite') }}"
                                               min="{{ now(Actuacion::ZONA_HORARIA)->toDateString() }}"
                                               @if ($erroresTarea->has('fecha_limite')) aria-invalid="true" aria-describedby="fecha_limite-error" @endif
                                               @class([$input, 'sm:w-56', 'border-red-500' => $erroresTarea->has('fecha_limite'), 'border-gray-300' => ! $erroresTarea->has('fecha_limite')])>
                                        <x-input-error id="fecha_limite-error" :messages="$erroresTarea->get('fecha_limite')" class="mt-2" />
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit"
                                                class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                            Guardar tarea
                                        </button>
                                    </div>
                                </form>
                            </details>
                        @elseif ($sinAtencion && $esSuCaso)
                            <p class="text-xs text-amber-700 mb-3">{{ $sinAtencion }} Por eso no se pueden asignar tareas.</p>
                        @elseif (! $caso->estaActivo())
                            <p class="text-xs text-gray-500 mb-3">El caso está en estado "{{ $caso->estado_caso }}": solo se pueden asignar tareas en casos activos.</p>
                        @endif

                        @if ($caso->tareas->isEmpty())
                            <div class="py-8 px-5 text-center text-sm text-gray-500 bg-gray-50 rounded-xl">
                                No hay tareas terapéuticas asignadas a este expediente.
                            </div>
                        @else
                            <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl">
                                @foreach ($caso->tareas as $tarea)
                                    <li class="p-4 text-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-bold text-gray-900 break-words">{{ $tarea->titulo }}</p>
                                                <p class="mt-1 text-xs text-gray-600 leading-relaxed whitespace-pre-line break-words">{{ $tarea->descripcion }}</p>
                                            </div>
                                            @php [$icono, $color, $etiqueta] = $estadoTarea[$tarea->estado] ?? ['', 'bg-gray-100 text-gray-600', $tarea->estado]; @endphp
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold text-right {{ $color }}">{{ trim("$icono $etiqueta") }}</span>
                                        </div>
                                        <p class="mt-2 text-[11px] text-gray-500 font-medium">
                                            Asignada: {{ $tarea->fecha_asignacion?->format('Y-m-d') }}
                                            @if ($tarea->fecha_limite)
                                                · Fecha límite: {{ $tarea->fecha_limite->format('Y-m-d') }}
                                            @endif
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <div class="h-px bg-gray-100"></div>

                    <!-- Desistimiento de la atención: respaldo legal cuando la persona decide no continuar -->
                    @if ($caso->desistimientos->isNotEmpty() || ($caso->estaActivo() && $esSuCaso))
                        <section id="desistimiento" aria-labelledby="desistimiento-titulo">
                            <h2 id="desistimiento-titulo" class="text-base font-bold text-gray-900 mb-3">Desistimiento</h2>

                            @foreach ($caso->desistimientos as $desistimiento)
                                <div class="border border-gray-100 rounded-xl p-4 mb-3 text-sm space-y-1">
                                    <p class="font-semibold text-gray-900">{{ $desistimiento->motivo }}</p>
                                    @if ($desistimiento->observaciones)
                                        <p class="text-gray-600 whitespace-pre-line break-words">{{ $desistimiento->observaciones }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500">Registrado el {{ $desistimiento->fecha_hora->format('Y-m-d H:i') }} por {{ $desistimiento->profesional_nombre }}. No se puede modificar.</p>
                                    @if ($desistimiento->id_profesional === auth()->id() || $esSuCaso)
                                        <a href="{{ route('desistimientos.pdf', $desistimiento) }}" class="inline-block mt-1 text-xs font-bold text-[#007F2F] hover:underline">Descargar formato PDF para firmar</a>
                                    @endif
                                </div>
                            @endforeach

                            @if ($caso->estaActivo() && $esSuCaso)
                                @php $erroresDesistimiento = $errors->getBag('desistimiento'); @endphp
                                <details class="border border-gray-100 rounded-xl" @if ($erroresDesistimiento->any()) open @endif>
                                    <summary class="px-4 py-2.5 cursor-pointer text-sm font-bold text-gray-700 select-none">Registrar desistimiento</summary>
                                    <form method="POST" action="{{ route('desistimientos.store', $caso) }}" class="px-4 pb-4 pt-1 space-y-3"
                                          x-data="{ motivo: @js(old('motivo', '')) }"
                                          onsubmit="return confirm('Se cerrará el caso {{ $caso->codigo_expediente }} y se cancelarán sus citas y tareas pendientes. ¿Continuar?')">
                                        @csrf
                                        <p class="text-xs text-gray-500">Úsalo cuando la persona decide no continuar la atención o no contar lo sucedido. El caso se cierra y se genera el formato para que lo firmen la persona y tú.</p>
                                        <div>
                                            <label for="motivo_desistimiento" class="block text-sm font-medium text-gray-700 mb-1.5">Motivo</label>
                                            <select id="motivo_desistimiento" name="motivo" required x-model="motivo" class="{{ $input }} border-gray-300">
                                                <option value="">Selecciona…</option>
                                                @foreach (\App\Models\Desistimiento::MOTIVOS as $motivo)
                                                    <option value="{{ $motivo }}" @selected(old('motivo') === $motivo)>{{ $motivo }}</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$erroresDesistimiento->get('motivo')" class="mt-1" />
                                        </div>
                                        <div>
                                            <label for="observaciones_desistimiento" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                Observaciones <span class="text-gray-500 font-normal" x-show="motivo !== @js(\App\Models\Desistimiento::OTRO)">(opcional)</span>
                                            </label>
                                            <textarea id="observaciones_desistimiento" name="observaciones" rows="3" maxlength="{{ \App\Models\Desistimiento::OBSERVACIONES_MAX }}" :required="motivo === @js(\App\Models\Desistimiento::OTRO)" class="{{ $input }} border-gray-300 resize-y">{{ old('observaciones') }}</textarea>
                                            <x-input-error :messages="$erroresDesistimiento->get('observaciones')" class="mt-1" />
                                        </div>
                                        <label class="flex items-start gap-2 text-sm text-gray-800">
                                            <input type="checkbox" name="confirmacion" value="1" required class="mt-0.5 rounded border-gray-300 text-[#007F2F] focus:ring-[#007F2F]">
                                            <span>La persona manifestó de forma libre su decisión de desistir.</span>
                                        </label>
                                        <x-input-error :messages="$erroresDesistimiento->get('confirmacion')" />
                                        <button type="submit" class="px-4 py-2 bg-white border border-rose-200 text-rose-700 hover:bg-rose-50 text-sm font-bold rounded-xl transition">Registrar desistimiento y cerrar el caso</button>
                                    </form>
                                </details>
                            @endif
                        </section>

                        <div class="h-px bg-gray-100"></div>
                    @endif

                    <!-- Remisiones (HU-007) -->
                    <section id="remisiones" aria-labelledby="remisiones-titulo">
                        <h2 id="remisiones-titulo" class="text-base font-bold text-gray-900 mb-3">Remisiones</h2>

                        @if ($erroresRemision->has('consentimiento'))
                            <p class="text-sm text-rose-700 mb-3" role="alert">{{ $erroresRemision->first('consentimiento') }}</p>
                        @endif

                        @if ($puedeAgendar && $sinAtencion)
                            <p class="text-xs text-amber-700 mb-3">{{ $sinAtencion }} Por eso no se puede remitir el caso.</p>
                        @elseif ($puedeAgendar)
                            @php
                                $destinoInicial = old('destino_remision', Actuacion::DESTINO_INTERNA);
                                $avisoCambios = collect([
                                    $citasPorCancelar->count() ? $citasPorCancelar->count().' cita(s) próxima(s) se cancelarán' : null,
                                    $citasSinAsistencia->count() ? $citasSinAsistencia->count().' cita(s) pasada(s) sin asistencia quedarán como "No asistió"' : null,
                                    $tareasPorCancelar->count() ? $tareasPorCancelar->count().' tarea(s) pendiente(s) se cancelarán' : null,
                                ])->filter()->implode('; ');
                                $confirmacion = 'El caso dejará de estar a tu cargo y quedará cerrado y remitido para ti.'.($avisoCambios ? ' Además: '.$avisoCambios.'.' : '').' ¿Deseas continuar?';
                            @endphp
                            <details class="mb-4 border border-gray-100 rounded-xl" @if ($erroresRemision->any()) open @endif>
                                <summary class="px-4 py-2.5 cursor-pointer text-sm font-bold text-[#007F2F] select-none">→ Remitir caso</summary>
                                <form method="POST" action="{{ route('remisiones.store', $caso) }}" class="px-4 pb-4 pt-1 space-y-4"
                                      x-data="{ destino: @js($destinoInicial), entidad: @js(old('entidad_destino', '')), categoria: @js(old('categoria', '')), enviando: false }"
                                      @submit="if (! confirm(@js($confirmacion))) { $event.preventDefault() } else { enviando = true }">
                                    @csrf

                                    <fieldset>
                                        <legend class="block text-sm font-medium text-gray-700 mb-1.5">¿A quién remites el caso?</legend>
                                        <div class="flex flex-wrap gap-4 text-sm">
                                            <label class="flex items-center gap-2">
                                                <input type="radio" name="destino_remision" value="{{ Actuacion::DESTINO_INTERNA }}" x-model="destino"
                                                       @checked($destinoInicial === Actuacion::DESTINO_INTERNA) class="text-[#007F2F] focus:ring-[#007F2F]">
                                                Otro profesional de Bienestar
                                            </label>
                                            <label class="flex items-center gap-2">
                                                <input type="radio" name="destino_remision" value="{{ Actuacion::DESTINO_EXTERNA }}" x-model="destino"
                                                       @checked($destinoInicial === Actuacion::DESTINO_EXTERNA) class="text-[#007F2F] focus:ring-[#007F2F]">
                                                Entidad externa
                                            </label>
                                        </div>
                                        <x-input-error :messages="$erroresRemision->get('destino_remision')" class="mt-2" />
                                    </fieldset>

                                    <fieldset x-show="destino === @js(Actuacion::DESTINO_INTERNA)" :disabled="destino !== @js(Actuacion::DESTINO_INTERNA)"
                                              @if ($destinoInicial !== Actuacion::DESTINO_INTERNA) style="display: none;" disabled @endif>
                                        @php
                                            // Traslado: sin trámite dentro de la facultad del estudiante; a otra facultad, con autorización.
                                            [$mismaFacultad, $otraFacultad] = $idsMismaFacultad === null
                                                ? [$profesionalesDestino, collect()]
                                                : $profesionalesDestino->partition(fn ($p) => in_array($p->id_usuario, $idsMismaFacultad, true));
                                        @endphp
                                        <div x-data="{ destinoProfesional: @js(old('id_profesional_destino', '')), otros: @js($otraFacultad->pluck('id_usuario')->values()) }" class="space-y-3">
                                            <div>
                                                <label for="id_profesional_destino" class="block text-sm font-medium text-gray-700 mb-1.5">Profesional que recibe el caso</label>
                                                <select id="id_profesional_destino" name="id_profesional_destino" required x-model="destinoProfesional"
                                                        @if ($erroresRemision->has('id_profesional_destino')) aria-invalid="true" aria-describedby="id_profesional_destino-error" @endif
                                                        @class([$input, 'border-red-500' => $erroresRemision->has('id_profesional_destino'), 'border-gray-300' => ! $erroresRemision->has('id_profesional_destino')])>
                                                    <option value="">Selecciona un profesional…</option>
                                                    @foreach ([[$mismaFacultad, $idsMismaFacultad === null ? null : 'De la facultad del estudiante'], [$otraFacultad, 'De otra facultad (requiere autorización)']] as [$grupo, $etiquetaGrupo])
                                                        @continue($grupo->isEmpty())
                                                        @if ($etiquetaGrupo) <optgroup label="{{ $etiquetaGrupo }}"> @endif
                                                        @foreach ($grupo as $profesional)
                                                            <option value="{{ $profesional->id_usuario }}" @selected(old('id_profesional_destino') === $profesional->id_usuario)>
                                                                {{ $profesional->persona?->nombre_completo ?? $profesional->codigo_unico }}
                                                            </option>
                                                        @endforeach
                                                        @if ($etiquetaGrupo) </optgroup> @endif
                                                    @endforeach
                                                </select>
                                                @if ($profesionalesDestino->isEmpty())
                                                    <p class="mt-1 text-xs text-gray-500">No hay otros profesionales activos para recibir el caso.</p>
                                                @elseif ($idsMismaFacultad !== null && $mismaFacultad->isEmpty())
                                                    <p class="mt-1 text-xs text-amber-700">Ningún otro profesional atiende la facultad del estudiante: el traslado a otra facultad requiere autorización.</p>
                                                @endif
                                                <p class="mt-1 text-xs text-gray-500">El caso pasará a su cargo con todo su historial; tú lo verás cerrado.</p>
                                                <x-input-error id="id_profesional_destino-error" :messages="$erroresRemision->get('id_profesional_destino')" class="mt-2" />
                                            </div>

                                            <div x-show="otros.includes(destinoProfesional)" @unless (in_array(old('id_profesional_destino'), $otraFacultad->pluck('id_usuario')->all(), true)) style="display: none;" @endunless>
                                                <label for="autorizacion_traslado" class="block text-sm font-medium text-gray-700 mb-1.5">¿Quién autorizó el traslado a otra facultad?</label>
                                                <input type="text" id="autorizacion_traslado" name="autorizacion_traslado" value="{{ old('autorizacion_traslado') }}"
                                                       maxlength="{{ \App\Http\Requests\StoreRemisionRequest::AUTORIZACION_MAX }}" :required="otros.includes(destinoProfesional)"
                                                       placeholder="Ej. Coordinación de Bienestar, 20/10/2026"
                                                       @class([$input, 'border-red-500' => $erroresRemision->has('autorizacion_traslado'), 'border-gray-300' => ! $erroresRemision->has('autorizacion_traslado')])>
                                                <x-input-error :messages="$erroresRemision->get('autorizacion_traslado')" class="mt-2" />
                                            </div>
                                        </div>
                                    </fieldset>

                                    <fieldset x-show="destino === @js(Actuacion::DESTINO_EXTERNA)" :disabled="destino !== @js(Actuacion::DESTINO_EXTERNA)" class="space-y-3"
                                              @if ($destinoInicial !== Actuacion::DESTINO_EXTERNA) style="display: none;" disabled @endif>
                                        <div>
                                            <label for="entidad_destino" class="block text-sm font-medium text-gray-700 mb-1.5">Entidad que recibe el caso</label>
                                            <select id="entidad_destino" name="entidad_destino" required x-model="entidad"
                                                    @if ($erroresRemision->has('entidad_destino')) aria-invalid="true" aria-describedby="entidad_destino-error" @endif
                                                    @class([$input, 'border-red-500' => $erroresRemision->has('entidad_destino'), 'border-gray-300' => ! $erroresRemision->has('entidad_destino')])>
                                                <option value="">Selecciona una entidad…</option>
                                                @foreach (Actuacion::ENTIDADES as $entidad)
                                                    <option value="{{ $entidad }}" @selected(old('entidad_destino') === $entidad)>{{ $entidad }}</option>
                                                @endforeach
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500">La atención sale de Bienestar: el caso se cerrará.</p>
                                            <x-input-error id="entidad_destino-error" :messages="$erroresRemision->get('entidad_destino')" class="mt-2" />
                                        </div>
                                        <div x-show="entidad === @js(Actuacion::ENTIDAD_OTRA)" @if (old('entidad_destino') !== Actuacion::ENTIDAD_OTRA) style="display: none;" @endif>
                                            <label for="entidad_otra" class="block text-sm font-medium text-gray-700 mb-1.5">Nombre de la entidad</label>
                                            <input type="text" id="entidad_otra" name="entidad_otra" maxlength="{{ Actuacion::ENTIDAD_MAX }}"
                                                   value="{{ old('entidad_otra') }}" :required="entidad === @js(Actuacion::ENTIDAD_OTRA)"
                                                   @if ($erroresRemision->has('entidad_otra')) aria-invalid="true" aria-describedby="entidad_otra-error" @endif
                                                   @class([$input, 'border-red-500' => $erroresRemision->has('entidad_otra'), 'border-gray-300' => ! $erroresRemision->has('entidad_otra')])>
                                            <x-input-error id="entidad_otra-error" :messages="$erroresRemision->get('entidad_otra')" class="mt-2" />
                                        </div>

                                        <!-- Formato GBU-GPGR-F-016: categoría, datos del estudiante y acompañante (RQ-2) -->
                                        <fieldset>
                                            <legend class="block text-sm font-medium text-gray-700 mb-1.5">Categoría</legend>
                                            <div class="flex flex-col sm:flex-row gap-2 sm:gap-6 text-sm">
                                                @foreach ([RemisionExterna::URGENTE => 'el estudiante va a urgencias', RemisionExterna::PRIORITARIO => 'el estudiante pide cita en su EPS'] as $categoria => $ayuda)
                                                    <label class="flex items-start gap-2">
                                                        <input type="radio" name="categoria" value="{{ $categoria }}" x-model="categoria" required
                                                               @checked(old('categoria') === $categoria) class="mt-0.5 text-[#007F2F] focus:ring-[#007F2F]">
                                                        <span><strong>{{ $categoria }}</strong> <span class="text-xs text-gray-500">· {{ $ayuda }}</span></span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <x-input-error :messages="$erroresRemision->get('categoria')" class="mt-2" />
                                        </fieldset>

                                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 text-xs text-gray-700">
                                            <p class="font-bold text-gray-800 mb-1">Datos que van en el formato (tomados del registro)</p>
                                            @if ($persona?->datosRemisionCompletos())
                                                <p>
                                                    {{ $persona->nombre_completo }} · {{ $persona->tipo_documento }} {{ $persona->numero_documento }} · Sexo {{ $persona->sexo }}
                                                    · Nació el {{ $persona->fecha_nacimiento->format('d-m-y') }} ({{ $persona->edadEn($fechaAtencion) }} años)
                                                </p>
                                            @else
                                                <p class="text-amber-800">
                                                    Faltan el tipo de documento, el sexo o la fecha de nacimiento del estudiante.
                                                    <a href="#estudiante" class="font-bold underline">Complétalos aquí</a> antes de remitir.
                                                </p>
                                            @endif
                                            <p class="mt-1">
                                                Atención: {{ $fechaAtencion->format('d-m-y') }} a las {{ $fechaAtencion->format('g:i a') }}
                                                {{ $ultimaAtendida ? '(última cita a la que asistió)' : '(fecha de la remisión)' }}
                                                · Psicólogo(a): {{ auth()->user()->persona?->nombre_completo }}
                                            </p>
                                            <x-input-error :messages="$erroresRemision->get('datos_estudiante')" class="mt-1" />
                                        </div>

                                        <fieldset x-show="categoria === @js(RemisionExterna::URGENTE)" :disabled="categoria !== @js(RemisionExterna::URGENTE)"
                                                  class="border border-rose-100 bg-rose-50/40 rounded-xl p-3 space-y-3"
                                                  @if (old('categoria') !== RemisionExterna::URGENTE) style="display: none;" disabled @endif>
                                            <legend class="px-1 text-sm font-bold text-rose-800">Datos del acompañante (remisión por urgencias)</legend>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                @foreach ([
                                                    'acompanante_nombre' => ['Nombre de la persona responsable del estudiante', 150],
                                                    'acompanante_parentesco' => ['Parentesco', 50],
                                                    'acompanante_numero_documento' => ['Número de identificación', 20],
                                                    'acompanante_telefono' => ['Número de contacto', 20],
                                                ] as $campo => [$etiqueta, $maximo])
                                                    <div @class(['sm:col-span-2' => $campo === 'acompanante_nombre'])>
                                                        <label for="{{ $campo }}" class="block text-xs font-medium text-gray-700 mb-1">{{ $etiqueta }}</label>
                                                        <input type="{{ $campo === 'acompanante_telefono' ? 'tel' : 'text' }}" id="{{ $campo }}" name="{{ $campo }}" maxlength="{{ $maximo }}" required
                                                               value="{{ old($campo) }}"
                                                               @if ($erroresRemision->has($campo)) aria-invalid="true" aria-describedby="{{ $campo }}-error" @endif
                                                               @class([$input, 'border-red-500' => $erroresRemision->has($campo), 'border-gray-300' => ! $erroresRemision->has($campo)])>
                                                        <x-input-error id="{{ $campo }}-error" :messages="$erroresRemision->get($campo)" class="mt-1" />
                                                    </div>
                                                @endforeach
                                                <div>
                                                    <label for="acompanante_tipo_documento" class="block text-xs font-medium text-gray-700 mb-1">Tipo de identificación</label>
                                                    <select id="acompanante_tipo_documento" name="acompanante_tipo_documento" required
                                                            @class([$input, 'border-red-500' => $erroresRemision->has('acompanante_tipo_documento'), 'border-gray-300' => ! $erroresRemision->has('acompanante_tipo_documento')])>
                                                        <option value="">Selecciona…</option>
                                                        @foreach (RemisionExterna::TIPOS_DOCUMENTO_ACOMPANANTE as $tipo)
                                                            <option value="{{ $tipo }}" @selected(old('acompanante_tipo_documento') === $tipo)>{{ $tipo === 'PA' ? 'Pasaporte' : $tipo }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$erroresRemision->get('acompanante_tipo_documento')" class="mt-1" />
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-rose-800">La firma del acompañante se hace a mano en el documento impreso.</p>
                                        </fieldset>
                                    </fieldset>

                                    <div>
                                        <label for="motivo_remision" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            <span x-show="destino === @js(Actuacion::DESTINO_EXTERNA)">Justificación de la remisión <span class="text-xs text-gray-500 font-normal">· va en el formato de remisión</span></span>
                                            <span x-show="destino !== @js(Actuacion::DESTINO_EXTERNA)">Nota para quien recibe el caso <span class="text-xs text-gray-500 font-normal">· opcional, no hace falta justificar el traslado</span></span>
                                        </label>
                                        <textarea id="motivo_remision" name="motivo" rows="5" :required="destino === @js(Actuacion::DESTINO_EXTERNA)" maxlength="{{ Actuacion::MOTIVO_REMISION_MAX }}"
                                                  placeholder="Lo que debe tener en cuenta quien recibe el caso…"
                                                  @if ($erroresRemision->has('motivo')) aria-invalid="true" aria-describedby="motivo_remision-error" @endif
                                                  @class([$input, 'resize-y', 'border-red-500' => $erroresRemision->has('motivo'), 'border-gray-300' => ! $erroresRemision->has('motivo')])>{{ old('motivo') }}</textarea>
                                        <x-input-error id="motivo_remision-error" :messages="$erroresRemision->get('motivo')" class="mt-2" />
                                    </div>

                                    @if ($citasPendientes->isNotEmpty() || $tareasPorCancelar->isNotEmpty())
                                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-900 space-y-2">
                                            <p class="font-bold">Al remitir, lo pendiente del caso queda registrado así en el historial:</p>
                                            <ul class="list-disc ps-5 space-y-1">
                                                @foreach ($citasPorCancelar as $cita)
                                                    <li>Cita del {{ $cita->fecha_hora->format('Y-m-d') }} a las {{ $cita->fecha_hora->format('g:i a') }}: se cancela.</li>
                                                @endforeach
                                                @foreach ($citasSinAsistencia as $cita)
                                                    <li>Cita del {{ $cita->fecha_hora->format('Y-m-d') }} a las {{ $cita->fecha_hora->format('g:i a') }}: sin asistencia registrada, queda como "No asistió".</li>
                                                @endforeach
                                                @foreach ($tareasPorCancelar as $tarea)
                                                    <li>Tarea "{{ $tarea->titulo }}": se cancela (no completada).</li>
                                                @endforeach
                                            </ul>
                                            @if ($citasPendientes->isNotEmpty())
                                                <label class="flex items-start gap-2 pt-1 text-sm text-amber-900 font-medium">
                                                    <input type="checkbox" name="confirmar_citas" value="1" required @checked(old('confirmar_citas'))
                                                           @if ($erroresRemision->has('confirmar_citas')) aria-invalid="true" aria-describedby="confirmar_citas-error" @endif
                                                           class="mt-0.5 rounded border-amber-400 text-[#007F2F] focus:ring-[#007F2F]">
                                                    Confirmo que se cancelen las citas pendientes para remitir el caso.
                                                </label>
                                                <x-input-error id="confirmar_citas-error" :messages="$erroresRemision->get('confirmar_citas')" class="mt-1" />
                                            @endif
                                        </div>
                                    @endif

                                    <div class="flex justify-end">
                                        <button type="submit" :disabled="enviando"
                                                class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] disabled:opacity-60 text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                            <span x-show="!enviando">Registrar remisión</span>
                                            <span x-show="enviando" style="display: none;">Remitiendo…</span>
                                        </button>
                                    </div>
                                </form>
                            </details>
                        @endif

                        @if ($caso->remisiones->isEmpty())
                            <div class="py-8 px-5 text-center text-sm text-gray-500 bg-gray-50 rounded-xl">
                                Este expediente no tiene remisiones registradas.
                            </div>
                        @else
                            <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl">
                                @foreach ($caso->remisiones as $remision)
                                    <li class="p-4 text-sm">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <p class="font-bold text-gray-900 break-words">
                                                {{ $remision->profesional?->persona?->nombre_completo ?? 'Profesional' }}
                                                <span class="text-gray-500 font-normal" aria-hidden="true">→</span>
                                                <span class="sr-only">remitió a</span>
                                                {{ $remision->etiquetaDestino() }}
                                            </p>
                                            <span @class(['px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap', 'bg-sky-50 text-sky-700' => $remision->esRemisionInterna(), 'bg-violet-50 text-violet-700' => ! $remision->esRemisionInterna()])>
                                                {{ $remision->esTrasladoAdministrativo() ? 'Traslado administrativo' : ($remision->esRemisionInterna() ? 'Profesional interno' : 'Entidad externa') }}
                                            </span>
                                        </div>
                                        @if ($remision->remisionExterna)
                                            <p class="mt-1 text-xs">
                                                <span @class(['px-2 py-0.5 rounded-full text-[11px] font-bold', 'bg-rose-100 text-rose-700' => $remision->remisionExterna->esUrgente(), 'bg-amber-50 text-amber-700' => ! $remision->remisionExterna->esUrgente()])>{{ $remision->remisionExterna->categoria }}</span>
                                                @if ($remision->remisionExterna->esUrgente())
                                                    <span class="text-gray-600">· Acompañante: {{ $remision->remisionExterna->acompanante_nombre }} ({{ $remision->remisionExterna->acompanante_parentesco }})</span>
                                                @endif
                                            </p>
                                            @if (in_array(auth()->id(), [$remision->id_profesional, $caso->id_profesional], true))
                                                <p class="mt-2 flex flex-wrap gap-2">
                                                    <a href="{{ route('remisiones.documento.pdf', $remision) }}"
                                                       class="px-3 py-1.5 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                                        Descargar PDF
                                                    </a>
                                                    <a href="{{ route('remisiones.documento.word', $remision) }}"
                                                       class="px-3 py-1.5 bg-white border border-[#007F2F] text-[#007F2F] hover:bg-emerald-50 text-xs font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                                        Descargar Word
                                                    </a>
                                                    <span class="text-[11px] text-gray-500 self-center">Formato {{ $remision->remisionExterna->formato_codigo }} v{{ $remision->remisionExterna->formato_version }} · para imprimir y firmar</span>
                                                </p>
                                            @endif
                                        @endif
                                        @if ($remision->descripcion)
                                            <p class="mt-1 text-xs text-gray-600 leading-relaxed whitespace-pre-line break-words">Motivo: {{ $remision->descripcion }}</p>
                                        @endif
                                        @if ($remision->autorizacion_traslado)
                                            <p class="mt-1 text-xs text-gray-600 break-words">{{ $remision->etiquetaAutorizacion() }}: {{ $remision->autorizacion_traslado }}</p>
                                        @endif
                                        <p class="mt-2 text-[11px] text-gray-500 font-medium">Registrada: {{ $remision->fecha_hora->format('Y-m-d H:i') }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <!-- Acciones -->
                    <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-2">
                        <a href="{{ auth()->user()->urlCasos() }}"
                           class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl text-sm transition-colors text-center focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                            Volver a Salud Mental
                        </a>
                        <a href="{{ route('citas.agenda') }}"
                           class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl text-sm transition-colors text-center focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                            Mi agenda
                        </a>
                        <a href="{{ route('casos.create') }}"
                           class="px-5 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition-colors shadow-sm text-center focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                            + Abrir otro caso
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

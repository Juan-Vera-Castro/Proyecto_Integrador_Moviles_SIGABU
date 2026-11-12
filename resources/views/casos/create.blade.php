@php
    use App\Models\Actuacion;
    use App\Models\Caso;
    use App\Models\PropuestaCita;

    $campos = ['nombre_completo', 'numero_documento', 'correo_inst', 'tipo_servicio', 'nivel_riesgo', 'motivo', 'acepta_consentimiento'];
    $servicioActual = old('tipo_servicio', $servicioInicial);
    $primerError = collect($campos)->first(fn ($campo) => $errors->has($campo));
    $foco = $primerError ?? ($registroManual ? 'nombre_completo' : ($paciente ? null : 'q'));
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';

    // Resumen del paciente elegido.
    $yo = Auth::user()->id_usuario;
    $activos = $casosPaciente->filter(fn (Caso $caso) => $caso->estaActivo());
    $pendiente = $casosPaciente->firstWhere('estado_caso', Caso::ESTADO_PENDIENTE);
    $abrirCaso = $paciente && $activos->isEmpty() && ! $pendiente;
    $academicos = $paciente?->datosAcademicos();
    $errPropuesta = $errors->getBag('propuestas');
    $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();
    $volverA = fn (array $extra = []) => route('casos.create', array_filter(['servicio' => $servicioInicial] + $extra));
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-[#007F2F] tracking-tight">Buscar paciente</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Consulta su proceso, envíale una solicitud de cita o abre un nuevo caso de atención · Bienestar Universitario</p>
            </div>

            <!-- Botón X -->
            <a href="{{ auth()->user()->urlCasos() }}"
               class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
               title="Volver a Salud Mental" aria-label="Cerrar y volver a Salud Mental">
                ✕
            </a>
        </div>

        <!-- 2. CONTENIDO PRINCIPAL -->
        <div class="flex-1 p-4 sm:p-8 flex flex-col items-center gap-6">

            @if (session('status'))
                <div class="w-full max-w-3xl bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-sm text-[#1F5134] font-medium" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Buscador de pacientes -->
            <section class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100 w-full max-w-3xl space-y-4" aria-labelledby="titulo-busqueda">
                <h2 id="titulo-busqueda" class="text-base sm:text-lg font-bold text-gray-900">Paciente</h2>
                <form method="GET" action="{{ route('casos.create') }}" class="flex flex-col sm:flex-row gap-3" role="search">
                    <input type="hidden" name="servicio" value="{{ $servicioInicial }}">
                    <div class="flex-1">
                        <label for="q" class="sr-only">Buscar paciente</label>
                        <input id="q" name="q" type="search" value="{{ $busqueda }}" minlength="3" autocomplete="off"
                               placeholder="Nombre, documento, correo o código de expediente"
                               @if ($foco === 'q') autofocus @endif
                               class="{{ $input }} border-gray-300">
                    </div>
                    <button type="submit" class="px-5 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        🔍 Buscar
                    </button>
                </form>

                @if ($busqueda !== '' && mb_strlen($busqueda) < 3)
                    <p class="text-xs text-gray-500">Escribe al menos 3 caracteres.</p>
                @elseif ($busqueda !== '')
                    @if ($resultados->isEmpty())
                        <p class="text-sm text-gray-600">No hay estudiantes registrados que coincidan con «{{ $busqueda }}».</p>
                    @else
                        <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden" aria-label="Resultados de la búsqueda">
                            @foreach ($resultados as $resultado)
                                <li>
                                    <a href="{{ $volverA(['q' => $busqueda, 'paciente' => $resultado->id_persona]) }}"
                                       @class(['flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm hover:bg-green-50/60 transition', 'bg-green-50' => $paciente?->id_persona === $resultado->id_persona])>
                                        <span class="font-semibold text-gray-900">{{ $resultado->nombre_completo }}</span>
                                        <span class="text-xs text-gray-500">{{ $resultado->numero_documento }} · {{ $resultado->correo_inst }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif

                @unless ($registroManual)
                    <p class="text-xs text-gray-500">
                        ¿El paciente no tiene cuenta en SIGABU?
                        <a href="{{ $volverA(['nuevo' => 1]) }}" class="font-bold text-[#007F2F] hover:underline">Registrar sus datos y abrir el caso</a>
                    </p>
                @endunless
            </section>

            <!-- Resumen del paciente elegido -->
            @if ($paciente)
                <section class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100 w-full max-w-3xl space-y-5" aria-labelledby="titulo-paciente">
                    <div>
                        <h2 id="titulo-paciente" class="text-lg font-bold text-gray-900">{{ $paciente->nombre_completo }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $paciente->numero_documento }} · {{ $paciente->correo_inst }}
                            @if ($academicos['programa'] ?? null) · {{ $academicos['programa'] }} @endif
                            @if ($academicos['facultad'] ?? null) · {{ $academicos['facultad']->nombre }} @endif
                        </p>
                    </div>

                    @foreach ($activos as $caso)
                        @php
                            $esMio = $caso->id_profesional === $yo;
                            $proxima = $caso->proximaCita();
                            $respuesta = $respuestas[$caso->id_caso] ?? null;
                        @endphp
                        <div class="border border-gray-200 rounded-xl p-4 space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-bold text-gray-900">{{ $caso->codigo_expediente }} · {{ $caso->solicitud?->tipo_servicio }}</p>
                                <span class="px-2.5 py-0.5 bg-green-50 text-[#007F2F] text-xs font-bold rounded-full">{{ $caso->estado_caso }}</span>
                            </div>

                            @if ($esMio)
                                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="bg-gray-50 rounded-xl p-3">
                                        <dt class="text-xs text-gray-500 font-medium">Citas a las que asistió</dt>
                                        <dd class="text-2xl font-bold text-gray-900 tabular-nums">{{ $caso->sesionesRealizadas() }}</dd>
                                        <dd class="text-[11px] text-gray-400">de {{ $caso->citas->reject(fn ($c) => $c->estaCancelada())->count() }} agendadas</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl p-3">
                                        <dt class="text-xs text-gray-500 font-medium">Inicio del proceso</dt>
                                        <dd class="text-lg font-bold text-gray-900">{{ $caso->fecha_apertura?->format('Y-m-d') ?? '—' }}</dd>
                                        <dd class="text-[11px] text-gray-400">{{ $caso->fecha_apertura?->locale('es')->diffForHumans() }}</dd>
                                    </div>
                                    <div class="bg-gray-50 rounded-xl p-3">
                                        <dt class="text-xs text-gray-500 font-medium">Próxima cita</dt>
                                        <dd class="text-lg font-bold {{ $proxima ? 'text-gray-900' : 'text-amber-700' }}">
                                            {{ $proxima ? $proxima->fecha_hora->format('Y-m-d') : 'Pendiente' }}
                                        </dd>
                                        <dd class="text-[11px] text-gray-400">{{ $proxima?->fecha_hora->format('g:i a') }}</dd>
                                    </div>
                                </dl>

                                @if ($proxima)
                                    <p class="text-xs text-gray-600">Ya tiene su próxima cita agendada. Podrás enviarle otra solicitud cuando esa cita pase.</p>
                                @elseif ($caso->propuestasVigentes->isNotEmpty())
                                    <div class="bg-sky-50 border border-sky-200 rounded-xl p-3 text-xs text-sky-900 space-y-2">
                                        <p class="font-bold">Solicitud de cita enviada · esperando que el paciente la acepte o la rechace</p>
                                        <p>{{ $caso->propuestasVigentes->map->etiqueta()->implode(' · ') }}</p>
                                        <form method="POST" action="{{ route('propuestas.retirar', $caso) }}">
                                            @csrf
                                            <button type="submit" class="font-bold text-gray-500 hover:text-rose-600">Retirar la solicitud</button>
                                        </form>
                                    </div>
                                @else
                                    @if ($respuesta?->estado === PropuestaCita::RECHAZADA)
                                        <p class="text-xs text-rose-800 bg-rose-50 border border-rose-200 rounded-xl p-3">
                                            El paciente rechazó la solicitud del {{ $respuesta->etiqueta() }} (respondió el {{ $respuesta->fecha_respuesta?->format('Y-m-d') }}). Envíale otra fecha.
                                        </p>
                                    @endif
                                    <form method="POST" action="{{ route('propuestas.store', $caso) }}" class="flex flex-wrap items-end gap-3">
                                        @csrf
                                        <div>
                                            <label for="cita-fecha-{{ $caso->id_caso }}" class="block text-xs font-medium text-gray-600 mb-1">Fecha</label>
                                            <input id="cita-fecha-{{ $caso->id_caso }}" type="date" name="opciones[0][fecha]" min="{{ $hoy }}" required value="{{ old('opciones.0.fecha') }}"
                                                   class="rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#007F2F] focus:ring-[#007F2F]">
                                        </div>
                                        <div>
                                            <label for="cita-hora-{{ $caso->id_caso }}" class="block text-xs font-medium text-gray-600 mb-1">Hora</label>
                                            <select id="cita-hora-{{ $caso->id_caso }}" name="opciones[0][hora]" required
                                                    class="rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#007F2F] focus:ring-[#007F2F]">
                                                <option value="">Elige</option>
                                                @foreach (Actuacion::HORARIOS as $hora)
                                                    <option value="{{ $hora }}" @selected(old('opciones.0.hora') === $hora)>{{ Actuacion::etiquetaHora($hora) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="px-4 py-2.5 bg-sky-700 hover:bg-sky-800 text-white text-sm font-bold rounded-xl shadow-sm transition focus:outline-none focus:ring-2 focus:ring-sky-700 focus:ring-offset-2">
                                            📨 Enviar solicitud de cita
                                        </button>
                                    </form>
                                    @if ($errPropuesta->any())
                                        <p class="text-xs text-red-600 font-medium" role="alert">{{ $errPropuesta->first('opciones.0') ?: $errPropuesta->first() }}</p>
                                    @endif
                                    <p class="text-[11px] text-gray-400">El paciente la verá en «Mis citas» y podrá aceptarla o rechazarla. Si la acepta, queda en tu agenda.</p>
                                @endif

                                <a href="{{ route('casos.show', $caso) }}" class="inline-block text-xs font-bold text-[#007F2F] hover:underline">Ver el caso completo →</a>
                            @else
                                <p class="text-xs text-gray-600">
                                    Está en proceso desde el {{ $caso->fecha_apertura?->format('Y-m-d') }} con {{ $caso->profesional?->persona?->nombre_completo ?? 'otro profesional' }}.
                                    Solo su profesional puede enviarle solicitudes de cita.
                                </p>
                            @endif
                        </div>
                    @endforeach

                    @if ($pendiente)
                        <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">
                            Tiene una solicitud de atención ({{ $pendiente->codigo_expediente }}) esperando que se le asigne un profesional.
                        </p>
                    @endif

                    @if ($abrirCaso)
                        <p class="text-sm text-gray-700">
                            No tiene un proceso activo{{ $casosPaciente->isNotEmpty() ? ' (tuvo '.$casosPaciente->count().' '.($casosPaciente->count() === 1 ? 'caso cerrado' : 'casos cerrados').')' : '' }}.
                            Abre su caso para poder enviarle solicitudes de cita.
                        </p>
                    @endif
                </section>
            @endif

            @if ($registroManual || $abrirCaso)
            <form method="POST" action="{{ route('casos.store') }}"
                  x-data="{ enviando: false, motivo: @js(old('motivo', '')), servicio: @js($servicioActual) }"
                  @submit="enviando = true"
                  class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100 w-full max-w-3xl space-y-6">
                @csrf

                <!-- Resumen de errores -->
                @if ($errors->any() || session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex gap-3 items-start" role="alert">
                        <span class="text-xl shrink-0" aria-hidden="true">⚠️</span>
                        <div class="text-xs sm:text-sm text-rose-700 font-medium leading-relaxed">
                            @if (session('error'))
                                <p>{{ session('error') }}</p>
                            @endif
                            @if ($errors->any())
                                <p>No se pudo guardar el caso. Corrige {{ $errors->count() === 1 ? 'el siguiente campo' : 'los siguientes campos' }}:</p>
                                <ul class="list-disc ms-5 mt-1 space-y-0.5">
                                    @foreach ($campos as $campo)
                                        @if ($errors->has($campo))
                                            <li><a href="#{{ $campo }}" class="underline hover:text-rose-900">{{ $errors->first($campo) }}</a></li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($paciente)
                    {{-- Los datos del solicitante ya están registrados: se toman de su cuenta. --}}
                    <input type="hidden" name="nombre_completo" value="{{ $paciente->nombre_completo }}">
                    <input type="hidden" name="numero_documento" value="{{ $paciente->numero_documento }}">
                    <input type="hidden" name="correo_inst" value="{{ $paciente->correo_inst }}">
                @else
                <!-- Sección: Datos del solicitante (paciente sin cuenta) -->
                <fieldset class="space-y-4">
                    <legend class="text-base sm:text-lg font-bold text-gray-900 mb-1">Datos del solicitante</legend>
                    <p class="text-xs text-gray-500 -mt-2">Los campos marcados con <span class="text-rose-600 font-bold">*</span> son obligatorios.</p>

                    <div>
                        <label for="nombre_completo" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Nombre completo <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <input id="nombre_completo" name="nombre_completo" type="text" value="{{ old('nombre_completo') }}"
                               required minlength="3" maxlength="150" autocomplete="off"
                               placeholder="Ej. Juan Esteban Morales Ríos"
                               @if ($foco === 'nombre_completo') autofocus @endif
                               @error('nombre_completo') aria-invalid="true" aria-describedby="nombre_completo-error" @enderror
                               @class([$input, 'border-red-500' => $errors->has('nombre_completo'), 'border-gray-300' => ! $errors->has('nombre_completo')])>
                        <x-input-error id="nombre_completo-error" :messages="$errors->get('nombre_completo')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="numero_documento" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Documento de identidad <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            <input id="numero_documento" name="numero_documento" type="text" value="{{ old('numero_documento') }}"
                                   required inputmode="numeric" pattern="[0-9 .]{6,15}" maxlength="15" autocomplete="off"
                                   placeholder="Número de cédula o TI"
                                   title="Solo números, entre 6 y 11 dígitos."
                                   @if ($foco === 'numero_documento') autofocus @endif
                                   aria-describedby="numero_documento-ayuda @error('numero_documento') numero_documento-error @enderror"
                                   @error('numero_documento') aria-invalid="true" @enderror
                                   @class([$input, 'border-red-500' => $errors->has('numero_documento'), 'border-gray-300' => ! $errors->has('numero_documento')])>
                            <p id="numero_documento-ayuda" class="text-xs text-gray-400 mt-1">Solo números, entre 6 y 11 dígitos.</p>
                            <x-input-error id="numero_documento-error" :messages="$errors->get('numero_documento')" class="mt-2" />
                        </div>

                        <div>
                            <label for="correo_inst" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Correo institucional <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            <input id="correo_inst" name="correo_inst" type="email" value="{{ old('correo_inst') }}"
                                   required maxlength="120" autocomplete="off"
                                   placeholder="usuario@uceva.edu.co"
                                   @if ($foco === 'correo_inst') autofocus @endif
                                   @error('correo_inst') aria-invalid="true" aria-describedby="correo_inst-error" @enderror
                                   @class([$input, 'border-red-500' => $errors->has('correo_inst'), 'border-gray-300' => ! $errors->has('correo_inst')])>
                            <x-input-error id="correo_inst-error" :messages="$errors->get('correo_inst')" class="mt-2" />
                        </div>
                    </div>
                </fieldset>
                @endif

                <div class="h-px bg-gray-100"></div>

                <!-- Sección: Información del caso -->
                <fieldset class="space-y-4">
                    <legend class="text-base sm:text-lg font-bold text-gray-900 mb-1">{{ $paciente ? 'Abrir caso de '.$paciente->nombre_completo : 'Información del caso' }}</legend>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="tipo_servicio" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Servicio <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            <select id="tipo_servicio" name="tipo_servicio" required x-model="servicio"
                                    @if ($foco === 'tipo_servicio') autofocus @endif
                                    @error('tipo_servicio') aria-invalid="true" aria-describedby="tipo_servicio-error" @enderror
                                    @class([$input, 'border-red-500' => $errors->has('tipo_servicio'), 'border-gray-300' => ! $errors->has('tipo_servicio')])>
                                @foreach ($servicios as $servicio)
                                    <option value="{{ $servicio }}" @selected($servicioActual === $servicio)>{{ $servicio }}</option>
                                @endforeach
                            </select>
                            <x-input-error id="tipo_servicio-error" :messages="$errors->get('tipo_servicio')" class="mt-2" />
                        </div>

                        <div>
                            <label for="nivel_riesgo" class="block text-sm font-medium text-gray-700 mb-1.5">Nivel de riesgo inicial</label>
                            <select id="nivel_riesgo" name="nivel_riesgo"
                                    @if ($foco === 'nivel_riesgo') autofocus @endif
                                    @error('nivel_riesgo') aria-invalid="true" aria-describedby="nivel_riesgo-error" @enderror
                                    @class([$input, 'border-red-500' => $errors->has('nivel_riesgo'), 'border-gray-300' => ! $errors->has('nivel_riesgo')])>
                                <option value="">Sin definir</option>
                                @foreach ($nivelesRiesgo as $nivel)
                                    <option value="{{ $nivel }}" @selected(old('nivel_riesgo') === $nivel)>{{ $nivel }}</option>
                                @endforeach
                            </select>
                            <x-input-error id="nivel_riesgo-error" :messages="$errors->get('nivel_riesgo')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <label for="motivo" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Motivo de consulta <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <textarea id="motivo" name="motivo" rows="4" required minlength="10" maxlength="{{ $motivoMax }}"
                                  x-model="motivo"
                                  placeholder="Descripción del motivo de atención…"
                                  @if ($foco === 'motivo') autofocus @endif
                                  aria-describedby="motivo-contador @error('motivo') motivo-error @enderror"
                                  @error('motivo') aria-invalid="true" @enderror
                                  @class([$input, 'resize-y', 'border-red-500' => $errors->has('motivo'), 'border-gray-300' => ! $errors->has('motivo')])>{{ old('motivo') }}</textarea>
                        <p id="motivo-contador" class="text-xs text-gray-400 mt-1 text-right">
                            <span x-text="motivo.length">{{ mb_strlen(old('motivo', '')) }}</span>/{{ $motivoMax }} caracteres
                        </p>
                        <x-input-error id="motivo-error" :messages="$errors->get('motivo')" class="mt-2" />
                    </div>
                </fieldset>

                <!-- Consentimiento informado del servicio (HU-009) -->
                @foreach ($consentimientos as $servicio => $version)
                    @php $visible = $servicioActual === $servicio; @endphp
                    <fieldset x-show="servicio === @js($servicio)" :disabled="servicio !== @js($servicio)"
                              @unless ($visible) style="display: none;" disabled @endunless
                              class="space-y-3">
                        <div class="h-px bg-gray-100 mb-6"></div>
                        <legend class="sr-only">Consentimiento informado de {{ $servicio }}</legend>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="text-base sm:text-lg font-bold text-gray-900">Consentimiento informado</h2>
                            <span class="text-xs text-gray-400 font-medium">{{ $version->modelo->nombre }} · versión {{ $version->numero_version }}</span>
                        </div>
                        <input type="hidden" name="id_version" value="{{ $version->id_version }}">
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs sm:text-sm text-gray-700 leading-relaxed whitespace-pre-line break-words">{{ $version->texto }}</div>
                        <label class="flex items-start gap-2.5 text-sm text-gray-800">
                            <input type="checkbox" id="{{ $visible ? 'acepta_consentimiento' : 'acepta_consentimiento-'.$loop->index }}" name="acepta_consentimiento" value="1" @checked($visible && old('acepta_consentimiento'))
                                   @if ($visible && $errors->has('acepta_consentimiento')) aria-invalid="true" aria-describedby="acepta_consentimiento-error" @endif
                                   class="mt-0.5 rounded border-gray-300 text-[#007F2F] focus:ring-[#007F2F]">
                            <span>El solicitante leyó y aceptó este consentimiento informado. <span class="text-rose-600" aria-hidden="true">*</span></span>
                        </label>
                        @if ($visible)
                            <x-input-error id="acepta_consentimiento-error" :messages="$errors->get('acepta_consentimiento')" class="mt-2" />
                        @endif
                    </fieldset>
                @endforeach

                <!-- Nota informativa -->
                <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 flex gap-3 items-center">
                    <span class="text-xl shrink-0" aria-hidden="true">📁</span>
                    <p class="text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">
                        Al guardar, el caso queda en estado <strong>Abierto</strong>, asignado a ti, y se genera su código de expediente.
                    </p>
                </div>

                <!-- Acciones -->
                <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-2">
                    <a href="{{ auth()->user()->urlCasos() }}"
                       class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl text-sm transition-colors text-center focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        Cancelar
                    </a>
                    <button type="submit"
                            :disabled="enviando"
                            class="px-5 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition-colors shadow-sm disabled:opacity-70 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        <span x-show="!enviando">Guardar caso</span>
                        <span x-show="enviando" style="display: none;">Guardando…</span>
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>
</x-app-layout>

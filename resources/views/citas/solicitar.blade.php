@php
    use App\Http\Requests\StoreSolicitudCitaRequest;

    $tarjetas = [
        'Salud Mental' => ['icono' => '🧠', 'titulo' => 'Atención Psicológica', 'texto' => 'Orientación emocional, ansiedad, estrés académico, duelo, relaciones.'],
        'Inclusión' => ['icono' => '♿', 'titulo' => 'Inclusión Universitaria', 'texto' => 'Caracterización de discapacidad, ajustes razonables, apoyos académicos.'],
    ];
    $franjasInfo = [
        'Mañana' => ['icono' => '🌅', 'texto' => 'Antes del mediodía'],
        'Tarde' => ['icono' => '☀️', 'texto' => 'Después del mediodía'],
        'Noche' => ['icono' => '🌙', 'texto' => 'Al final de la jornada'],
        'Todas' => ['icono' => '🕒', 'texto' => 'Cualquier franja me sirve'],
    ];
    $pasos = [1 => 'Servicio', 2 => 'Tus datos y horario', 3 => 'Consentimiento', 4 => 'Confirmación'];
    $jornadaInicial = old('jornada', $jornadaRegistrada ?? '');

    // Con errores, el formulario vuelve al primer paso que tiene uno.
    $pasoInicial = collect(StoreSolicitudCitaRequest::PASOS)
        ->filter(fn ($paso, $campo) => $errors->has($campo))
        ->min() ?? 1;
    $servicioActual = old('tipo_servicio', '');
    $facultadInicial = (string) old('id_facultad', $facultadRegistrada?->id_facultad ?? '');
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col">

        <!-- 1. FRANJA BLANCA SUPERIOR (HEADER DE ORILLA A ORILLA) -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-4 flex items-center justify-between gap-3 w-full shrink-0">
            <div class="flex items-center gap-4">
                <a href="{{ route('estudiante.home') }}" class="text-[#007F2F] font-bold hover:underline flex items-center gap-1.5 text-sm shrink-0">
                    <span>&larr;</span> Inicio
                </a>
                <div class="h-6 w-px bg-gray-200 shrink-0"></div>
                <div>
                    <h1 class="text-lg font-bold text-gray-900 leading-tight">Solicitar cita</h1>
                    <p class="text-xs text-gray-500">Vicerrectoría de Bienestar Universitario · UCEVA</p>
                </div>
            </div>

            <a href="{{ route('estudiante.home') }}" class="bg-pink-50 hover:bg-pink-100 text-pink-600 p-2.5 rounded-xl transition-colors flex items-center justify-center shadow-xs shrink-0" title="Volver al inicio" aria-label="Volver al inicio">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </a>
        </div>

        <!-- 2. CONTENIDO PRINCIPAL CENTRADO (CARD CON STEPPER) -->
        <div class="flex-1 p-6 sm:p-8 flex justify-center items-start">
            <form method="POST" action="{{ route('citas.solicitar.store') }}"
                  x-data="{
                      paso: {{ $pasoInicial }},
                      enviando: false,
                      servicio: @js($servicioActual),
                      facultad: @js($facultadInicial),
                      jornada: @js($jornadaInicial),
                      franja: @js(old('franja_horaria', '')),
                      acepta: @js((bool) old('acepta_consentimiento')),
                      emergencia: @js((bool) old('es_emergencia')),
                      nombresFacultades: @js($facultades->mapWithKeys(fn ($f) => [(string) $f->id_facultad => $f->nombre])),
                      conConsentimiento: @js($consentimientos->keys()),
                      puedeAvanzar() {
                          if (this.paso === 1) return this.servicio !== '';
                          if (this.paso === 2) return this.facultad !== '' && this.jornada !== '' && this.franja !== '';
                          if (this.paso === 3) return ! this.conConsentimiento.includes(this.servicio) || this.acepta;
                          return true;
                      },
                      avanzar() { if (this.puedeAvanzar()) { this.paso++; this.$nextTick(() => this.$refs['paso' + this.paso]?.focus()) } },
                      retroceder() { this.paso--; this.$nextTick(() => this.$refs['paso' + this.paso]?.focus()) },
                  }"
                  @submit="enviando = true"
                  @keydown.enter="if (paso < 4) { $event.preventDefault(); avanzar() }"
                  class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100 w-full max-w-3xl">
                @csrf

                <!-- Indicador de Pasos (Stepper) -->
                <div class="max-w-xl mx-auto mb-8 relative">
                    <div class="absolute left-0 top-4 w-full h-0.5 bg-gray-200 z-0" aria-hidden="true"></div>
                    <ol class="flex justify-between items-center relative" aria-label="Pasos de la solicitud">
                        @foreach ($pasos as $numero => $nombre)
                            <li class="relative z-10 flex flex-col items-center bg-white px-1 sm:px-3" :aria-current="paso === {{ $numero }} ? 'step' : null">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition-colors"
                                     :class="paso >= {{ $numero }} ? 'bg-[#007F2F] text-white shadow-xs' : 'bg-gray-200 text-gray-500'">
                                    <span x-show="paso <= {{ $numero }}">{{ $numero }}</span>
                                    <span x-show="paso > {{ $numero }}" style="display: none;" aria-hidden="true">✓</span>
                                </div>
                                <span class="text-xs mt-1 text-center" :class="paso >= {{ $numero }} ? 'font-bold text-[#007F2F]' : 'font-medium text-gray-400'">{{ $nombre }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <!-- Resumen de errores -->
                @if ($errors->any() || session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex gap-3 items-start mb-6" role="alert">
                        <span class="text-xl shrink-0" aria-hidden="true">⚠️</span>
                        <div class="text-xs sm:text-sm text-rose-700 font-medium leading-relaxed">
                            @if (session('error'))
                                <p>{{ session('error') }}</p>
                            @endif
                            @if ($errors->any())
                                <p>No se pudo enviar la solicitud:</p>
                                <ul class="list-disc ms-5 mt-1 space-y-0.5">
                                    @foreach (array_keys(StoreSolicitudCitaRequest::PASOS) as $campo)
                                        @foreach ($errors->get($campo) as $mensaje)
                                            <li>{{ $mensaje }}</li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- PASO 1: SERVICIO -->
                <fieldset x-show="paso === 1" @if ($pasoInicial !== 1) style="display: none;" @endif>
                    <legend class="mb-6 w-full">
                        <h2 x-ref="paso1" tabindex="-1" class="text-xl sm:text-2xl font-bold text-gray-900 focus:outline-none">¿Qué servicio necesitas?</h2>
                        <p class="text-gray-500 text-xs sm:text-sm mt-1">Selecciona el tipo de acompañamiento que estás buscando.</p>
                    </legend>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                        @foreach ($servicios as $servicio)
                            <label class="block cursor-pointer">
                                <input type="radio" name="tipo_servicio" value="{{ $servicio }}" x-model="servicio" class="peer sr-only"
                                       @checked($servicioActual === $servicio)
                                       @error('tipo_servicio') aria-invalid="true" @enderror>
                                <div class="h-full border rounded-2xl p-5 transition-all duration-200 shadow-xs border-gray-200 bg-white hover:border-gray-300 peer-checked:border-[#007F2F] peer-checked:bg-green-50/60 peer-checked:ring-2 peer-checked:ring-[#007F2F] peer-focus-visible:ring-2 peer-focus-visible:ring-[#007F2F] peer-focus-visible:ring-offset-2">
                                    <div class="text-3xl mb-3" aria-hidden="true">{{ $tarjetas[$servicio]['icono'] }}</div>
                                    <h3 class="font-bold text-gray-900 text-sm sm:text-base mb-1">{{ $tarjetas[$servicio]['titulo'] }}</h3>
                                    <p class="text-xs text-gray-500 leading-relaxed">{{ $tarjetas[$servicio]['texto'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('tipo_servicio')" class="-mt-4 mb-4" />
                </fieldset>

                <!-- PASO 2: FACULTAD, JORNADA Y FRANJA HORARIA -->
                <div x-show="paso === 2" @if ($pasoInicial !== 2) style="display: none;" @endif class="space-y-6 mb-8">
                    <div>
                        <h2 x-ref="paso2" tabindex="-1" class="text-xl sm:text-2xl font-bold text-gray-900 focus:outline-none">Tus datos y tu horario</h2>
                        <p class="text-gray-500 text-xs sm:text-sm mt-1">Con tu facultad y tu jornada te asignamos el profesional que te atenderá, quien acordará contigo la hora exacta de la cita.</p>
                    </div>

                    {{-- Datos académicos del registro del estudiante: no se preguntan. --}}
                    @if ($facultadRegistrada || $programa || $jornadaRegistrada)
                        <dl class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            @foreach (['Programa académico' => $programa, 'Facultad' => $facultadRegistrada?->nombre, 'Jornada' => $jornadaRegistrada, 'Semestre' => $semestre] as $etiqueta => $valor)
                                @if ($valor)
                                    <div>
                                        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">{{ $etiqueta }}</dt>
                                        <dd class="text-gray-900">{{ $valor }}</dd>
                                    </div>
                                @endif
                            @endforeach
                            <p class="sm:col-span-2 text-xs text-gray-500">Según tu registro en la universidad. Si algún dato no es correcto, comunícate con Bienestar Universitario.</p>
                        </dl>
                    @endif

                    @unless ($facultadRegistrada)
                        <div>
                            <label for="id_facultad" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Facultad <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            <select id="id_facultad" name="id_facultad" required x-model="facultad"
                                    @error('id_facultad') aria-invalid="true" aria-describedby="id_facultad-error" @enderror
                                    @class([$input, 'border-red-500' => $errors->has('id_facultad'), 'border-gray-300' => ! $errors->has('id_facultad')])>
                                <option value="" disabled @selected($facultadInicial === '')>Selecciona tu facultad…</option>
                                @foreach ($facultades as $facultad)
                                    <option value="{{ $facultad->id_facultad }}" @selected($facultadInicial === (string) $facultad->id_facultad)>{{ $facultad->nombre }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Tu facultad no está en tu registro; indícala esta vez.</p>
                            <x-input-error id="id_facultad-error" :messages="$errors->get('id_facultad')" class="mt-1" />
                        </div>
                    @endunless

                    @unless ($jornadaRegistrada)
                        <fieldset>
                            <legend class="block text-sm font-medium text-gray-700 mb-1.5">
                                Jornada <span class="text-rose-600" aria-hidden="true">*</span>
                            </legend>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ($jornadas as $opcion)
                                    <label class="block cursor-pointer">
                                        <input type="radio" name="jornada" value="{{ $opcion }}" x-model="jornada" class="peer sr-only"
                                               @checked(old('jornada') === $opcion) @error('jornada') aria-invalid="true" @enderror>
                                        <div class="text-center border rounded-xl p-3 text-sm font-bold text-gray-900 transition border-gray-200 hover:border-gray-300 peer-checked:border-[#007F2F] peer-checked:bg-green-50/60 peer-checked:ring-2 peer-checked:ring-[#007F2F] peer-focus-visible:ring-2 peer-focus-visible:ring-[#007F2F] peer-focus-visible:ring-offset-2">
                                            {{ $opcion }}
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('jornada')" class="mt-2" />
                        </fieldset>
                    @endunless

                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-1.5">
                            Franja horaria en la que puedes asistir <span class="text-rose-600" aria-hidden="true">*</span>
                        </legend>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach ($franjas as $opcion)
                                <label class="block cursor-pointer">
                                    <input type="radio" name="franja_horaria" value="{{ $opcion }}" x-model="franja" class="peer sr-only"
                                           @checked(old('franja_horaria') === $opcion) @error('franja_horaria') aria-invalid="true" @enderror>
                                    <div class="h-full flex flex-col items-center gap-1 border rounded-xl p-3 transition border-gray-200 hover:border-gray-300 peer-checked:border-[#007F2F] peer-checked:bg-green-50/60 peer-checked:ring-2 peer-checked:ring-[#007F2F] peer-focus-visible:ring-2 peer-focus-visible:ring-[#007F2F] peer-focus-visible:ring-offset-2">
                                        <span class="text-2xl" aria-hidden="true">{{ $franjasInfo[$opcion]['icono'] }}</span>
                                        <span class="text-sm font-bold text-gray-900">{{ $opcion }}</span>
                                        <span class="text-[11px] text-gray-500 text-center">{{ $franjasInfo[$opcion]['texto'] }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('franja_horaria')" class="mt-2" />
                    </fieldset>

                    <!-- Caso de emergencia: opcional -->
                    <label class="flex items-start gap-3 border rounded-xl p-4 cursor-pointer transition"
                           :class="emergencia ? 'border-rose-300 bg-rose-50' : 'border-gray-200 hover:border-gray-300'">
                        <input type="checkbox" id="es_emergencia" name="es_emergencia" value="1" x-model="emergencia" @checked(old('es_emergencia'))
                               class="mt-0.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500">
                        <span>
                            <span class="block text-sm font-bold text-gray-900">🚨 Caso de emergencia <span class="font-normal text-gray-500">(opcional)</span></span>
                            <span class="block text-xs text-gray-600 mt-0.5">Márcalo si necesitas atención urgente. Tu solicitud le aparecerá al psicólogo como prioritaria.</span>
                        </span>
                    </label>
                    <p x-show="emergencia" @unless (old('es_emergencia')) style="display: none;" @endunless
                       class="text-xs text-rose-800 bg-rose-50 border border-rose-200 rounded-xl p-3" role="note">
                        Si tu vida o la de otra persona está en peligro en este momento, no esperes la cita: llama a la línea de emergencias <strong>123</strong> o acude al servicio de urgencias más cercano.
                    </p>

                    <p class="text-xs text-gray-500">La atención es presencial. Si necesitas atención virtual, coméntalo con tu profesional, que la autoriza en casos especiales.</p>
                </div>

                <!-- PASO 3: CONSENTIMIENTO INFORMADO DEL SERVICIO (HU-009) -->
                <div x-show="paso === 3" @if ($pasoInicial !== 3) style="display: none;" @endif class="space-y-6 mb-8">
                    <div>
                        <h2 x-ref="paso3" tabindex="-1" class="text-xl sm:text-2xl font-bold text-gray-900 focus:outline-none">Consentimiento informado</h2>
                        <p class="text-gray-500 text-xs sm:text-sm mt-1">Léelo con atención. Lo que hables en la atención es confidencial.</p>
                    </div>

                    @foreach ($consentimientos as $servicio => $version)
                        @php $visible = $servicioActual === $servicio; @endphp
                        <fieldset x-show="servicio === @js($servicio)" :disabled="servicio !== @js($servicio)"
                                  @unless ($visible) style="display: none;" disabled @endunless
                                  class="space-y-3">
                            <legend class="sr-only">Consentimiento informado de {{ $servicio }}</legend>
                            <p class="text-xs text-gray-400 font-medium">{{ $version->modelo->nombre }} · versión {{ $version->numero_version }}</p>
                            <input type="hidden" name="id_version" value="{{ $version->id_version }}">
                            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs sm:text-sm text-gray-700 leading-relaxed whitespace-pre-line break-words max-h-72 overflow-y-auto" tabindex="0">{{ $version->texto }}</div>
                            <label class="flex items-start gap-2.5 text-sm text-gray-800 cursor-pointer">
                                <input type="checkbox" name="acepta_consentimiento" value="1" x-model="acepta"
                                       @if ($visible && $errors->has('acepta_consentimiento')) aria-invalid="true" aria-describedby="acepta_consentimiento-error" @endif
                                       class="mt-0.5 rounded border-gray-300 text-[#007F2F] focus:ring-[#007F2F]">
                                <span>Leí y acepto el consentimiento informado. <span class="text-rose-600" aria-hidden="true">*</span></span>
                            </label>
                            @if ($visible)
                                <x-input-error id="acepta_consentimiento-error" :messages="$errors->get('acepta_consentimiento')" class="mt-2" />
                            @endif
                        </fieldset>
                    @endforeach

                    <p x-show="! conConsentimiento.includes(servicio)" @if ($consentimientos->has($servicioActual)) style="display: none;" @endif
                       class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-xl p-4">
                        Este servicio aún no tiene un consentimiento publicado. Tu profesional te lo presentará en la primera cita.
                    </p>
                </div>

                <!-- PASO 4: CONFIRMACIÓN -->
                <div x-show="paso === 4" style="display: none;" class="space-y-6 mb-8">
                    <div>
                        <h2 x-ref="paso4" tabindex="-1" class="text-xl sm:text-2xl font-bold text-gray-900 focus:outline-none">Confirma tu solicitud</h2>
                        <p class="text-gray-500 text-xs sm:text-sm mt-1">Revisa los datos antes de enviar.</p>
                    </div>

                    <dl class="border border-gray-200 rounded-xl divide-y divide-gray-100 text-sm">
                        <div class="flex flex-col sm:flex-row sm:gap-4 px-4 py-3">
                            <dt class="sm:w-44 shrink-0 text-xs font-bold text-gray-500 uppercase tracking-wide">Servicio</dt>
                            <dd class="text-gray-900">
                                @foreach ($servicios as $servicio)
                                    <span x-show="servicio === @js($servicio)" style="display: none;">{{ $tarjetas[$servicio]['icono'] }} {{ $tarjetas[$servicio]['titulo'] }}</span>
                                @endforeach
                            </dd>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:gap-4 px-4 py-3">
                            <dt class="sm:w-44 shrink-0 text-xs font-bold text-gray-500 uppercase tracking-wide">Facultad</dt>
                            <dd class="text-gray-900" x-text="nombresFacultades[facultad] ?? ''"></dd>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:gap-4 px-4 py-3">
                            <dt class="sm:w-44 shrink-0 text-xs font-bold text-gray-500 uppercase tracking-wide">Jornada</dt>
                            <dd class="text-gray-900" x-text="jornada"></dd>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:gap-4 px-4 py-3">
                            <dt class="sm:w-44 shrink-0 text-xs font-bold text-gray-500 uppercase tracking-wide">Franja horaria</dt>
                            <dd class="text-gray-900" x-text="franja === 'Todas' ? 'Cualquier franja' : franja"></dd>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:gap-4 px-4 py-3">
                            <dt class="sm:w-44 shrink-0 text-xs font-bold text-gray-500 uppercase tracking-wide">Emergencia</dt>
                            <dd class="text-gray-900" x-text="emergencia ? '🚨 Sí, necesito atención urgente' : 'No'"></dd>
                        </div>
                    </dl>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex gap-3 items-start">
                        <span class="text-xl shrink-0" aria-hidden="true">⚠️</span>
                        <p class="text-xs sm:text-sm text-amber-800 font-medium leading-relaxed">
                            <strong>Importante:</strong> esta es una solicitud de cita, no una cita confirmada. El profesional de tu facultad
                            se comunicará contigo para acordar la fecha y la hora. Podrás ver el estado en <strong>Mis citas</strong>.
                        </p>
                    </div>
                </div>

                <!-- Navegación entre pasos -->
                <div class="flex gap-3">
                    <button type="button" x-show="paso > 1" @click="retroceder()" style="display: none;"
                            class="px-5 py-3.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        &larr; Atrás
                    </button>
                    <button type="button" x-show="paso < 4" @click="avanzar()" :disabled="! puedeAvanzar()"
                            class="flex-1 py-3.5 rounded-xl font-bold text-sm transition-all duration-200 flex items-center justify-center gap-2 bg-[#8AFF00] hover:bg-[#7be600] text-gray-900 shadow-md disabled:bg-[#A9D8A5] disabled:text-white disabled:shadow-none disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        Continuar &rarr;
                    </button>
                    <button type="submit" x-show="paso === 4" style="display: none;" :disabled="enviando"
                            class="flex-1 py-3.5 rounded-xl font-bold text-sm transition-colors bg-[#007F2F] hover:bg-[#006324] text-white shadow-md disabled:opacity-70 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        <span x-show="! enviando">✓ Enviar solicitud</span>
                        <span x-show="enviando" style="display: none;">Enviando…</span>
                    </button>
                </div>
            </form>
        </div>

    </div>
</x-app-layout>

@php
    use App\Models\Actuacion;

    $campos = ['id_caso', 'fecha', 'hora', 'observaciones'];
    $primerError = collect($campos)->first(fn ($campo) => $errors->has($campo));
    $foco = $primerError ?? ($casoInicial ? 'fecha' : 'id_caso');
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $sinCasos = $casos->isEmpty();
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-[#007F2F] tracking-tight">Agendar cita</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Asesoría psicológica · Bienestar Universitario</p>
            </div>

            <!-- Botón X -->
            <a href="{{ $volverA }}"
               class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
               title="Cerrar y volver" aria-label="Cerrar y volver">
                ✕
            </a>
        </div>

        <!-- 2. CONTENIDO PRINCIPAL -->
        <div class="flex-1 p-4 sm:p-8 flex justify-center items-start">
            <form method="POST" action="{{ route('citas.store') }}"
                  x-data="{
                      enviando: false,
                      idCaso: @js(old('id_caso', $casoInicial ?? '')),
                      fecha: @js(old('fecha', '')),
                      hora: @js(old('hora', '')),
                      observaciones: @js(old('observaciones', '')),
                      ocupadas: [],
                      pasadas: [],
                      consultando: false,
                      errorConsulta: false,
                      noDisponible(h) { return this.ocupadas.includes(h) || this.pasadas.includes(h) },
                      async consultar() {
                          this.ocupadas = []; this.pasadas = []; this.errorConsulta = false;
                          if (! this.fecha) return;
                          this.consultando = true;
                          try {
                              const { data } = await window.axios.get(@js(route('citas.disponibilidad')), { params: { fecha: this.fecha } });
                              this.ocupadas = data.ocupadas; this.pasadas = data.pasadas;
                              if (this.noDisponible(this.hora)) this.hora = '';
                          } catch (e) {
                              this.errorConsulta = true;
                          } finally {
                              this.consultando = false;
                          }
                      },
                  }"
                  x-init="consultar()"
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
                                <p>No se pudo agendar la cita. Corrige {{ $errors->count() === 1 ? 'el siguiente campo' : 'los siguientes campos' }}:</p>
                                <ul class="list-disc ms-5 mt-1 space-y-0.5">
                                    @foreach ($campos as $campo)
                                        @if ($errors->has($campo))
                                            <li><a href="#{{ $campo === 'hora' ? 'hora-grupo' : $campo }}" class="underline hover:text-rose-900">{{ $errors->first($campo) }}</a></li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($avisoCaso)
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex gap-3 items-start" role="status">
                        <span class="text-xl shrink-0" aria-hidden="true">ℹ️</span>
                        <p class="text-xs sm:text-sm text-amber-800 font-medium leading-relaxed">{{ $avisoCaso }}</p>
                    </div>
                @endif

                <!-- Sección: Caso -->
                <fieldset class="space-y-4">
                    <legend class="text-base sm:text-lg font-bold text-gray-900 mb-1">Caso</legend>
                    <p class="text-xs text-gray-500 -mt-2">Los campos marcados con <span class="text-rose-600 font-bold">*</span> son obligatorios.</p>

                    @if ($sinCasos)
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs sm:text-sm text-gray-600 font-medium leading-relaxed" id="id_caso">
                            No tienes casos activos asignados. Solo se pueden agendar citas para casos en estado
                            <strong>{{ implode(' o ', \App\Models\Caso::ESTADOS_ACTIVOS) }}</strong>.
                            <a href="{{ route('casos.create') }}" class="text-[#007F2F] font-bold underline hover:text-[#006324]">Abrir un caso</a>
                        </div>
                    @else
                        <div>
                            <label for="id_caso" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Caso activo <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            <select id="id_caso" name="id_caso" required x-model="idCaso"
                                    @if ($foco === 'id_caso') autofocus @endif
                                    aria-describedby="id_caso-ayuda @error('id_caso') id_caso-error @enderror"
                                    @error('id_caso') aria-invalid="true" @enderror
                                    @class([$input, 'border-red-500' => $errors->has('id_caso'), 'border-gray-300' => ! $errors->has('id_caso')])>
                                <option value="">Selecciona un caso…</option>
                                @foreach ($casos as $caso)
                                    <option value="{{ $caso->id_caso }}" @selected(old('id_caso', $casoInicial) === $caso->id_caso)>
                                        {{ $caso->codigo_expediente }} · {{ $caso->solicitud?->persona?->nombre_completo }} ({{ $caso->estado_caso }}){{ in_array($caso->id_caso, $idsPorReagendar, true) ? ' · por reagendar' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p id="id_caso-ayuda" class="text-xs text-gray-400 mt-1">Solo aparecen tus casos activos. "Por reagendar" indica que el paciente ya tuvo su cita y no tiene la siguiente.</p>
                            <x-input-error id="id_caso-error" :messages="$errors->get('id_caso')" class="mt-2" />
                        </div>

                        <!-- Resumen del paciente seleccionado: citas, historial clínico e información importante -->
                        <div aria-live="polite">
                            @foreach ($casos as $caso)
                                <div x-show="idCaso === @js($caso->id_caso)" @if (old('id_caso', $casoInicial) !== $caso->id_caso) style="display: none;" @endif>
                                    @include('citas.partials.resumen-paciente', ['caso' => $caso])
                                </div>
                            @endforeach
                        </div>
                    @endif
                </fieldset>

                <div class="h-px bg-gray-100"></div>

                <!-- Sección: Fecha y hora -->
                <fieldset class="space-y-4">
                    <legend class="text-base sm:text-lg font-bold text-gray-900 mb-1">Fecha y hora</legend>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="fecha" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Fecha de la cita <span class="text-rose-600" aria-hidden="true">*</span>
                            </label>
                            <input id="fecha" name="fecha" type="date" value="{{ old('fecha') }}"
                                   required min="{{ $hoy }}"
                                   x-model="fecha" @change="consultar()"
                                   @if ($foco === 'fecha') autofocus @endif
                                   @error('fecha') aria-invalid="true" aria-describedby="fecha-error" @enderror
                                   @class([$input, 'border-red-500' => $errors->has('fecha'), 'border-gray-300' => ! $errors->has('fecha')])>
                            <x-input-error id="fecha-error" :messages="$errors->get('fecha')" class="mt-2" />
                        </div>

                        <fieldset id="hora-grupo" aria-describedby="hora-ayuda @error('hora') hora-error @enderror">
                            <legend class="block text-sm font-medium text-gray-700 mb-1.5">
                                Hora de la cita <span class="text-rose-600" aria-hidden="true">*</span>
                            </legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($horarios as $h)
                                    <label class="relative">
                                        <input type="radio" name="hora" value="{{ $h }}" required
                                               x-model="hora" :disabled="noDisponible(@js($h))"
                                               @checked(old('hora') === $h)
                                               @if ($foco === 'hora' && $loop->first) autofocus @endif
                                               @error('hora') aria-invalid="true" @enderror
                                               class="peer sr-only">
                                        <span @class([
                                            'inline-flex flex-col items-center min-w-[4.5rem] px-3 py-2 rounded-lg border-[1.5px] bg-white text-xs font-medium text-gray-700 cursor-pointer transition hover:border-gray-300',
                                            'peer-checked:border-[#007F2F] peer-checked:bg-[#f0fdf4] peer-checked:text-[#007F2F]',
                                            'peer-focus-visible:ring-2 peer-focus-visible:ring-[#007F2F] peer-focus-visible:ring-offset-2',
                                            'peer-disabled:cursor-not-allowed peer-disabled:bg-gray-50 peer-disabled:text-gray-400 peer-disabled:hover:border-gray-200',
                                            'border-red-500' => $errors->has('hora'),
                                            'border-gray-200' => ! $errors->has('hora'),
                                        ])>
                                            {{ Actuacion::etiquetaHora($h) }}
                                            <span x-show="ocupadas.includes(@js($h))" style="display: none;" class="text-[10px] font-semibold text-rose-600">Ocupada</span>
                                            <span x-show="! ocupadas.includes(@js($h)) && pasadas.includes(@js($h))" style="display: none;" class="text-[10px] font-semibold">Pasada</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p id="hora-ayuda" class="text-xs text-gray-400 mt-1" aria-live="polite">
                                <span x-show="! fecha">Elige primero la fecha para ver las horas disponibles.</span>
                                <span x-show="fecha && consultando" style="display: none;">Consultando disponibilidad…</span>
                                <span x-show="fecha && ! consultando && ! errorConsulta" style="display: none;">Sesiones de {{ Actuacion::DURACION_MINUTOS }} minutos. Las horas ocupadas en tu agenda no se pueden elegir.</span>
                                <span x-show="errorConsulta" style="display: none;" class="text-amber-700">No se pudo consultar la disponibilidad. Se verificará al guardar.</span>
                            </p>
                            <x-input-error id="hora-error" :messages="$errors->get('hora')" class="mt-2" />
                        </fieldset>
                    </div>
                </fieldset>

                <div class="h-px bg-gray-100"></div>

                <!-- Sección: Información adicional -->
                <fieldset class="space-y-4">
                    <legend class="text-base sm:text-lg font-bold text-gray-900 mb-1">Información adicional</legend>

                    <div>
                        <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" rows="3" maxlength="{{ $observacionesMax }}"
                                  x-model="observaciones"
                                  placeholder="Objetivo de la sesión, indicaciones para el estudiante…"
                                  @if ($foco === 'observaciones') autofocus @endif
                                  aria-describedby="observaciones-contador @error('observaciones') observaciones-error @enderror"
                                  @error('observaciones') aria-invalid="true" @enderror
                                  @class([$input, 'resize-y', 'border-red-500' => $errors->has('observaciones'), 'border-gray-300' => ! $errors->has('observaciones')])>{{ old('observaciones') }}</textarea>
                        <p id="observaciones-contador" class="text-xs text-gray-400 mt-1 text-right">
                            <span x-text="observaciones.length">{{ mb_strlen(old('observaciones', '')) }}</span>/{{ $observacionesMax }} caracteres
                        </p>
                        <x-input-error id="observaciones-error" :messages="$errors->get('observaciones')" class="mt-2" />
                    </div>
                </fieldset>

                <!-- Nota informativa -->
                <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 flex gap-3 items-center">
                    <span class="text-xl shrink-0" aria-hidden="true">📅</span>
                    <p class="text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">
                        Al guardar, la cita queda asociada al caso y a ti, y aparece en tu agenda y en el expediente del caso.
                    </p>
                </div>

                <!-- Acciones -->
                <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-2">
                    <a href="{{ $volverA }}"
                       class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl text-sm transition-colors text-center focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        Cancelar
                    </a>
                    <button type="submit"
                            @disabled($sinCasos)
                            :disabled="enviando || {{ $sinCasos ? 'true' : 'false' }}"
                            class="px-5 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition-colors shadow-sm disabled:opacity-70 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        <span x-show="!enviando">Agendar cita</span>
                        <span x-show="enviando" style="display: none;">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@php
    use App\Models\Vbg\Reporte;

    $opciones = [
        Reporte::AFECTADA => ['🙋', 'Soy la persona afectada', 'Quiero reportar una situación que estoy viviendo.'],
        Reporte::TERCERO => ['🤝', 'Reporto en nombre de otra persona', 'Conozco a alguien que necesita apoyo y acompañamiento.'],
        Reporte::ANONIMO => ['🕶️', 'Prefiero reportar de forma anónima', 'No compartiré mi identidad ni la de la persona afectada.'],
    ];
    $pasos = [1 => 'Quién reporta', 2 => 'Relato y consentimiento', 3 => 'Confirmación'];
    $pasoInicial = $errors->hasAny(['relato', 'afectado_nombre', 'tipo_violencia', 'lugar', 'fecha_hechos', 'medio_contacto', 'acepta_datos']) ? 2 : 1;
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#E30052] focus:ring-[#E30052]';
    $enviado = session('reporte_enviado');
    $colorEstado = [Reporte::RECIBIDO => 'bg-amber-50 text-amber-700', Reporte::EN_ATENCION => 'bg-sky-50 text-sky-700', Reporte::CERRADO => 'bg-gray-100 text-gray-600'];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col">

        <!-- 1. FRANJA BLANCA SUPERIOR DE ORILLA A ORILLA (HEADER) -->
        <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-4 flex items-center justify-between gap-3 w-full shrink-0">
            <div class="flex items-center gap-4">
                <a href="{{ route('estudiante.home') }}" class="text-[#E30052] font-bold hover:underline flex items-center gap-1.5 text-sm shrink-0">
                    <span>&larr;</span> Inicio
                </a>
                <div class="h-6 w-px bg-gray-200 shrink-0"></div>
                <div>
                    <h1 class="text-lg font-bold text-gray-900 leading-tight">Reportar una situación</h1>
                    <p class="text-xs text-gray-500">Violencias de Género · Confidencial</p>
                </div>
            </div>
            <a href="{{ route('estudiante.home') }}"
               class="bg-pink-50 hover:bg-pink-100 text-[#E30052] p-2.5 rounded-xl transition-colors flex items-center justify-center shadow-xs shrink-0"
               title="Volver al inicio" aria-label="Volver al inicio">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </a>
        </div>

        <div class="max-w-3xl mx-auto w-full p-4 sm:p-6 space-y-6">

            @if ($enviado)
                <!-- Reporte enviado -->
                <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100 text-center space-y-3" role="status">
                    <div class="text-4xl" aria-hidden="true">💜</div>
                    <h2 class="text-xl font-bold text-gray-900">Recibimos tu reporte</h2>
                    <p class="text-sm text-gray-600">Código <strong class="text-[#E30052]">{{ $enviado['codigo'] }}</strong>. El equipo de Violencias de Género de la Vicerrectoría de Bienestar Universitario lo revisará de forma confidencial.</p>
                    @if ($enviado['anonimo'])
                        <p class="text-xs text-gray-500">Como es anónimo, no guardamos quién lo envió. Si quieres acompañamiento, puedes acercarte a Bienestar y mencionar este código.</p>
                    @endif
                    <p class="text-xs text-gray-500">Si estás en peligro inmediato, llama a la línea 155 (orientación a mujeres) o al 123.</p>
                    <a href="{{ route('estudiante.home') }}" class="inline-block mt-2 px-5 py-2.5 bg-[#E30052] hover:bg-[#C00045] text-white text-sm font-bold rounded-xl transition">Volver al inicio</a>
                </div>
            @else
                <form method="POST" action="{{ route('reportes.store') }}"
                      x-data="{
                          paso: {{ $pasoInicial }},
                          quien: @js(old('tipo_reportante', '')),
                          relato: @js(old('relato', '')),
                          afectado: @js(old('afectado_nombre', '')),
                          contacto: @js((bool) old('desea_contacto')),
                          medio: @js(old('medio_contacto', '')),
                          acepta: @js((bool) old('acepta_datos')),
                          enviando: false,
                          minimo: {{ Reporte::RELATO_MIN }},
                          puedeAvanzar() {
                              if (this.paso === 1) return this.quien !== '';
                              if (this.paso === 2) return this.relato.trim().length >= this.minimo && this.acepta
                                  && (this.quien !== @js(Reporte::TERCERO) || this.afectado.trim() !== '')
                                  && (this.quien === @js(Reporte::ANONIMO) || ! this.contacto || this.medio.trim() !== '');
                              return true;
                          },
                      }"
                      @submit="enviando = true"
                      class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100">
                    @csrf

                    <!-- Mensaje de alerta confidencial -->
                    <div class="bg-[#FFF0F5] border border-[#FFD6E6] rounded-xl p-4 mb-6 flex gap-3 items-center">
                        <span class="text-xl shrink-0" aria-hidden="true">🔒</span>
                        <p class="text-xs sm:text-sm text-[#CC0044] font-medium leading-relaxed">
                            Este espacio es confidencial. Solo el equipo autorizado de la Vicerrectoría de Bienestar Universitario podrá acceder a la información que compartas.
                        </p>
                    </div>

                    <!-- Indicador de pasos -->
                    <div class="max-w-md mx-auto mb-6 relative">
                        <div class="absolute left-0 top-4 w-full h-0.5 bg-gray-200 z-0" aria-hidden="true"></div>
                        <ol class="flex justify-between items-center relative" aria-label="Pasos del reporte">
                            @foreach ($pasos as $numero => $nombre)
                                <li class="relative z-10 flex flex-col items-center bg-white px-2" :aria-current="paso === {{ $numero }} ? 'step' : null">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs" :class="paso >= {{ $numero }} ? 'bg-[#E30052] text-white shadow-sm' : 'bg-gray-100 text-gray-400'">{{ $numero }}</div>
                                    <span class="text-xs mt-1.5 text-center" :class="paso >= {{ $numero }} ? 'font-bold text-[#E30052]' : 'font-medium text-gray-400'">{{ $nombre }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    @if ($errors->any() || session('error'))
                        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 mb-6 text-sm text-rose-700" role="alert">
                            {{ session('error') ?? 'Revisa los datos marcados.' }}
                        </div>
                    @endif

                    <!-- PASO 1: QUIÉN REPORTA -->
                    <fieldset x-show="paso === 1" @if ($pasoInicial !== 1) style="display: none;" @endif>
                        <legend class="mb-5">
                            <span class="block text-xl font-bold text-gray-900">¿Quién está reportando?</span>
                            <span class="block text-gray-500 text-sm mt-0.5">Puedes reportar por ti mismo/a, en nombre de otra persona, o de forma anónima.</span>
                        </legend>
                        <div class="flex flex-col gap-3.5 mb-6">
                            @foreach ($opciones as $valor => [$icono, $titulo, $texto])
                                <label class="block cursor-pointer">
                                    <input type="radio" name="tipo_reportante" value="{{ $valor }}" x-model="quien" class="peer sr-only" @checked(old('tipo_reportante') === $valor)>
                                    <div class="border rounded-2xl p-4 transition-all duration-200 flex items-center gap-4 border-gray-200 bg-white hover:border-gray-300 peer-checked:border-[#E30052] peer-checked:ring-1 peer-checked:ring-[#E30052] peer-checked:bg-pink-50/30 peer-focus-visible:ring-2 peer-focus-visible:ring-[#E30052]">
                                        <div class="text-2xl shrink-0" aria-hidden="true">{{ $icono }}</div>
                                        <div>
                                            <h3 class="font-bold text-sm mb-0.5 text-gray-900">{{ $titulo }}</h3>
                                            <p class="text-xs text-gray-500">{{ $texto }}</p>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('tipo_reportante')" class="-mt-3 mb-4" />
                    </fieldset>

                    <!-- PASO 2: RELATO Y CONSENTIMIENTO -->
                    <div x-show="paso === 2" @if ($pasoInicial !== 2) style="display: none;" @endif class="space-y-5 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Cuéntanos qué ocurrió</h2>
                            <p class="text-gray-500 text-sm mt-0.5">Escribe solo lo que te sientas cómodo/a compartiendo.</p>
                        </div>

                        <div x-show="quien === @js(Reporte::TERCERO)" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="afectado_nombre" class="block text-sm font-medium text-gray-700 mb-1.5">Nombre de la persona afectada</label>
                                <input id="afectado_nombre" name="afectado_nombre" type="text" maxlength="150" x-model="afectado" :disabled="quien !== @js(Reporte::TERCERO)" class="{{ $input }}">
                                <x-input-error :messages="$errors->get('afectado_nombre')" class="mt-1" />
                            </div>
                            <div>
                                <label for="afectado_vinculo" class="block text-sm font-medium text-gray-700 mb-1.5">Su relación con la universidad <span class="text-gray-400 font-normal">(opcional)</span></label>
                                <input id="afectado_vinculo" name="afectado_vinculo" type="text" maxlength="100" value="{{ old('afectado_vinculo') }}" :disabled="quien !== @js(Reporte::TERCERO)" placeholder="Ej. estudiante de Enfermería" class="{{ $input }}">
                            </div>
                        </div>

                        <div>
                            <label for="relato" class="block text-sm font-medium text-gray-700 mb-1.5">¿Qué pasó?</label>
                            <textarea id="relato" name="relato" rows="6" maxlength="{{ Reporte::RELATO_MAX }}" x-model="relato" class="{{ $input }} resize-y"
                                      placeholder="Describe la situación: qué ocurrió, quiénes estuvieron involucrados y si sigue ocurriendo."></textarea>
                            <p class="text-xs text-gray-400 mt-1" x-show="relato.trim().length < minimo">Escribe al menos {{ Reporte::RELATO_MIN }} caracteres.</p>
                            <x-input-error :messages="$errors->get('relato')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="tipo_violencia" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de violencia <span class="text-gray-400 font-normal">(opcional)</span></label>
                                <select id="tipo_violencia" name="tipo_violencia" class="{{ $input }}">
                                    <option value="">No estoy seguro/a</option>
                                    @foreach (Reporte::TIPOS_VIOLENCIA as $tipo)
                                        <option value="{{ $tipo }}" @selected(old('tipo_violencia') === $tipo)>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="lugar" class="block text-sm font-medium text-gray-700 mb-1.5">Lugar <span class="text-gray-400 font-normal">(opcional)</span></label>
                                <input id="lugar" name="lugar" type="text" maxlength="150" value="{{ old('lugar') }}" placeholder="Ej. Bloque C" class="{{ $input }}">
                            </div>
                            <div>
                                <label for="fecha_hechos" class="block text-sm font-medium text-gray-700 mb-1.5">Fecha <span class="text-gray-400 font-normal">(opcional)</span></label>
                                <input id="fecha_hechos" name="fecha_hechos" type="date" max="{{ $hoy }}" value="{{ old('fecha_hechos') }}" class="{{ $input }}">
                                <x-input-error :messages="$errors->get('fecha_hechos')" class="mt-1" />
                            </div>
                        </div>

                        <div x-show="quien !== @js(Reporte::ANONIMO)" class="space-y-3">
                            <label class="flex items-start gap-2.5 text-sm text-gray-800 cursor-pointer">
                                <input type="checkbox" name="desea_contacto" value="1" x-model="contacto" :disabled="quien === @js(Reporte::ANONIMO)" class="mt-0.5 rounded border-gray-300 text-[#E30052] focus:ring-[#E30052]">
                                <span>Quiero que el equipo me contacte para acompañamiento.</span>
                            </label>
                            <div x-show="contacto">
                                <label for="medio_contacto" class="block text-sm font-medium text-gray-700 mb-1.5">¿Cómo podemos contactarte de forma segura?</label>
                                <input id="medio_contacto" name="medio_contacto" type="text" maxlength="150" x-model="medio" :disabled="quien === @js(Reporte::ANONIMO) || ! contacto" placeholder="Ej. correo institucional, en las tardes" class="{{ $input }}">
                                <x-input-error :messages="$errors->get('medio_contacto')" class="mt-1" />
                            </div>
                        </div>

                        <label class="flex items-start gap-2.5 text-sm text-gray-800 cursor-pointer bg-gray-50 border border-gray-200 rounded-xl p-4">
                            <input type="checkbox" name="acepta_datos" value="1" x-model="acepta" class="mt-0.5 rounded border-gray-300 text-[#E30052] focus:ring-[#E30052]">
                            <span>Autorizo a la Vicerrectoría de Bienestar Universitario a tratar de forma confidencial la información de este reporte para brindar orientación y activar las rutas de atención que correspondan (Ley 1581 de 2012).</span>
                        </label>
                        <x-input-error :messages="$errors->get('acepta_datos')" class="-mt-3" />
                    </div>

                    <!-- PASO 3: CONFIRMACIÓN -->
                    <div x-show="paso === 3" style="display: none;" class="space-y-4 mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Confirma tu reporte</h2>
                        <dl class="border border-gray-200 rounded-xl divide-y divide-gray-100 text-sm">
                            <div class="px-4 py-3 flex flex-col sm:flex-row sm:gap-4">
                                <dt class="sm:w-40 text-xs font-bold text-gray-500 uppercase tracking-wide">Quién reporta</dt>
                                <dd class="text-gray-900" x-text="quien === @js(Reporte::ANONIMO) ? 'Anónimo' : (quien === @js(Reporte::TERCERO) ? 'En nombre de ' + afectado : 'La persona afectada')"></dd>
                            </div>
                            <div class="px-4 py-3 flex flex-col sm:flex-row sm:gap-4">
                                <dt class="sm:w-40 text-xs font-bold text-gray-500 uppercase tracking-wide">Relato</dt>
                                <dd class="text-gray-900 whitespace-pre-line break-words" x-text="relato.trim()"></dd>
                            </div>
                        </dl>
                        <p class="text-xs text-gray-500">Si estás en peligro inmediato, llama a la línea 155 (orientación a mujeres) o al 123.</p>
                    </div>

                    <!-- Navegación -->
                    <div class="flex gap-3">
                        <button type="button" x-show="paso > 1" @click="paso--" style="display: none;"
                                class="px-5 py-3.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl text-sm transition">&larr; Atrás</button>
                        <button type="button" x-show="paso < 3" @click="if (puedeAvanzar()) paso++" :disabled="! puedeAvanzar()"
                                class="flex-1 py-3.5 rounded-xl font-medium transition-all duration-200 text-sm text-white bg-[#E30052] hover:bg-[#C00045] shadow-md disabled:bg-[#F4A5C0] disabled:shadow-none disabled:cursor-not-allowed">
                            Continuar &rarr;
                        </button>
                        <button type="submit" x-show="paso === 3" style="display: none;" :disabled="enviando"
                                class="flex-1 py-3.5 rounded-xl font-bold text-sm text-white bg-[#E30052] hover:bg-[#C00045] shadow-md disabled:opacity-70">
                            <span x-show="! enviando">Enviar reporte</span>
                            <span x-show="enviando" style="display: none;">Enviando…</span>
                        </button>
                    </div>
                </form>
            @endif

            @if ($misReportes->isNotEmpty())
                <section class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100" aria-labelledby="mis-reportes">
                    <h2 id="mis-reportes" class="text-base font-bold text-gray-900 mb-3">Mis reportes</h2>
                    <p class="text-xs text-gray-500 mb-3">Los reportes anónimos no aparecen aquí: no guardamos quién los envió.</p>
                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl text-sm">
                        @foreach ($misReportes as $reporte)
                            <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                                <span><strong>{{ $reporte->codigo }}</strong> · {{ $reporte->fecha_reporte->format('Y-m-d') }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $colorEstado[$reporte->estado] ?? '' }}">{{ $reporte->estado }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>

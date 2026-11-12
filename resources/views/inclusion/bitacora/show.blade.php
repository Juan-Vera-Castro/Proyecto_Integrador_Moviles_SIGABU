{{-- P4: bitácora de orientación y acompañamiento. Secciones I a IV del formato. --}}
@php
    use App\Documentos\ConsentimientoInclusion;
    use App\Models\Actuacion;
    use App\Models\ConsentimientoAceptacion;
    use App\Models\Inclusion\Bitacora;
    use App\Models\Inclusion\Borrador;
    use App\Models\Inclusion\Constancia;
    use App\Models\Inclusion\EntradaBitacora;

    $persona = $caso->solicitud?->persona;
    $control = 'block w-full rounded-lg border-gray-300 shadow-sm text-base sm:text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $etiqueta = 'block text-sm font-medium text-gray-700 mb-1';
    $tarjeta = 'bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100';
    $resumen = 'text-base font-bold text-gray-900 cursor-pointer select-none focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded';
    $puedeEditar = $motivoBloqueo === null;
    $entradas = $bitacora?->entradas ?? collect();
    $cierrePendiente = $bitacora?->cierrePendiente();
    $erroresEntrada = $errors->getBag('entrada');
    $enCelularCerrado = "\$el.open = window.innerWidth >= 768";

    // Tipos que admite la próxima entrada (RN-16). Un consentimiento revocado solo deja cerrar (RN-14).
    $tiposPermitidos = match (true) {
        $entradas->isEmpty() => [EntradaBitacora::ATENCION_INICIAL],
        $puedeEditar => [EntradaBitacora::SEGUIMIENTO, EntradaBitacora::CIERRE],
        default => [EntradaBitacora::CIERRE],
    };
    if ($cierrePendiente) {
        $tiposPermitidos = array_values(array_diff($tiposPermitidos, [EntradaBitacora::CIERRE]));
    }
    $admiteEntradas = $bitacora && $caso->estaActivo() && ($puedeEditar || $entradas->isNotEmpty()) && $tiposPermitidos !== [];
    $reabiertas = $bitacora?->reaperturas->keyBy('id_constancia') ?? collect();
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-[#007F2F] tracking-tight">Bitácora de orientación y acompañamiento</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Caso {{ $caso->codigo_expediente }} · Unidad de Inclusión</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if ($bitacora)
                    <x-estado-documento :estado="$bitacora->estadoDocumento()" />
                @elseif ($borrador)
                    <x-estado-documento estado="Borrador" />
                @endif
                <a href="{{ route('profesional.expediente.show', $caso) }}"
                   class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm"
                   title="Volver al expediente" aria-label="Volver al expediente">✕</a>
            </div>
        </div>

        <div class="flex-1 p-3 sm:p-8 flex justify-center items-start">
            <div class="w-full max-w-5xl space-y-4 pb-8">

                @if ($bitacora)
                    <div class="flex justify-end">
                        <a href="{{ route('inclusion.bitacora.pdf', $caso) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-[#007F2F] text-[#006324] hover:bg-emerald-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2 w-full sm:w-auto"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>Descargar PDF</a>
                    </div>
                @endif

                @if (session('status'))
                    <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-sm text-[#1F5134] font-medium" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-700 font-medium" role="alert">{{ session('error') }}</div>
                @endif
                @if ($motivoBloqueo && ! session('status'))
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900 font-medium" role="status">{{ $motivoBloqueo }}</div>
                @endif
                @if ($sinDatosSensibles)
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900" role="status">
                        La persona no autorizó el tratamiento de datos sensibles. Puedes continuar, pero ten en cuenta esa decisión al registrar la información.
                    </div>
                @endif

                <details class="{{ $tarjeta }}" x-data x-init="{{ $enCelularCerrado }}">
                    <summary class="{{ $resumen }}">I. Datos generales del caso</summary>
                    <dl class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                        <div><dt class="text-xs text-gray-500">ID de caso</dt><dd class="font-semibold text-gray-900">{{ $caso->codigo_expediente }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Estado del proceso</dt><dd class="font-semibold text-gray-900">{{ $caso->estaActivo() ? 'Abierto' : $caso->estado_caso }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Fecha de apertura</dt><dd class="font-semibold text-gray-900">{{ $caso->fecha_apertura?->format('d/m/Y') }}</dd></div>
                        <div class="col-span-2 sm:col-span-2"><dt class="text-xs text-gray-500">Nombre completo</dt><dd class="font-semibold text-gray-900 break-words">{{ $detalle?->nombre_persona ?? $persona?->nombre_completo }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Identificación</dt><dd class="font-semibold text-gray-900 break-words">{{ $detalle?->documento_persona ?? $persona?->numero_documento }}</dd></div>
                    </dl>
                </details>

                <details class="{{ $tarjeta }}" x-data x-init="$el.open = window.innerWidth >= 768 || @js(! $bitacora || $errors->any())">
                    <summary class="{{ $resumen }}">II. Caracterización y registro de caso</summary>

                    @if ($puedeEditar)
                        <form method="POST" action="{{ route('inclusion.bitacora.guardar', $caso) }}" class="mt-4 space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="rol_institucional" class="{{ $etiqueta }}">Rol institucional</label>
                                    <select id="rol_institucional" name="rol_institucional" aria-required="true" aria-describedby="rol_institucional-error" class="{{ $control }}">
                                        <option value="">Selecciona…</option>
                                        @foreach (array_keys(ConsentimientoInclusion::VINCULACIONES) as $opcion)
                                            <option value="{{ $opcion }}" @selected(old('rol_institucional', $iniciales['rol_institucional']) === $opcion)>{{ $opcion }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error id="rol_institucional-error" :messages="$errors->get('rol_institucional')" class="mt-1" />
                                </div>
                                <div>
                                    <label for="programa_dependencia" class="{{ $etiqueta }}">Programa académico / Dependencia</label>
                                    <input type="text" id="programa_dependencia" name="programa_dependencia" value="{{ old('programa_dependencia', $iniciales['programa_dependencia']) }}" maxlength="150" autocomplete="off" aria-required="true" aria-describedby="programa_dependencia-error" class="{{ $control }}">
                                    <x-input-error id="programa_dependencia-error" :messages="$errors->get('programa_dependencia')" class="mt-1" />
                                </div>
                            </div>

                            @foreach (['situacion', 'ruta_activada', 'soportes_texto', 'orientacion'] as $campo)
                                @php [$nombre, $obligatorio, $maximo] = Bitacora::CARACTERIZACION[$campo]; @endphp
                                <div>
                                    <label for="{{ $campo }}" class="{{ $etiqueta }}">{{ $nombre }}{{ $obligatorio ? '' : ' (opcional)' }}</label>
                                    <textarea id="{{ $campo }}" name="{{ $campo }}" rows="3" maxlength="{{ $maximo }}" @if ($obligatorio) aria-required="true" @endif aria-describedby="{{ $campo }}-error" class="{{ $control }}">{{ old($campo, $iniciales[$campo]) }}</textarea>
                                    <x-input-error id="{{ $campo }}-error" :messages="$errors->get($campo)" class="mt-1" />
                                </div>
                            @endforeach

                            <x-evidencias :caso="$caso" id="soportes" nombre="soportes" etiqueta="Archivos de soporte" :iniciales="$soportesTemporales" />
                            <x-input-error :messages="$errors->get('soportes')" />

                            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2"
                                     x-data="borradorFormulario(@js([
                                         'url' => route('inclusion.borradores.guardar', [$caso, Borrador::BITACORA]),
                                         'guardadoEn' => $borrador?->fecha_actualizacion?->format('H:i'),
                                     ]))">
                                    <button type="button" @click="guardar()" :disabled="guardando"
                                            class="w-full sm:w-auto px-5 py-2.5 bg-white border border-[#007F2F] text-[#006324] hover:bg-emerald-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                        Guardar borrador
                                    </button>
                                    <p class="text-xs text-gray-600" aria-live="polite" x-text="estado"></p>
                                </div>
                                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    {{ $bitacora ? 'Guardar cambios' : 'Guardar caracterización' }}
                                </button>
                            </div>
                        </form>
                    @elseif ($bitacora)
                        <dl class="mt-4 space-y-3 text-sm">
                            @foreach (Bitacora::CARACTERIZACION as $campo => [$nombre])
                                <div>
                                    <dt class="text-xs text-gray-500">{{ $nombre }}</dt>
                                    <dd class="text-gray-900 whitespace-pre-line break-words">{{ $bitacora->{$campo} ?: '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-3 text-sm text-gray-600">Este caso no tiene caracterización registrada.</p>
                    @endif

                    @if ($bitacora?->soportes->isNotEmpty())
                        <div class="mt-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-1">Soportes guardados</h3>
                            @include('inclusion.bitacora.partials.evidencias', ['evidencias' => $bitacora->soportes])
                        </div>
                    @endif

                    @if ($bitacora?->cambios->isNotEmpty())
                        <details class="mt-4">
                            <summary class="text-xs font-bold text-[#006324] cursor-pointer">Historial de cambios ({{ $bitacora->cambios->count() }})</summary>
                            <ul class="mt-2 space-y-2 text-xs text-gray-700">
                                @foreach ($bitacora->cambios as $cambio)
                                    <li class="border-l-2 border-gray-200 pl-3 break-words">
                                        <p class="font-semibold">{{ Bitacora::CARACTERIZACION[$cambio->campo][0] ?? $cambio->campo }} · {{ $cambio->fecha_hora->format('d/m/Y H:i') }} · {{ $cambio->profesional?->persona?->nombre_completo }}</p>
                                        <p>Antes: {{ $cambio->valor_anterior ?: '—' }}</p>
                                        <p>Después: {{ $cambio->valor_nuevo ?: '—' }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </details>

                <section id="matriz" class="{{ $tarjeta }} space-y-3" aria-labelledby="matriz-titulo">
                    <h2 id="matriz-titulo" class="text-base font-bold text-gray-900">III. Matriz de seguimiento e intervenciones</h2>

                    @include('inclusion.bitacora.partials.matriz', ['entradas' => $entradas, 'puedeAnotar' => $puedeEditar])

                    @if ($admiteEntradas)
                        <details class="border border-gray-200 rounded-xl p-3" @if ($erroresEntrada->any()) open @endif>
                            <summary class="text-sm font-bold text-[#006324] cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">Agregar entrada N° {{ ($entradas->last()?->numero ?? 0) + 1 }}</summary>
                            <form method="POST" action="{{ route('inclusion.entradas.store', $caso) }}" class="mt-3 space-y-3">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="fecha_hora" class="{{ $etiqueta }}">Fecha y hora</label>
                                        <input type="datetime-local" id="fecha_hora" name="fecha_hora" value="{{ old('fecha_hora', now(Actuacion::ZONA_HORARIA)->format('Y-m-d\TH:i')) }}" max="{{ now(Actuacion::ZONA_HORARIA)->format('Y-m-d\TH:i') }}" aria-required="true" aria-describedby="fecha_hora-error" class="{{ $control }}">
                                        <x-input-error id="fecha_hora-error" :messages="$erroresEntrada->get('fecha_hora')" class="mt-1" />
                                    </div>
                                    <div>
                                        <label for="tipo" class="{{ $etiqueta }}">Tipo de acción</label>
                                        <select id="tipo" name="tipo" aria-required="true" aria-describedby="tipo-error" class="{{ $control }}">
                                            @foreach ($tiposPermitidos as $tipo)
                                                <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ $tipo }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error id="tipo-error" :messages="$erroresEntrada->get('tipo')" class="mt-1" />
                                    </div>
                                </div>
                                <div>
                                    <label for="descripcion" class="{{ $etiqueta }}">Descripción detallada de la orientación</label>
                                    <textarea id="descripcion" name="descripcion" rows="4" maxlength="{{ EntradaBitacora::TEXTO_MAX }}" aria-required="true" aria-describedby="descripcion-error" class="{{ $control }}">{{ old('descripcion') }}</textarea>
                                    <x-input-error id="descripcion-error" :messages="$erroresEntrada->get('descripcion')" class="mt-1" />
                                </div>
                                <x-evidencias :caso="$caso" id="entrada" nombre="evidencias" etiqueta="Evidencias de la entrada" :iniciales="$evidenciasTemporales" />
                                <x-input-error :messages="$erroresEntrada->get('evidencias')" />
                                <div class="flex justify-end">
                                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                        Guardar entrada
                                    </button>
                                </div>
                            </form>
                        </details>
                    @elseif (! $bitacora && $caso->estaActivo())
                        <p class="text-sm text-gray-600">Guarda la caracterización para empezar la matriz.</p>
                    @endif

                    <p class="text-xs text-gray-600"><span class="font-semibold">*Nota:</span> {{ Bitacora::NOTA }}</p>
                </section>

                <section class="{{ $tarjeta }} space-y-3" aria-labelledby="constancia-titulo">
                    <h2 id="constancia-titulo" class="text-base font-bold text-gray-900">IV. Constancia de gestión</h2>
                    <p class="text-sm text-gray-700 leading-relaxed">{{ Constancia::TEXTO }}</p>

                    @foreach ($bitacora?->constancias ?? [] as $constancia)
                        <div class="border border-gray-100 bg-gray-50 rounded-xl p-3 text-sm text-gray-800">
                            <p class="font-semibold break-words">Firmada por {{ $constancia->nombre_profesional }} el {{ $constancia->fecha_hora->format('d/m/Y H:i') }}.</p>
                            @if ($reapertura = $reabiertas->get($constancia->id_constancia))
                                <p class="mt-1 text-xs text-gray-700 break-words">Caso reabierto el {{ $reapertura->fecha_hora->format('d/m/Y H:i') }} por {{ $reapertura->administrador?->persona?->nombre_completo }}. Motivo: {{ $reapertura->motivo }}</p>
                            @endif
                        </div>
                    @endforeach

                    @if ($cierrePendiente && $caso->estaActivo())
                        <form method="POST" action="{{ route('inclusion.cierre', $caso) }}"
                              x-data="firmaTactil(@js(['confirmacionEnvio' => "Se cerrará el caso {$caso->codigo_expediente} y se cancelarán sus citas y tareas pendientes. Después no se podrá agregar nada. ¿Continuar?"]))"
                              @submit.prevent class="space-y-2">
                            @csrf
                            <div class="flex items-center justify-between gap-3">
                                <p id="constancia-ayuda" class="text-sm font-semibold text-gray-800">Firma del profesional, con el dedo o el mouse.</p>
                                <button type="button" @click="borrar()" :disabled="enviando"
                                        class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    Borrar
                                </button>
                            </div>
                            <x-firma-lienzo ayuda="constancia-ayuda" etiqueta="Área de firma de la constancia" class="max-w-xl" />
                            <p class="text-sm text-red-600" role="alert" x-show="error('firma') || mensaje" x-text="error('firma') || mensaje" style="display: none;"></p>
                            <button type="button" @click="enviar('{{ ConsentimientoAceptacion::ACEPTADO }}')" :disabled="enviando || ! hayTrazo"
                                    class="w-full sm:w-auto px-6 py-2.5 bg-[#007F2F] hover:bg-[#006324] disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                <span x-show="! enviando">Firmar y cerrar el caso</span>
                                <span x-show="enviando" style="display: none;">Guardando…</span>
                            </button>
                        </form>
                    @elseif ($caso->estaActivo())
                        <p class="text-sm text-gray-600">Para cerrar el caso, primero agrega una entrada de tipo Cierre en la matriz.</p>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>

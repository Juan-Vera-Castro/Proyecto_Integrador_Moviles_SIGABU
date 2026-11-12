{{-- P5: recurso y plantilla 5.8 de ajustes pedagógicos del caso. --}}
@php
    use App\Documentos\PdfInclusion;
    use App\Models\Actuacion;
    use App\Models\AjusteRazonable;
    use App\Models\Inclusion\Borrador;

    $control = 'block w-full rounded-lg border-gray-300 shadow-sm text-base sm:text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $etiqueta = 'block text-sm font-medium text-gray-700 mb-1';
    $tarjeta = 'bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100';
    $valor = fn (string $campo, $porDefecto = '') => old($campo, $iniciales[$campo] ?? $porDefecto);
    $avisoCompartir = PdfInclusion::avisoCompartir($caso);
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-[#007F2F] tracking-tight">Plantilla de ajustes pedagógicos</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Caso {{ $caso->codigo_expediente }} · para acceso equitativo</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if ($caso->ajustes->isNotEmpty())
                    <x-estado-documento estado="Completado" />
                @elseif ($borrador)
                    <x-estado-documento estado="Borrador" />
                @endif
                <a href="{{ route('profesional.expediente.show', $caso) }}"
                   class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm"
                   title="Volver al expediente" aria-label="Volver al expediente">✕</a>
            </div>
        </div>

        <div class="flex-1 p-3 sm:p-8 flex justify-center items-start">
            <div class="w-full max-w-6xl space-y-4 pb-8">

                @if (session('status'))
                    <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-sm text-[#1F5134] font-medium" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-700 font-medium" role="alert">{{ session('error') }}</div>
                @endif
                @if ($motivoBloqueo)
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900 font-medium" role="status">{{ $motivoBloqueo }}</div>
                @endif

                @if ($recurso)
                    <details class="{{ $tarjeta }}">
                        <summary class="text-base font-bold text-gray-900 cursor-pointer select-none focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">Recurso: {{ $recurso->titulo }}</summary>
                        <p class="mt-1 text-xs text-gray-500">{{ $recurso->condicion }} · versión {{ $recurso->version }}</p>
                        <div class="mt-3">
                            @include('inclusion.recursos.partials.bloques', ['bloques' => $recurso->contenido, 'prefijo' => 'recurso'])
                        </div>
                    </details>
                @endif

                <section class="{{ $tarjeta }} space-y-3" aria-labelledby="filas-titulo">
                    <h2 id="filas-titulo" class="text-base font-bold text-gray-900">Plantilla 5.8</h2>
                    <p class="text-xs text-gray-600">Una fila guardada no se edita: su avance se registra en el seguimiento del ajuste.</p>
                    @include('inclusion.plantilla.partials.filas', ['ajustes' => $caso->ajustes])

                    @if ($caso->ajustes->isNotEmpty())
                        <div class="pt-3 border-t border-gray-100 space-y-2">
                            @if ($avisoCompartir)
                                <p id="aviso-compartir" class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-900">
                                    <span class="font-bold">Antes de compartir:</span> {{ $avisoCompartir }} Puedes descargarlo de todas formas.
                                </p>
                            @endif
                            <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                                <a href="{{ route('inclusion.plantilla.pdf', $caso) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-[#007F2F] text-[#006324] hover:bg-emerald-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>PDF completo (expediente)</a>
                                <a href="{{ route('inclusion.plantilla.compartir', $caso) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-[#007F2F] text-[#006324] hover:bg-emerald-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2"
                                   @if ($avisoCompartir) aria-describedby="aviso-compartir" onclick="return confirm(@js($avisoCompartir.' ¿Deseas descargar el PDF para compartir de todas formas?'))" @endif><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>PDF para compartir</a>
                            </div>
                            <p class="text-xs text-gray-600">El PDF para compartir lleva solo la plantilla: sin el recurso, sin nombrar la condición y sin la caracterización.</p>
                        </div>
                    @endif
                </section>

                @unless ($motivoBloqueo)
                    <form method="POST" action="{{ route('inclusion.plantilla.guardar', $caso) }}" class="{{ $tarjeta }} space-y-4" aria-labelledby="agregar-titulo">
                        @csrf
                        <h2 id="agregar-titulo" class="text-base font-bold text-gray-900">Agregar fila</h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label for="area" class="{{ $etiqueta }}">Área / Barrera</label>
                                <select id="area" name="area" aria-required="true" aria-describedby="area-error" class="{{ $control }}">
                                    <option value="">Selecciona…</option>
                                    @foreach (AjusteRazonable::AREAS as $area)
                                        <option value="{{ $area }}" @selected($valor('area') === $area)>{{ $area }}</option>
                                    @endforeach
                                </select>
                                <x-input-error id="area-error" :messages="$errors->get('area')" class="mt-1" />
                            </div>
                            <div>
                                <label for="responsable" class="{{ $etiqueta }}">Responsable</label>
                                <input type="text" id="responsable" name="responsable" value="{{ $valor('responsable') }}" maxlength="150" autocomplete="off" aria-required="true" aria-describedby="responsable-error" class="{{ $control }}">
                                <x-input-error id="responsable-error" :messages="$errors->get('responsable')" class="mt-1" />
                            </div>
                            <div>
                                <label for="fecha_inicio" class="{{ $etiqueta }}">Fecha</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" value="{{ $valor('fecha_inicio', now(Actuacion::ZONA_HORARIA)->toDateString()) }}" aria-required="true" aria-describedby="fecha_inicio-error" class="{{ $control }}">
                                <x-input-error id="fecha_inicio-error" :messages="$errors->get('fecha_inicio')" class="mt-1" />
                            </div>
                            <div>
                                <label for="id_recurso" class="{{ $etiqueta }}">Recurso de apoyo</label>
                                <select id="id_recurso" name="id_recurso" aria-describedby="id_recurso-error" class="{{ $control }}">
                                    <option value="">Ninguno</option>
                                    @foreach ($recursos as $opcion)
                                        <option value="{{ $opcion->id_recurso }}" @selected((int) $recurso?->id_recurso === $opcion->id_recurso)>{{ $opcion->condicion }} (v{{ $opcion->version }})</option>
                                    @endforeach
                                </select>
                                <x-input-error id="id_recurso-error" :messages="$errors->get('id_recurso')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="barrera" class="{{ $etiqueta }}">Descripción de la barrera</label>
                                <textarea id="barrera" name="barrera" rows="3" maxlength="{{ AjusteRazonable::TEXTO_MAX }}" aria-required="true" aria-describedby="barrera-error" class="{{ $control }}">{{ $valor('barrera') }}</textarea>
                                <x-input-error id="barrera-error" :messages="$errors->get('barrera')" class="mt-1" />
                            </div>
                            <div>
                                <label for="descripcion" class="{{ $etiqueta }}">Ajuste o estrategia pedagógica implementada</label>
                                <textarea id="descripcion" name="descripcion" rows="3" maxlength="{{ AjusteRazonable::TEXTO_MAX }}" aria-required="true" aria-describedby="descripcion-error" class="{{ $control }}">{{ $valor('descripcion') }}</textarea>
                                <x-input-error id="descripcion-error" :messages="$errors->get('descripcion')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <label for="observaciones" class="{{ $etiqueta }}">Observaciones (opcional)</label>
                            <textarea id="observaciones" name="observaciones" rows="2" maxlength="{{ AjusteRazonable::TEXTO_MAX }}" aria-describedby="observaciones-error" class="{{ $control }}">{{ $valor('observaciones') }}</textarea>
                            <x-input-error id="observaciones-error" :messages="$errors->get('observaciones')" class="mt-1" />
                        </div>

                        <x-evidencias :caso="$caso" id="ajuste" nombre="evidencias" etiqueta="Evidencia asociada" :iniciales="$evidenciasTemporales" />
                        <x-input-error :messages="$errors->get('evidencias')" />

                        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2"
                                 x-data="borradorFormulario(@js([
                                     'url' => route('inclusion.borradores.guardar', [$caso, Borrador::PLANTILLA]),
                                     'guardadoEn' => $borrador?->fecha_actualizacion?->format('H:i'),
                                 ]))">
                                <button type="button" @click="guardar()" :disabled="guardando"
                                        class="w-full sm:w-auto px-5 py-2.5 bg-white border border-[#007F2F] text-[#006324] hover:bg-emerald-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    Guardar borrador
                                </button>
                                <p class="text-xs text-gray-600" aria-live="polite" x-text="estado"></p>
                            </div>
                            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                Agregar fila
                            </button>
                        </div>
                    </form>
                @endunless
            </div>
        </div>
    </div>
</x-app-layout>

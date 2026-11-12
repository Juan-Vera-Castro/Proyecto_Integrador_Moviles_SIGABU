{{-- Inclusión: ajustes razonables (HU-018) con su historial de seguimiento (HU-019). Recibe $caso, $esSuCaso e $input. --}}
@php
    use App\Models\Actuacion;
    use App\Models\AjusteRazonable;
    use App\Models\SeguimientoAjuste;

    $gestionable = $esSuCaso && $caso->estaActivo();
    $sinAtencionAjustes = $gestionable ? $caso->motivoSinAtencion() : null;
    $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();
    $colorEstado = [AjusteRazonable::VIGENTE => 'bg-sky-50 text-sky-700', AjusteRazonable::CUMPLIDO => 'bg-emerald-50 text-emerald-700', AjusteRazonable::SUSPENDIDO => 'bg-gray-100 text-gray-600'];
    $colorCumplimiento = ['Se cumple' => 'text-emerald-700', 'Se cumple parcialmente' => 'text-amber-700', 'No se cumple' => 'text-rose-700'];
@endphp

<section id="ajustes" aria-labelledby="ajustes-titulo">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 id="ajustes-titulo" class="text-base font-bold text-gray-900">Ajustes razonables</h2>
            <p class="text-xs text-gray-500 mt-0.5">Cada seguimiento queda en el historial del ajuste con su fecha y observaciones; no se modifica.</p>
        </div>
        {{-- HU-020: reporte de seguimiento del proceso, para comunicar su estado --}}
        @if ($caso->ajustes->isNotEmpty())
            <a href="{{ route('casos.reporte-inclusion', $caso) }}"
               class="px-3 py-1.5 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-lg transition shrink-0 focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                Descargar reporte PDF
            </a>
        @endif
    </div>

    @if ($sinAtencionAjustes)
        <p class="text-xs text-amber-700 mb-3">{{ $sinAtencionAjustes }} Por eso no se pueden registrar ajustes ni seguimientos.</p>
    @endif

    @forelse ($caso->ajustes as $ajuste)
        @php $erroresSeguimiento = $errors->getBag('seguimiento-'.$ajuste->id_ajuste); @endphp
        <article id="ajuste-{{ $ajuste->id_ajuste }}" class="border border-gray-100 rounded-xl p-4 mb-3 space-y-3 scroll-mt-6">
            <header class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="font-bold text-gray-900 text-sm">{{ $ajuste->etiqueta() }}</p>
                    <p class="text-xs text-gray-500">Desde el {{ $ajuste->fecha_inicio->format('Y-m-d') }}{{ $ajuste->responsable ? ' · aplica: '.$ajuste->responsable : '' }}</p>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $colorEstado[$ajuste->estado] }}">{{ $ajuste->estado }}</span>
            </header>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">Barrera</dt>
                    <dd class="text-gray-800 whitespace-pre-line break-words">{{ $ajuste->barrera }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Ajuste</dt>
                    <dd class="text-gray-800 whitespace-pre-line break-words">{{ $ajuste->descripcion }}</dd>
                </div>
            </dl>

            @if ($ajuste->observaciones)
                <p class="text-sm text-gray-800 whitespace-pre-line break-words"><span class="block text-xs text-gray-500">Observaciones</span>{{ $ajuste->observaciones }}</p>
            @endif
            @if ($ajuste->evidencias->isNotEmpty())
                <div>
                    <p class="text-xs text-gray-500">Evidencia</p>
                    @include('inclusion.bitacora.partials.evidencias', ['evidencias' => $ajuste->evidencias])
                </div>
            @endif

            <div>
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Historial de seguimiento</h3>
                @if ($ajuste->seguimientos->isEmpty())
                    <p class="text-xs text-gray-500">Todavía no hay seguimientos.</p>
                @else
                    <ol class="border-l-2 border-gray-200 pl-4 space-y-2.5">
                        @foreach ($ajuste->seguimientos as $seguimiento)
                            <li class="text-sm">
                                <p class="text-[11px] text-gray-500">
                                    {{ $seguimiento->fecha->format('Y-m-d') }} · <span class="font-semibold {{ $colorCumplimiento[$seguimiento->cumplimiento] ?? '' }}">{{ $seguimiento->cumplimiento }}</span>
                                    · {{ $seguimiento->profesional?->persona?->nombre_completo }}
                                    @if ($seguimiento->estado_resultante) · ajuste pasó a "{{ $seguimiento->estado_resultante }}" @endif
                                </p>
                                <p class="text-gray-800 whitespace-pre-line break-words">{{ $seguimiento->observaciones }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            @if ($gestionable && ! $sinAtencionAjustes)
                <details @if ($erroresSeguimiento->any()) open @endif>
                    <summary class="cursor-pointer text-xs font-bold text-[#007F2F] select-none">+ Registrar seguimiento</summary>
                    <form method="POST" action="{{ route('ajustes.seguimiento', $ajuste) }}" class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @csrf
                        <div>
                            <label for="fecha-{{ $ajuste->id_ajuste }}" class="block text-xs font-medium text-gray-700 mb-1">Fecha</label>
                            <input id="fecha-{{ $ajuste->id_ajuste }}" name="fecha" type="date" required max="{{ $hoy }}" min="{{ $ajuste->fecha_inicio->format('Y-m-d') }}"
                                   value="{{ $erroresSeguimiento->any() ? old('fecha') : $hoy }}" class="{{ $input }} border-gray-300">
                            <x-input-error :messages="$erroresSeguimiento->get('fecha')" class="mt-1" />
                        </div>
                        <div>
                            <label for="cumplimiento-{{ $ajuste->id_ajuste }}" class="block text-xs font-medium text-gray-700 mb-1">Cumplimiento</label>
                            <select id="cumplimiento-{{ $ajuste->id_ajuste }}" name="cumplimiento" required class="{{ $input }} border-gray-300">
                                <option value="">Selecciona…</option>
                                @foreach (SeguimientoAjuste::CUMPLIMIENTOS as $opcion)
                                    <option value="{{ $opcion }}" @selected($erroresSeguimiento->any() && old('cumplimiento') === $opcion)>{{ $opcion }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$erroresSeguimiento->get('cumplimiento')" class="mt-1" />
                        </div>
                        <div>
                            <label for="estado-{{ $ajuste->id_ajuste }}" class="block text-xs font-medium text-gray-700 mb-1">Estado del ajuste</label>
                            <select id="estado-{{ $ajuste->id_ajuste }}" name="estado_resultante" class="{{ $input }} border-gray-300">
                                <option value="">Sin cambio ({{ $ajuste->estado }})</option>
                                @foreach (AjusteRazonable::ESTADOS as $estado)
                                    @continue($estado === $ajuste->estado)
                                    <option value="{{ $estado }}">Pasa a {{ mb_strtolower($estado) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="observaciones-{{ $ajuste->id_ajuste }}" class="block text-xs font-medium text-gray-700 mb-1">Observaciones del avance</label>
                            <textarea id="observaciones-{{ $ajuste->id_ajuste }}" name="observaciones" rows="3" required maxlength="{{ AjusteRazonable::TEXTO_MAX }}"
                                      placeholder="Qué se verificó, con quién y cómo avanza el estudiante…" class="{{ $input }} border-gray-300 resize-y">{{ $erroresSeguimiento->any() ? old('observaciones') : '' }}</textarea>
                            <x-input-error :messages="$erroresSeguimiento->get('observaciones')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-3">
                            <button type="submit" class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition">Guardar seguimiento</button>
                        </div>
                    </form>
                </details>
            @endif
        </article>
    @empty
        <p class="text-sm text-gray-500 mb-3">Este caso no tiene ajustes razonables registrados.</p>
    @endforelse

    @can('inclusion.gestionar', $caso)
        <a href="{{ route('inclusion.plantilla', $caso) }}"
           class="inline-block px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-sm font-bold rounded-xl transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
            Abrir plantilla de ajustes
        </a>
    @endcan
</section>

{{-- Matriz de seguimiento: tarjetas apiladas en celular y filas desde md. Variables: $entradas, $puedeAnotar. --}}
@php
    use App\Models\Inclusion\AnotacionBitacora;

    $columnas = 'md:grid-cols-[3rem_8rem_8rem_minmax(0,1fr)_10rem]';
    $erroresAnotacion = $errors->getBag('anotacion');
@endphp

@if ($entradas->isEmpty())
    <p class="text-sm text-gray-600">Aún no hay entradas.</p>
@else
    <div class="hidden md:grid {{ $columnas }} gap-3 px-3 text-xs font-semibold text-gray-500" aria-hidden="true">
        <span>N°</span><span>Fecha y hora</span><span>Tipo de acción</span><span>Descripción</span><span>Evidencia</span>
    </div>

    <div class="space-y-2">
        @foreach ($entradas as $entrada)
            <article class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-sm" aria-label="Entrada {{ $entrada->numero }}">
                <div class="grid grid-cols-1 {{ $columnas }} gap-1.5 md:gap-3">
                    <p class="font-bold text-gray-900"><span class="md:hidden">N° </span>{{ $entrada->numero }}</p>
                    <p class="text-xs text-gray-700">{{ $entrada->fecha_hora->format('d/m/Y H:i') }}</p>
                    <p class="text-xs font-semibold text-gray-800">{{ $entrada->tipo }}</p>
                    <div class="min-w-0">
                        <p class="text-gray-800 whitespace-pre-line break-words">{{ $entrada->descripcion }}</p>
                        <p class="mt-1 text-[11px] text-gray-500">{{ $entrada->profesional?->persona?->nombre_completo }}</p>
                    </div>
                    <div class="min-w-0">
                        @if ($entrada->evidencias->isNotEmpty())
                            @include('inclusion.bitacora.partials.evidencias', ['evidencias' => $entrada->evidencias])
                        @else
                            <span class="text-xs text-gray-500">Sin evidencias</span>
                        @endif
                    </div>
                </div>

                @if ($entrada->anotaciones->isNotEmpty())
                    <ul class="mt-2 space-y-1.5">
                        @foreach ($entrada->anotaciones as $anotacion)
                            <li class="border-l-2 border-[#007F2F] pl-3 text-xs text-gray-800 break-words">
                                <p class="whitespace-pre-line">{{ $anotacion->texto }}</p>
                                <p class="text-[11px] text-gray-500">Anotación de {{ $anotacion->profesional?->persona?->nombre_completo }} · {{ $anotacion->fecha_hora->format('d/m/Y H:i') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($puedeAnotar)
                    @php $conError = old('entrada_anotada') === $entrada->id_entrada && $erroresAnotacion->any(); @endphp
                    <details class="mt-2" @if ($conError) open @endif>
                        <summary class="text-xs font-bold text-[#006324] cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">Anotar</summary>
                        <form method="POST" action="{{ route('inclusion.entradas.anotar', $entrada) }}" class="mt-2 space-y-2">
                            @csrf
                            <input type="hidden" name="entrada_anotada" value="{{ $entrada->id_entrada }}">
                            <label for="anotacion-{{ $entrada->id_entrada }}" class="block text-xs font-medium text-gray-700">Anotación a la entrada N° {{ $entrada->numero }}</label>
                            <textarea id="anotacion-{{ $entrada->id_entrada }}" name="texto" rows="2" maxlength="{{ AnotacionBitacora::TEXTO_MAX }}" aria-required="true"
                                      class="block w-full rounded-lg border-gray-300 shadow-sm text-base sm:text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]">{{ $conError ? old('texto') : '' }}</textarea>
                            @if ($conError)
                                <x-input-error :messages="$erroresAnotacion->get('texto')" />
                            @endif
                            <button type="submit" class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                Guardar anotación
                            </button>
                        </form>
                    </details>
                @endif
            </article>
        @endforeach
    </div>
@endif

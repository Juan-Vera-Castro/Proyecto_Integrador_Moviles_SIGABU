{{-- Factores de riesgo y protectores del caso. Los críticos generan una alerta temprana (HU-026). --}}
@php
    use App\Models\Caso;

    $erroresFactores = $errors->getBag('factores');
    $riesgoMarcado = $erroresFactores->any() ? (array) old('factores_riesgo', []) : ($caso->factores_riesgo ?? []);
    $protectoresMarcados = $erroresFactores->any() ? (array) old('factores_protectores', []) : ($caso->factores_protectores ?? []);
    $grupos = [
        ['factores_riesgo', 'Factores de riesgo', Caso::FACTORES_RIESGO, $riesgoMarcado, 'bg-rose-50/60 border-rose-100', 'text-rose-800', 'text-rose-600 focus:ring-rose-500'],
        ['factores_protectores', 'Factores protectores', Caso::FACTORES_PROTECTORES, $protectoresMarcados, 'bg-emerald-50/60 border-emerald-100', 'text-emerald-800', 'text-emerald-600 focus:ring-emerald-500'],
    ];
@endphp

<section id="factores" aria-labelledby="factores-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
    <h2 id="factores-titulo" class="text-base font-bold text-gray-900">Factores de riesgo y protectores</h2>
    <p class="text-xs text-gray-500 mt-0.5 mb-4">Los factores marcados con <span aria-hidden="true">⚠</span><span class="sr-only">advertencia</span> generan una alerta temprana.</p>

    @if ($caso->estaActivo())
        <form method="POST" action="{{ route('casos.actualizar-factores', $caso) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($grupos as [$campo, $titulo, $opciones, $marcados, $fondo, $colorTitulo, $colorCasilla])
                    <fieldset class="p-4 rounded-xl border {{ $fondo }}">
                        <legend class="px-1 text-xs font-bold uppercase tracking-wide {{ $colorTitulo }}">{{ $titulo }}</legend>
                        <div class="space-y-2 mt-1">
                            @foreach ($opciones as $opcion)
                                <label class="flex items-start gap-2 text-xs sm:text-sm text-gray-800">
                                    <input type="checkbox" name="{{ $campo }}[]" value="{{ $opcion }}" @checked(in_array($opcion, $marcados, true))
                                           class="mt-0.5 rounded border-gray-300 {{ $colorCasilla }}">
                                    <span>
                                        {{ $opcion }}
                                        @if (in_array($opcion, Caso::FACTORES_CRITICOS, true))
                                            <span class="text-rose-700" aria-hidden="true">⚠</span><span class="sr-only">(genera alerta temprana)</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$erroresFactores->get($campo.'.*')" class="mt-2" />
                    </fieldset>
                @endforeach
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                    Guardar factores
                </button>
            </div>
        </form>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            @foreach ($grupos as [$campo, $titulo, $opciones, $marcados, $fondo, $colorTitulo])
                <div class="p-4 rounded-xl border {{ $fondo }}">
                    <h3 class="text-xs font-bold uppercase tracking-wide {{ $colorTitulo }} mb-1">{{ $titulo }}</h3>
                    @forelse ($marcados as $factor)
                        <p class="text-gray-800">{{ $factor }}</p>
                    @empty
                        <p class="text-gray-500">Ninguno registrado.</p>
                    @endforelse
                </div>
            @endforeach
        </div>
    @endif
</section>

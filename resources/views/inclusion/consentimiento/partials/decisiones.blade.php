{{-- Las 4 casillas del formato, independientes entre sí (RN-08). Variables: $iniciales, $titulo, $ayuda. --}}
<fieldset class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100">
    <legend class="sr-only">{{ $titulo }}</legend>
    <h2 class="text-base font-bold text-gray-900" aria-hidden="true">{{ $titulo }}</h2>
    <p class="text-xs text-gray-500 mt-0.5 mb-3">{{ $ayuda }}</p>
    <div class="space-y-2">
        @foreach (\App\Documentos\ConsentimientoInclusion::DECISIONES as $campo => $texto)
            <label for="{{ $campo }}" class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" id="{{ $campo }}" name="{{ $campo }}" value="1" @checked(! empty($iniciales[$campo]))
                       class="mt-0.5 w-5 h-5 shrink-0 rounded border-gray-400 text-[#007F2F] focus:ring-[#007F2F]">
                <span class="text-sm text-gray-800">{{ $texto }}</span>
            </label>
        @endforeach
    </div>
</fieldset>

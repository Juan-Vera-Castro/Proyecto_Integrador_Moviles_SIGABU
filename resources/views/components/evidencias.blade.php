{{-- Carga de archivos y links de un formato de Inclusión. Agrega al formulario un campo oculto por evidencia. --}}
@props(['caso', 'id', 'nombre' => 'evidencias', 'etiqueta' => 'Evidencias', 'iniciales' => []])

@php
    use App\Models\Inclusion\Evidencia;

    $extensiones = collect(array_keys(Evidencia::TIPOS_PERMITIDOS))->map(fn ($extension) => '.'.$extension)->implode(',');
@endphp

<div x-data="evidencias(@js([
        'subir' => route('inclusion.evidencias.store', $caso),
        'maximo' => Evidencia::MAX_POR_REGISTRO,
        'iniciales' => $iniciales,
     ]))" {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <div>
        <label for="{{ $id }}-archivo" class="block text-sm font-medium text-gray-700 mb-1.5">{{ $etiqueta }}</label>
        <input id="{{ $id }}-archivo" type="file" multiple accept="{{ $extensiones }}" aria-describedby="{{ $id }}-ayuda" @change="elegir($event)" :disabled="lleno"
               class="block w-full text-sm text-gray-700 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-[#EAF7EF] file:text-[#006324] file:font-semibold hover:file:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded-lg">
        <p id="{{ $id }}-ayuda" class="text-xs text-gray-500 mt-1">PDF, JPG, PNG, DOCX o XLSX, hasta {{ Evidencia::ARCHIVO_MAX_KB / 1024 }} MB cada uno. Los correos se adjuntan guardados como PDF.</p>
    </div>

    <div>
        <label for="{{ $id }}-enlace" class="block text-sm font-medium text-gray-700 mb-1.5">Link (https)</label>
        <div class="flex flex-col sm:flex-row gap-2">
            <input id="{{ $id }}-enlace" type="url" maxlength="500" placeholder="https://…" x-model="enlace" aria-describedby="{{ $id }}-aviso"
                   @keydown.enter.prevent="agregarEnlace()"
                   class="flex-1 min-w-0 rounded-lg border-gray-300 text-sm focus:border-[#007F2F] focus:ring-[#007F2F]">
            <button type="button" @click="agregarEnlace()" :disabled="lleno"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 disabled:opacity-50 text-gray-700 text-sm font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                Agregar link
            </button>
        </div>
        <p id="{{ $id }}-aviso" class="text-xs text-amber-800 mt-1">SIGABU no controla quién abre un link externo: revisa sus permisos en Drive.</p>
    </div>

    <p class="text-sm text-red-600" role="alert" x-show="error" x-text="error" style="display: none;"></p>
    <p class="sr-only" aria-live="polite" x-text="anuncio"></p>

    <ul class="space-y-2" x-show="items.length || subiendo.length" style="display: none;">
        <template x-for="carga in subiendo" :key="carga.clave">
            <li class="border border-gray-100 rounded-lg p-2.5">
                <p class="text-sm text-gray-800 break-words" x-text="carga.nombre"></p>
                <div class="mt-1.5 h-1.5 bg-gray-100 rounded-full overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="carga.progreso" :aria-label="`Subiendo ${carga.nombre}`">
                    <div class="h-full bg-[#007F2F] transition-all" :style="`width: ${carga.progreso}%`"></div>
                </div>
            </li>
        </template>
        <template x-for="item in items" :key="item.id">
            <li class="flex items-center gap-3 border border-gray-100 rounded-lg p-2.5">
                <input type="hidden" name="{{ $nombre }}[]" :value="item.id">
                <template x-if="item.vista">
                    <img :src="item.vista" alt="" class="w-10 h-10 object-cover rounded shrink-0">
                </template>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-800 break-words" x-text="item.nombre"></p>
                    <p class="text-xs text-gray-500" x-show="item.tamano" x-text="item.tamano"></p>
                </div>
                <button type="button" @click="quitar(item)" :aria-label="`Quitar ${item.nombre}`"
                        class="px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50 rounded-lg transition shrink-0 focus:outline-none focus:ring-2 focus:ring-[#007F2F]">
                    Quitar
                </button>
            </li>
        </template>
    </ul>
</div>

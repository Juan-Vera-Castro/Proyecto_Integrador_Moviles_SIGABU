{{-- Error de un campo: del servidor al recargar o de la respuesta del envío con fetch. --}}
<div id="{{ $campo }}-error">
    <x-input-error :messages="$errors->get($campo)" class="mt-1" />
    <p class="mt-1 text-sm text-red-600" x-show="error('{{ $campo }}')" x-text="error('{{ $campo }}')" style="display: none;"></p>
</div>

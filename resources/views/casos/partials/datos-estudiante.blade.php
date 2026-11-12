{{-- Formulario de tipo de documento, sexo y fecha de nacimiento del estudiante (D-01, D-20).
     Recibe $caso, $persona y $volver ('expediente'). --}}
@php
    use App\Models\Persona;

    $erroresEstudiante = $errors->getBag('estudiante');
    $campo = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $valor = fn (string $nombre) => old($nombre, $nombre === 'fecha_nacimiento' ? $persona->fecha_nacimiento?->format('Y-m-d') : $persona->{$nombre});
@endphp

<form method="POST" action="{{ route('estudiante.datos.update', $caso) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
    @csrf
    @method('PATCH')
    <input type="hidden" name="volver" value="{{ $volver }}">

    <div>
        <label for="tipo_documento" class="block text-xs font-medium text-gray-700 mb-1">Tipo de documento</label>
        <select id="tipo_documento" name="tipo_documento" required
                @if ($erroresEstudiante->has('tipo_documento')) aria-invalid="true" aria-describedby="tipo_documento-error" @endif
                @class([$campo, 'border-red-500' => $erroresEstudiante->has('tipo_documento'), 'border-gray-300' => ! $erroresEstudiante->has('tipo_documento')])>
            <option value="">Selecciona…</option>
            @foreach (Persona::TIPOS_DOCUMENTO as $tipo)
                <option value="{{ $tipo }}" @selected($valor('tipo_documento') === $tipo)>{{ $tipo }}</option>
            @endforeach
        </select>
        <x-input-error id="tipo_documento-error" :messages="$erroresEstudiante->get('tipo_documento')" class="mt-1" />
    </div>

    <fieldset>
        <legend class="block text-xs font-medium text-gray-700 mb-1">Sexo</legend>
        <div class="flex gap-4 py-2 text-sm">
            @foreach (Persona::SEXOS as $sexo)
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="sexo" value="{{ $sexo }}" required @checked($valor('sexo') === $sexo) class="text-[#007F2F] focus:ring-[#007F2F]">
                    {{ $sexo }}
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$erroresEstudiante->get('sexo')" class="mt-1" />
    </fieldset>

    <div>
        <label for="fecha_nacimiento" class="block text-xs font-medium text-gray-700 mb-1">Fecha de nacimiento</label>
        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required min="1900-01-01"
               max="{{ now(\App\Models\Actuacion::ZONA_HORARIA)->toDateString() }}" value="{{ $valor('fecha_nacimiento') }}"
               @if ($erroresEstudiante->has('fecha_nacimiento')) aria-invalid="true" aria-describedby="fecha_nacimiento-error" @endif
               @class([$campo, 'border-red-500' => $erroresEstudiante->has('fecha_nacimiento'), 'border-gray-300' => ! $erroresEstudiante->has('fecha_nacimiento')])>
        <x-input-error id="fecha_nacimiento-error" :messages="$erroresEstudiante->get('fecha_nacimiento')" class="mt-1" />
    </div>

    <div class="sm:col-span-3 flex justify-end">
        <button type="submit"
                class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
            Guardar datos del estudiante
        </button>
    </div>
</form>

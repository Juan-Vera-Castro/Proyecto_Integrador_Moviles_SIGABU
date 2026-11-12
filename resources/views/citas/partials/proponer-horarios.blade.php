{{-- El psicólogo propone horarios para que el estudiante elija uno. Recibe $caso (con propuestasVigentes cargadas). --}}
@php
    use App\Models\Actuacion;
    use App\Models\PropuestaCita;

    $errores = $errors->getBag('propuestas');
    $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();
    $filasIniciales = max(3, count(old('opciones', [])));
@endphp

<div class="bg-sky-50/60 border border-sky-200 rounded-xl p-4 space-y-3">
    @if ($caso->propuestasVigentes->isNotEmpty())
        <div>
            <p class="text-xs font-bold text-sky-900">Horarios enviados · esperando que el estudiante elija</p>
            <ul class="mt-1.5 flex flex-wrap gap-2">
                @foreach ($caso->propuestasVigentes as $propuesta)
                    <li class="px-2.5 py-1 bg-white border border-sky-200 rounded-lg text-xs text-sky-900">{{ $propuesta->etiqueta() }}</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('propuestas.retirar', $caso) }}" class="mt-2">
                @csrf
                <button type="submit" class="text-xs font-bold text-gray-500 hover:text-rose-600">Retirar estos horarios</button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('propuestas.store', $caso) }}" x-data="{ filas: {{ $filasIniciales }} }" class="space-y-2">
        @csrf
        <p class="text-xs font-bold text-gray-700">
            {{ $caso->propuestasVigentes->isNotEmpty() ? 'Enviar otros horarios (reemplazan los anteriores)' : 'Proponer horarios: el estudiante elige el que le sirve' }}
        </p>
        @foreach (range(0, PropuestaCita::MAXIMO - 1) as $i)
            <div class="flex flex-wrap items-center gap-2" x-show="filas > {{ $i }}" @if ($i >= $filasIniciales) style="display: none;" @endif>
                <span class="text-xs text-gray-500 w-5">{{ $i + 1 }}.</span>
                <label for="opcion-{{ $caso->id_caso }}-{{ $i }}-fecha" class="sr-only">Fecha del horario {{ $i + 1 }}</label>
                <input id="opcion-{{ $caso->id_caso }}-{{ $i }}-fecha" type="date" name="opciones[{{ $i }}][fecha]" min="{{ $hoy }}" value="{{ old("opciones.$i.fecha") }}"
                       class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 focus:border-[#007F2F] focus:ring-[#007F2F]">
                <label for="opcion-{{ $caso->id_caso }}-{{ $i }}-hora" class="sr-only">Hora del horario {{ $i + 1 }}</label>
                <select id="opcion-{{ $caso->id_caso }}-{{ $i }}-hora" name="opciones[{{ $i }}][hora]"
                        class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 focus:border-[#007F2F] focus:ring-[#007F2F]">
                    <option value="">Hora</option>
                    @foreach (Actuacion::HORARIOS as $hora)
                        <option value="{{ $hora }}" @selected(old("opciones.$i.hora") === $hora)>{{ Actuacion::etiquetaHora($hora) }}</option>
                    @endforeach
                </select>
                @if ($errores->has("opciones.$i"))
                    <span class="text-xs text-red-600 font-medium" role="alert">{{ $errores->first("opciones.$i") }}</span>
                @endif
            </div>
        @endforeach
        <x-input-error :messages="$errores->get('opciones')" />
        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="button" x-show="filas < {{ PropuestaCita::MAXIMO }}" @click="filas++" class="text-xs font-bold text-[#007F2F] hover:underline">+ Otro horario</button>
            <button type="submit" class="px-4 py-2 bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold rounded-xl shadow-sm transition">Enviar horarios al estudiante</button>
        </div>
    </form>
</div>

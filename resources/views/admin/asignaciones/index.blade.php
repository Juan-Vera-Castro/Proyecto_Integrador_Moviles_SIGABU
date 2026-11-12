@php
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F] border-gray-300';
    $erroresAsignacion = $errors->getBag('asignacion');
    $erroresFacultad = $errors->getBag('facultad');
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Asignación de profesionales</h1>
                <p class="text-gray-500 text-sm">Qué profesional atiende cada servicio en cada facultad y jornada</p>
            </div>
            <a href="{{ route('admin.administracion') }}" class="w-10 h-10 flex items-center justify-center bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors"
               title="Volver a Administración" aria-label="Cerrar y volver a Administración">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </a>
        </div>

        <div class="p-4 sm:p-8 max-w-5xl mx-auto w-full flex flex-col gap-6">

            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-600 text-sm font-medium rounded-lg px-4 py-3" role="alert">{{ session('error') }}</div>
            @endif
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
            @endif

            <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">
                Cuando un estudiante solicita una cita indica su facultad y su jornada, y el caso llega directo al profesional asignado aquí.
                Si hay varios, le llega al que tiene menos casos activos. Si nadie atiende esa facultad y jornada, le llega a quien atienda la misma
                facultad en otra jornada y, si tampoco, a quien atienda ese servicio. Solo queda sin asignar si nadie atiende el servicio; al crear la
                asignación que falta, esas solicitudes se asignan de inmediato.
                @if ($sinAsignar > 0)
                    <strong class="block mt-1 text-amber-800">Hoy hay {{ $sinAsignar }} {{ $sinAsignar === 1 ? 'solicitud' : 'solicitudes' }} sin profesional asignado.</strong>
                @endif
            </div>

            <!-- Nueva asignación -->
            <form method="POST" action="{{ route('admin.asignaciones.store') }}" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @csrf
                <h2 class="sm:col-span-2 text-base font-bold text-gray-900">Asignar un profesional</h2>

                <div>
                    <label for="id_profesional" class="block text-sm font-medium text-gray-700 mb-1.5">Profesional</label>
                    <select id="id_profesional" name="id_profesional" required class="{{ $input }}">
                        <option value="">Selecciona…</option>
                        @foreach ($profesionales as $profesional)
                            <option value="{{ $profesional->id_usuario }}" @selected(old('id_profesional') === $profesional->id_usuario)>{{ $profesional->persona?->nombre_completo ?? $profesional->codigo_unico }} · {{ $profesional->etiquetaArea() ?? 'Sin área' }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$erroresAsignacion->get('id_profesional')" class="mt-1" />
                </div>
                <div>
                    <label for="tipo_servicio" class="block text-sm font-medium text-gray-700 mb-1.5">Servicio</label>
                    <select id="tipo_servicio" name="tipo_servicio" required class="{{ $input }}">
                        @foreach ($servicios as $servicio)
                            <option value="{{ $servicio }}" @selected(old('tipo_servicio') === $servicio)>{{ $servicio }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$erroresAsignacion->get('tipo_servicio')" class="mt-1" />
                </div>
                <div>
                    <label for="id_facultad" class="block text-sm font-medium text-gray-700 mb-1.5">Facultad</label>
                    <select id="id_facultad" name="id_facultad" required class="{{ $input }}">
                        <option value="">Selecciona…</option>
                        @foreach ($facultades->where('activa', true) as $facultad)
                            <option value="{{ $facultad->id_facultad }}" @selected((string) old('id_facultad') === (string) $facultad->id_facultad)>{{ $facultad->nombre }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$erroresAsignacion->get('id_facultad')" class="mt-1" />
                </div>
                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700 mb-1.5">Jornadas</legend>
                    <div class="flex gap-4 text-sm pt-2">
                        @foreach ($jornadas as $jornada)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="jornadas[]" value="{{ $jornada }}" @checked(in_array($jornada, old('jornadas', $jornadas), true))
                                       class="rounded border-gray-300 text-[#007F2F] focus:ring-[#007F2F]">
                                {{ $jornada }}
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$erroresAsignacion->get('jornadas')" class="mt-1" />
                </fieldset>
                <div class="sm:col-span-2">
                    <button type="submit" class="px-5 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white text-sm font-bold rounded-xl shadow-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        Guardar asignación
                    </button>
                </div>
            </form>

            <!-- Asignaciones por facultad -->
            @foreach ($facultades as $facultad)
                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-3 {{ $facultad->activa ? '' : 'opacity-60' }}" aria-labelledby="facultad-{{ $facultad->id_facultad }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="facultad-{{ $facultad->id_facultad }}" class="text-base font-bold text-gray-900">
                            {{ $facultad->nombre }}
                            @unless ($facultad->activa) <span class="text-xs font-semibold text-gray-500">(inactiva)</span> @endunless
                        </h2>
                        <form method="POST" action="{{ route('admin.facultades.estado', $facultad) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-xs font-bold {{ $facultad->activa ? 'text-gray-500 hover:text-rose-600' : 'text-[#007F2F] hover:underline' }}">
                                {{ $facultad->activa ? 'Desactivar facultad' : 'Activar facultad' }}
                            </button>
                        </form>
                    </div>

                    @if ($facultad->asignaciones->isEmpty())
                        <p class="text-sm text-amber-700">Sin profesionales asignados: las solicitudes de esta facultad quedarán sin asignar.</p>
                    @else
                        <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl text-sm">
                            @foreach ($facultad->asignaciones as $asignacion)
                                <li class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 {{ $asignacion->activa ? '' : 'text-gray-400' }}">
                                    <span>
                                        <strong class="{{ $asignacion->activa ? 'text-gray-900' : '' }}">{{ $asignacion->profesional?->persona?->nombre_completo ?? 'Profesional' }}</strong>
                                        · {{ $asignacion->tipo_servicio }} · jornada {{ $asignacion->jornada }}
                                        @unless ($asignacion->activa) · inactiva @endunless
                                    </span>
                                    <form method="POST" action="{{ route('admin.asignaciones.estado', $asignacion) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-xs font-bold {{ $asignacion->activa ? 'text-gray-500 hover:text-rose-600' : 'text-[#007F2F] hover:underline' }}">
                                            {{ $asignacion->activa ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach

            <!-- Nueva facultad -->
            <form method="POST" action="{{ route('admin.facultades.store') }}" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1 min-w-[240px]">
                    <label for="nombre_facultad" class="block text-sm font-medium text-gray-700 mb-1.5">Agregar facultad</label>
                    <input id="nombre_facultad" name="nombre" type="text" maxlength="150" required value="{{ old('nombre') }}" placeholder="Ej. Facultad de …" class="{{ $input }}">
                    <x-input-error :messages="$erroresFacultad->get('nombre')" class="mt-1" />
                </div>
                <button type="submit" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-bold rounded-xl transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                    Agregar
                </button>
            </form>
        </div>
    </div>
</x-app-layout>

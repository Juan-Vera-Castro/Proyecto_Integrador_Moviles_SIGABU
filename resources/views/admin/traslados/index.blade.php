@php
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F] border-gray-300';
    $nombre = $usuario->persona?->nombre_completo ?? $usuario->codigo_unico;
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Traslado de casos · último recurso</h1>
                <p class="text-gray-500 text-sm">Casos activos a cargo de {{ $nombre }}</p>
            </div>
            <a href="{{ route('admin.administracion') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900 shrink-0">Volver</a>
        </div>

        <div class="p-4 sm:p-8 max-w-[1000px] mx-auto w-full flex-1 flex flex-col gap-6">

            <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 text-sm text-amber-900" role="note">
                <p class="font-bold">Usa esta opción solo si el profesional no puede entregar sus casos.</p>
                <p class="mt-1">Lo correcto es que {{ $nombre }} los cierre o los remita, porque conoce a cada estudiante. Este traslado
                    cancela las citas futuras y las tareas pendientes de cada caso, y queda registrado a tu nombre en la auditoría.
                    El sistema no avisa a los estudiantes: alguien de Bienestar debe contactarlos.</p>
            </div>

            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-600 text-sm font-medium rounded-lg px-4 py-3" role="alert">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-600 text-sm font-medium rounded-lg px-4 py-3" role="alert">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.traslados.store', $usuario) }}" class="flex flex-col gap-6">
                @csrf

                <!-- 2. CASOS -->
                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6">
                    <h2 class="text-base font-bold text-gray-900">1. Elige quién recibe cada caso</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Solo aparecen profesionales activos del área del caso. Si dejas un caso sin destino, sigue a cargo de {{ $nombre }}.</p>

                    <ul class="mt-4 flex flex-col gap-3">
                        @foreach ($casos as $caso)
                            @php
                                $opciones = $destinos[$caso->solicitud?->tipo_servicio] ?? collect();
                                $citasPendientes = $caso->citas->filter(fn ($cita) => $cita->asistenciaPendiente() && $cita->esProxima())->count();
                            @endphp
                            <li class="border border-gray-200 rounded-xl p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 break-words">
                                        {{ $caso->solicitud?->persona?->nombre_completo }}
                                        @if ($caso->esEmergencia())
                                            <span class="ml-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[11px] font-bold">Emergencia</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $caso->codigo_expediente }} · {{ $caso->solicitud?->tipo_servicio }}
                                        · Riesgo: {{ $caso->nivel_riesgo ?? 'sin definir' }}
                                        · {{ $citasPendientes === 1 ? '1 cita próxima' : $citasPendientes.' citas próximas' }}
                                    </p>
                                </div>
                                <div>
                                    <label for="destino-{{ $caso->id_caso }}" class="sr-only">Profesional que recibe el caso {{ $caso->codigo_expediente }}</label>
                                    @if ($opciones->isEmpty())
                                        <p class="text-sm text-amber-700">No hay otro profesional activo en esta área. Designa uno en Administración.</p>
                                    @else
                                        <select id="destino-{{ $caso->id_caso }}" name="destinos[{{ $caso->id_caso }}]" class="{{ $input }}">
                                            <option value="">Sin trasladar</option>
                                            @foreach ($opciones as $profesional)
                                                <option value="{{ $profesional->id_usuario }}" @selected(old('destinos.'.$caso->id_caso) === $profesional->id_usuario)>
                                                    {{ $profesional->persona?->nombre_completo ?? $profesional->codigo_unico }} · {{ $profesional->casos_activos === 1 ? '1 caso activo' : $profesional->casos_activos.' casos activos' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <!-- 3. JUSTIFICACIÓN -->
                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <h2 class="sm:col-span-2 text-base font-bold text-gray-900">2. Justifica el traslado</h2>

                    <div class="sm:col-span-2">
                        <label for="motivo" class="block text-sm font-medium text-gray-700 mb-1.5">¿Por qué no puede entregar sus casos?</label>
                        <select id="motivo" name="motivo" required class="{{ $input }}">
                            <option value="">Selecciona…</option>
                            @foreach ($motivos as $motivo)
                                <option value="{{ $motivo }}" @selected(old('motivo') === $motivo)>{{ $motivo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="justificacion" class="block text-sm font-medium text-gray-700 mb-1.5">Justificación</label>
                        <textarea id="justificacion" name="justificacion" rows="3" required minlength="10" maxlength="{{ $justificacionMax }}" class="{{ $input }}">{{ old('justificacion') }}</textarea>
                    </div>
                    <div>
                        <label for="autoriza_nombre" class="block text-sm font-medium text-gray-700 mb-1.5">Nombre de quien autorizó</label>
                        <input type="text" id="autoriza_nombre" name="autoriza_nombre" value="{{ old('autoriza_nombre') }}" required maxlength="150" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="autoriza_cargo" class="block text-sm font-medium text-gray-700 mb-1.5">Cargo de quien autorizó</label>
                        <input type="text" id="autoriza_cargo" name="autoriza_cargo" value="{{ old('autoriza_cargo') }}" required maxlength="100" class="{{ $input }}">
                    </div>
                    <label class="sm:col-span-2 flex items-start gap-3 text-sm text-gray-700">
                        <input type="checkbox" name="confirmacion" value="1" required @checked(old('confirmacion')) class="mt-0.5 rounded text-[#007F2F] focus:ring-[#007F2F]">
                        <span>Confirmo que {{ $nombre }} no puede cerrar ni remitir estos casos, y que entiendo que se cancelarán sus citas futuras y tareas pendientes.</span>
                    </label>
                </section>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.administracion') }}" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</a>
                    <button type="submit" class="bg-[#007F2F] hover:bg-[#006826] text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors">Trasladar casos</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

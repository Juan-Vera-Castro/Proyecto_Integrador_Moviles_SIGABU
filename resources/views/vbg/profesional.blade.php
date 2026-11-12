@php
    use App\Models\Vbg\Reporte;

    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#7E22CE] focus:ring-[#7E22CE]';
    $colorEstado = [Reporte::RECIBIDO => 'bg-amber-50 text-amber-700', Reporte::EN_ATENCION => 'bg-sky-50 text-sky-700', Reporte::CERRADO => 'bg-gray-100 text-gray-600'];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#7E22CE]">Violencias de Género</h1>
                <p class="text-gray-500 text-sm">Activa la ruta de atención en VBG para un estudiante que atiendes</p>
            </div>
        </div>

        <div class="p-4 sm:p-8 max-w-5xl mx-auto w-full grid grid-cols-1 lg:grid-cols-5 gap-6">

            <form method="POST" action="{{ route('profesional.vbg.store') }}" class="lg:col-span-3 bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-4">
                @csrf
                <h2 class="text-base font-bold text-gray-900">Reportar un caso al equipo VBG</h2>
                <p class="text-xs text-gray-500">El reporte queda a tu nombre, en representación de la persona afectada. Solo lo ve el equipo de Violencias de Género.</p>

                @if (session('status'))
                    <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium rounded-lg px-4 py-3" role="alert">{{ session('error') }}</div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="afectado_nombre" class="block text-sm font-medium text-gray-700 mb-1.5">Persona afectada</label>
                        <input id="afectado_nombre" name="afectado_nombre" type="text" maxlength="150" required value="{{ old('afectado_nombre') }}" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('afectado_nombre')" class="mt-1" />
                    </div>
                    <div>
                        <label for="afectado_vinculo" class="block text-sm font-medium text-gray-700 mb-1.5">Vínculo con la universidad <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <input id="afectado_vinculo" name="afectado_vinculo" type="text" maxlength="100" value="{{ old('afectado_vinculo') }}" placeholder="Ej. estudiante de Enfermería" class="{{ $input }}">
                    </div>
                </div>
                <div>
                    <label for="relato" class="block text-sm font-medium text-gray-700 mb-1.5">Situación</label>
                    <textarea id="relato" name="relato" rows="5" required maxlength="{{ Reporte::RELATO_MAX }}" class="{{ $input }} resize-y">{{ old('relato') }}</textarea>
                    <x-input-error :messages="$errors->get('relato')" class="mt-1" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="tipo_violencia" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de violencia</label>
                        <select id="tipo_violencia" name="tipo_violencia" class="{{ $input }}">
                            <option value="">Sin determinar</option>
                            @foreach (Reporte::TIPOS_VIOLENCIA as $tipo)
                                <option value="{{ $tipo }}" @selected(old('tipo_violencia') === $tipo)>{{ $tipo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="lugar" class="block text-sm font-medium text-gray-700 mb-1.5">Lugar <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <input id="lugar" name="lugar" type="text" maxlength="150" value="{{ old('lugar') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="fecha_hechos" class="block text-sm font-medium text-gray-700 mb-1.5">Fecha <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <input id="fecha_hechos" name="fecha_hechos" type="date" max="{{ $hoy }}" value="{{ old('fecha_hechos') }}" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('fecha_hechos')" class="mt-1" />
                    </div>
                </div>
                <input type="hidden" name="desea_contacto" value="1">
                <div>
                    <label for="medio_contacto" class="block text-sm font-medium text-gray-700 mb-1.5">Cómo te contacta el equipo VBG</label>
                    <input id="medio_contacto" name="medio_contacto" type="text" maxlength="150" required value="{{ old('medio_contacto', auth()->user()->persona?->correo_inst) }}" class="{{ $input }}">
                    <x-input-error :messages="$errors->get('medio_contacto')" class="mt-1" />
                </div>
                <label class="flex items-start gap-2.5 text-sm text-gray-800 bg-gray-50 border border-gray-200 rounded-xl p-4">
                    <input type="checkbox" name="acepta_datos" value="1" required @checked(old('acepta_datos')) class="mt-0.5 rounded border-gray-300 text-[#7E22CE] focus:ring-[#7E22CE]">
                    <span>La persona afectada conoce el reporte y autoriza el tratamiento confidencial de su información (Ley 1581 de 2012).</span>
                </label>
                <x-input-error :messages="$errors->get('acepta_datos')" class="-mt-2" />
                <button type="submit" class="px-5 py-2.5 bg-[#7E22CE] hover:bg-[#6B21A8] text-white text-sm font-bold rounded-xl transition">Enviar al equipo VBG</button>
            </form>

            <section class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl shadow-sm p-5" aria-labelledby="mis-reportes">
                <h2 id="mis-reportes" class="text-base font-bold text-gray-900 mb-3">Mis reportes</h2>
                @if ($misReportes->isEmpty())
                    <p class="text-sm text-gray-500">Todavía no has reportado casos.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($misReportes as $reporte)
                            <li class="py-3 space-y-0.5">
                                <div class="flex items-center justify-between gap-2">
                                    <strong>{{ $reporte->codigo }}</strong>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $colorEstado[$reporte->estado] }}">{{ $reporte->estado }}</span>
                                </div>
                                <p class="text-xs text-gray-500">{{ $reporte->etiquetaAfectado() }} · {{ $reporte->fecha_reporte->format('Y-m-d') }}</p>
                                @if ($reporte->profesional)
                                    <p class="text-xs text-gray-500">A cargo de {{ $reporte->profesional->persona?->nombre_completo }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>

@php
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $errores = $errors->getBag('modelo');
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Modelos de consentimiento</h1>
                <p class="text-gray-500 text-sm">Textos de consentimiento informado por servicio y sus versiones</p>
            </div>
            <a href="{{ route('admin.administracion') }}" class="w-10 h-10 flex items-center justify-center bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors"
               title="Volver a Administración" aria-label="Cerrar y volver a Administración">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </a>
        </div>

        <!-- 2. CONTENIDO -->
        <div class="p-4 sm:p-8 max-w-5xl mx-auto w-full flex flex-col gap-6">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
            @endif

            <!-- Exigencia del consentimiento firmado (D-28, D-29) -->
            <div @class(['rounded-xl p-4 text-xs sm:text-sm border', 'bg-gray-50 border-gray-200 text-gray-700' => ! $exigencia['exigir'], 'bg-amber-50 border-amber-200 text-amber-900' => $exigencia['exigir'] && $exigencia['faltantes'], 'bg-green-50 border-green-200 text-[#1F5134]' => $exigencia['exigir'] && ! $exigencia['faltantes']])>
                <p class="font-bold">
                    Consentimiento firmado: {{ $exigencia['exigir'] ? 'se exige para atender' : 'todavía no se exige' }}
                    @if ($exigencia['exigir'] && $exigencia['desde'])
                        (casos abiertos desde el {{ $exigencia['desde'] }})
                    @endif
                </p>
                @if (! $exigencia['exigir'])
                    <p class="mt-1">Se activa con SIGABU_EXIGIR_CONSENTIMIENTO=true en el .env del servidor, cuando esté todo lo de abajo.</p>
                @endif
                @if ($exigencia['faltantes'])
                    <p class="mt-1">Falta: {{ implode('; ', $exigencia['faltantes']) }}. Mientras falte, los casos de ese servicio no se bloquean.</p>
                @else
                    <p class="mt-1">Están todos los textos publicados y el almacenamiento de firmas configurado.</p>
                @endif
            </div>

            <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">
                Cada servicio usa el texto de la <strong>versión publicada</strong> de su modelo activo. Al abrir un caso, el profesional registra que el solicitante lo aceptó y queda guardada la versión exacta.
                Una versión publicada no se edita: para cambiar el texto se crea una versión nueva.
            </div>

            <!-- Listado -->
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="relative overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3">Modelo</th>
                                <th class="px-5 py-3">Servicio</th>
                                <th class="px-5 py-3">Versión vigente</th>
                                <th class="px-5 py-3">Estado</th>
                                <th class="px-5 py-3"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($modelos as $modelo)
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-gray-800">{{ $modelo->nombre }}</p>
                                        <p class="text-xs text-gray-400">{{ $modelo->codigo }} · {{ $modelo->versiones_count }} {{ $modelo->versiones_count === 1 ? 'versión' : 'versiones' }}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        {{ $modelo->tipo_servicio ?? '—' }}
                                        @if ($modelo->esDeMenores())
                                            <span class="ms-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700">Menores</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($modelo->versionVigente)
                                            <span class="font-semibold text-gray-800">v{{ $modelo->versionVigente->numero_version }}</span>
                                            <span class="text-xs text-gray-400">· {{ $modelo->versionVigente->fecha_publicacion?->format('Y-m-d') }}</span>
                                        @else
                                            <span class="text-xs text-amber-700">Sin versión publicada</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($modelo->esLegado())
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold border bg-gray-100 text-gray-600 border-gray-200">Histórico</span>
                                        @elseif ($modelo->activo)
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold border bg-green-50 text-[#007F2F] border-green-200">Activo</span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold border bg-gray-100 text-gray-600 border-gray-200">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('admin.consentimientos.show', $modelo) }}" class="text-[#007F2F] font-semibold text-sm hover:underline">Ver versiones</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">Aún no hay modelos de consentimiento.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Nuevo modelo -->
            <details class="bg-white border border-gray-200 rounded-2xl shadow-sm" @if ($errores->any()) open @endif>
                <summary class="px-5 py-4 cursor-pointer text-sm font-bold text-[#007F2F] select-none">+ Nuevo modelo de consentimiento</summary>
                <form method="POST" action="{{ route('admin.consentimientos.store') }}" class="px-5 pb-5 pt-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    <div>
                        <label for="codigo" class="block text-sm font-medium text-gray-700 mb-1.5">Código</label>
                        <input id="codigo" name="codigo" type="text" required maxlength="50" value="{{ old('codigo') }}" placeholder="SM-CONSENT"
                               @if ($errores->has('codigo')) aria-invalid="true" aria-describedby="codigo-error" @endif
                               @class([$input, 'uppercase', 'border-red-500' => $errores->has('codigo'), 'border-gray-300' => ! $errores->has('codigo')])>
                        <x-input-error id="codigo-error" :messages="$errores->get('codigo')" class="mt-2" />
                    </div>
                    <div>
                        <label for="tipo_servicio" class="block text-sm font-medium text-gray-700 mb-1.5">Servicio</label>
                        <select id="tipo_servicio" name="tipo_servicio" required
                                @if ($errores->has('tipo_servicio')) aria-invalid="true" aria-describedby="tipo_servicio-error" @endif
                                @class([$input, 'border-red-500' => $errores->has('tipo_servicio'), 'border-gray-300' => ! $errores->has('tipo_servicio')])>
                            @foreach ($servicios as $servicio)
                                <option value="{{ $servicio }}" @selected(old('tipo_servicio') === $servicio)>{{ $servicio }}</option>
                            @endforeach
                        </select>
                        <x-input-error id="tipo_servicio-error" :messages="$errores->get('tipo_servicio')" class="mt-2" />
                    </div>
                    <div>
                        <label for="poblacion" class="block text-sm font-medium text-gray-700 mb-1.5">Población</label>
                        <select id="poblacion" name="poblacion" required
                                @class([$input, 'border-red-500' => $errores->has('poblacion'), 'border-gray-300' => ! $errores->has('poblacion')])>
                            @foreach ($poblaciones as $poblacion)
                                <option value="{{ $poblacion }}" @selected(old('poblacion', 'General') === $poblacion)>{{ $poblacion === 'Menores' ? 'Menores de edad (firma el acudiente)' : 'General' }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errores->get('poblacion')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
                        <input id="nombre" name="nombre" type="text" required maxlength="150" value="{{ old('nombre') }}" placeholder="Consentimiento informado de atención psicológica"
                               @if ($errores->has('nombre')) aria-invalid="true" aria-describedby="nombre-error" @endif
                               @class([$input, 'border-red-500' => $errores->has('nombre'), 'border-gray-300' => ! $errores->has('nombre')])>
                        <x-input-error id="nombre-error" :messages="$errores->get('nombre')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1.5">Descripción <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <textarea id="descripcion" name="descripcion" rows="2" maxlength="500"
                                  @class([$input, 'resize-y', 'border-red-500' => $errores->has('descripcion'), 'border-gray-300' => ! $errores->has('descripcion')])>{{ old('descripcion') }}</textarea>
                        <x-input-error :messages="$errores->get('descripcion')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end">
                        <button type="submit" class="bg-[#6FD835] hover:bg-[#5ec22b] text-black font-semibold px-6 py-2.5 rounded-lg shadow-sm transition-colors">Crear modelo</button>
                    </div>
                </form>
            </details>
        </div>
    </div>
</x-app-layout>

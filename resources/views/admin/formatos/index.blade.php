@php
    use App\Models\FormatoInstitucional;

    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Formatos institucionales</h1>
                <p class="text-gray-500 text-sm">Código, versión y fecha que Calidad asigna a los formatos que genera el sistema</p>
            </div>
            <a href="{{ route('admin.administracion') }}" class="w-10 h-10 flex items-center justify-center bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors"
               title="Volver a Administración" aria-label="Cerrar y volver a Administración">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </a>
        </div>

        <div class="p-4 sm:p-8 max-w-4xl mx-auto w-full flex flex-col gap-6">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
            @endif

            <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-xs sm:text-sm text-[#1F5134] font-medium leading-relaxed">
                Cuando Calidad actualice un formato, cambia aquí su código, versión o fecha. Los documentos nuevos saldrán con los datos actualizados;
                las remisiones ya registradas conservan el formato con que se generaron.
            </div>

            @forelse ($formatos as $formato)
                @php $errores = $errors->getBag('formato-'.$formato->id_formato); @endphp
                <form method="POST" action="{{ route('admin.formatos.update', $formato) }}" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @csrf
                    @method('PATCH')
                    <div class="sm:col-span-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-base font-bold text-gray-900">{{ $formato->nombre }}</h2>
                        <p class="text-xs text-gray-500">
                            Vigente: {{ $formato->codigo }} · VERSIÓN: {{ $formato->version }} · FECHA: {{ FormatoInstitucional::fechaComoFormato($formato->fecha_formato) }}
                            @if ($formato->autor)
                                · actualizado por {{ $formato->autor->persona?->nombre_completo }} el {{ $formato->fecha_actualizacion->format('Y-m-d') }}
                            @endif
                        </p>
                    </div>

                    @foreach ([
                        ['proceso', 'Proceso', 'text', $formato->proceso, 'sm:col-span-2'],
                        ['nombre', 'Nombre del formato', 'text', $formato->nombre, ''],
                        ['codigo', 'Código', 'text', $formato->codigo, ''],
                        ['version', 'Versión', 'text', $formato->version, ''],
                        ['fecha_formato', 'Fecha del formato', 'date', $formato->fecha_formato->format('Y-m-d'), ''],
                    ] as [$campo, $etiqueta, $tipo, $valor, $ancho])
                        <div class="{{ $ancho }}">
                            <label for="{{ $campo }}-{{ $formato->id_formato }}" class="block text-sm font-medium text-gray-700 mb-1.5">{{ $etiqueta }}</label>
                            <input id="{{ $campo }}-{{ $formato->id_formato }}" name="{{ $campo }}" type="{{ $tipo }}" required
                                   value="{{ $errores->any() ? old($campo) : $valor }}"
                                   @if ($errores->has($campo)) aria-invalid="true" aria-describedby="{{ $campo }}-{{ $formato->id_formato }}-error" @endif
                                   @class([$input, 'border-red-500' => $errores->has($campo), 'border-gray-300' => ! $errores->has($campo)])>
                            <x-input-error id="{{ $campo }}-{{ $formato->id_formato }}-error" :messages="$errores->get($campo)" class="mt-2" />
                        </div>
                    @endforeach

                    <div class="sm:col-span-3 flex justify-end">
                        <button type="submit" class="px-5 py-2 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition-colors shadow-sm">Guardar formato</button>
                    </div>
                </form>
            @empty
                <div class="bg-white border border-gray-200 rounded-2xl p-10 text-center text-sm text-gray-400">No hay formatos configurados.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>

@props([
    'titulo',
    'subtitulo',
    'color' => '#007F2F',
    // Lista de módulos: [['icono' => '📦', 'nombre' => '...', 'descripcion' => '...'], ...]
    'modulos' => [],
])

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold" style="color: {{ $color }}">{{ $titulo }} · SIGABU</h1>
                <p class="text-gray-500 text-sm">{{ $subtitulo }}</p>
            </div>
        </div>

        <!-- 2. CONTENIDO -->
        <div class="p-4 sm:p-8 max-w-[1400px] mx-auto w-full flex-1 flex flex-col gap-6">
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-[#0a4220]">Buen día, {{ Auth::user()->name }} 👋</h2>
                <p class="text-gray-500 mt-1">Estos son los módulos disponibles para tu rol.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach ($modulos as $modulo)
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm flex flex-col gap-3">
                        <div class="w-11 h-11 rounded-lg flex items-center justify-center text-2xl" style="background-color: {{ $color }}1A">
                            {{ $modulo['icono'] }}
                        </div>
                        <h3 class="font-bold text-gray-800">{{ $modulo['nombre'] }}</h3>
                        <p class="text-sm text-gray-500 flex-1">{{ $modulo['descripcion'] }}</p>
                        <span class="self-start text-[11px] font-semibold uppercase tracking-wider text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full">Próximamente</span>
                    </div>
                @endforeach
            </div>

            {{ $slot }}
        </div>
    </div>
</x-app-layout>

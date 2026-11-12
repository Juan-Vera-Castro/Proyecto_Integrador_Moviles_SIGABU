{{-- Catálogo de recursos pedagógicos de la Unidad de Inclusión. --}}
<x-app-layout>
    <div class="p-4 sm:p-6 space-y-4 max-w-4xl">
        <div class="bg-white p-4 sm:px-6 rounded-2xl shadow-sm border border-gray-100">
            <h1 class="text-2xl font-bold text-[#007F2F]">Recursos de inclusión</h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Orientaciones pedagógicas de la Unidad de Inclusión, por condición.</p>
        </div>

        @forelse ($recursos as $recurso)
            <a href="{{ route('inclusion.recursos.show', $recurso) }}"
               class="block bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-[#007F2F] transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                <p class="font-bold text-gray-900">{{ $recurso->titulo }}</p>
                <p class="text-sm text-gray-600 mt-0.5">{{ $recurso->condicion }} · versión {{ $recurso->version }}</p>
            </a>
        @empty
            <p class="bg-white p-4 rounded-2xl border border-gray-100 text-sm text-gray-600">Aún no hay recursos publicados.</p>
        @endforelse
    </div>
</x-app-layout>

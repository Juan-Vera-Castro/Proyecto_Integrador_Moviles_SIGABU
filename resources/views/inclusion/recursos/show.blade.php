{{-- Recurso pedagógico con su índice. El texto se transcribe literal (RN-36). --}}
@php
    $secciones = collect($recurso->contenido)->filter(fn ($bloque) => $bloque['tipo'] === 'titulo');
@endphp

<x-app-layout>
    <div class="p-4 sm:p-6 space-y-4">
        <div class="bg-white p-4 sm:px-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-[#007F2F]">{{ $recurso->titulo }}</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">{{ $recurso->condicion }} · versión {{ $recurso->version }}</p>
            </div>
            <a href="{{ route('inclusion.recursos.index') }}" class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
               title="Volver al catálogo" aria-label="Volver al catálogo">✕</a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[16rem_minmax(0,1fr)] gap-4 items-start">
            <nav aria-label="Índice del recurso" class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 lg:sticky lg:top-4">
                <p class="text-sm font-bold text-gray-900 mb-2">Índice</p>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($secciones as $indice => $seccion)
                        <li><a href="#seccion-{{ $indice }}" class="text-[#006324] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">{{ $seccion['texto'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <article class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-100 min-w-0">
                @include('inclusion.recursos.partials.bloques', ['bloques' => $recurso->contenido, 'prefijo' => 'seccion'])
            </article>
        </div>
    </div>
</x-app-layout>

{{-- Búsqueda y filtros de un listado de casos. Recibe $ruta, $filtros, $estados, $niveles y $hayFiltros. --}}
@php
    $actual = fn (string $clave, ?string $valor) => ($filtros[$clave] ?? null) === $valor;
    $enlace = fn (array $cambios) => route($ruta, array_filter(array_merge($filtros, $cambios), fn ($valor) => $valor !== null && $valor !== ''));
    $boton = 'px-3.5 py-1.5 text-xs rounded-xl transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2';
    $activo = 'bg-[#007F2F] text-white font-bold shadow-sm';
    $inactivo = 'bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium';
@endphp

<div class="bg-white p-3 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
    <form method="GET" action="{{ route($ruta) }}" class="flex flex-wrap items-center gap-2" role="search">
        @foreach (['estado', 'riesgo'] as $clave)
            @if (! empty($filtros[$clave]))
                <input type="hidden" name="{{ $clave }}" value="{{ $filtros[$clave] }}">
            @endif
        @endforeach
        <div class="relative flex-1 min-w-full sm:min-w-[220px]">
            <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <label for="buscar-casos" class="sr-only">Buscar por nombre, documento o código</label>
            <input id="buscar-casos" type="search" name="q" value="{{ $filtros['q'] ?? '' }}" maxlength="100" placeholder="Buscar por nombre, documento o código..."
                   class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:outline-none focus:border-[#007F2F] focus:ring-[#007F2F]">
        </div>
        @if ($filtrosInclusion ?? false)
            <div class="w-full sm:w-auto">
                <label for="filtro-programa" class="block text-[11px] font-bold text-gray-500 mb-0.5">Programa</label>
                <input id="filtro-programa" type="text" name="programa" value="{{ $filtros['programa'] ?? '' }}" maxlength="150" class="w-full sm:w-44 py-1.5 bg-gray-50 border-gray-200 rounded-xl text-xs text-gray-700 focus:border-[#007F2F] focus:ring-[#007F2F]">
            </div>
            <div class="w-[calc(50%-0.25rem)] sm:w-auto">
                <label for="filtro-desde" class="block text-[11px] font-bold text-gray-500 mb-0.5">Abierto desde</label>
                <input id="filtro-desde" type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="w-full py-1.5 bg-gray-50 border-gray-200 rounded-xl text-xs text-gray-700 focus:border-[#007F2F] focus:ring-[#007F2F]">
            </div>
            <div class="w-[calc(50%-0.25rem)] sm:w-auto">
                <label for="filtro-hasta" class="block text-[11px] font-bold text-gray-500 mb-0.5">Hasta</label>
                <input id="filtro-hasta" type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="w-full py-1.5 bg-gray-50 border-gray-200 rounded-xl text-xs text-gray-700 focus:border-[#007F2F] focus:ring-[#007F2F]">
            </div>
            <x-input-error :messages="array_merge($errors->get('desde'), $errors->get('hasta'))" class="w-full" />
        @endif
        <button type="submit" class="{{ $boton }} {{ $activo }}">Buscar</button>
        @if ($hayFiltros)
            <a href="{{ route($ruta) }}" class="{{ $boton }} {{ $inactivo }}">Limpiar filtros</a>
        @endif
    </form>

    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide me-1">Estado</span>
        <a href="{{ $enlace(['estado' => null]) }}" @class([$boton, $activo => $actual('estado', null), $inactivo => ! $actual('estado', null)]) @if ($actual('estado', null)) aria-current="true" @endif>Todos</a>
        @foreach ($estados as $estado)
            <a href="{{ $enlace(['estado' => $estado]) }}" @class([$boton, $activo => $actual('estado', $estado), $inactivo => ! $actual('estado', $estado)]) @if ($actual('estado', $estado)) aria-current="true" @endif>{{ $estado }}</a>
        @endforeach

        <div class="h-4 w-px bg-gray-300 mx-1" aria-hidden="true"></div>

        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide me-1">Riesgo</span>
        <a href="{{ $enlace(['riesgo' => null]) }}" @class([$boton, $activo => $actual('riesgo', null), $inactivo => ! $actual('riesgo', null)]) @if ($actual('riesgo', null)) aria-current="true" @endif>Todos</a>
        @foreach ($niveles as $nivel)
            <a href="{{ $enlace(['riesgo' => $nivel]) }}" @class([$boton, $activo => $actual('riesgo', $nivel), $inactivo => ! $actual('riesgo', $nivel)]) @if ($actual('riesgo', $nivel)) aria-current="true" @endif>• {{ $nivel }}</a>
        @endforeach
    </div>
</div>

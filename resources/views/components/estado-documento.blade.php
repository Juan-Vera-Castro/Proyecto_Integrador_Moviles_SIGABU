{{-- Estado de un formato de Inclusión en el expediente. El texto siempre acompaña al color. --}}
@props(['estado'])

@php
    $colores = [
        'Borrador' => 'bg-gray-100 text-gray-700',
        'Completado' => 'bg-sky-50 text-sky-800',
        'Firmado' => 'bg-emerald-50 text-emerald-800',
        'Revocado' => 'bg-rose-50 text-rose-800',
        'Rechazado' => 'bg-amber-50 text-amber-800',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold '.($colores[$estado] ?? $colores['Borrador'])]) }}>{{ $estado }}</span>

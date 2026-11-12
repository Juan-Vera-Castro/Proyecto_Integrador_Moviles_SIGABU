{{-- Lienzo de firma: debe ir dentro de un elemento con x-data="firmaTactil(...)". Con el dedo, lápiz o mouse. --}}
@props(['ayuda', 'etiqueta' => 'Área de firma'])

<canvas x-ref="canvas" role="img" aria-describedby="{{ $ayuda }}" aria-label="{{ $etiqueta }}"
        @pointerdown="empezar($event)" @pointermove="mover($event)" @pointerup="terminar()" @pointercancel="terminar()" @pointerleave="terminar()"
        {{ $attributes->merge(['class' => 'w-full aspect-[2/1] sm:aspect-[8/3] rounded-xl border-2 border-dashed border-gray-300 bg-white cursor-crosshair']) }}
        style="touch-action: none;"></canvas>

{{-- Evidencias ya guardadas: archivos por la aplicación y links externos. Variable: $evidencias. --}}
<ul class="space-y-1 text-xs">
    @foreach ($evidencias as $evidencia)
        <li class="break-words">
            @if ($evidencia->esArchivo())
                <a href="{{ route('inclusion.evidencias.archivo', $evidencia) }}" class="font-semibold text-[#006324] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">{{ $evidencia->nombre }}</a>
            @else
                <a href="{{ $evidencia->url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#006324] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded">{{ $evidencia->url }}<span class="sr-only"> (abre en otra pestaña)</span></a>
            @endif
        </li>
    @endforeach
</ul>

{{-- Si el caso tiene historia clínica. Solo para el profesional asignado, que es quien la consulta;
     requiere withExists('historiaClinica') en la consulta. Recibe $caso y $forma (clases de tamaño). --}}
@if ($caso->esDeSaludMental() && $caso->id_profesional === auth()->id())
    @if ($caso->historia_clinica_exists)
        <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 font-semibold {{ $forma }}">✓ Historia clínica</span>
    @elseif ($caso->estaActivo())
        <span class="px-2.5 py-0.5 bg-amber-50 text-amber-700 font-semibold {{ $forma }}">Sin historia clínica</span>
    @endif
@endif

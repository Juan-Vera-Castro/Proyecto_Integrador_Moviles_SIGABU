{{-- Distintivo de un caso que el estudiante marcó como emergencia (atención urgente). Recibe $caso. --}}
@if ($caso->esEmergencia())
    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-rose-600 text-white text-[11px] font-bold rounded-full align-middle" title="El estudiante pidió atención urgente">
        <span aria-hidden="true">🚨</span> Emergencia
    </span>
@endif

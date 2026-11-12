{{-- Datos de una solicitud para el profesional: facultad, jornada, franja y contacto. Sin el motivo:
     el estudiante ya no lo escribe al solicitar. Recibe $solicitud, $persona y $consentimiento. --}}
<dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
    <div>
        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Facultad y horario</dt>
        <dd class="text-gray-900 mt-0.5">{{ $solicitud?->etiquetaAtencion() ?? 'Sin dato (solicitud anterior)' }}</dd>
    </div>
    <div>
        <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Contacto</dt>
        <dd class="text-gray-900 mt-0.5 break-words">{{ $persona?->correo_inst }}</dd>
    </div>
    @if ($consentimiento?->aceptado)
        <div class="sm:col-span-2 text-xs text-gray-500">
            Consentimiento informado aceptado el {{ $consentimiento->fecha_hora_aceptacion->format('Y-m-d') }}
            @if ($consentimiento->version) (versión {{ $consentimiento->version->numero_version }}) @endif
        </div>
    @endif
</dl>

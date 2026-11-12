{{-- P3: formatos de Inclusión del caso. Una tarjeta por documento en celular y una fila desde md. --}}
@php
    use App\Documentos\ConsentimientoInclusion;
    use App\Models\Inclusion\Borrador;

    $ultima = $caso->aceptacionesConsentimiento->first();
    $vigente = $caso->consentimientoVigente();
    $hayBorrador = Borrador::activos()->where('id_caso', $caso->id_caso)->where('documento', Borrador::CONSENTIMIENTO)->exists();
    $bitacora = $caso->bitacora;
    $estadoBitacora = $bitacora?->estadoDocumento()
        ?? (Borrador::activos()->where('id_caso', $caso->id_caso)->where('documento', Borrador::BITACORA)->exists() ? 'Borrador' : 'Sin diligenciar');
    $ajustesDelCaso = $caso->relationLoaded('ajustes') ? $caso->ajustes : collect();
    $ultimoAjuste = $ajustesDelCaso->first();
    $estadoPlantilla = match (true) {
        $ajustesDelCaso->isNotEmpty() => 'Completado',
        Borrador::activos()->where('id_caso', $caso->id_caso)->where('documento', Borrador::PLANTILLA)->exists() => 'Borrador',
        default => 'Sin diligenciar',
    };
    $autor = match (true) {
        $ultima === null => '—',
        $ultima->esEnPapel() => $ultima->profesional?->persona?->nombre_completo,
        $ultima->loOtorgaAcudiente() => $ultima->acudiente_nombre,
        default => $ultima->detalleInclusion?->nombre_persona ?? $caso->solicitud?->persona?->nombre_completo,
    };
    $enlace = 'text-xs font-bold text-[#006324] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded';
@endphp

<section aria-labelledby="inclusion-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
    <h2 id="inclusion-titulo" class="text-base font-bold text-gray-900 mb-3">Documentos de Inclusión</h2>


    <div class="hidden md:grid md:grid-cols-[2fr_1fr_1fr_2fr_2fr] gap-3 px-3 pb-2 text-xs font-semibold text-gray-500" aria-hidden="true">
        <span>Documento</span><span>Estado</span><span>Fecha</span><span>Autor</span><span>Acciones</span>
    </div>

    <div class="space-y-2">
        <article class="p-3 bg-gray-50 rounded-xl border border-gray-100 grid grid-cols-1 md:grid-cols-[2fr_1fr_1fr_2fr_2fr] gap-2 md:gap-3 md:items-center text-sm">
            <h3 class="font-semibold text-gray-800">Consentimiento informado</h3>
            <div><x-estado-documento :estado="$caso->estadoConsentimiento() ?? ($hayBorrador ? 'Borrador' : 'Sin diligenciar')" /></div>
            <p class="text-xs text-gray-600"><span class="md:hidden">Fecha: </span>{{ $ultima?->fecha_hora?->format('d/m/Y') ?? '—' }}</p>
            <p class="text-xs text-gray-600 break-words"><span class="md:hidden">Autor: </span>{{ $autor }}</p>
            <div class="flex flex-wrap gap-x-3 gap-y-1">
                @if ($ultima)
                    <a href="{{ route('inclusion.consentimiento.ver', $caso) }}" class="{{ $enlace }}">Ver</a>
                @endif
                @if ($caso->aceptacionesConsentimiento->contains(fn ($fila) => $fila->esAceptado()))
                    <a href="{{ route('inclusion.consentimiento.pdf', $caso) }}" class="{{ $enlace }}">PDF</a>
                @endif
                @if ($vigente?->faltaFirmaProfesional() && $vigente->id_profesional === auth()->id())
                    <a href="{{ route('casos.show', $caso) }}#consentimiento" class="{{ $enlace }}">Contrafirmar</a>
                @endif
                @if (! $vigente && $caso->estaActivo() && ConsentimientoInclusion::aplicaA($caso) && Gate::allows('inclusion.gestionar', $caso))
                    <a href="{{ route('inclusion.consentimiento.papel', $caso) }}" class="{{ $enlace }}">Cargar en papel</a>
                @endif
            </div>
        </article>

        <article class="p-3 bg-gray-50 rounded-xl border border-gray-100 grid grid-cols-1 md:grid-cols-[2fr_1fr_1fr_2fr_2fr] gap-2 md:gap-3 md:items-center text-sm">
            <h3 class="font-semibold text-gray-800">Bitácora</h3>
            <div><x-estado-documento :estado="$estadoBitacora" /></div>
            <p class="text-xs text-gray-600"><span class="md:hidden">Fecha: </span>{{ $bitacora?->fecha_actualizacion?->format('d/m/Y') ?? '—' }}</p>
            <p class="text-xs text-gray-600 break-words"><span class="md:hidden">Autor: </span>{{ $bitacora?->profesional?->persona?->nombre_completo ?? '—' }}</p>
            <div class="flex flex-wrap gap-x-3 gap-y-1">
                @can('inclusion.gestionar', $caso)
                    <a href="{{ route('inclusion.bitacora', $caso) }}" class="{{ $enlace }}">{{ $bitacora ? 'Ver' : 'Abrir' }}</a>
                    @if ($bitacora)
                        <a href="{{ route('inclusion.bitacora.pdf', $caso) }}" class="{{ $enlace }}">PDF</a>
                    @endif
                @endcan
            </div>
        </article>

        <article class="p-3 bg-gray-50 rounded-xl border border-gray-100 grid grid-cols-1 md:grid-cols-[2fr_1fr_1fr_2fr_2fr] gap-2 md:gap-3 md:items-center text-sm">
            <h3 class="font-semibold text-gray-800">Plantilla de ajustes</h3>
            <div><x-estado-documento :estado="$estadoPlantilla" /></div>
            <p class="text-xs text-gray-600"><span class="md:hidden">Fecha: </span>{{ $ultimoAjuste?->fecha_registro?->format('d/m/Y') ?? '—' }}</p>
            <p class="text-xs text-gray-600 break-words"><span class="md:hidden">Autor: </span>{{ $ultimoAjuste?->profesional?->persona?->nombre_completo ?? '—' }}</p>
            <div class="flex flex-wrap gap-x-3 gap-y-1">
                @can('inclusion.gestionar', $caso)
                    <a href="{{ route('inclusion.plantilla', $caso) }}" class="{{ $enlace }}">{{ $ajustesDelCaso->isNotEmpty() ? 'Ver' : 'Abrir' }}</a>
                    @if ($ajustesDelCaso->isNotEmpty())
                        <a href="{{ route('inclusion.plantilla.pdf', $caso) }}" class="{{ $enlace }}">PDF</a>
                    @endif
                @endcan
            </div>
        </article>
    </div>
</section>

{{-- Tabla de la plantilla 5.8. Variables: $ajustes y $compartir. Al compartir, los archivos se cuentan sin nombrarlos: su nombre podría revelar un soporte clínico. --}}
<table>
    <thead>
        <tr>
            <th style="width: 12%">Área / Barrera</th>
            <th style="width: 17%">Descripción de la barrera</th>
            <th style="width: 19%">Ajuste o estrategia pedagógica implementada</th>
            <th style="width: 11%">Responsable</th>
            <th style="width: 9%">Fecha</th>
            <th style="width: 14%">Evidencia asociada</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ajustes as $ajuste)
            <tr class="fila">
                <td>{{ $ajuste->etiqueta() }}</td>
                <td class="texto">{{ $ajuste->barrera }}</td>
                <td class="texto">{{ $ajuste->descripcion }}</td>
                <td>{{ $ajuste->responsable ?: '—' }}</td>
                <td>{{ $ajuste->fecha_inicio->format('d/m/Y') }}</td>
                <td>
                    @if ($compartir)
                        @foreach ($ajuste->evidencias->reject->esArchivo() as $enlace)
                            <div>{{ $enlace->url }}</div>
                        @endforeach
                        @php $archivos = $ajuste->evidencias->filter->esArchivo()->count(); @endphp
                        @if ($archivos > 0)
                            <div>{{ $archivos }} {{ $archivos === 1 ? 'archivo' : 'archivos' }} en el expediente</div>
                        @endif
                    @else
                        @include('documentos.partials.evidencias-inclusion', ['evidencias' => $ajuste->evidencias])
                    @endif
                    @if ($ajuste->evidencias->isEmpty())
                        —
                    @endif
                </td>
                <td class="texto">{{ $ajuste->observaciones ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="vacio">La plantilla no tiene filas.</td></tr>
        @endforelse
    </tbody>
</table>

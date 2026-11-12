@php
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#007F2F] focus:ring-[#007F2F]';
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Auditoría de expedientes</h1>
                <p class="text-gray-500 text-sm">Trazabilidad de las acciones sensibles: consentimientos, firmas consultadas, remisiones e historias clínicas</p>
            </div>
        </div>

        <div class="p-4 sm:p-8 max-w-[1400px] mx-auto w-full flex flex-col gap-4">
            <form method="GET" action="{{ route('admin.auditoria') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[240px]">
                    <label for="accion" class="block text-sm font-medium text-gray-700 mb-1.5">Acción</label>
                    <select id="accion" name="accion" class="{{ $input }}" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach ($acciones as $opcion)
                            <option value="{{ $opcion }}" @selected($accion === $opcion)>{{ str_replace('_', ' ', $opcion) }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="px-4 py-2 bg-gray-100 text-sm font-bold rounded-xl">Filtrar</button></noscript>
                <p class="text-xs text-gray-500 pb-2">Los últimos 300 registros. La auditoría no se puede modificar ni borrar.</p>
            </form>

            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm relative overflow-x-auto" aria-label="Registros de auditoría">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase tracking-wide text-left">
                        <tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Acción</th><th class="px-4 py-3">Expediente</th><th class="px-4 py-3">IP</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($registros as $registro)
                            <tr>
                                <td class="px-4 py-2.5 whitespace-nowrap">{{ $registro->fecha_hora?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-2.5">{{ $registro->usuario?->persona?->nombre_completo ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    {{ str_replace('_', ' ', $registro->accion) }}
                                    @if ($registro->accion === \App\Models\AuditoriaExpediente::CASO_TRASLADADO_ADMIN)
                                        <p class="text-xs text-gray-500 mt-0.5 break-words">
                                            De {{ $registro->detalle['origen'] ?? '—' }} a {{ $registro->detalle['destino'] ?? '—' }} · {{ $registro->detalle['motivo'] ?? '—' }}: {{ $registro->detalle['justificacion'] ?? '—' }} · Autorizó: {{ $registro->detalle['autorizo'] ?? '—' }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">{{ $registro->caso?->codigo_expediente ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-500">{{ $registro->ip_origen ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No hay registros de auditoría.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>

@php
    use App\Models\Actuacion;
    use App\Notifications\ContenidoAviso;
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Notificaciones</h1>
                <p class="text-gray-500 text-sm">
                    @if ($sinLeer > 0)
                        Tienes {{ $sinLeer }} {{ $sinLeer === 1 ? 'aviso sin leer' : 'avisos sin leer' }}.
                    @else
                        Estás al día: no tienes avisos sin leer.
                    @endif
                </p>
            </div>

            @if ($sinLeer > 0)
                <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-[#007F2F] border border-[#007F2F] hover:bg-green-50 px-4 py-2 rounded-lg transition-colors">
                        Marcar todas como leídas
                    </button>
                </form>
            @endif
        </div>

        <div class="p-4 sm:p-8 max-w-3xl mx-auto w-full flex flex-col gap-4">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
            @endif

            @forelse ($notificaciones as $aviso)
                @php
                    $leido = $aviso->read_at !== null;
                    $urgente = ($aviso->data['prioridad'] ?? null) === ContenidoAviso::PRIORIDAD_ALTA;
                @endphp
                <article @class([
                    'rounded-2xl border shadow-sm p-5 flex flex-col sm:flex-row sm:items-start gap-4',
                    'bg-white border-gray-200' => $leido,
                    'bg-green-50/60 border-green-200' => ! $leido && ! $urgente,
                    'bg-rose-50 border-rose-200' => ! $leido && $urgente,
                ])>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @unless ($leido)
                                <span class="w-2.5 h-2.5 rounded-full {{ $urgente ? 'bg-rose-500' : 'bg-[#007F2F]' }}" aria-hidden="true"></span>
                                <span class="sr-only">Sin leer.</span>
                            @endunless
                            @if ($urgente)
                                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full">Urgente</span>
                            @endif
                            <h2 class="font-bold text-gray-900">{{ $aviso->data['titulo'] ?? 'Aviso' }}</h2>
                        </div>
                        <p class="text-sm text-gray-600 mt-1.5 leading-relaxed">{{ $aviso->data['mensaje'] ?? '' }}</p>
                        <p class="text-xs text-gray-400 mt-2">
                            <time datetime="{{ $aviso->created_at->toIso8601String() }}">{{ $aviso->created_at->timezone(Actuacion::ZONA_HORARIA)->format('Y-m-d g:i a') }}</time>
                        </p>
                    </div>

                    <form method="POST" action="{{ route('notificaciones.abrir', $aviso->id) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="w-full sm:w-auto text-sm font-semibold text-white bg-[#007F2F] hover:bg-[#006324] px-4 py-2 rounded-lg transition-colors">
                            Ver
                        </button>
                    </form>
                </article>
            @empty
                <div class="bg-white rounded-2xl p-10 shadow-sm border border-gray-100 flex flex-col items-center text-center">
                    <div class="text-5xl mb-4" aria-hidden="true">🔔</div>
                    <h2 class="text-lg font-bold text-gray-800 mb-1">Aún no tienes avisos</h2>
                    <p class="text-gray-500 text-sm max-w-sm">Aquí verás los recordatorios de citas, las solicitudes nuevas y los pendientes de firma.</p>
                </div>
            @endforelse

            {{ $notificaciones->links() }}
        </div>
    </div>
</x-app-layout>

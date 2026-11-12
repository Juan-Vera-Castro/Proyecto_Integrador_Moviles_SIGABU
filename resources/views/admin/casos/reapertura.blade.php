{{-- Reapertura de casos de Inclusión: el administrador solo ve el código, el estado y las fechas. --}}
@php
    use App\Models\Inclusion\Reapertura;
@endphp

<x-app-layout>
    <div class="p-4 sm:p-6 space-y-4 max-w-3xl">
        <div class="bg-white p-4 sm:px-6 rounded-2xl shadow-sm border border-gray-100">
            <h1 class="text-2xl font-bold text-[#007F2F]">Reabrir casos</h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Solo casos de Inclusión cerrados con constancia.</p>
        </div>

        @if (session('status'))
            <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-sm text-[#1F5134] font-medium" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-700 font-medium" role="alert">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('admin.casos.reapertura') }}" role="search" class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col sm:flex-row sm:items-end gap-3">
            <div class="flex-1">
                <label for="codigo" class="block text-sm font-medium text-gray-700 mb-1">Código del caso</label>
                <input type="search" id="codigo" name="codigo" value="{{ $codigo }}" maxlength="20" placeholder="IN-2026-0001" autocomplete="off"
                       class="block w-full rounded-lg border-gray-300 shadow-sm text-base sm:text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]">
            </div>
            <button type="submit" class="px-6 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">Buscar</button>
        </form>

        @if ($codigo !== '' && ! $caso)
            <p class="bg-white p-4 rounded-2xl border border-gray-100 text-sm text-gray-600" role="status">No se encontró un caso de Inclusión con ese código.</p>
        @elseif ($caso)
            <section class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4" aria-labelledby="caso-titulo">
                <h2 id="caso-titulo" class="text-base font-bold text-gray-900">{{ $caso->codigo_expediente }}</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div><dt class="text-xs text-gray-500">Estado</dt><dd class="font-semibold text-gray-900">{{ $caso->estado_caso }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Apertura</dt><dd class="font-semibold text-gray-900">{{ $caso->fecha_apertura?->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Cierre</dt><dd class="font-semibold text-gray-900">{{ $caso->fecha_cierre?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>

                @if ($motivoSinReapertura)
                    <p class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-900" role="status">{{ $motivoSinReapertura }}</p>
                @else
                    <form method="POST" action="{{ route('admin.casos.reabrir', $caso) }}" class="space-y-3"
                          onsubmit="return confirm(@js("¿Reabrir el caso {$caso->codigo_expediente}? Para cerrarlo de nuevo se necesitará otra entrada de Cierre y otra constancia."))">
                        @csrf
                        <div>
                            <label for="motivo" class="block text-sm font-medium text-gray-700 mb-1">Motivo de la reapertura</label>
                            <textarea id="motivo" name="motivo" rows="3" maxlength="{{ Reapertura::MOTIVO_MAX }}" aria-required="true" aria-describedby="motivo-error"
                                      class="block w-full rounded-lg border-gray-300 shadow-sm text-base sm:text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]">{{ old('motivo') }}</textarea>
                            <x-input-error id="motivo-error" :messages="$errors->get('motivo')" class="mt-1" />
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">Reabrir caso</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>

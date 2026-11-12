{{-- Registro de atenciones del expediente: formulario (casos activos con atención habilitada) e historial. --}}
@php
    use App\Models\Actuacion;
    use App\Models\Atencion;

    $erroresAtencion = $errors->getBag('atencion');
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $ahora = now(Actuacion::ZONA_HORARIA)->format('Y-m-d\TH:i');
    $colorAsistencia = [
        Actuacion::ASISTIO => 'bg-emerald-50 text-emerald-700',
        Actuacion::NO_ASISTIO => 'bg-rose-50 text-rose-700',
        Actuacion::CANCELADA => 'bg-gray-100 text-gray-600',
    ];
    $motivoSinAtencion = $caso->estaActivo() ? $caso->motivoSinAtencion() : null;
@endphp

<section id="registro-atenciones" aria-labelledby="registro-atenciones-titulo" class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
    <div class="flex items-center justify-between mb-4">
        <h2 id="registro-atenciones-titulo" class="text-base font-bold text-gray-900">Registro de atenciones</h2>
        <span class="px-2 py-0.5 bg-emerald-50 text-[#007F2F] text-xs font-bold rounded-full">{{ $caso->atenciones->count() }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            @if (! $caso->estaActivo())
                <p class="text-sm text-gray-500">El caso está en estado "{{ $caso->estado_caso }}": no admite nuevas atenciones.</p>
            @elseif ($motivoSinAtencion)
                <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">{{ $motivoSinAtencion }}</p>
            @else
                <form method="POST" action="{{ route('atenciones.store', $caso) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="tipo-atencion" class="block text-xs font-semibold text-gray-700 mb-1">Tipo de atención <span class="text-rose-600">*</span></label>
                        <select id="tipo-atencion" name="tipo_atencion" required class="{{ $input }}">
                            <option value="">Selecciona el tipo</option>
                            @foreach (Atencion::TIPOS as $tipo)
                                <option value="{{ $tipo }}" @selected(old('tipo_atencion') === $tipo)>{{ $tipo }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$erroresAtencion->get('tipo_atencion')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-3">
                        <div>
                            <label for="fecha-atencion" class="block text-xs font-semibold text-gray-700 mb-1">Fecha y hora <span class="text-rose-600">*</span></label>
                            <input id="fecha-atencion" type="datetime-local" name="fecha_atencion" required max="{{ $ahora }}"
                                   value="{{ old('fecha_atencion', $ahora) }}" class="{{ $input }}">
                            <x-input-error :messages="$erroresAtencion->get('fecha_atencion')" class="mt-1" />
                        </div>
                        <div>
                            <label for="asistencia-atencion" class="block text-xs font-semibold text-gray-700 mb-1">Asistencia <span class="text-rose-600">*</span></label>
                            <select id="asistencia-atencion" name="asistencia" required class="{{ $input }}">
                                @foreach (Atencion::ASISTENCIAS as $opcion)
                                    <option value="{{ $opcion }}" @selected(old('asistencia', Actuacion::ASISTIO) === $opcion)>{{ $opcion }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$erroresAtencion->get('asistencia')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label for="observaciones-atencion" class="block text-xs font-semibold text-gray-700 mb-1">Observaciones y evolución <span class="text-rose-600">*</span></label>
                        <textarea id="observaciones-atencion" name="observaciones" rows="4" required
                                  minlength="{{ Atencion::OBSERVACIONES_MIN }}" maxlength="{{ Atencion::OBSERVACIONES_MAX }}"
                                  class="{{ $input }}">{{ old('observaciones') }}</textarea>
                        <x-input-error :messages="$erroresAtencion->get('observaciones')" class="mt-1" />
                    </div>

                    <div>
                        <label for="acuerdos-atencion" class="block text-xs font-semibold text-gray-700 mb-1">Acuerdos y compromisos (opcional)</label>
                        <textarea id="acuerdos-atencion" name="acuerdos_compromisos" rows="2" maxlength="{{ Atencion::ACUERDOS_MAX }}"
                                  class="{{ $input }}">{{ old('acuerdos_compromisos') }}</textarea>
                        <x-input-error :messages="$erroresAtencion->get('acuerdos_compromisos')" class="mt-1" />
                    </div>

                    <p class="text-xs text-gray-500">La atención queda en el expediente y en la auditoría; no se puede modificar después.</p>
                    <button type="submit"
                            class="w-full px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        Registrar atención
                    </button>
                </form>
            @endif
        </div>

        <div class="lg:col-span-2">
            @forelse ($caso->atenciones as $atencion)
                <article class="p-3 sm:p-4 bg-gray-50 rounded-xl border border-gray-100 mb-2 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-800">{{ $atencion->tipo_atencion }}</h3>
                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $colorAsistencia[$atencion->asistencia] ?? 'bg-gray-100 text-gray-600' }}">{{ $atencion->asistencia }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $atencion->fecha_atencion->format('Y-m-d H:i') }} · {{ $atencion->profesional?->persona?->nombre_completo }}</p>
                    <p class="text-xs sm:text-sm text-gray-700 mt-2 whitespace-pre-line break-words">{{ $atencion->observaciones }}</p>
                    @if ($atencion->acuerdos_compromisos)
                        <p class="text-xs sm:text-sm text-gray-800 mt-2 p-2 bg-white rounded-lg border border-gray-200 whitespace-pre-line break-words"><span class="font-semibold">Acuerdos:</span> {{ $atencion->acuerdos_compromisos }}</p>
                    @endif
                </article>
            @empty
                <p class="text-sm text-gray-500">Aún no hay atenciones registradas en este expediente.</p>
            @endforelse
        </div>
    </div>
</section>

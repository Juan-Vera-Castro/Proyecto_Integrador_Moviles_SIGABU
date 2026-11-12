{{-- P6: el profesional registra un consentimiento firmado en papel. --}}
@php
    use App\Documentos\ConsentimientoInclusion;
    use App\Models\Actuacion;
    use App\Models\Inclusion\ConsentimientoDetalle;
    use App\Models\Inclusion\Evidencia;

    $nombres = ['nombre_persona', 'documento_persona', 'numero_contacto', 'vinculacion', 'facultad', 'programa_academico', 'semestre', 'dependencia',
        'acudiente_nombre', 'acudiente_tipo_documento', 'acudiente_numero_documento', 'acudiente_parentesco'];
    $campos = collect($nombres)->mapWithKeys(fn ($nombre) => [$nombre => old($nombre, $iniciales[$nombre] ?? '')])->all();
    $marcadas = collect(ConsentimientoInclusion::DECISIONES)->map(fn ($texto, $campo) => old($campo))->all();
    $extensiones = collect(array_keys(Evidencia::TIPOS_PERMITIDOS))->map(fn ($extension) => '.'.$extension)->implode(',');
    $control = 'block w-full rounded-lg border-gray-300 shadow-sm text-base sm:text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-[#007F2F] tracking-tight">Consentimiento firmado en papel</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Caso {{ $caso->codigo_expediente }} · para quien no puede firmar en la plataforma</p>
            </div>
            <a href="{{ route('profesional.expediente.show', $caso) }}"
               class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
               title="Volver al expediente" aria-label="Volver al expediente">✕</a>
        </div>

        <div class="flex-1 p-3 sm:p-8 flex justify-center items-start">
            <form method="POST" action="{{ route('inclusion.consentimiento.papel.registrar', $caso) }}" enctype="multipart/form-data"
                  x-data="{ campos: @js($campos), error: () => '' }" class="w-full max-w-3xl space-y-4">
                @csrf

                @if (session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-700 font-medium" role="alert">{{ session('error') }}</div>
                @endif

                <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3" aria-labelledby="imprimir-titulo">
                    <div>
                        <h2 id="imprimir-titulo" class="text-base font-bold text-gray-900">Formato para imprimir</h2>
                        <p class="text-sm text-gray-600">Sale con el texto vigente y los datos de la persona. Después de firmarlo, cárgalo aquí.</p>
                    </div>
                    <a href="{{ route('inclusion.consentimiento.imprimible', $caso) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-[#007F2F] text-[#006324] hover:bg-emerald-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2 shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>Descargar formato</a>
                </section>

                <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-4" aria-labelledby="archivo-titulo">
                    <h2 id="archivo-titulo" class="text-base font-bold text-gray-900">Formato firmado</h2>
                    <div>
                        <label for="archivo" class="block text-sm font-medium text-gray-700 mb-1">Archivo escaneado</label>
                        <input type="file" id="archivo" name="archivo" accept="{{ $extensiones }}" aria-required="true" aria-describedby="archivo-ayuda archivo-error"
                               class="block w-full text-sm text-gray-700 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-[#EAF7EF] file:text-[#006324] file:font-semibold hover:file:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-[#007F2F] rounded-lg">
                        <p id="archivo-ayuda" class="text-xs text-gray-500 mt-1">PDF, JPG, PNG, DOCX o XLSX, hasta {{ Evidencia::ARCHIVO_MAX_KB / 1024 }} MB.</p>
                        @include('inclusion.consentimiento.partials.error', ['campo' => 'archivo'])
                    </div>
                    <div>
                        <label for="fecha_firma_papel" class="block text-sm font-medium text-gray-700 mb-1">Fecha de la firma</label>
                        <input type="date" id="fecha_firma_papel" name="fecha_firma_papel" value="{{ old('fecha_firma_papel') }}" max="{{ now(Actuacion::ZONA_HORARIA)->toDateString() }}" aria-required="true" aria-describedby="fecha_firma_papel-error" class="{{ $control }} sm:max-w-xs">
                        @include('inclusion.consentimiento.partials.error', ['campo' => 'fecha_firma_papel'])
                    </div>
                </section>

                @include('inclusion.consentimiento.partials.datos')

                @include('inclusion.consentimiento.partials.decisiones', [
                    'iniciales' => $marcadas,
                    'titulo' => 'Decisiones marcadas en el papel',
                    'ayuda' => 'Marca exactamente lo que la persona marcó en el formato.',
                ])

                <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-2" aria-labelledby="motivo-titulo">
                    <h2 id="motivo-titulo" class="text-base font-bold text-gray-900">Motivo</h2>
                    <label for="motivo_papel" class="block text-sm font-medium text-gray-700">Por qué la persona no firmó en la plataforma</label>
                    <textarea id="motivo_papel" name="motivo_papel" rows="4" maxlength="{{ ConsentimientoDetalle::MOTIVO_PAPEL_MAX }}" aria-required="true" aria-describedby="motivo_papel-error" class="{{ $control }}">{{ old('motivo_papel') }}</textarea>
                    @include('inclusion.consentimiento.partials.error', ['campo' => 'motivo_papel'])
                    <p class="text-xs text-amber-800">Quedará la constancia: «{{ ConsentimientoDetalle::LEYENDA_PAPEL }}», con el motivo y tu nombre.</p>
                </section>

                <div class="flex justify-end pb-8">
                    <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                        Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@php
    use App\Models\Vbg\Informe;
    use App\Models\Vbg\Reporte;

    $input = 'block w-full rounded-lg shadow-sm text-base sm:text-sm text-gray-900 border-gray-300 focus:border-[#7E22CE] focus:ring-[#7E22CE]';
    $volver = route('vbg.reportes.show', $reporte).'#informe';

    // Se parte de la versión anterior, si la hay.
    $titulosLegal = collect($anterior?->marco_legal ?? [])->pluck('titulo')->all();
    $titulosRuta = collect($anterior?->ruta_atencion ?? [])->pluck('titulo')->all();
    $legalElegido = old('marco_legal', collect($marcoLegal)->keys()->filter(fn ($i) => in_array($marcoLegal[$i]['titulo'], $titulosLegal, true))->all());
    $rutaElegida = old('ruta_atencion', collect($rutaAtencion)->keys()->filter(fn ($i) => in_array($rutaAtencion[$i]['titulo'], $titulosRuta, true))->all());
    $rutaOtros = old('ruta_otros', collect($titulosRuta)->reject(fn ($t) => in_array($t, array_column($rutaAtencion, 'titulo'), true))->implode("\n"));
    $tiposElegidos = old('tipos_violencia', $anterior?->tipos_violencia ?? array_filter([$reporte->tipo_violencia]));
    $documentosElegidos = array_map('intval', old('documentos', $anterior?->documentos_repositorio ?? []));
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex items-center justify-between gap-3 border-b border-gray-200 shrink-0">
            <div class="min-w-0">
                <a href="{{ $volver }}" class="text-xs font-bold text-[#7E22CE] hover:underline">&larr; Caso {{ $reporte->codigo }}</a>
                <h1 class="text-xl font-bold text-gray-900">Informe de análisis</h1>
                <p class="text-gray-500 text-sm">{{ $anterior ? 'Nueva versión: parte de la del '.$anterior->creado_en->format('Y-m-d H:i').'. La anterior se conserva.' : 'Primera versión del informe del caso.' }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('vbg.informes.store', $reporte) }}" class="p-3 sm:p-8 w-full max-w-3xl mx-auto space-y-4">
            @csrf

            @if ($errors->any())
                <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium rounded-lg px-4 py-3" role="alert">Revisa los datos marcados.</div>
            @endif

            <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-4">
                <div>
                    <label for="resumen" class="block text-sm font-medium text-gray-700 mb-1">Resumen del análisis</label>
                    <textarea id="resumen" name="resumen" rows="7" required maxlength="{{ Informe::RESUMEN_MAX }}" class="{{ $input }} resize-y">{{ old('resumen', $anterior?->resumen) }}</textarea>
                    <x-input-error :messages="$errors->get('resumen')" class="mt-1" />
                </div>

                @if ($transcripciones->isNotEmpty())
                    <div>
                        <label for="id_transcripcion" class="block text-sm font-medium text-gray-700 mb-1">Basado en la transcripción (opcional)</label>
                        <select id="id_transcripcion" name="id_transcripcion" class="{{ $input }}">
                            <option value="">Ninguna</option>
                            @foreach ($transcripciones as $t)
                                <option value="{{ $t->id_transcripcion }}" @selected(old('id_transcripcion', $anterior?->id_transcripcion) === $t->id_transcripcion)>
                                    Cita del {{ $t->audio->cita->fecha->format('Y-m-d') }} · {{ $t->audio->nombre_original }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('id_transcripcion')" class="mt-1" />
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="nivel_riesgo" class="block text-sm font-medium text-gray-700 mb-1">Nivel de riesgo</label>
                        <select id="nivel_riesgo" name="nivel_riesgo" required class="{{ $input }}">
                            <option value="">Selecciona…</option>
                            @foreach (Reporte::NIVELES_RIESGO as $nivel)
                                <option value="{{ $nivel }}" @selected(old('nivel_riesgo', $anterior?->nivel_riesgo ?? $reporte->nivel_riesgo) === $nivel)>{{ $nivel }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('nivel_riesgo')" class="mt-1" />
                    </div>
                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-1">Tipos de violencia</legend>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                            @foreach (Reporte::TIPOS_VIOLENCIA as $tipo)
                                <label class="flex items-center gap-2 text-sm text-gray-800">
                                    <input type="checkbox" name="tipos_violencia[]" value="{{ $tipo }}" @checked(in_array($tipo, $tiposElegidos, true)) class="rounded border-gray-300 text-[#7E22CE] focus:ring-[#7E22CE]">
                                    {{ $tipo }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('tipos_violencia')" class="mt-1" />
                    </fieldset>
                </div>
            </section>

            <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3">
                <h2 class="text-base font-bold text-gray-900">Marco legal aplicable</h2>
                <div class="space-y-2">
                    @foreach ($marcoLegal as $i => $norma)
                        <label class="flex items-start gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="marco_legal[]" value="{{ $i }}" @checked(in_array((string) $i, array_map('strval', $legalElegido), true)) class="mt-0.5 rounded border-gray-300 text-[#7E22CE] focus:ring-[#7E22CE]">
                            <span><strong>{{ $norma['titulo'] }}</strong><span class="block text-xs text-gray-500">{{ $norma['descripcion'] }}</span></span>
                        </label>
                    @endforeach
                </div>
                @if ($documentos->isNotEmpty())
                    <div class="border-t border-gray-100 pt-3 space-y-2">
                        <p class="text-sm font-medium text-gray-700">Documentos del Repositorio</p>
                        @foreach ($documentos as $documento)
                            <label class="flex items-start gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="documentos[]" value="{{ $documento->id_documento }}" @checked(in_array($documento->id_documento, $documentosElegidos, true)) class="mt-0.5 rounded border-gray-300 text-[#7E22CE] focus:ring-[#7E22CE]">
                                <span>{{ $documento->titulo }} <span class="text-xs text-gray-500">· {{ $documento->categoria }}</span></span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-500">Para citar leyes o protocolos propios, publícalos en el <a href="{{ route('repositorio.index') }}" class="font-bold text-[#7E22CE] hover:underline">Repositorio</a> con la categoría «Ley o norma» o «Protocolo».</p>
                @endif
            </section>

            <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3">
                <h2 class="text-base font-bold text-gray-900">Ruta de atención</h2>
                <div class="space-y-2">
                    @foreach ($rutaAtencion as $i => $paso)
                        <label class="flex items-start gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="ruta_atencion[]" value="{{ $i }}" @checked(in_array((string) $i, array_map('strval', $rutaElegida), true)) class="mt-0.5 rounded border-gray-300 text-[#7E22CE] focus:ring-[#7E22CE]">
                            <span><strong>{{ $paso['titulo'] }}</strong><span class="block text-xs text-gray-500">{{ $paso['descripcion'] }}</span></span>
                        </label>
                    @endforeach
                </div>
                <div>
                    <label for="ruta_otros" class="block text-sm font-medium text-gray-700 mb-1">Otros pasos (uno por línea)</label>
                    <textarea id="ruta_otros" name="ruta_otros" rows="3" maxlength="3000" class="{{ $input }} resize-y">{{ $rutaOtros }}</textarea>
                </div>
                <x-input-error :messages="$errors->get('ruta_atencion')" />
            </section>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-between gap-3 pb-8">
                <a href="{{ $volver }}" class="w-full sm:w-auto text-center px-5 py-3 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-bold rounded-xl text-sm transition">Cancelar</a>
                <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-[#7E22CE] hover:bg-[#6B21A8] text-white font-bold rounded-xl text-sm transition shadow-sm">Guardar informe</button>
            </div>
        </form>
    </div>
</x-app-layout>

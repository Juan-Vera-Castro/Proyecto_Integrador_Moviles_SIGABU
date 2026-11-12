@php
    use App\Models\Vbg\ConsentimientoCita;

    $input = 'block w-full rounded-lg shadow-sm text-base sm:text-sm text-gray-900 border-gray-300 focus:border-[#7E22CE] focus:ring-[#7E22CE]';
    $volver = route('vbg.reportes.show', $reporte).'#cita-'.$cita->id_cita;
    $campos = [
        'firmante_nombre' => old('firmante_nombre', $sugerido['firmante_nombre']),
        'firmante_documento' => old('firmante_documento', $sugerido['firmante_documento']),
        'es_menor' => (bool) old('es_menor', $sugerido['es_menor']),
    ];
    $acudiente = [
        'acudiente_nombre' => ['Nombre completo del acudiente', 'text', ''],
        'acudiente_telefono' => ['Teléfono del acudiente', 'tel', 'Ej. 3001234567'],
        'acudiente_documento' => ['Número de cédula del acudiente', 'text', ''],
        'acudiente_parentesco' => ['Parentesco con la persona atendida', 'text', 'Ej. Madre, padre, tío'],
    ];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex items-center justify-between gap-3 border-b border-gray-200 shrink-0">
            <div class="min-w-0">
                <a href="{{ $volver }}" class="text-xs font-bold text-[#7E22CE] hover:underline">&larr; Caso {{ $reporte->codigo }}</a>
                <h1 class="text-xl font-bold text-gray-900">Consentimiento de la cita</h1>
                <p class="text-gray-500 text-sm">{{ $cita->fecha->format('Y-m-d') }} · {{ $cita->horario() }} · {{ $cita->modalidad }}@if ($cita->lugar) · {{ $cita->lugar }}@endif</p>
            </div>
        </div>

        <div class="flex-1 p-3 sm:p-8 flex justify-center items-start">
            <form method="POST" action="{{ route('vbg.citas.consentimiento.store', $cita) }}" class="w-full max-w-2xl space-y-4"
                  x-data="firmaTactil(@js([
                      'plantilla' => $config['texto'],
                      'marcadores' => [],
                      'camposDeFirma' => [],
                      'datosBase' => [],
                      'campos' => $campos,
                      'confirmacionRechazo' => '¿Confirmas que la persona NO acepta el consentimiento informado? Quedará registrado y no se podrá iniciar la cita.',
                  ]))"
                  @submit.prevent>
                @csrf

                @if ($errors->has('reporte'))
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium rounded-lg px-4 py-3" role="alert">{{ $errors->first('reporte') }}</div>
                @endif

                <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3" aria-labelledby="persona-titulo">
                    <h2 id="persona-titulo" class="text-base font-bold text-gray-900">Persona atendida</h2>
                    <div>
                        <label for="firmante_nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                        <input type="text" id="firmante_nombre" name="firmante_nombre" value="{{ $campos['firmante_nombre'] }}" x-model="campos.firmante_nombre" maxlength="150" required autocomplete="off" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('firmante_nombre')" class="mt-1" />
                        <p class="mt-1 text-sm text-red-600" x-show="error('firmante_nombre')" x-text="error('firmante_nombre')" style="display: none;"></p>
                    </div>
                    <div>
                        <label for="firmante_documento" class="block text-sm font-medium text-gray-700 mb-1">Número de documento</label>
                        <input type="text" id="firmante_documento" name="firmante_documento" value="{{ $campos['firmante_documento'] }}" x-model="campos.firmante_documento" maxlength="20" required autocomplete="off" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('firmante_documento')" class="mt-1" />
                        <p class="mt-1 text-sm text-red-600" x-show="error('firmante_documento')" x-text="error('firmante_documento')" style="display: none;"></p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" name="es_menor" value="1" x-model="campos.es_menor" class="rounded border-gray-300 text-[#7E22CE] focus:ring-[#7E22CE]">
                        Es menor de edad: firma también su acudiente
                    </label>
                </section>

                <section x-show="campos.es_menor" x-cloak class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3" aria-labelledby="acudiente-titulo">
                    <h2 id="acudiente-titulo" class="text-base font-bold text-gray-900">Acudiente que otorga el consentimiento</h2>
                    @foreach ($acudiente as $nombre => [$etiqueta, $tipo, $ejemplo])
                        <div>
                            <label for="{{ $nombre }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $etiqueta }}</label>
                            <input type="{{ $tipo }}" @if ($tipo === 'tel') inputmode="tel" @endif id="{{ $nombre }}" name="{{ $nombre }}" value="{{ old($nombre) }}"
                                   maxlength="150" placeholder="{{ $ejemplo }}" autocomplete="off" class="{{ $input }}">
                            <x-input-error :messages="$errors->get($nombre)" class="mt-1" />
                            <p class="mt-1 text-sm text-red-600" x-show="error('{{ $nombre }}')" x-text="error('{{ $nombre }}')" style="display: none;"></p>
                        </div>
                    @endforeach
                </section>

                <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="texto-titulo">
                    <h2 id="texto-titulo" class="text-base font-bold text-gray-900 mb-1">{{ $config['titulo'] }}</h2>
                    <p class="text-xs text-gray-500 mb-3">Versión {{ $config['version'] }} · léela con la persona antes de firmar.</p>
                    <div class="text-[15px] sm:text-sm text-gray-800 leading-relaxed whitespace-pre-line break-words">{{ $config['texto'] }}</div>
                </section>

                {{-- Menor de edad: firma también la persona atendida --}}
                <template x-if="campos.es_menor">
                    <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="firma-menor-titulo">
                        <div x-data="lienzoFirma()" data-lienzo="firma_estudiante" class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <h2 id="firma-menor-titulo" class="text-base font-bold text-gray-900">Firma de la persona atendida</h2>
                                <button type="button" @click="borrar()" :disabled="enviando" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Borrar</button>
                            </div>
                            <p id="firma-menor-ayuda" class="text-xs text-gray-500">La persona menor de edad firma con el dedo dentro del recuadro.</p>
                            <x-firma-lienzo ayuda="firma-menor-ayuda" etiqueta="Área de firma de la persona atendida" />
                            <p id="firma_estudiante" tabindex="-1" class="text-sm text-red-600" x-show="error('firma_estudiante')" x-text="error('firma_estudiante')" style="display: none;"></p>
                        </div>
                    </section>
                </template>

                <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3" aria-labelledby="firma-titulo">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="firma-titulo" class="text-base font-bold text-gray-900" x-text="campos.es_menor ? 'Firma del acudiente' : 'Firma de la persona atendida'">Firma de la persona atendida</h2>
                        <button type="button" @click="borrar()" :disabled="enviando" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Borrar</button>
                    </div>
                    <p id="firma-ayuda" class="text-xs text-gray-500">Firma con el dedo (o con el mouse) dentro del recuadro.</p>
                    <x-firma-lienzo ayuda="firma-ayuda" />
                    <x-input-error :messages="$errors->get('firma')" />
                    <p id="firma" tabindex="-1" class="text-sm text-red-600" x-show="error('firma')" x-text="error('firma')" style="display: none;"></p>
                </section>

                <p class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-sm text-rose-700 font-medium" role="alert" x-show="mensaje" x-text="mensaje" style="display: none;"></p>

                <div class="flex flex-col-reverse sm:flex-row sm:justify-between gap-3 pb-8">
                    <button type="button" @click="enviar('{{ ConsentimientoCita::RECHAZADO }}')" :disabled="enviando"
                            class="w-full sm:w-auto px-5 py-3 bg-white border-2 border-rose-300 text-rose-700 hover:bg-rose-50 font-bold rounded-xl text-sm transition">
                        No acepta
                    </button>
                    <button type="button" @click="enviar('{{ ConsentimientoCita::ACEPTADO }}')" :disabled="enviando || ! firmasCompletas()"
                            class="w-full sm:w-auto px-6 py-3 bg-[#7E22CE] hover:bg-[#6B21A8] disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm transition shadow-sm">
                        <span x-show="! enviando">Acepta y firma</span>
                        <span x-show="enviando" style="display: none;">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

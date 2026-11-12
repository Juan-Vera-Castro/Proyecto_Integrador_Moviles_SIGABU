{{-- P1: la persona diligencia, firma y envía el consentimiento del Proceso de Inclusión. --}}
@php
    use App\Documentos\ConsentimientoInclusion;
    use App\Documentos\TextoConsentimiento;
    use App\Models\ConsentimientoAceptacion;
    use App\Models\Inclusion\Borrador;
    use App\Models\Inclusion\EstadoDocumento;

    $nombres = ['nombre_persona', 'documento_persona', 'numero_contacto', 'vinculacion', 'facultad', 'programa_academico', 'semestre', 'dependencia',
        'acudiente_nombre', 'acudiente_tipo_documento', 'acudiente_numero_documento', 'acudiente_parentesco'];
    $campos = collect($nombres)->mapWithKeys(fn ($nombre) => [$nombre => $iniciales[$nombre] ?? ''])->all();
    $notaDecisiones = '[Tus decisiones se marcan en la sección «Decisiones», después de este texto.]';
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-[#007F2F] tracking-tight">Consentimiento informado</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Caso {{ $caso->codigo_expediente }} · Proceso de Inclusión Universitaria</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if ($borrador)
                    <x-estado-documento :estado="EstadoDocumento::BORRADOR" />
                @endif
                <a href="{{ route('citas.miscitas') }}#caso-{{ $caso->id_caso }}"
                   class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm"
                   title="Volver a Mis citas" aria-label="Cerrar y volver a Mis citas">✕</a>
            </div>
        </div>

        <div class="flex-1 p-3 sm:p-8 flex justify-center items-start">
            <div class="w-full max-w-3xl space-y-4">

                @if (session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-700 font-medium" role="alert">{{ session('error') }}</div>
                @endif

                @if ($faltanDatos)
                    <section class="bg-amber-50 border border-amber-200 rounded-2xl p-5 sm:p-6 space-y-2 text-sm text-amber-900" role="alert" aria-labelledby="faltan-titulo">
                        <h2 id="faltan-titulo" class="text-base font-bold">Aún no puedes firmar</h2>
                        <p>Antes de firmar, tu profesional ({{ $datosBase['profesional'] ?? 'Bienestar Universitario' }}) debe registrar tu tipo de documento, sexo y fecha de nacimiento: con la fecha se sabe si firmas tú o tu acudiente.</p>
                        <p>Pídeselo en tu próxima cita o comunícate con Bienestar Universitario.</p>
                    </section>
                @else
                    @unless ($almacenamientoListo)
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900" role="alert">
                            Por ahora no es posible guardar firmas. Puedes guardar el borrador y avisar a Bienestar Universitario.
                        </div>
                    @endunless

                    <form method="POST" action="{{ route('inclusion.consentimiento.enviar', $caso) }}"
                          x-data="firmaTactil(@js([
                              'plantilla' => $version->texto,
                              'marcadores' => TextoConsentimiento::MARCADORES,
                              'camposDeFirma' => ConsentimientoInclusion::CAMPOS_DE_FIRMA,
                              'datosBase' => $datosBase + ['decisiones' => $notaDecisiones],
                              'campos' => $campos,
                              'validarEnServidor' => true,
                              'casillaAConfirmar' => 'decision_acompanamiento',
                              'confirmacionCasilla' => 'No marcaste la decisión de recibir acompañamiento y seguimiento del Área de Inclusión. ¿Confirmas que quieres enviar el consentimiento así?',
                          ]))"
                          @submit.prevent
                          class="space-y-4">
                        @csrf
                        <input type="hidden" name="id_version" value="{{ $version->id_version }}">

                        @include('inclusion.consentimiento.partials.datos')

                        <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="texto-titulo">
                            <h2 id="texto-titulo" class="text-base font-bold text-gray-900 mb-1">{{ $version->modelo->nombre }}</h2>
                            <p class="text-xs text-gray-500 mb-3">Versión {{ $version->numero_version }} · {{ $menor ? 'el acudiente debe leerla' : 'léela' }} con atención antes de decidir.</p>
                            <div class="text-[15px] sm:text-sm text-gray-800 leading-relaxed whitespace-pre-line break-words" x-text="texto"></div>
                        </section>

                        @include('inclusion.consentimiento.partials.decisiones', [
                            'titulo' => 'Decisiones',
                            'ayuda' => 'Son independientes: marca solo lo que autorizas.',
                        ])

                        <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3" aria-labelledby="firma-titulo">
                            <div class="flex items-center justify-between gap-3">
                                <h2 id="firma-titulo" class="text-base font-bold text-gray-900">{{ $menor ? 'Firma del acudiente' : 'Tu firma' }}</h2>
                                <button type="button" @click="borrar()" :disabled="enviando"
                                        class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    Borrar
                                </button>
                            </div>
                            <p id="firma-ayuda" class="text-xs text-gray-500">Firma con el dedo o con el mouse dentro del recuadro. La firma no se guarda en el borrador. Si no puedes firmar en pantalla, avisa a tu profesional: puedes firmar en papel.</p>
                            <x-firma-lienzo ayuda="firma-ayuda" />
                            <p class="text-sm text-red-600" x-show="error('firma')" x-text="error('firma')" style="display: none;"></p>
                        </section>

                        <p class="text-sm text-red-600" x-show="error('id_version')" x-text="error('id_version')" style="display: none;"></p>
                        <p class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-sm text-rose-700 font-medium" role="alert" x-show="mensaje" x-text="mensaje" style="display: none;"></p>

                        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pb-8">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2"
                                 x-data="borradorFormulario(@js([
                                     'url' => route('inclusion.borradores.guardar', [$caso, Borrador::CONSENTIMIENTO]),
                                     'guardadoEn' => $borrador?->fecha_actualizacion?->format('H:i'),
                                 ]))">
                                <button type="button" @click="guardar()" :disabled="guardando"
                                        class="w-full sm:w-auto px-5 py-3 bg-white border border-[#007F2F] text-[#006324] hover:bg-emerald-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    Guardar borrador
                                </button>
                                <p class="text-xs text-gray-600" aria-live="polite" x-text="estado"></p>
                            </div>
                            <button type="button" @click="enviar('{{ ConsentimientoAceptacion::ACEPTADO }}')" :disabled="enviando || {{ $almacenamientoListo ? 'false' : 'true' }}"
                                    class="w-full sm:w-auto px-6 py-3 bg-[#007F2F] hover:bg-[#006324] disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                <span x-show="! enviando">Firmar y enviar</span>
                                <span x-show="enviando" style="display: none;">Guardando…</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

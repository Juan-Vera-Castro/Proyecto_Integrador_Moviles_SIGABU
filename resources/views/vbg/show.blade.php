@php
    use App\Models\Vbg\Accion;
    use App\Models\Vbg\Cita;
    use App\Models\Vbg\Reporte;

    $esMio = $reporte->id_profesional === auth()->id();
    $gestionable = $esMio && $reporte->estado === Reporte::EN_ATENCION;
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#7E22CE] focus:ring-[#7E22CE]';
    $colorEstado = [Reporte::RECIBIDO => 'bg-amber-50 text-amber-700', Reporte::EN_ATENCION => 'bg-sky-50 text-sky-700', Reporte::CERRADO => 'bg-gray-100 text-gray-600'];
    $erroresValoracion = $errors->getBag('valoracion');
    $erroresAccion = $errors->getBag('accion');
    $erroresCierre = $errors->getBag('cierre');
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <a href="{{ route('vbg.home') }}" class="text-xs font-bold text-[#7E22CE] hover:underline">&larr; Reportes</a>
                <h1 class="text-xl font-bold text-gray-900">Caso {{ $reporte->codigo }}</h1>
                <p class="text-gray-500 text-sm">Reportado el {{ $reporte->fecha_reporte->format('Y-m-d H:i') }}</p>
            </div>
            <span class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $colorEstado[$reporte->estado] }}">{{ $reporte->estado }}</span>
        </div>

        <div class="p-4 sm:p-8 max-w-5xl mx-auto w-full grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">
                @if (session('status'))
                    <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->has('reporte'))
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium rounded-lg px-4 py-3" role="alert">{{ $errors->first('reporte') }}</div>
                @endif

                <!-- Reporte -->
                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-4" aria-labelledby="reporte-titulo">
                    <h2 id="reporte-titulo" class="text-base font-bold text-gray-900">Reporte</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Persona afectada</dt>
                            <dd class="font-semibold text-gray-900">{{ $reporte->etiquetaAfectado() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Quién reporta</dt>
                            <dd class="font-semibold text-gray-900">
                                @if ($reporte->esAnonimo())
                                    Anónimo
                                @else
                                    {{ $reporte->reportante?->nombre_completo }} · {{ $reporte->reportante?->correo_inst }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Lugar y fecha de los hechos</dt>
                            <dd class="font-semibold text-gray-900">{{ collect([$reporte->lugar, $reporte->fecha_hechos?->format('Y-m-d')])->filter()->implode(' · ') ?: 'Sin dato' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Contacto</dt>
                            <dd class="font-semibold text-gray-900">{{ $reporte->desea_contacto ? 'Quiere ser contactado: '.$reporte->medio_contacto : 'No pidió contacto' }}</dd>
                        </div>
                    </dl>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Relato</p>
                        <div class="pl-3 border-l-2 border-purple-200 text-sm text-gray-800 whitespace-pre-line break-words">{{ $reporte->relato }}</div>
                    </div>
                </section>

                <!-- Citas: agenda, consentimiento por cita, audios y transcripción (base sigabu_vbg) -->
                @php
                    $erroresCita = $errors->getBag('cita');
                    $colorCita = [Cita::PROGRAMADA => 'bg-sky-50 text-sky-700', Cita::EN_CURSO => 'bg-amber-50 text-amber-700', Cita::COMPLETADA => 'bg-emerald-50 text-emerald-700', Cita::CANCELADA => 'bg-gray-100 text-gray-500'];
                    $botonEstado = [Cita::EN_CURSO => 'Iniciar cita', Cita::COMPLETADA => 'Marcar completada', Cita::CANCELADA => 'Cancelar cita'];
                @endphp
                <section id="citas" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-4" aria-labelledby="citas-titulo">
                    <h2 id="citas-titulo" class="text-base font-bold text-gray-900">Citas</h2>

                    @unless ($vbgDisponible)
                        <p class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800" role="alert">
                            Las citas, los audios y los informes no se pueden mostrar en este momento: la base de datos de VBG no responde. Avisa al administrador.
                        </p>
                    @else
                        @if ($citas->isEmpty())
                            <p class="text-sm text-gray-500">Todavía no hay citas agendadas.</p>
                        @else
                            <ol class="space-y-4">
                                @foreach ($citas as $cita)
                                    @php
                                        $consentimiento = $cita->consentimientoVigente();
                                        $erroresAudio = $errors->getBag('audio-'.$cita->id_cita);
                                    @endphp
                                    <li id="cita-{{ $cita->id_cita }}" class="border border-gray-200 rounded-xl p-4 space-y-3 scroll-mt-20">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="font-bold text-gray-900">{{ $cita->fecha->format('Y-m-d') }} · {{ $cita->horario() }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $cita->modalidad }}@if ($cita->lugar) · {{ $cita->lugar }}@endif</p>
                                            </div>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $colorCita[$cita->estado] }}">{{ $cita->estado }}</span>
                                        </div>

                                        @if ($cita->motivo || $cita->notas_previas)
                                            <dl class="text-sm space-y-1">
                                                @if ($cita->motivo)<div><dt class="inline text-xs text-gray-500">Motivo:</dt> <dd class="inline text-gray-800">{{ $cita->motivo }}</dd></div>@endif
                                                @if ($cita->notas_previas)<div><dt class="inline text-xs text-gray-500">Notas previas:</dt> <dd class="inline text-gray-800 whitespace-pre-line break-words">{{ $cita->notas_previas }}</dd></div>@endif
                                            </dl>
                                        @endif

                                        {{-- Consentimiento de la cita --}}
                                        <div class="text-sm">
                                            @if ($consentimiento?->esAceptado())
                                                <p class="text-emerald-700 font-semibold">✓ Consentimiento aceptado y firmado el {{ $consentimiento->fecha_hora->format('Y-m-d H:i') }} por {{ $consentimiento->es_menor ? $consentimiento->acudiente_nombre.' (acudiente) y '.$consentimiento->firmante_nombre : $consentimiento->firmante_nombre }}.</p>
                                                @if ($esMio)
                                                    <div class="mt-2 flex flex-wrap gap-3">
                                                        <figure>
                                                            <img src="{{ route('vbg.consentimientos.firma', $consentimiento) }}" alt="Firma de {{ $consentimiento->es_menor ? 'el acudiente' : 'la persona atendida' }}" loading="lazy" class="h-16 w-auto border border-gray-200 rounded-lg bg-white">
                                                            <figcaption class="text-[11px] text-gray-500">{{ $consentimiento->es_menor ? 'Acudiente' : 'Persona atendida' }}</figcaption>
                                                        </figure>
                                                        @if ($consentimiento->firma_menor_ruta)
                                                            <figure>
                                                                <img src="{{ route('vbg.consentimientos.firma', [$consentimiento, 'menor']) }}" alt="Firma de la persona atendida, menor de edad" loading="lazy" class="h-16 w-auto border border-gray-200 rounded-lg bg-white">
                                                                <figcaption class="text-[11px] text-gray-500">Persona atendida (menor)</figcaption>
                                                            </figure>
                                                        @endif
                                                    </div>
                                                @endif
                                            @elseif ($consentimiento)
                                                <p class="text-rose-700 font-semibold">✗ La persona no aceptó el consentimiento ({{ $consentimiento->fecha_hora->format('Y-m-d H:i') }}).</p>
                                            @else
                                                <p class="text-amber-700 font-semibold">Falta el consentimiento informado de esta cita.</p>
                                            @endif
                                        </div>

                                        @if ($gestionable && $cita->estaAbierta())
                                            <div class="flex flex-wrap gap-2">
                                                @unless ($consentimiento?->esAceptado())
                                                    <a href="{{ route('vbg.citas.consentimiento', $cita) }}" class="px-3 py-2 bg-[#7E22CE] hover:bg-[#6B21A8] text-white text-xs font-bold rounded-lg transition">Firmar consentimiento</a>
                                                @endunless
                                                @foreach (Cita::TRANSICIONES[$cita->estado] as $siguiente)
                                                    <form method="POST" action="{{ route('vbg.citas.estado', $cita) }}"
                                                          @if ($siguiente === Cita::CANCELADA) onsubmit="return confirm('¿Cancelar esta cita?')" @endif>
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="estado" value="{{ $siguiente }}">
                                                        <button type="submit" @disabled($siguiente === Cita::EN_CURSO && ! $consentimiento?->esAceptado())
                                                                @class(['px-3 py-2 text-xs font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed',
                                                                    'bg-white border border-rose-200 text-rose-700 hover:bg-rose-50' => $siguiente === Cita::CANCELADA,
                                                                    'bg-gray-100 hover:bg-gray-200 text-gray-800' => $siguiente !== Cita::CANCELADA])>
                                                            {{ $botonEstado[$siguiente] }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Audios de la sesión: solo el profesional a cargo --}}
                                        @if ($esMio && ($cita->audios->isNotEmpty() || ($gestionable && $consentimiento?->esAceptado() && $cita->estado !== Cita::CANCELADA)))
                                            <div class="border-t border-gray-100 pt-3 space-y-3">
                                                <h3 class="text-sm font-bold text-gray-900">Audio y transcripción</h3>
                                                @foreach ($cita->audios as $audio)
                                                    @php($erroresTranscripcion = $errors->getBag('transcripcion-'.$audio->id_audio))
                                                    <div id="audio-{{ $audio->id_audio }}" class="rounded-lg bg-gray-50 border border-gray-100 p-3 space-y-2 scroll-mt-20">
                                                        <p class="text-xs text-gray-600 break-words">{{ $audio->nombre_original }} · {{ collect([$audio->duracionLegible(), $audio->tamanoLegible(), $audio->creado_en?->format('Y-m-d H:i')])->filter()->implode(' · ') }}</p>
                                                        <audio controls preload="none" class="w-full" src="{{ route('vbg.audios.show', $audio) }}">Tu navegador no reproduce este audio.</audio>
                                                        @php($transcripcion = $audio->transcripcion)
                                                        @if ($transcripcion?->estado === \App\Models\Vbg\Transcripcion::EN_COLA)
                                                            <p class="text-xs text-amber-700">Transcripción automática en cola ({{ $transcripcion->modelo }}).</p>
                                                        @elseif ($transcripcion?->estado === \App\Models\Vbg\Transcripcion::ERROR)
                                                            <p class="text-xs text-rose-700">La transcripción automática falló. Puedes escribirla.</p>
                                                        @endif
                                                        @if ($transcripcion?->estaCompleta())
                                                            <details>
                                                                <summary class="cursor-pointer text-xs font-bold text-[#7E22CE]">Ver transcripción ({{ $transcripcion->origen === 'IA' ? 'automática, '.$transcripcion->modelo : 'escrita por el profesional' }})</summary>
                                                                <div class="mt-2 text-sm text-gray-800 whitespace-pre-line break-words">{{ $transcripcion->texto }}</div>
                                                            </details>
                                                        @endif
                                                        @if ($gestionable)
                                                            <details @if ($erroresTranscripcion->any()) open @endif>
                                                                <summary class="cursor-pointer text-xs font-bold text-gray-700">{{ $transcripcion?->estaCompleta() ? 'Corregir transcripción' : 'Escribir transcripción' }}</summary>
                                                                <form method="POST" action="{{ route('vbg.audios.transcripcion', $audio) }}" class="mt-2 space-y-2">
                                                                    @csrf
                                                                    @method('PUT')
                                                                    <label for="texto-{{ $audio->id_audio }}" class="sr-only">Transcripción</label>
                                                                    <textarea id="texto-{{ $audio->id_audio }}" name="texto" rows="5" required class="{{ $input }} resize-y">{{ old('texto', $transcripcion?->texto) }}</textarea>
                                                                    <x-input-error :messages="$erroresTranscripcion->get('texto')" />
                                                                    <button type="submit" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold rounded-lg transition">Guardar transcripción</button>
                                                                </form>
                                                            </details>
                                                        @endif
                                                    </div>
                                                @endforeach

                                                @if ($gestionable && $consentimiento?->esAceptado() && $cita->estado !== Cita::CANCELADA)
                                                    <form method="POST" action="{{ route('vbg.audios.store', $cita) }}" enctype="multipart/form-data" class="space-y-2"
                                                          x-data="{
                                                              duracion: '',
                                                              leer(evento) {
                                                                  this.duracion = '';
                                                                  const archivo = evento.target.files[0];
                                                                  if (! archivo) return;
                                                                  const sonido = new Audio();
                                                                  sonido.preload = 'metadata';
                                                                  sonido.onloadedmetadata = () => { if (isFinite(sonido.duration)) this.duracion = Math.round(sonido.duration); URL.revokeObjectURL(sonido.src); };
                                                                  sonido.src = URL.createObjectURL(archivo);
                                                              },
                                                          }">
                                                        @csrf
                                                        <label for="audio-{{ $cita->id_cita }}" class="block text-sm font-medium text-gray-700">Subir el audio de la sesión</label>
                                                        <input type="file" id="audio-{{ $cita->id_cita }}" name="audio" required accept=".mp3,.wav,.m4a,audio/mpeg,audio/wav,audio/mp4,audio/x-m4a" @change="leer($event)"
                                                               class="block w-full text-sm text-gray-700 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-purple-50 file:text-[#7E22CE] file:font-bold">
                                                        <input type="hidden" name="duracion_segundos" :value="duracion">
                                                        <p class="text-xs text-gray-500">MP3, WAV o M4A, de hasta 2 horas y {{ intdiv(config('sigabu.vbg.audio.max_kb'), 1024) }} MB.</p>
                                                        <x-input-error :messages="$erroresAudio->get('audio')" />
                                                        <x-input-error :messages="$erroresAudio->get('duracion_segundos')" />
                                                        <button type="submit" class="px-3 py-2 bg-[#7E22CE] hover:bg-[#6B21A8] text-white text-xs font-bold rounded-lg transition">Subir audio</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        @if ($gestionable)
                            <details class="border-t border-gray-100 pt-4" @if ($erroresCita->any()) open @endif>
                                <summary class="cursor-pointer text-sm font-bold text-[#7E22CE]">+ Agendar cita</summary>
                                <form method="POST" action="{{ route('vbg.citas.store', $reporte) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    @csrf
                                    <div>
                                        <label for="fecha" class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                                        <input type="date" id="fecha" name="fecha" required min="{{ now(\App\Models\Actuacion::ZONA_HORARIA)->toDateString() }}" value="{{ old('fecha') }}" class="{{ $input }}">
                                        <x-input-error :messages="$erroresCita->get('fecha')" class="mt-1" />
                                    </div>
                                    <div>
                                        <label for="hora_inicio" class="block text-sm font-medium text-gray-700 mb-1">Inicio</label>
                                        <input type="time" id="hora_inicio" name="hora_inicio" required value="{{ old('hora_inicio') }}" class="{{ $input }}">
                                        <x-input-error :messages="$erroresCita->get('hora_inicio')" class="mt-1" />
                                    </div>
                                    <div>
                                        <label for="hora_fin" class="block text-sm font-medium text-gray-700 mb-1">Fin</label>
                                        <input type="time" id="hora_fin" name="hora_fin" required value="{{ old('hora_fin') }}" class="{{ $input }}">
                                        <x-input-error :messages="$erroresCita->get('hora_fin')" class="mt-1" />
                                    </div>
                                    <div>
                                        <label for="modalidad" class="block text-sm font-medium text-gray-700 mb-1">Modalidad</label>
                                        <select id="modalidad" name="modalidad" required class="{{ $input }}">
                                            @foreach (Cita::MODALIDADES as $modalidad)
                                                <option value="{{ $modalidad }}" @selected(old('modalidad', Cita::PRESENCIAL) === $modalidad)>{{ $modalidad }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$erroresCita->get('modalidad')" class="mt-1" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="lugar" class="block text-sm font-medium text-gray-700 mb-1">Consultorio o enlace</label>
                                        <input type="text" id="lugar" name="lugar" maxlength="200" value="{{ old('lugar') }}" placeholder="Ej. Bloque B - Oficina 204, o el enlace de la reunión" class="{{ $input }}">
                                        <x-input-error :messages="$erroresCita->get('lugar')" class="mt-1" />
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label for="motivo_cita" class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                                        <input type="text" id="motivo_cita" name="motivo" maxlength="2000" value="{{ old('motivo') }}" placeholder="Ej. Orientación y seguimiento" class="{{ $input }}">
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label for="notas_previas" class="block text-sm font-medium text-gray-700 mb-1">Notas previas</label>
                                        <textarea id="notas_previas" name="notas_previas" rows="2" maxlength="4000" class="{{ $input }} resize-y">{{ old('notas_previas') }}</textarea>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <button type="submit" class="w-full sm:w-auto px-4 py-2.5 bg-[#7E22CE] hover:bg-[#6B21A8] text-white text-sm font-bold rounded-xl transition">Agendar cita</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    @endunless
                </section>

                <!-- Informe de análisis (base sigabu_vbg) -->
                @if ($vbgDisponible && ($esMio || $informes->isNotEmpty()))
                    @php($informe = $informes->first())
                    <section id="informe" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-4 scroll-mt-20" aria-labelledby="informe-titulo">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 id="informe-titulo" class="text-base font-bold text-gray-900">Informe de análisis</h2>
                            @if ($gestionable)
                                <a href="{{ route('vbg.informes.create', $reporte) }}" class="px-3 py-2 bg-[#7E22CE] hover:bg-[#6B21A8] text-white text-xs font-bold rounded-lg transition">{{ $informe ? 'Nueva versión' : 'Elaborar informe' }}</a>
                            @endif
                        </div>

                        @if (! $informe)
                            <p class="text-sm text-gray-500">Todavía no hay un informe de análisis.</p>
                        @elseif (! $esMio)
                            <p class="text-sm text-gray-600">Hay un informe registrado el {{ $informe->creado_en->format('Y-m-d H:i') }}. Solo lo consulta el profesional a cargo.</p>
                        @else
                            <div class="space-y-3 text-sm">
                                <p class="text-xs text-gray-500">Versión del {{ $informe->creado_en->format('Y-m-d H:i') }} · {{ $informe->profesional?->persona?->nombre_completo }} · elaborado por el profesional</p>
                                <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
                                    <span class="px-2.5 py-1 rounded-full bg-rose-50 text-rose-700">Riesgo {{ mb_strtolower($informe->nivel_riesgo) }}</span>
                                    @foreach ($informe->tipos_violencia as $tipo)
                                        <span class="px-2.5 py-1 rounded-full bg-purple-50 text-[#7E22CE]">{{ $tipo }}</span>
                                    @endforeach
                                </div>
                                <div class="whitespace-pre-line break-words text-gray-800">{{ $informe->resumen }}</div>
                                @if ($informe->marco_legal || $informe->documentos_repositorio)
                                    <div>
                                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Marco legal</p>
                                        <ul class="list-disc pl-5 text-gray-800">
                                            @foreach ($informe->marco_legal as $norma)<li>{{ $norma['titulo'] }}</li>@endforeach
                                            @if ($informe->documentos_repositorio)<li>{{ count($informe->documentos_repositorio) }} documento(s) del Repositorio (ver PDF)</li>@endif
                                        </ul>
                                    </div>
                                @endif
                                <div>
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Ruta de atención</p>
                                    <ol class="list-decimal pl-5 text-gray-800">
                                        @foreach ($informe->ruta_atencion as $paso)<li>{{ $paso['titulo'] }}</li>@endforeach
                                    </ol>
                                </div>
                                <a href="{{ route('vbg.informes.pdf', $informe) }}" class="inline-flex text-xs font-bold text-[#7E22CE] hover:underline">↓ Descargar informe (PDF)</a>
                            </div>
                            @if ($informes->count() > 1)
                                <details class="border-t border-gray-100 pt-3">
                                    <summary class="cursor-pointer text-xs font-bold text-gray-700">Versiones anteriores ({{ $informes->count() - 1 }})</summary>
                                    <ul class="mt-2 space-y-1 text-xs">
                                        @foreach ($informes->skip(1) as $anterior)
                                            <li><a href="{{ route('vbg.informes.pdf', $anterior) }}" class="text-[#7E22CE] hover:underline">{{ $anterior->creado_en->format('Y-m-d H:i') }} · riesgo {{ mb_strtolower($anterior->nivel_riesgo) }} (PDF)</a></li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        @endif
                    </section>
                @endif

                <!-- Acciones -->
                <section id="acciones" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-4" aria-labelledby="acciones-titulo">
                    <h2 id="acciones-titulo" class="text-base font-bold text-gray-900">Acciones y seguimiento</h2>

                    @if ($reporte->acciones->isEmpty())
                        <p class="text-sm text-gray-500">Todavía no hay acciones registradas.</p>
                    @else
                        <ol class="space-y-3">
                            @foreach ($reporte->acciones as $accion)
                                <li class="border border-gray-100 rounded-xl p-4">
                                    <p class="text-xs text-gray-500"><strong class="text-[#7E22CE]">{{ $accion->tipo_accion }}</strong> · {{ $accion->fecha_hora->format('Y-m-d H:i') }} · {{ $accion->usuario?->persona?->nombre_completo }}</p>
                                    <p class="text-sm text-gray-800 mt-1 whitespace-pre-line break-words">{{ $accion->descripcion }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if ($gestionable)
                        <form method="POST" action="{{ route('vbg.reportes.accion', $reporte) }}" class="border-t border-gray-100 pt-4 space-y-3">
                            @csrf
                            <div>
                                <label for="tipo_accion" class="block text-sm font-medium text-gray-700 mb-1.5">Registrar acción o ruta activada</label>
                                <select id="tipo_accion" name="tipo_accion" required class="{{ $input }}">
                                    <option value="">Selecciona…</option>
                                    @foreach (Accion::TIPOS as $tipo)
                                        <option value="{{ $tipo }}" @selected(old('tipo_accion') === $tipo)>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$erroresAccion->get('tipo_accion')" class="mt-1" />
                            </div>
                            <div>
                                <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1.5">Descripción</label>
                                <textarea id="descripcion" name="descripcion" rows="3" required maxlength="{{ Accion::DESCRIPCION_MAX }}" class="{{ $input }} resize-y">{{ old('descripcion') }}</textarea>
                                <x-input-error :messages="$erroresAccion->get('descripcion')" class="mt-1" />
                            </div>
                            <button type="submit" class="px-4 py-2 bg-[#7E22CE] hover:bg-[#6B21A8] text-white text-sm font-bold rounded-xl transition">Registrar</button>
                            <p class="text-xs text-gray-500">Las acciones no se pueden editar ni borrar.</p>
                        </form>
                    @endif
                </section>
            </div>

            <!-- Columna lateral: gestión -->
            <aside class="space-y-6">
                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 space-y-3" aria-labelledby="gestion-titulo">
                    <h2 id="gestion-titulo" class="text-base font-bold text-gray-900">Gestión</h2>
                    <p class="text-sm text-gray-700">
                        A cargo: <strong>{{ $reporte->profesional?->persona?->nombre_completo ?? 'nadie todavía' }}</strong>
                    </p>

                    @if ($reporte->estado === Reporte::RECIBIDO)
                        <form method="POST" action="{{ route('vbg.reportes.tomar', $reporte) }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 bg-[#7E22CE] hover:bg-[#6B21A8] text-white text-sm font-bold rounded-xl transition">Tomar este caso</button>
                        </form>
                    @elseif ($reporte->estaCerrado() && $esMio)
                        <form method="POST" action="{{ route('vbg.reportes.reabrir', $reporte) }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-bold rounded-xl transition">Reabrir caso</button>
                        </form>
                    @endif
                </section>

                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 space-y-3" aria-labelledby="valoracion-titulo">
                    <h2 id="valoracion-titulo" class="text-base font-bold text-gray-900">Valoración</h2>
                    @if ($gestionable)
                        <form method="POST" action="{{ route('vbg.reportes.valorar', $reporte) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="nivel_riesgo" class="block text-sm font-medium text-gray-700 mb-1.5">Nivel de riesgo</label>
                                <select id="nivel_riesgo" name="nivel_riesgo" required class="{{ $input }}">
                                    <option value="">Selecciona…</option>
                                    @foreach (Reporte::NIVELES_RIESGO as $nivel)
                                        <option value="{{ $nivel }}" @selected(old('nivel_riesgo', $reporte->nivel_riesgo) === $nivel)>{{ $nivel }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$erroresValoracion->get('nivel_riesgo')" class="mt-1" />
                            </div>
                            <div>
                                <label for="tipo_violencia" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de violencia</label>
                                <select id="tipo_violencia" name="tipo_violencia" required class="{{ $input }}">
                                    <option value="">Selecciona…</option>
                                    @foreach (Reporte::TIPOS_VIOLENCIA as $tipo)
                                        <option value="{{ $tipo }}" @selected(old('tipo_violencia', $reporte->tipo_violencia) === $tipo)>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$erroresValoracion->get('tipo_violencia')" class="mt-1" />
                            </div>
                            <button type="submit" class="w-full px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-bold rounded-xl transition">Guardar valoración</button>
                        </form>
                    @else
                        <p class="text-sm text-gray-700">Riesgo: <strong>{{ $reporte->nivel_riesgo ?? 'sin valorar' }}</strong></p>
                        <p class="text-sm text-gray-700">Tipo: <strong>{{ $reporte->tipo_violencia ?? 'sin clasificar' }}</strong></p>
                    @endif
                </section>

                @if ($gestionable)
                    <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5" aria-labelledby="cierre-titulo">
                        <h2 id="cierre-titulo" class="text-base font-bold text-gray-900 mb-3">Cerrar caso</h2>
                        <form method="POST" action="{{ route('vbg.reportes.cerrar', $reporte) }}" class="space-y-3"
                              onsubmit="return confirm('¿Cerrar el caso {{ $reporte->codigo }}? Podrás reabrirlo después.')">
                            @csrf
                            <label for="motivo" class="sr-only">Motivo del cierre</label>
                            <textarea id="motivo" name="motivo" rows="3" required maxlength="{{ Accion::DESCRIPCION_MAX }}" placeholder="Por qué se cierra: atención completada, desistimiento, remitido…" class="{{ $input }} resize-y">{{ old('motivo') }}</textarea>
                            <x-input-error :messages="$erroresCierre->get('motivo')" class="mt-1" />
                            <button type="submit" class="w-full px-4 py-2 bg-white border border-rose-200 text-rose-700 hover:bg-rose-50 text-sm font-bold rounded-xl transition">Cerrar caso</button>
                        </form>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>

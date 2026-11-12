@php
    use App\Documentos\TextoConsentimiento;
    use App\Models\ConsentimientoAceptacion;

    $errores = $errors->getBag('consentimiento');
    $campo = 'block w-full rounded-lg shadow-sm text-base sm:text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $nombresCampos = $menor
        ? ['acudiente_nombre', 'acudiente_telefono', 'acudiente_tipo_documento', 'acudiente_numero_documento', 'acudiente_lugar_expedicion', 'acudiente_parentesco', 'eps_ips']
        : ['lugar_expedicion', 'eps_ips'];
    $campos = collect($nombresCampos)->mapWithKeys(fn ($nombre) => [$nombre => old($nombre, $nombre === 'acudiente_tipo_documento' ? 'CC' : '')])->all();
    // Personas a quienes avisar en una urgencia (solo cuando firma el estudiante): al menos una fila.
    $contactosIniciales = collect(is_array(old('contactos')) ? old('contactos') : [])
        ->values()
        ->map(fn ($c, $i) => ['uid' => $i, 'nombre' => $c['nombre'] ?? '', 'telefono' => $c['telefono'] ?? '', 'parentesco' => $c['parentesco'] ?? ''])
        ->whenEmpty(fn ($c) => $c->push(['uid' => 0, 'nombre' => '', 'telefono' => '', 'parentesco' => '']))
        ->all();
    if (! $menor) {
        $campos['contacto_urgencia'] = ConsentimientoAceptacion::resumirContactosUrgencia($contactosIniciales) ?? '';
    }
    $textoInicial = $version
        ? TextoConsentimiento::renderizar($version->texto, $datosBase + TextoConsentimiento::datosDeFirma($campos))
        : '';
    $etiquetas = [
        'lugar_expedicion' => ['Lugar de expedición del documento del estudiante', 'Ej. Tuluá'],
        'eps_ips' => ['EPS o IPS del estudiante', 'Ej. Nueva EPS'],
        'acudiente_nombre' => ['Nombre completo del acudiente que firma', ''],
        'acudiente_telefono' => ['Teléfono del acudiente', 'Ej. 3001234567'],
        'acudiente_numero_documento' => ['Número de cédula del acudiente', ''],
        'acudiente_parentesco' => ['Parentesco con el estudiante', 'Ej. Madre, padre, tío'],
        'acudiente_lugar_expedicion' => ['Lugar de expedición del documento del acudiente', 'Ej. Tuluá'],
    ];
    // Encabezado del formato en papel, sugerido con lo que ya se sabe de la vinculación.
    $vinculacionSugerida = match ($vinculacion?->tipo_vinculacion) {
        'Estudiante' => ConsentimientoAceptacion::VINCULACION_ESTUDIANTE,
        'Administrativo' => 'Funcionario',
        default => '',
    };
    $vinculacionInicial = old('vinculacion', $vinculacionSugerida);
@endphp

<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] flex flex-col font-sans">

        <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-[#007F2F] tracking-tight">Consentimiento informado</h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">Caso {{ $caso->codigo_expediente }} · {{ $caso->solicitud->tipo_servicio }}</p>
            </div>
            <a href="{{ route('citas.miscitas') }}#caso-{{ $caso->id_caso }}"
               class="w-9 h-9 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl flex items-center justify-center transition shadow-sm text-sm shrink-0"
               title="Volver a Mis citas" aria-label="Cerrar y volver a Mis citas">✕</a>
        </div>

        <div class="flex-1 p-3 sm:p-8 flex justify-center items-start">
            <div class="w-full max-w-2xl space-y-4">

                @if (session('status'))
                    <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-sm text-[#1F5134] font-medium" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-700 font-medium" role="alert">{{ session('error') }}</div>
                @endif

                @if ($faltanDatos)
                    <section class="bg-amber-50 border border-amber-200 rounded-2xl p-5 sm:p-6 space-y-2 text-sm text-amber-900" role="alert" aria-labelledby="datos-titulo">
                        <h2 id="datos-titulo" class="text-base font-bold">Aún no puedes firmar</h2>
                        <p>
                            Antes de firmar, tu profesional ({{ $caso->profesional?->persona?->nombre_completo ?? 'Bienestar Universitario' }}) debe registrar tu tipo de documento,
                            sexo y fecha de nacimiento: con la fecha se sabe si el consentimiento lo firmas tú o tu acudiente.
                        </p>
                        <p>Pídeselo en tu próxima cita o comunícate con Bienestar Universitario.</p>
                    </section>
                @elseif (! $version)
                    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-900" role="alert">
                        <p class="font-bold">No hay consentimiento {{ $menor ? 'para menores de edad ' : '' }}publicado para {{ $caso->solicitud->tipo_servicio }}.</p>
                        <p class="mt-1">Bienestar Universitario debe publicarlo antes de que puedas firmar. Vuelve a intentarlo más tarde.</p>
                    </div>
                @else
                    <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="persona-titulo">
                        <h2 id="persona-titulo" class="sr-only">Datos de la atención</h2>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div class="col-span-2">
                                <dt class="text-xs text-gray-500">Estudiante</dt>
                                <dd class="font-semibold text-gray-900 break-words">{{ $persona->nombre_completo }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Documento</dt>
                                <dd class="font-semibold text-gray-900">{{ $persona->tipo_documento }} {{ $persona->numero_documento }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Edad</dt>
                                <dd class="font-semibold text-gray-900">{{ $edad }} años{{ $menor ? ' · menor de edad' : '' }}</dd>
                            </div>
                            @if ($vinculacion)
                                <div>
                                    <dt class="text-xs text-gray-500">Vínculo</dt>
                                    <dd class="font-semibold text-gray-900">{{ $vinculacion->tipo_vinculacion }}</dd>
                                </div>
                                @if ($vinculacion->facultad)
                                    <div>
                                        <dt class="text-xs text-gray-500">Facultad</dt>
                                        <dd class="font-semibold text-gray-900 break-words">{{ $vinculacion->facultad }}</dd>
                                    </div>
                                @endif
                            @endif
                            <div class="col-span-2">
                                <dt class="text-xs text-gray-500">Profesional</dt>
                                <dd class="font-semibold text-gray-900 break-words">{{ $datosBase['profesional'] }}</dd>
                            </div>
                        </dl>
                    </section>

                    @unless ($almacenamientoListo)
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900" role="alert">
                            Por ahora no es posible guardar firmas. Avisa a Bienestar Universitario; mientras tanto solo se puede registrar que no aceptas.
                        </div>
                    @endunless

                    <form method="POST" action="{{ route('consentimientos.registrar', $caso) }}"
                          x-data="firmaTactil(@js([
                              'plantilla' => $version->texto,
                              'marcadores' => TextoConsentimiento::MARCADORES,
                              'camposDeFirma' => TextoConsentimiento::CAMPOS_DE_FIRMA,
                              'datosBase' => $datosBase,
                              'campos' => $campos,
                              'confirmacionRechazo' => $menor
                                  ? '¿Confirmas que el acudiente NO acepta el consentimiento informado? Quedará registrado y no se podrá prestar la atención.'
                                  : '¿Confirmas que NO aceptas el consentimiento informado? Quedará registrado y no se podrá prestar la atención.',
                          ]))"
                          @submit.prevent
                          class="space-y-4">
                        @csrf
                        <input type="hidden" name="id_version" value="{{ $version->id_version }}">
                        <x-input-error :messages="$errores->get('id_version')" />

                        <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3" aria-labelledby="encabezado-titulo"
                                 x-data="{ vinculacion: @js($vinculacionInicial) }">
                            <h2 id="encabezado-titulo" class="text-base font-bold text-gray-900">{{ $menor ? 'Datos del estudiante para el formato' : 'Tu vinculación con la universidad' }}</h2>

                            <div>
                                <label for="vinculacion" class="block text-sm font-medium text-gray-700 mb-1">Vinculación</label>
                                <select id="vinculacion" name="vinculacion" x-model="vinculacion" required
                                        @class([$campo, 'border-red-500' => $errores->has('vinculacion'), 'border-gray-300' => ! $errores->has('vinculacion')])>
                                    <option value="">Selecciona…</option>
                                    @foreach (ConsentimientoAceptacion::VINCULACIONES as $opcion)
                                        <option value="{{ $opcion }}" @selected($vinculacionInicial === $opcion)>{{ $opcion }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errores->get('vinculacion')" class="mt-1" />
                                <p class="mt-1 text-sm text-red-600" x-show="error('vinculacion')" x-text="error('vinculacion')" style="display: none;"></p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" x-show="vinculacion === '{{ ConsentimientoAceptacion::VINCULACION_ESTUDIANTE }}'">
                                <div class="sm:col-span-2">
                                    <label for="programa_academico" class="block text-sm font-medium text-gray-700 mb-1">Programa académico</label>
                                    <input type="text" id="programa_academico" name="programa_academico" value="{{ old('programa_academico', $vinculacion?->programa_academico ?? '') }}"
                                           placeholder="Ej. Psicología" maxlength="150" autocomplete="off"
                                           :required="vinculacion === '{{ ConsentimientoAceptacion::VINCULACION_ESTUDIANTE }}'"
                                           @class([$campo, 'border-red-500' => $errores->has('programa_academico'), 'border-gray-300' => ! $errores->has('programa_academico')])>
                                    <x-input-error :messages="$errores->get('programa_academico')" class="mt-1" />
                                    <p class="mt-1 text-sm text-red-600" x-show="error('programa_academico')" x-text="error('programa_academico')" style="display: none;"></p>
                                </div>
                                <div>
                                    <label for="semestre" class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                                    <input type="number" id="semestre" name="semestre" value="{{ old('semestre', $vinculacion?->semestre ?? '') }}"
                                           min="1" max="{{ ConsentimientoAceptacion::SEMESTRE_MAX }}" inputmode="numeric"
                                           :required="vinculacion === '{{ ConsentimientoAceptacion::VINCULACION_ESTUDIANTE }}'"
                                           @class([$campo, 'border-red-500' => $errores->has('semestre'), 'border-gray-300' => ! $errores->has('semestre')])>
                                    <x-input-error :messages="$errores->get('semestre')" class="mt-1" />
                                    <p class="mt-1 text-sm text-red-600" x-show="error('semestre')" x-text="error('semestre')" style="display: none;"></p>
                                </div>
                            </div>

                            <div>
                                <label for="dependencia_facultad" class="block text-sm font-medium text-gray-700 mb-1">Dependencia y/o facultad</label>
                                <input type="text" id="dependencia_facultad" name="dependencia_facultad" value="{{ old('dependencia_facultad', $vinculacion?->facultad) }}"
                                       placeholder="Ej. Facultad de Ciencias de la Salud" maxlength="150" required autocomplete="off"
                                       @class([$campo, 'border-red-500' => $errores->has('dependencia_facultad'), 'border-gray-300' => ! $errores->has('dependencia_facultad')])>
                                <x-input-error :messages="$errores->get('dependencia_facultad')" class="mt-1" />
                                <p class="mt-1 text-sm text-red-600" x-show="error('dependencia_facultad')" x-text="error('dependencia_facultad')" style="display: none;"></p>
                            </div>
                        </section>

                        <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3" aria-labelledby="campos-titulo">
                            <h2 id="campos-titulo" class="text-base font-bold text-gray-900">
                                {{ $menor ? 'Datos del acudiente que otorga el consentimiento' : 'Tus datos para el consentimiento' }}
                            </h2>
                            @if ($menor)
                                <p class="text-xs text-gray-600">Eres menor de edad: tu madre, padre o acudiente llena sus datos y firma el consentimiento, y tú también lo firmas (formato GBU-GPGR-F-019).</p>
                            @endif

                            @foreach ($nombresCampos as $nombre)
                                <div>
                                    @if ($nombre === 'acudiente_tipo_documento')
                                        <label for="{{ $nombre }}" class="block text-sm font-medium text-gray-700 mb-1">Tipo de documento del acudiente</label>
                                        <select id="{{ $nombre }}" name="{{ $nombre }}" x-model="campos.{{ $nombre }}" required
                                                @class([$campo, 'border-red-500' => $errores->has($nombre), 'border-gray-300' => ! $errores->has($nombre)])>
                                            <option value="">Selecciona…</option>
                                            @foreach (ConsentimientoAceptacion::TIPOS_DOCUMENTO_ACUDIENTE as $tipo)
                                                <option value="{{ $tipo }}">{{ ['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'TI' => 'Tarjeta de identidad', 'PA' => 'Pasaporte'][$tipo] ?? $tipo }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <label for="{{ $nombre }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $etiquetas[$nombre][0] }}</label>
                                        <input type="{{ $nombre === 'acudiente_telefono' ? 'tel' : 'text' }}" @if ($nombre === 'acudiente_telefono') inputmode="tel" @endif id="{{ $nombre }}" name="{{ $nombre }}" x-model="campos.{{ $nombre }}" value="{{ old($nombre) }}"
                                               placeholder="{{ $etiquetas[$nombre][1] }}" maxlength="150" required autocomplete="off"
                                               @class([$campo, 'border-red-500' => $errores->has($nombre), 'border-gray-300' => ! $errores->has($nombre)])>
                                    @endif
                                    <x-input-error :messages="$errores->get($nombre)" class="mt-1" />
                                    <p class="mt-1 text-sm text-red-600" x-show="error('{{ $nombre }}')" x-text="error('{{ $nombre }}')" style="display: none;"></p>
                                </div>
                            @endforeach

                            @unless ($menor)
                                {{-- Personas a quienes avisar en una urgencia: el resumen llena el texto del consentimiento --}}
                                <fieldset class="pt-1"
                                          x-data="{
                                              contactos: @js($contactosIniciales),
                                              max: {{ ConsentimientoAceptacion::MAX_CONTACTOS_URGENCIA }},
                                              siguiente: {{ count($contactosIniciales) }},
                                              agregar() {
                                                  if (this.contactos.length >= this.max) return;
                                                  this.contactos.push({ uid: this.siguiente++, nombre: '', telefono: '', parentesco: '' });
                                                  this.$nextTick(() => document.getElementById('contactos.' + (this.contactos.length - 1) + '.nombre')?.focus());
                                              },
                                              quitar(i) { this.contactos.splice(i, 1); },
                                              resumen() {
                                                  return this.contactos.map((c) => {
                                                      const nombre = (c.nombre || '').trim();
                                                      const telefono = (c.telefono || '').replace(/[\s().-]/g, '');
                                                      const parentesco = (c.parentesco || '').trim();
                                                      if (!nombre && !telefono && !parentesco) return null;
                                                      return (nombre + (parentesco ? ' (' + parentesco + ')' : '') + (telefono ? ' - Tel. ' + telefono : '')).trim();
                                                  }).filter(Boolean).join('; ');
                                              },
                                          }"
                                          x-effect="campos.contacto_urgencia = resumen()">
                                    <legend class="block text-sm font-medium text-gray-700">Personas a quienes avisar en caso de urgencia</legend>
                                    <p class="text-xs text-gray-500 mt-0.5 mb-2">Agrega al menos una con su nombre, teléfono y parentesco. Puedes agregar hasta {{ ConsentimientoAceptacion::MAX_CONTACTOS_URGENCIA }}.</p>
                                    <p id="contactos" tabindex="-1" class="mb-2 text-sm text-red-600" x-show="error('contactos')" x-text="error('contactos')" style="display: none;"></p>
                                    @foreach ($errores->getMessages() as $clave => $mensajes)
                                        @if (str_starts_with($clave, 'contactos'))
                                            <x-input-error :messages="$mensajes" class="mb-1" />
                                        @endif
                                    @endforeach

                                    <template x-for="(contacto, i) in contactos" :key="contacto.uid">
                                        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 sm:p-4 space-y-3 mb-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-semibold text-gray-800" x-text="'Persona ' + (i + 1)"></p>
                                                <button type="button" x-show="contactos.length > 1" @click="quitar(i)" :disabled="enviando"
                                                        class="text-sm font-semibold text-rose-600 hover:text-rose-700 px-2 py-1.5 rounded-lg hover:bg-rose-50"
                                                        :aria-label="'Quitar a la persona ' + (i + 1)">Quitar</button>
                                            </div>
                                            <div>
                                                <label :for="'contactos.' + i + '.nombre'" class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                                                <input type="text" :id="'contactos.' + i + '.nombre'" :name="'contactos[' + i + '][nombre]'" x-model="contacto.nombre"
                                                       maxlength="100" required autocomplete="off" placeholder="Ej. María Pérez"
                                                       class="{{ $campo }}" :class="error('contactos.' + i + '.nombre') ? 'border-red-500' : 'border-gray-300'">
                                                <p class="mt-1 text-sm text-red-600" x-show="error('contactos.' + i + '.nombre')" x-text="error('contactos.' + i + '.nombre')" style="display: none;"></p>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label :for="'contactos.' + i + '.telefono'" class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                                    <input type="tel" inputmode="tel" :id="'contactos.' + i + '.telefono'" :name="'contactos[' + i + '][telefono]'" x-model="contacto.telefono"
                                                           maxlength="20" required autocomplete="off" placeholder="Ej. 3001234567"
                                                           class="{{ $campo }}" :class="error('contactos.' + i + '.telefono') ? 'border-red-500' : 'border-gray-300'">
                                                    <p class="mt-1 text-sm text-red-600" x-show="error('contactos.' + i + '.telefono')" x-text="error('contactos.' + i + '.telefono')" style="display: none;"></p>
                                                </div>
                                                <div>
                                                    <label :for="'contactos.' + i + '.parentesco'" class="block text-sm font-medium text-gray-700 mb-1">Parentesco</label>
                                                    <select :id="'contactos.' + i + '.parentesco'" :name="'contactos[' + i + '][parentesco]'" x-model="contacto.parentesco" required
                                                            class="{{ $campo }}" :class="error('contactos.' + i + '.parentesco') ? 'border-red-500' : 'border-gray-300'">
                                                        <option value="">Selecciona…</option>
                                                        @foreach (ConsentimientoAceptacion::PARENTESCOS_URGENCIA as $parentesco)
                                                            <option value="{{ $parentesco }}">{{ $parentesco }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="mt-1 text-sm text-red-600" x-show="error('contactos.' + i + '.parentesco')" x-text="error('contactos.' + i + '.parentesco')" style="display: none;"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <button type="button" x-show="contactos.length < max" @click="agregar()" :disabled="enviando"
                                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-dashed border-[#007F2F] text-[#007F2F] text-sm font-semibold hover:bg-emerald-50 transition">
                                        <span aria-hidden="true">+</span> Agregar otra persona
                                    </button>
                                </fieldset>
                            @endunless
                        </section>

                        <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="texto-titulo">
                            <h2 id="texto-titulo" class="text-base font-bold text-gray-900 mb-1">{{ $version->modelo->nombre }}</h2>
                            <p class="text-xs text-gray-500 mb-3">Versión {{ $version->numero_version }} · {{ $menor ? 'tu acudiente debe leerla' : 'léela' }} con atención antes de firmar.</p>
                            <div class="text-[15px] sm:text-sm text-gray-800 leading-relaxed whitespace-pre-line break-words" x-text="texto">{{ $textoInicial }}</div>
                        </section>

                        @if ($menor)
                            {{-- Menor de edad: firma también el estudiante (firma_estudiante), además del acudiente --}}
                            <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100" aria-labelledby="firma-estudiante-titulo">
                                <div x-data="lienzoFirma()" data-lienzo="firma_estudiante" class="space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <h2 id="firma-estudiante-titulo" class="text-base font-bold text-gray-900">Firma del estudiante</h2>
                                        <button type="button" @click="borrar()" :disabled="enviando"
                                                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                            Borrar
                                        </button>
                                    </div>
                                    <p id="firma-estudiante-ayuda" class="text-xs text-gray-500">Tú (el estudiante) firmas con el dedo dentro del recuadro.</p>
                                    <x-firma-lienzo ayuda="firma-estudiante-ayuda" etiqueta="Área de firma del estudiante" />
                                    <x-input-error :messages="$errores->get('firma_estudiante')" />
                                    <p id="firma_estudiante" tabindex="-1" class="text-sm text-red-600" x-show="error('firma_estudiante')" x-text="error('firma_estudiante')" style="display: none;"></p>
                                </div>
                            </section>
                        @endif

                        <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-3" aria-labelledby="firma-titulo">
                            <div class="flex items-center justify-between gap-3">
                                <h2 id="firma-titulo" class="text-base font-bold text-gray-900">{{ $menor ? 'Firma del acudiente' : 'Tu firma' }}</h2>
                                <button type="button" @click="borrar()" :disabled="enviando"
                                        class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                    Borrar
                                </button>
                            </div>
                            <p id="firma-ayuda" class="text-xs text-gray-500">{{ $menor ? 'El acudiente firma con el dedo (o con el mouse) dentro del recuadro.' : 'Firma con el dedo (o con el mouse) dentro del recuadro.' }}</p>
                            <x-firma-lienzo ayuda="firma-ayuda" />
                            <x-input-error :messages="$errores->get('firma')" />
                            <p class="text-sm text-red-600" x-show="error('firma')" x-text="error('firma')" style="display: none;"></p>
                        </section>

                        <p class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-sm text-rose-700 font-medium" role="alert" x-show="mensaje" x-text="mensaje" style="display: none;"></p>

                        <div class="flex flex-col-reverse sm:flex-row sm:justify-between gap-3 pb-8">
                            <button type="button" @click="enviar('{{ ConsentimientoAceptacion::RECHAZADO }}')" :disabled="enviando"
                                    class="w-full sm:w-auto px-5 py-3 bg-white border-2 border-rose-300 text-rose-700 hover:bg-rose-50 font-bold rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                                No acepto
                            </button>
                            <button type="button" @click="enviar('{{ ConsentimientoAceptacion::ACEPTADO }}')"
                                    :disabled="enviando || ! firmasCompletas() || {{ $almacenamientoListo ? 'false' : 'true' }}"
                                    class="w-full sm:w-auto px-6 py-3 bg-[#007F2F] hover:bg-[#006324] disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2">
                                <span x-show="! enviando">Acepto y firmo</span>
                                <span x-show="enviando" style="display: none;">Guardando…</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

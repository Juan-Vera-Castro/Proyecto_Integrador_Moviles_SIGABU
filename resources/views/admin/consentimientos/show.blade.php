@php
    use App\Models\VersionConsentimiento;

    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $erroresModelo = $errors->getBag('modelo');
    $erroresVersion = $errors->getBag('version');
    $estadoVersion = [
        VersionConsentimiento::PUBLICADA => ['Vigente', 'bg-green-50 text-[#007F2F] border-green-200'],
        VersionConsentimiento::BORRADOR => ['Borrador', 'bg-amber-50 text-amber-700 border-amber-200'],
        VersionConsentimiento::RETIRADA => ['Retirada', 'bg-gray-100 text-gray-600 border-gray-200'],
    ];
    $editable = ! $modelo->esLegado();
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <!-- 1. FRANJA BLANCA SUPERIOR -->
        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">{{ $modelo->nombre }}</h1>
                <p class="text-gray-500 text-sm">{{ $modelo->codigo }} · {{ $modelo->tipo_servicio ?? 'Sin servicio' }} · {{ $modelo->esDeMenores() ? 'Menores de edad' : 'Población general' }}</p>
            </div>
            <a href="{{ route('admin.consentimientos.index') }}" class="w-10 h-10 flex items-center justify-center bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors"
               title="Volver a los modelos" aria-label="Cerrar y volver a los modelos de consentimiento">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </a>
        </div>

        <div class="p-4 sm:p-8 max-w-4xl mx-auto w-full flex flex-col gap-6">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-600 text-sm font-medium rounded-lg px-4 py-3" role="alert">{{ session('error') }}</div>
            @endif

            @if ($modelo->esLegado())
                <div class="bg-gray-100 border border-gray-200 rounded-xl p-4 text-sm text-gray-600">
                    Este modelo guarda los consentimientos registrados antes de HU-009. Es solo de consulta: no admite versiones nuevas.
                </div>
            @endif

            <!-- Datos del modelo -->
            @if ($editable)
                <form method="POST" action="{{ route('admin.consentimientos.update', $modelo) }}" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PATCH')
                    <h2 class="sm:col-span-2 text-base font-bold text-gray-900">Datos del modelo</h2>
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
                        <input id="nombre" name="nombre" type="text" required maxlength="150" value="{{ old('nombre', $modelo->nombre) }}"
                               @if ($erroresModelo->has('nombre')) aria-invalid="true" aria-describedby="nombre-error" @endif
                               @class([$input, 'border-red-500' => $erroresModelo->has('nombre'), 'border-gray-300' => ! $erroresModelo->has('nombre')])>
                        <x-input-error id="nombre-error" :messages="$erroresModelo->get('nombre')" class="mt-2" />
                    </div>
                    <div>
                        <label for="tipo_servicio" class="block text-sm font-medium text-gray-700 mb-1.5">Servicio</label>
                        <select id="tipo_servicio" name="tipo_servicio" required @class([$input, 'border-red-500' => $erroresModelo->has('tipo_servicio'), 'border-gray-300' => ! $erroresModelo->has('tipo_servicio')])>
                            @foreach ($servicios as $servicio)
                                <option value="{{ $servicio }}" @selected(old('tipo_servicio', $modelo->tipo_servicio) === $servicio)>{{ $servicio }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$erroresModelo->get('tipo_servicio')" class="mt-2" />
                    </div>
                    <div>
                        <label for="poblacion" class="block text-sm font-medium text-gray-700 mb-1.5">Población</label>
                        <select id="poblacion" name="poblacion" required @class([$input, 'border-red-500' => $erroresModelo->has('poblacion'), 'border-gray-300' => ! $erroresModelo->has('poblacion')])>
                            @foreach ($poblaciones as $poblacion)
                                <option value="{{ $poblacion }}" @selected(old('poblacion', $modelo->poblacion) === $poblacion)>{{ $poblacion === 'Menores' ? 'Menores de edad (firma el acudiente)' : 'General' }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$erroresModelo->get('poblacion')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1.5">Descripción <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <textarea id="descripcion" name="descripcion" rows="2" maxlength="500"
                                  @class([$input, 'resize-y', 'border-red-500' => $erroresModelo->has('descripcion'), 'border-gray-300' => ! $erroresModelo->has('descripcion')])>{{ old('descripcion', $modelo->descripcion) }}</textarea>
                        <x-input-error :messages="$erroresModelo->get('descripcion')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="activo" value="1" @checked(old('activo', $modelo->activo)) class="rounded border-gray-300 text-[#007F2F] focus:ring-[#007F2F]">
                                Activo: se usa al abrir casos de este servicio
                            </label>
                            <x-input-error :messages="$erroresModelo->get('activo')" class="mt-2" />
                        </div>
                        <button type="submit" class="px-5 py-2 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition-colors shadow-sm">Guardar datos</button>
                    </div>
                </form>

                <!-- Borrador o nueva versión -->
                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-4" aria-labelledby="texto-titulo">
                    @if ($borrador)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 id="texto-titulo" class="text-base font-bold text-gray-900">Borrador de la versión {{ $borrador->numero_version }}</h2>
                            <form method="POST" action="{{ route('admin.consentimientos.versiones.publicar', $borrador) }}"
                                  onsubmit="return confirm('¿Publicar la versión {{ $borrador->numero_version }}? {{ $modelo->versionVigente ? 'La versión '.$modelo->versionVigente->numero_version.' quedará retirada.' : '' }} Después no se podrá editar.')">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-[#6FD835] hover:bg-[#5ec22b] text-black font-semibold rounded-lg text-sm shadow-sm transition-colors">Publicar versión {{ $borrador->numero_version }}</button>
                            </form>
                        </div>
                        <p class="text-xs text-gray-500">Guarda tus cambios antes de publicar. Al publicar, esta versión pasa a ser la vigente.</p>
                        <form method="POST" action="{{ route('admin.consentimientos.versiones.update', $borrador) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <label for="texto" class="sr-only">Texto del consentimiento</label>
                            <textarea id="texto" name="texto" rows="12" required maxlength="{{ $textoMax }}"
                                      @if ($erroresVersion->has('texto')) aria-invalid="true" aria-describedby="texto-error" @endif
                                      @class([$input, 'resize-y', 'border-red-500' => $erroresVersion->has('texto'), 'border-gray-300' => ! $erroresVersion->has('texto')])>{{ old('texto', $borrador->texto) }}</textarea>
                            <x-input-error id="texto-error" :messages="$erroresVersion->get('texto')" class="mt-2" />
                            <div class="flex justify-end">
                                <button type="submit" class="px-5 py-2 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition-colors shadow-sm">Guardar borrador</button>
                            </div>
                        </form>
                    @else
                        <h2 id="texto-titulo" class="text-base font-bold text-gray-900">
                            {{ $modelo->versionVigente ? 'Nueva versión del texto' : 'Primera versión del texto' }}
                        </h2>
                        <p class="text-xs text-gray-500">
                            Se guarda como borrador. {{ $modelo->versionVigente ? 'Parte del texto vigente; la versión '.$modelo->versionVigente->numero_version.' se sigue usando hasta que publiques la nueva.' : 'No se usará hasta que la publiques.' }}
                        </p>
                        <form method="POST" action="{{ route('admin.consentimientos.versiones.store', $modelo) }}" class="space-y-3">
                            @csrf
                            <label for="texto" class="sr-only">Texto del consentimiento</label>
                            <textarea id="texto" name="texto" rows="12" required maxlength="{{ $textoMax }}"
                                      placeholder="Escribe el texto que el solicitante leerá y aceptará…"
                                      @if ($erroresVersion->has('texto')) aria-invalid="true" aria-describedby="texto-error" @endif
                                      @class([$input, 'resize-y', 'border-red-500' => $erroresVersion->has('texto'), 'border-gray-300' => ! $erroresVersion->has('texto')])>{{ old('texto', $modelo->versionVigente?->texto) }}</textarea>
                            <x-input-error id="texto-error" :messages="$erroresVersion->get('texto')" class="mt-2" />
                            <div class="flex justify-end">
                                <button type="submit" class="px-5 py-2 bg-[#007F2F] hover:bg-[#006324] text-white font-bold rounded-xl text-sm transition-colors shadow-sm">Guardar borrador</button>
                            </div>
                        </form>
                    @endif

                    <!-- Marcadores que el sistema llena al mostrar el texto (D-24) -->
                    <details class="border border-gray-100 rounded-xl text-sm">
                        <summary class="px-4 py-2.5 cursor-pointer font-bold text-[#007F2F] select-none">Marcadores disponibles</summary>
                        <div class="px-4 pb-4 space-y-2">
                            <p class="text-xs text-gray-500">Escríbelos tal cual en el texto, con llaves. Al firmar se reemplazan por el dato real; los que no se conocen aparecen como ____.</p>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                @foreach ($marcadores as $marcador => $descripcion)
                                    <div class="flex gap-2">
                                        <dt><code class="text-[#007F2F] font-semibold">{{ '{'.$marcador.'}' }}</code></dt>
                                        <dd class="text-gray-600">{{ $descripcion }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </details>
                </section>
            @endif

            <!-- Historial -->
            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden" aria-labelledby="historial-titulo">
                <h2 id="historial-titulo" class="px-5 sm:px-6 py-4 text-base font-bold text-gray-900 border-b border-gray-100">Historial de versiones</h2>
                @if ($modelo->versiones->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-400">Este modelo aún no tiene versiones.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($modelo->versiones as $version)
                            @php
                                [$etiqueta, $clase] = $estadoVersion[$version->estado] ?? [$version->estado, 'bg-gray-100 text-gray-600 border-gray-200'];
                                $aceptados = $version->aceptaciones_count + $version->consentimientos_count;
                            @endphp
                            <li>
                                <details class="px-5 sm:px-6 py-3 group">
                                    <summary class="flex flex-wrap items-center gap-3 cursor-pointer select-none">
                                        <span class="font-semibold text-gray-800">Versión {{ $version->numero_version }}</span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $clase }}">{{ $etiqueta }}</span>
                                        <span class="text-xs text-gray-400">
                                            @if ($version->fecha_publicacion)
                                                Publicada el {{ $version->fecha_publicacion->format('Y-m-d H:i') }}
                                            @else
                                                Creada el {{ $version->fecha_creacion?->format('Y-m-d H:i') }}
                                            @endif
                                            @if ($version->autor?->persona)
                                                · {{ $version->autor->persona->nombre_completo }}
                                            @endif
                                        </span>
                                        @unless ($version->esBorrador())
                                            <span class="text-xs text-gray-500">· {{ $aceptados }} {{ $aceptados === 1 ? 'consentimiento registrado' : 'consentimientos registrados' }} con esta versión</span>
                                        @endunless
                                    </summary>
                                    <div class="mt-3 pl-3 border-l-2 border-gray-200 text-sm text-gray-700 whitespace-pre-line break-words">{{ $version->texto }}</div>
                                </details>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>

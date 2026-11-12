@php
    use App\Models\RepositorioDocumento;

    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $errores = $errors->getBag('documento');
    $iconos = ['Ley o norma' => '⚖️', 'Protocolo' => '🧭', 'Guía' => '📘', 'Formato' => '📄', 'Material psicoeducativo' => '🌱'];
    $usuario = auth()->user();
    $tipoInicial = old('tipo_contenido', $errores->has('url') && ! $errores->has('archivo') ? 'enlace' : 'archivo');
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Repositorio</h1>
                <p class="text-gray-500 text-sm">Leyes, protocolos, guías, formatos y material psicoeducativo de Bienestar</p>
            </div>
        </div>

        <div class="p-4 sm:p-8 max-w-6xl mx-auto w-full flex flex-col gap-6">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium rounded-lg px-4 py-3" role="alert">{{ session('error') }}</div>
            @endif

            <details class="bg-white border border-gray-200 rounded-2xl shadow-sm" @if ($errores->any() || session('error')) open @endif>
                <summary class="px-5 py-4 cursor-pointer text-base font-bold text-gray-900 select-none">+ Publicar documento</summary>
                <form method="POST" action="{{ route('repositorio.store') }}" enctype="multipart/form-data"
                      x-data="{ tipo: @js($tipoInicial), enviando: false }" @submit="enviando = true"
                      class="px-5 pb-5 grid grid-cols-1 sm:grid-cols-4 gap-4">
                    @csrf
                    <div class="sm:col-span-2">
                        <label for="titulo" class="block text-sm font-medium text-gray-700 mb-1.5">Título</label>
                        <input id="titulo" name="titulo" type="text" maxlength="150" required value="{{ old('titulo') }}" class="{{ $input }}">
                        <x-input-error :messages="$errores->get('titulo')" class="mt-1" />
                    </div>
                    <div>
                        <label for="categoria" class="block text-sm font-medium text-gray-700 mb-1.5">Categoría</label>
                        <select id="categoria" name="categoria" required class="{{ $input }}">
                            @foreach (RepositorioDocumento::CATEGORIAS as $opcion)
                                <option value="{{ $opcion }}" @selected(old('categoria') === $opcion)>{{ $opcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="audiencia" class="block text-sm font-medium text-gray-700 mb-1.5">Quién lo ve</label>
                        <select id="audiencia" name="audiencia" required class="{{ $input }}">
                            <option value="{{ RepositorioDocumento::PROFESIONALES }}" @selected(old('audiencia') === RepositorioDocumento::PROFESIONALES)>Solo los equipos de Bienestar</option>
                            <option value="{{ RepositorioDocumento::TODOS }}" @selected(old('audiencia') === RepositorioDocumento::TODOS)>Todos (también los estudiantes)</option>
                        </select>
                    </div>

                    <fieldset class="sm:col-span-4">
                        <legend class="block text-sm font-medium text-gray-700 mb-1.5">Contenido</legend>
                        <div class="flex gap-4 text-sm mb-3">
                            <label class="flex items-center gap-2"><input type="radio" name="tipo_contenido" value="archivo" x-model="tipo" class="text-[#007F2F] focus:ring-[#007F2F]"> Subir un archivo</label>
                            <label class="flex items-center gap-2"><input type="radio" name="tipo_contenido" value="enlace" x-model="tipo" class="text-[#007F2F] focus:ring-[#007F2F]"> Pegar un enlace</label>
                        </div>
                        <div x-show="tipo === 'archivo'" @if ($tipoInicial !== 'archivo') style="display: none;" @endif>
                            <label for="archivo" class="sr-only">Archivo</label>
                            <input id="archivo" name="archivo" type="file" :disabled="tipo !== 'archivo'" :required="tipo === 'archivo'"
                                   accept="{{ collect(RepositorioDocumento::EXTENSIONES)->map(fn ($e) => '.'.$e)->implode(',') }}"
                                   class="block w-full text-sm text-gray-700 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-[#EAF7EF] file:text-[#007F2F] file:font-bold hover:file:bg-[#D7F0E0]">
                            <p class="text-xs text-gray-500 mt-1">PDF, Word, Excel, PowerPoint, texto o imagen. Máximo {{ RepositorioDocumento::ARCHIVO_MAX_KB / 1024 }} MB.</p>
                            <x-input-error :messages="$errores->get('archivo')" class="mt-1" />
                        </div>
                        <div x-show="tipo === 'enlace'" @if ($tipoInicial !== 'enlace') style="display: none;" @endif>
                            <label for="url" class="sr-only">Enlace (https)</label>
                            <input id="url" name="url" type="url" maxlength="500" value="{{ old('url') }}" placeholder="https://…" :disabled="tipo !== 'enlace'" :required="tipo === 'enlace'" class="{{ $input }}">
                            <x-input-error :messages="$errores->get('url')" class="mt-1" />
                        </div>
                    </fieldset>

                    <div class="sm:col-span-4">
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1.5">Descripción <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <input id="descripcion" name="descripcion" type="text" maxlength="500" value="{{ old('descripcion') }}" class="{{ $input }}">
                    </div>
                    <div class="sm:col-span-4">
                        <button type="submit" :disabled="enviando" class="px-5 py-2.5 bg-[#007F2F] hover:bg-[#006324] disabled:opacity-70 text-white text-sm font-bold rounded-xl transition">
                            <span x-show="! enviando">Publicar</span>
                            <span x-show="enviando" style="display: none;">Subiendo…</span>
                        </button>
                    </div>
                </form>
            </details>

            <form method="GET" action="{{ route('repositorio.index') }}" class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[220px]">
                    <label for="q" class="sr-only">Buscar documento</label>
                    <input id="q" name="q" type="search" value="{{ $busqueda }}" placeholder="Buscar documento…" class="{{ $input }}">
                </div>
                <div>
                    <label for="filtro_categoria" class="sr-only">Categoría</label>
                    <select id="filtro_categoria" name="categoria" class="{{ $input }}" onchange="this.form.submit()">
                        <option value="">Todas las categorías</option>
                        @foreach (RepositorioDocumento::CATEGORIAS as $opcion)
                            <option value="{{ $opcion }}" @selected($categoria === $opcion)>{{ $opcion }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold rounded-xl transition">Buscar</button>
            </form>

            @if ($documentos->isEmpty())
                <p class="bg-white border border-gray-200 rounded-2xl p-8 text-center text-sm text-gray-500">
                    {{ $busqueda !== '' || $categoria ? 'Ningún documento coincide con la búsqueda.' : 'Todavía no hay documentos publicados.' }}
                </p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($documentos as $documento)
                        <article class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col gap-2 {{ $documento->activo ? '' : 'opacity-60' }}">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-2xl" aria-hidden="true">{{ $iconos[$documento->categoria] ?? '📄' }}</span>
                                <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">{{ $documento->categoria }}</span>
                            </div>
                            <h2 class="font-bold text-gray-900 break-words">{{ $documento->titulo }}</h2>
                            @if ($documento->descripcion)
                                <p class="text-sm text-gray-500">{{ $documento->descripcion }}</p>
                            @endif
                            @if ($documento->tieneArchivo())
                                <p class="text-xs text-gray-600 break-all">📎 {{ $documento->archivo_nombre }} · {{ $documento->tamanoLegible() }}</p>
                            @endif
                            <p class="text-[11px] text-gray-400 mt-auto">
                                {{ $documento->audiencia === RepositorioDocumento::TODOS ? 'Visible para todos' : 'Solo equipos de Bienestar' }}
                                · {{ $documento->autor?->persona?->nombre_completo ?? 'Bienestar' }} · {{ $documento->fecha_publicacion->format('Y-m-d') }}
                                @unless ($documento->activo) · retirado @endunless
                            </p>
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                <div class="flex flex-wrap gap-3">
                                    @if ($documento->tieneArchivo())
                                        <a href="{{ route('repositorio.archivo', $documento) }}" class="text-sm font-bold text-[#007F2F] hover:underline">Descargar →</a>
                                    @endif
                                    @if ($documento->url)
                                        <a href="{{ $documento->url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-bold text-[#007F2F] hover:underline">Abrir enlace →</a>
                                    @endif
                                </div>
                                @if ($documento->puedeRetirarlo($usuario))
                                    <form method="POST" action="{{ route('repositorio.estado', $documento) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-xs font-bold {{ $documento->activo ? 'text-gray-500 hover:text-rose-600' : 'text-[#007F2F] hover:underline' }}">{{ $documento->activo ? 'Retirar' : 'Publicar de nuevo' }}</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

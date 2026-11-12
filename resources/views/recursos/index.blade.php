@php
    // Solo lo que publicaron los profesionales o el administrador en el repositorio, para todos.
    $documentos ??= collect();
    $colores = ['emerald', 'pink', 'teal', 'amber'];
    $categorias = $documentos->pluck('categoria')->filter()->unique()->values();
    $colorDe = fn (?string $categoria) => $colores[max(0, $categorias->search($categoria)) % count($colores)];
    $estilos = [
        'emerald' => ['fondo' => 'bg-emerald-50/80', 'texto' => 'text-emerald-700', 'etiqueta' => 'text-emerald-600', 'boton' => 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'],
        'pink' => ['fondo' => 'bg-pink-50/80', 'texto' => 'text-pink-600', 'etiqueta' => 'text-pink-500', 'boton' => 'border-pink-200 text-pink-600 hover:bg-pink-50'],
        'teal' => ['fondo' => 'bg-teal-50/80', 'texto' => 'text-teal-700', 'etiqueta' => 'text-teal-600', 'boton' => 'border-teal-200 text-teal-700 hover:bg-teal-50'],
        'amber' => ['fondo' => 'bg-amber-50/80', 'texto' => 'text-amber-700', 'etiqueta' => 'text-amber-600', 'boton' => 'border-amber-200 text-amber-700 hover:bg-amber-50'],
    ];
    // Tipo e ícono según el archivo subido, o enlace si es una URL.
    $tipoDe = function ($documento) {
        if (! $documento->tieneArchivo()) {
            return ['🔗', 'Enlace'];
        }

        return match (strtolower(pathinfo((string) $documento->archivo_nombre, PATHINFO_EXTENSION))) {
            'pdf' => ['📄', 'PDF'],
            'jpg', 'jpeg', 'png' => ['🖼️', 'Imagen'],
            'xls', 'xlsx' => ['📊', 'Hoja de cálculo'],
            'ppt', 'pptx' => ['📑', 'Presentación'],
            default => ['📝', 'Documento'],
        };
    };
    $items = $documentos->map(fn ($d) => ['cat' => $d->categoria, 'texto' => mb_strtolower($d->titulo.' '.$d->descripcion.' '.$d->categoria)])->values();
@endphp

<x-app-layout>
    <div class="p-6 sm:p-8 bg-gray-50/50 min-h-screen" x-data="{
        activeCategory: 'Todos',
        searchQuery: '',
        items: @js($items),
        ver(cat, texto) {
            return (this.activeCategory === 'Todos' || cat === this.activeCategory)
                && texto.includes(this.searchQuery.toLowerCase());
        },
        get visibles() { return this.items.filter(i => this.ver(i.cat, i.texto)).length },
    }">

        <!-- 1. Encabezado superior -->
        <div class="flex items-center justify-between w-full mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">Recursos de bienestar</h1>
                <p class="text-xs text-gray-500">Material psicoeducativo gratuito para tu salud mental</p>
            </div>

            <!-- Botón (X) para salir al inicio -->
            <a href="{{ route('estudiante.home') }}" class="bg-pink-50 hover:bg-pink-100 text-[#E30052] p-2.5 rounded-xl transition-colors flex items-center justify-center shadow-sm" title="Salir al inicio">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </a>
        </div>

        @if ($documentos->isEmpty())
            <!-- Sin material publicado -->
            <div class="text-center py-16 px-6 bg-white rounded-2xl border border-gray-100 mb-10">
                <div class="text-4xl mb-3" aria-hidden="true">📚</div>
                <p class="text-gray-700 font-semibold text-sm">Aún no hay recursos publicados.</p>
                <p class="text-gray-400 text-xs mt-1">Cuando Bienestar Universitario publique material, lo encontrarás aquí.</p>
            </div>
        @else
            <!-- 2. Barra de Búsqueda -->
            <div class="relative mb-5">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input
                    type="text"
                    x-model="searchQuery"
                    placeholder="Buscar recursos..."
                    aria-label="Buscar recursos"
                    class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#007F2F] focus:ring-1 focus:ring-[#007F2F] transition-all shadow-sm">
            </div>

            <!-- 3. Botones de Filtro por Categoria (las que tienen material) -->
            @if ($categorias->count() > 1)
                <div class="flex items-center gap-2 relative overflow-x-auto pb-2 mb-6 scrollbar-none">
                    @foreach (['Todos', ...$categorias] as $categoria)
                        <button type="button"
                            @click="activeCategory = @js($categoria)"
                            :class="activeCategory === @js($categoria) ? 'bg-[#007F2F] text-white font-semibold' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                            class="px-4 py-1.5 rounded-full text-xs transition-all shrink-0 shadow-sm cursor-pointer">
                            {{ $categoria }}
                        </button>
                    @endforeach
                </div>
            @endif

            <!-- 4. Rejilla de Recursos -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-10">
                @foreach ($documentos as $documento)
                    @php
                        $estilo = $estilos[$colorDe($documento->categoria)];
                        [$icono, $tipo] = $tipoDe($documento);
                        $archivo = $documento->tieneArchivo();
                    @endphp
                    <div x-show="ver(@js($documento->categoria), @js($items[$loop->index]['texto']))" x-transition
                         class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">

                        <div>
                            <!-- Cabecera de la tarjeta con color pastel -->
                            <div class="p-4 flex justify-between items-start {{ $estilo['fondo'] }}">
                                <span class="text-xl" aria-hidden="true">{{ $icono }}</span>
                                <div class="text-right">
                                    <span class="block text-xs font-bold {{ $estilo['texto'] }}">{{ $tipo }}</span>
                                    <span class="text-[10px] text-gray-400 font-medium">{{ $archivo ? $documento->tamanoLegible() : 'Sitio externo' }}</span>
                                </div>
                            </div>

                            <!-- Contenido -->
                            <div class="p-4">
                                <span class="text-[10px] font-bold tracking-wider uppercase mb-1 block {{ $estilo['etiqueta'] }}">{{ $documento->categoria }}</span>
                                <h3 class="font-bold text-sm text-gray-900 leading-snug mb-2">{{ $documento->titulo }}</h3>
                                @if ($documento->descripcion)
                                    <p class="text-xs text-gray-500 leading-relaxed">{{ $documento->descripcion }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Botón de Acción -->
                        <div class="p-4 pt-0">
                            <a href="{{ $archivo ? route('repositorio.archivo', $documento) : $documento->url }}" @unless ($archivo) target="_blank" rel="noopener noreferrer" @endunless
                               class="w-full py-2 px-3 border rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors {{ $estilo['boton'] }}">
                                <span aria-hidden="true">{{ $archivo ? '↓' : '↗' }}</span>
                                <span>{{ $archivo ? 'Descargar recurso' : 'Abrir recurso' }}</span>
                            </a>
                        </div>

                    </div>
                @endforeach
            </div>

            <!-- Mensaje cuando no hay resultados de búsqueda -->
            <div x-show="visibles === 0" style="display: none;" class="text-center py-12 bg-white rounded-2xl border border-gray-100 mb-10">
                <p class="text-gray-400 text-sm">No se encontraron recursos que coincidan con tu búsqueda.</p>
            </div>
        @endif

        <!-- 5. Banner Inferior: ¿Quieres hablar con un profesional? -->
        <div class="bg-[#EBFBF3] border border-[#D1F5E1] rounded-2xl p-5 sm:p-6 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm mb-6">
            <div class="flex items-center gap-4">
                <div class="text-3xl shrink-0">💬</div>
                <div>
                    <h3 class="font-bold text-[#006827] text-base mb-0.5">¿Quieres hablar con un profesional?</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Los recursos son un buen comienzo, pero si sientes que necesitas más apoyo, solicita una cita. Estamos para acompañarte.
                    </p>
                </div>
            </div>

            <a href="{{ route('citas.solicitar') }}" class="shrink-0 bg-[#8AFF00] hover:bg-[#7AE600] text-gray-900 font-bold px-6 py-2.5 rounded-xl text-xs transition-colors shadow-sm text-center w-full sm:w-auto">
                Solicitar cita
            </a>
        </div>

    </div>
</x-app-layout>

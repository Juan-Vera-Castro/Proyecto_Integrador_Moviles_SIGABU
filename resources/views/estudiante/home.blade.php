<x-app-layout>
    <!-- Desde lg ocupa exactamente la ventana, sin scroll global; en celular y tableta la página fluye y hace scroll. -->
    <div class="min-h-screen lg:min-h-0 lg:h-screen lg:overflow-hidden bg-gray-50 flex flex-col">
        
        <!-- 1. FRANJA BLANCA SUPERIOR DE ORILLA A ORILLA (HEADER) -->
        <header class="w-full bg-white border-b border-gray-200 px-4 sm:px-8 py-4 shrink-0">
            <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        Buen Día, {{ Auth::user()->name }} <span>👋</span>
                    </h1>
                    <p class="text-xs text-gray-500 mt-0.5">Estudiante UCEVA</p>
                </div>
            </div>
        </header>

        <!-- 2. CONTENIDO DEL DASHBOARD (CON PADDING Y SIN SCROLL) -->
        <main class="flex-1 p-4 sm:p-6 lg:overflow-hidden flex flex-col">
            <div class="max-w-7xl mx-auto w-full lg:h-full flex flex-col gap-4 sm:gap-6">

            <!-- 2. Banner Principal (shrink-0) -->
            <div x-data="{ 
                    activeSlide: 1, 
                    autoSlide() { 
                        setInterval(() => { this.activeSlide = this.activeSlide === 4 ? 1 : this.activeSlide + 1 }, 6000) 
                    } 
                 }" 
                 x-init="autoSlide()"
                 class="shrink-0 bg-[#005c22] rounded-2xl p-6 text-white relative overflow-hidden flex items-center shadow-md h-[230px] sm:h-[160px] lg:h-[140px] group">
                
                <!-- Capa de fondo simulada con opacidad -->
                <div class="absolute inset-0 opacity-20 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1441974231531-c6227db76b6e?q=80&w=2560&auto=format&fit=crop');"></div>

                <!-- Botón Anterior -->
                <button @click="activeSlide = activeSlide === 1 ? 4 : activeSlide - 1" class="absolute left-4 z-20 bg-black/20 hover:bg-black/40 p-2 rounded-xl backdrop-blur-sm transition hidden sm:block opacity-0 group-hover:opacity-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>

                <!-- Contenedor de Slides -->
                <div class="relative z-10 w-full px-4 sm:px-12 h-full flex items-center">
                    
                    <!-- Slide 1: Bienestar -->
                    <div x-show="activeSlide === 1" x-transition.opacity.duration.500ms class="absolute w-full left-0 px-4 sm:px-12">
                        <span class="absolute -top-6 left-4 sm:left-12 bg-[#007F2F] text-white text-[10px] font-bold px-3 py-1 rounded-b-lg tracking-wider uppercase shadow-sm">Bienestar</span>
                        <h2 class="text-lg md:text-2xl font-medium leading-relaxed mt-2">
                            "Pedir ayuda es un acto de valentía. Estamos aquí para acompañarte en cada paso."
                        </h2>
                        <p class="text-green-200 mt-2 text-xs md:text-sm">— Bienestar Universitario UCEVA</p>
                    </div>

                    <!-- Slide 2: Salud Mental -->
                    <div x-show="activeSlide === 2" x-transition.opacity.duration.500ms class="absolute w-full left-0 px-4 sm:px-12" style="display: none;">
                        <span class="absolute -top-6 left-4 sm:left-12 bg-[#007F2F] text-white text-[10px] font-bold px-3 py-1 rounded-b-lg tracking-wider uppercase shadow-sm">Salud Mental</span>
                        <h2 class="text-lg md:text-2xl font-medium leading-relaxed mt-2">
                            "Tu salud mental importa tanto como tus notas. No tienes que cargarlo todo solo."
                        </h2>
                        <p class="text-green-200 mt-2 text-xs md:text-sm">— Bienestar Universitario UCEVA</p>
                    </div>

                    <!-- Slide 3: Inclusión -->
                    <div x-show="activeSlide === 3" x-transition.opacity.duration.500ms class="absolute w-full left-0 px-4 sm:px-12" style="display: none;">
                        <span class="absolute -top-6 left-4 sm:left-12 bg-yellow-500 text-gray-900 text-[10px] font-bold px-3 py-1 rounded-b-lg tracking-wider uppercase shadow-sm">Inclusión</span>
                        <h2 class="text-lg md:text-2xl font-medium leading-relaxed mt-2">
                            "Cada persona aprende diferente. La inclusión es nuestro compromiso contigo."
                        </h2>
                        <p class="text-green-200 mt-2 text-xs md:text-sm">— Bienestar Universitario UCEVA</p>
                    </div>

                    <!-- Slide 4: Autocuidado -->
                    <div x-show="activeSlide === 4" x-transition.opacity.duration.500ms class="absolute w-full left-0 px-4 sm:px-12" style="display: none;">
                        <span class="absolute -top-6 left-4 sm:left-12 bg-[#007F2F] text-white text-[10px] font-bold px-3 py-1 rounded-b-lg tracking-wider uppercase shadow-sm">Autocuidado</span>
                        <h2 class="text-lg md:text-2xl font-medium leading-relaxed mt-2">
                            "Un momento de pausa puede cambiar todo. Date permiso de descansar."
                        </h2>
                        <p class="text-green-200 mt-2 text-xs md:text-sm">— Bienestar Universitario UCEVA</p>
                    </div>

                </div>

                <!-- Botón Siguiente -->
                <button @click="activeSlide = activeSlide === 4 ? 1 : activeSlide + 1" class="absolute right-4 z-20 bg-black/20 hover:bg-black/40 p-2 rounded-xl backdrop-blur-sm transition hidden sm:block opacity-0 group-hover:opacity-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>

                <!-- Puntos del Carrusel -->
                <div class="absolute bottom-4 right-6 flex space-x-2 z-20">
                    <template x-for="i in 4" :key="i">
                        <button @click="activeSlide = i" 
                                class="h-1.5 rounded-full transition-all duration-300 focus:outline-none"
                                :class="activeSlide === i ? 'w-6 bg-[#8AFF00]' : 'w-2 bg-white/40 hover:bg-white/60'">
                        </button>
                    </template>
                </div>
            </div>

            <!-- 3. Recordatorio de Tareas Pendientes (flex-1 para estirarse al máximo) -->
            <div class="flex flex-col flex-1 min-h-0">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-3 shrink-0">
                    <h3 class="text-lg font-bold text-gray-900">Recordatorio de tareas pendientes</h3>
                    
                    <!-- Botón Desplegable de Filtro (Alpine.js) -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.away="open = false" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium py-2 px-4 rounded-lg text-sm flex items-center gap-2 shadow-sm transition">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            {{ $filtro === 'todas' ? 'Filtrar tareas' : $filtros[$filtro] }}
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <!-- Menú de opciones -->
                        <div x-show="open" style="display: none;" 
                             x-transition:enter="transition ease-out duration-100" 
                             x-transition:enter-start="transform opacity-0 scale-95" 
                             x-transition:enter-end="transform opacity-100 scale-100" 
                             x-transition:leave="transition ease-in duration-75" 
                             x-transition:leave-start="transform opacity-100 scale-100" 
                             x-transition:leave-end="transform opacity-0 scale-95" 
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-20 border border-gray-100">
                            @foreach ($filtros as $clave => $nombre)
                                @if ($clave === 'todas')
                                    <div class="border-t border-gray-100 my-1"></div>
                                @endif
                                <a href="{{ route('estudiante.home', $clave === 'todas' ? [] : ['tareas' => $clave]) }}"
                                   @if ($filtro === $clave) aria-current="true" @endif
                                   @class([
                                       'block px-4 py-2.5 text-sm hover:bg-gray-50 hover:text-[#007F2F] transition',
                                       'text-[#007F2F] font-bold' => $filtro === $clave,
                                       'text-gray-700' => $filtro !== $clave,
                                   ])>{{ $nombre }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Caja de tareas rediseñada con colores morados -->
                @if ($tareas->isEmpty() && ! $proximaCita)
                    <div class="flex-1 bg-purple-200 border-2 border-dashed border-purple-300 rounded-xl p-6 flex flex-col items-center justify-center text-center shadow-sm min-h-0 overflow-y-auto">
                        <div class="bg-purple-300/60 p-4 rounded-full mb-4">
                            <svg class="w-8 h-8 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <p class="text-gray-900 font-bold text-lg">
                            {{ $filtro === 'todas' ? 'No tienes tareas pendientes' : 'No tienes tareas que venzan en los '.mb_strtolower($filtros[$filtro]) }}
                        </p>
                        <p class="text-gray-700 text-sm mt-1 max-w-sm">Aquí aparecerán las actividades que te asigne tu profesional y tus próximas citas.</p>
                    </div>
                @else
                    <div class="flex-1 bg-purple-200 border border-purple-300 rounded-xl p-4 shadow-sm min-h-0 overflow-y-auto space-y-3">
                        @if ($proximaCita)
                            @php $fechaCita = $proximaCita->fecha_hora; @endphp
                            <a href="{{ route('citas.miscitas') }}" class="flex items-center gap-4 bg-[#007F2F] hover:bg-[#006324] text-white rounded-xl p-4 transition shadow-sm">
                                <span class="text-2xl shrink-0" aria-hidden="true">📅</span>
                                <span class="min-w-0">
                                    <span class="block text-[11px] font-bold uppercase tracking-wider text-green-200">Tu próxima cita</span>
                                    <span class="block font-bold first-letter:uppercase">{{ $fechaCita->locale('es')->translatedFormat('l j \d\e F') }} · {{ $fechaCita->format('g:i a') }}</span>
                                    @if ($proximaCita->profesional?->persona)
                                        <span class="block text-xs text-green-100">Con {{ $proximaCita->profesional->persona->nombre_completo }}</span>
                                    @endif
                                </span>
                            </a>
                        @endif

                        @forelse ($tareas as $tarea)
                            @php
                                $vencida = $tarea->fecha_limite && $tarea->fecha_limite->toDateString() < $hoy;
                                $venceHoy = $tarea->fecha_limite && $tarea->fecha_limite->toDateString() === $hoy;
                            @endphp
                            <article class="bg-white rounded-xl p-4 shadow-sm border border-purple-100">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h4 class="font-bold text-gray-900 text-sm">{{ $tarea->titulo }}</h4>
                                    @if ($tarea->fecha_limite)
                                        <span @class([
                                            'text-xs font-bold px-2 py-0.5 rounded-full',
                                            'bg-rose-100 text-rose-700' => $vencida,
                                            'bg-amber-100 text-amber-800' => $venceHoy,
                                            'bg-purple-100 text-purple-700' => ! $vencida && ! $venceHoy,
                                        ])>
                                            {{ $vencida ? 'Venció el '.$tarea->fecha_limite->format('Y-m-d') : ($venceHoy ? 'Vence hoy' : 'Hasta el '.$tarea->fecha_limite->format('Y-m-d')) }}
                                        </span>
                                    @else
                                        <span class="text-xs font-medium text-gray-400">Sin fecha límite</span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-700 mt-1.5 whitespace-pre-line break-words">{{ $tarea->descripcion }}</p>
                                <p class="text-xs text-gray-400 mt-2">
                                    Asignada el {{ $tarea->fecha_asignacion->format('Y-m-d') }} · {{ $tarea->caso?->codigo_expediente }}
                                </p>
                            </article>
                        @empty
                            <p class="text-sm text-gray-700 text-center py-4">
                                {{ $filtro === 'todas' ? 'No tienes tareas pendientes.' : 'No tienes tareas que venzan en los '.mb_strtolower($filtros[$filtro]).'.' }}
                            </p>
                        @endforelse
                    </div>
                @endif
            </div>

            <!-- 4. Sección Inferior (shrink-0 y altura estática) -->
            <div class="shrink-0 grid grid-cols-1 lg:grid-cols-2 gap-4 lg:h-[200px]">
                
                <!-- Consejo de Bienestar (Carrusel) -->
                <div x-data="{ 
                        tip: 1, 
                        autoTip() { 
                            setInterval(() => { this.tip = this.tip === 5 ? 1 : this.tip + 1 }, 15000) 
                        } 
                     }" 
                     x-init="autoTip()"
                     class="bg-purple-200 border border-purple-300 rounded-xl shadow-sm p-6 relative flex flex-col justify-between min-h-[180px] lg:min-h-0 lg:h-full">
                    
                    <div>
                        <div class="flex items-center gap-2 mb-4 text-[#007F2F] text-xs font-bold tracking-wider uppercase">
                            <span>💡</span> CONSEJO DE BIENESTAR
                        </div>
                        
                        <div class="relative min-h-[60px]">
                            <div x-show="tip === 1" x-transition.opacity.duration.500ms class="absolute inset-0 flex items-start gap-3">
                                <span class="text-2xl leading-none">🚶</span>
                                <p class="text-gray-900 text-sm md:text-base font-medium">Una caminata de 10 minutos al aire libre reduce el cortisol significativamente.</p>
                            </div>
                            
                            <div x-show="tip === 2" x-transition.opacity.duration.500ms class="absolute inset-0 flex items-start gap-3" style="display: none;">
                                <span class="text-2xl leading-none">😮‍💨</span>
                                <p class="text-gray-900 text-sm md:text-base font-medium">Respira profundo: inhala 4 segundos, retén 4, exhala 4. Repítelo 3 veces.</p>
                            </div>

                            <div x-show="tip === 3" x-transition.opacity.duration.500ms class="absolute inset-0 flex items-start gap-3" style="display: none;">
                                <span class="text-2xl leading-none">💤</span>
                                <p class="text-gray-900 text-sm md:text-base font-medium">Dormir 7-8 horas mejora la concentración y la memoria a largo plazo.</p>
                            </div>

                            <div x-show="tip === 4" x-transition.opacity.duration.500ms class="absolute inset-0 flex items-start gap-3" style="display: none;">
                                <span class="text-2xl leading-none">📵</span>
                                <p class="text-gray-900 text-sm md:text-base font-medium">Desconéctate de pantallas 30 min antes de dormir para un sueño más profundo.</p>
                            </div>

                            <div x-show="tip === 5" x-transition.opacity.duration.500ms class="absolute inset-0 flex items-start gap-3" style="display: none;">
                                <span class="text-2xl leading-none">🤝</span>
                                <p class="text-gray-900 text-sm md:text-base font-medium">Hablar con alguien de confianza sobre lo que sientes alivia la carga emocional.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-4">
                        <template x-for="i in 5" :key="i">
                            <button @click="tip = i" 
                                    class="h-1 flex-1 rounded-full transition-colors duration-300 focus:outline-none"
                                    :class="tip === i ? 'bg-[#007F2F]' : 'bg-purple-400 hover:bg-purple-500'">
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Ayuda Urgente -->
                <div class="bg-[#007F2F] rounded-xl shadow-sm p-6 text-white relative overflow-hidden min-h-[180px] lg:min-h-0 lg:h-full flex flex-col justify-center">
                    <div class="relative z-10">
                        <div class="flex items-center gap-2 mb-3 text-red-400 text-xs font-extrabold tracking-wider uppercase">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
                            ¿Necesitas ayuda urgente?
                        </div>
                        
                        <h3 class="font-bold text-lg mb-1 leading-tight">Si estás en una situación de crisis, no estás solo.</h3>
                        <p class="text-green-100 text-sm mb-4">
                            Línea 106 — atención gratuita las 24 horas, los 7 días de la semana.
                        </p>

                        <div class="grid grid-cols-2 gap-3 mt-auto">
                            <div class="bg-[#006324] text-center py-2 px-2 rounded-lg border border-[#009136] shadow-inner flex flex-col justify-center">
                                <div class="font-bold text-lg">106</div>
                                <div class="text-xs text-green-200 mt-0.5">Crisis emocional</div>
                            </div>
                            
                            <div class="bg-[#006324] text-center py-2 px-2 rounded-lg border border-[#009136] shadow-inner flex flex-col justify-center">
                                <div class="font-bold text-sm">Bienestar UCEVA</div>
                                <div class="text-xs text-green-200 mt-0.5">Lun-Vie 8am-5pm</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
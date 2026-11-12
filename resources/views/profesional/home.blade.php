<x-app-layout>
       <!-- Contenedor Maestro -->
<div class="min-h-screen bg-gray-50 flex flex-col justify-between">

    <!-- 1. FRANJA BLANCA SUPERIOR -->
    <div class="bg-white border-b border-gray-200 px-4 sm:px-8 py-3.5 flex justify-between items-center gap-3 shrink-0">
        <div>
            <h1 class="text-xl font-black text-[#007F2F] tracking-tight">
                Inicio · SIGABU
            </h1>
            <p class="text-xs text-gray-400 font-medium">Portal de Bienestar Universitario — UCEVA</p>
        </div>

        <!-- Botón de Notificaciones -->
        <button class="relative p-2.5 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-amber-400 rounded-full ring-2 ring-white"></span>
        </button>
    </div>

    <!-- CONTENIDO PRINCIPAL DISTRIBUIDO -->
    <div class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-8 flex flex-col justify-between gap-6">

        <!-- Emergencias por atender: las marcó el estudiante al pedir la cita -->
        @if ($emergencias->isNotEmpty())
            <section class="bg-rose-50 border border-rose-300 rounded-2xl p-5" role="alert" aria-labelledby="titulo-emergencias">
                <h2 id="titulo-emergencias" class="text-base font-bold text-rose-800 flex items-center gap-2">
                    <span aria-hidden="true">🚨</span> {{ $emergencias->count() === 1 ? '1 caso de emergencia' : $emergencias->count().' casos de emergencia' }} por atender
                </h2>
                <p class="text-xs text-rose-700 mt-0.5">El estudiante pidió atención urgente. Acuerda la primera cita lo antes posible.</p>
                <ul class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach ($emergencias as $caso)
                        <li>
                            <a href="{{ route('solicitudes.index') }}#solicitud-{{ $caso->id_caso }}"
                               class="flex flex-wrap items-center justify-between gap-2 bg-white border border-rose-200 hover:border-rose-400 rounded-xl px-4 py-2.5 text-sm transition">
                                <span class="font-semibold text-gray-900">{{ $caso->solicitud?->persona?->nombre_completo }}</span>
                                <span class="text-xs text-gray-500">{{ $caso->codigo_expediente }} · {{ $caso->id_profesional ? 'a tu cargo' : 'sin profesional' }} · desde el {{ $caso->fecha_apertura?->format('Y-m-d') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <!-- Casos recibidos por traslado administrativo que necesitan cita -->
        @if ($trasladados->isNotEmpty())
            <section class="bg-amber-50 border border-amber-300 rounded-2xl p-5" aria-labelledby="titulo-trasladados">
                <h2 id="titulo-trasladados" class="text-base font-bold text-amber-900">
                    {{ $trasladados->count() === 1 ? '1 caso recibido por traslado necesita cita' : $trasladados->count().' casos recibidos por traslado necesitan cita' }}
                </h2>
                <p class="text-xs text-amber-800 mt-0.5">Sus citas anteriores se cancelaron con el traslado. Contacta al estudiante y agenda la siguiente.</p>
                <ul class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach ($trasladados as $caso)
                        <li>
                            <a href="{{ route('citas.create', ['caso' => $caso->id_caso]) }}"
                               class="flex flex-wrap items-center justify-between gap-2 bg-white border border-amber-200 hover:border-amber-400 rounded-xl px-4 py-2.5 text-sm transition">
                                <span class="font-semibold text-gray-900">{{ $caso->solicitud?->persona?->nombre_completo }}</span>
                                <span class="text-xs text-gray-500">{{ $caso->codigo_expediente }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <!-- 2. SALUDO + KPIs ENGRANDECIDOS -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-[#007F2F] tracking-tight flex items-center gap-2">
                    Buen día, {{ Auth::user()->name }} 👋
                </h2>
                <p class="text-sm text-gray-500 mt-1 font-medium">
                    Sistema Inteligente de Gestión y Analítica del Bienestar Universitario
                </p>
            </div>

            <!-- KPIs amplios -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 w-full lg:w-auto">
                <div class="bg-white p-4 px-6 rounded-2xl border border-gray-200/90 shadow-sm text-center min-w-[130px]">
                    <span class="text-3xl font-black text-purple-600 block leading-none">{{ $indicadores['activos'] }}</span>
                    <span class="text-xs text-gray-500 font-bold mt-1.5 block">Casos activos</span>
                </div>
                <div class="bg-white p-4 px-6 rounded-2xl border border-gray-200/90 shadow-sm text-center min-w-[130px]">
                    <span class="text-3xl font-black text-red-600 block leading-none">{{ $indicadores['riesgoAlto'] }}</span>
                    <span class="text-xs text-gray-500 font-bold mt-1.5 block">Riesgo alto</span>
                </div>
                <div class="bg-white p-4 px-6 rounded-2xl border border-gray-200/90 shadow-sm text-center min-w-[130px]">
                    <span class="text-3xl font-black text-amber-500 block leading-none">{{ $indicadores['enSeguimiento'] }}</span>
                    <span class="text-xs text-gray-500 font-bold mt-1.5 block">En seguimiento</span>
                </div>
                <div class="bg-white p-4 px-6 rounded-2xl border border-gray-200/90 shadow-sm text-center min-w-[130px]">
                    <span class="text-3xl font-black text-emerald-600 block leading-none">{{ $indicadores['cerradosMes'] }}</span>
                    <span class="text-xs text-gray-500 font-bold mt-1.5 block">Cerrados este mes</span>
                </div>
            </div>
        </div>

        <!-- 3. BANNER / CARRUSEL PRINCIPAL -->
<div x-data="{
    activeSlide: 0,
    timer: null,
    slides: [
        {
            tag: 'APOYO',
            quote: 'Pedir ayuda es el acto más valiente. Estamos aquí para acompañarte en cada momento.',
            sub: '— SIGABU — UCEVA',
            bg: 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?q=80&w=2560&auto=format&fit=crop'
        },
        {
            tag: 'SALUD MENTAL',
            quote: 'Tu bienestar emocional es prioridad. Tómate una pausa y escucha lo que tu mente necesita.',
            sub: '— Área de Salud Mental — UCEVA',
            bg: 'https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?q=80&w=2560&auto=format&fit=crop'
        },
        {
            tag: 'INCLUSIÓN',
            quote: 'Construimos un campus seguro, equitativo y accesible para toda la comunidad universitaria.',
            sub: '— Programa de Inclusión — UCEVA',
            bg: 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?q=80&w=2560&auto=format&fit=crop'
        },
        {
            tag: 'ACOMPAÑAMIENTO',
            quote: 'No tienes que afrontar los retos solo. Nuestro equipo profesional está listo para escucharte.',
            sub: '— Atención Integral — SIGABU',
            bg: 'https://images.unsplash.com/photo-1499209974431-9dac3ada00d7?q=80&w=2560&auto=format&fit=crop'
        },
        {
            tag: 'PREVENCIÓN',
            quote: 'Cuidar de ti y de tus compañeros crea un entorno más humano y saludable para todos.',
            sub: '— Bienestar Universitario — UCEVA',
            bg: 'https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?q=80&w=2560&auto=format&fit=crop'
        },
        {
            tag: 'AUTOCUIDADO',
            quote: 'Avanzar a tu propio ritmo también es progresar. Valora cada logro de tu día a día.',
            sub: '— Desarrollo Humano — SIGABU',
            bg: 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?q=80&w=2560&auto=format&fit=crop'
        }
    ],
    startTimer() {
        this.timer = setInterval(() => {
            this.activeSlide = (this.activeSlide + 1) % this.slides.length;
        }, 5000);
    },
    stopTimer() {
        clearInterval(this.timer);
    }
}" 
x-init="startTimer()"
@mouseenter="stopTimer()" 
@mouseleave="startTimer()"
class="bg-[#004D1A] rounded-3xl p-7 text-white relative overflow-hidden flex items-center shadow-md h-[260px] sm:h-[170px] group">

    <!-- Diapositivas dinámicas -->
    <template x-for="(slide, index) in slides" :key="index">
        <div x-show="activeSlide === index"
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 translate-x-4"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-300 absolute inset-0"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 -translate-x-4"
             class="absolute inset-0 p-7 flex items-center">
             
            <!-- Imagen de fondo -->
            <div class="absolute inset-0 bg-cover bg-center opacity-30 transition-transform duration-700 group-hover:scale-105" 
                 :style="`background-image: url('${slide.bg}');`"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#003813] via-[#004D1A]/90 to-transparent"></div>
            
            <!-- Contenido de texto -->
            <div class="relative z-10 w-full px-3 sm:px-6">
                <span class="bg-[#007F2F] text-white text-[10px] font-black px-3 py-1 rounded-md uppercase tracking-wider shadow-sm" x-text="slide.tag"></span>
                <h3 class="text-xl sm:text-2xl font-black leading-snug mt-2.5 max-w-3xl tracking-wide" x-text="`'${slide.quote}'`"></h3>
                <p class="text-green-200 text-sm font-semibold mt-2" x-text="slide.sub"></p>
            </div>
        </div>
    </template>

    <!-- Indicadores de puntos (6 slides) -->
    <div class="absolute bottom-5 right-6 flex space-x-2 z-20">
        <template x-for="(slide, index) in slides" :key="index">
            <button @click="activeSlide = index"
                    class="h-2 rounded-full transition-all duration-300 focus:outline-none"
                    :class="activeSlide === index ? 'w-7 bg-[#8AFF00] shadow' : 'w-2 bg-white/40 hover:bg-white/70'">
            </button>
        </template>
    </div>
</div>

        <!-- 4. ACCESOS RÁPIDOS (Tarjetas con presencia visual amplia) -->
        <div>
            <h3 class="text-xs font-black text-gray-700 uppercase tracking-wider mb-3">ACCESOS RÁPIDOS</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                
                @if (Auth::user()->atiendeServicio('Salud Mental'))
                <a href="{{ route('casos.create', ['servicio' => 'Salud Mental']) }}" class="bg-white border border-gray-200/90 hover:border-[#007F2F] hover:shadow-lg transition-all duration-200 rounded-2xl p-4 flex flex-col justify-between h-[105px] group">
                    <div class="w-10 h-10 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition-transform">
                        🩺
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-gray-900 truncate leading-tight">Nueva consulta</h4>
                        <p class="text-[11px] font-medium text-gray-400 truncate mt-0.5">Salud Mental</p>
                    </div>
                </a>
                @endif

                @if (Auth::user()->atiendeServicio('Inclusión'))
                <a href="{{ route('profesional.inclusion') }}" class="bg-white border border-gray-200/90 hover:border-[#007F2F] hover:shadow-lg transition-all duration-200 rounded-2xl p-4 flex flex-col justify-between h-[105px] group">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition-transform">
                        ♿
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-gray-900 truncate leading-tight">Caracterización</h4>
                        <p class="text-[11px] font-medium text-gray-400 truncate mt-0.5">Inclusión</p>
                    </div>
                </a>
                @endif

                <a href="{{ route('profesional.vbg') }}" class="bg-white border border-gray-200/90 hover:border-[#007F2F] hover:shadow-lg transition-all duration-200 rounded-2xl p-4 flex flex-col justify-between h-[105px] group">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition-transform">
                        🛡️
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-gray-900 truncate leading-tight">Nuevo reporte</h4>
                        <p class="text-[11px] font-medium text-gray-400 truncate mt-0.5">Violencias de Género</p>
                    </div>
                </a>

                <a href="{{ route('profesional.expedientes') }}" class="bg-white border border-gray-200/90 hover:border-[#007F2F] hover:shadow-lg transition-all duration-200 rounded-2xl p-4 flex flex-col justify-between h-[105px] group">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition-transform">
                        📂
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-gray-900 truncate leading-tight">Expedientes</h4>
                        <p class="text-[11px] font-medium text-gray-400 truncate mt-0.5">Historias clínicas</p>
                    </div>
                </a>

                <a href="{{ route('profesional.analitica') }}" class="bg-white border border-gray-200/90 hover:border-[#007F2F] hover:shadow-lg transition-all duration-200 rounded-2xl p-4 flex flex-col justify-between h-[105px] group">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition-transform">
                        📊
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-gray-900 truncate leading-tight">Analítica</h4>
                        <p class="text-[11px] font-medium text-gray-400 truncate mt-0.5">Reportes e indicadores</p>
                    </div>
                </a>

                <a href="{{ route('repositorio.index') }}" class="bg-white border border-gray-200/90 hover:border-[#007F2F] hover:shadow-lg transition-all duration-200 rounded-2xl p-4 flex flex-col justify-between h-[105px] group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition-transform">
                        📚
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-gray-900 truncate leading-tight">Repositorio</h4>
                        <p class="text-[11px] font-medium text-gray-400 truncate mt-0.5">Material psicoeducativo</p>
                    </div>
                </a>

            </div>
        </div>

        <!-- 5. SECCIÓN INFERIOR PROPORCIONADA (REFLEXIÓN DEL DÍA + PANEL DE CONTROL) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 lg:h-[210px]">
            
            <!-- Tarjeta Verde: Reflexión del Día -->
            <div class="relative rounded-3xl overflow-hidden shadow-sm flex flex-col justify-end p-7 bg-[#004D1A] min-h-[190px] lg:min-h-0 lg:h-full">
                <div class="absolute inset-0 bg-cover bg-center opacity-40" style="background-image: url('https://images.unsplash.com/photo-1511497584788-876761c11969?q=80&w=1000&auto=format&fit=crop');"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-[#003d15] via-[#003d15]/80 to-transparent"></div>

                <div class="relative z-10">
                    <span class="text-xs font-black text-[#8AFF00] uppercase tracking-wider block mb-1.5">
                        REFLEXIÓN DEL DÍA
                    </span>
                    <p class="text-base sm:text-lg font-bold text-white leading-relaxed">
                        "No tienes que resolver todo hoy. Un paso a la vez es suficiente para avanzar."
                    </p>
                </div>
            </div>

            <!-- Tarjeta Blanca: Mi agenda -->
            <div class="bg-white border border-gray-200/90 rounded-3xl p-7 flex flex-col justify-between shadow-sm h-full">
                <div>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center mb-3 font-bold text-lg">
                        📊
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Mi agenda</h3>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1 leading-relaxed">
                        Hoy tienes <strong class="text-gray-800">{{ $citasHoy }} {{ $citasHoy === 1 ? 'cita' : 'citas' }}</strong>
                        y <strong class="text-gray-800">{{ $porAtender }} {{ $porAtender === 1 ? 'solicitud' : 'solicitudes' }}</strong> por atender.
                        Consulta tu agenda y los pacientes por reagendar.
                    </p>
                </div>

                <div>
                    <a href="{{ route('citas.agenda') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition-all shadow-sm hover:shadow">
                        Ir a mi agenda
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                </div>
            </div>

        </div>

    </div>
</div>
</x-app-layout>
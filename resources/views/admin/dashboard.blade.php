<x-app-layout>
    <div class="min-h-screen lg:min-h-0 lg:h-screen bg-[#F8FAFC] flex flex-col font-sans lg:overflow-hidden">

        <!-- 1. Encabezado Superior -->
        <div class="bg-white px-4 sm:px-6 py-2 flex items-center justify-between gap-3 border-b border-gray-100 shrink-0" style="min-height: 9vh;">
            <div>
                <h1 class="font-bold text-[#007F2F] flex items-center gap-2" style="font-size: clamp(1rem, 2.2vh, 1.25rem);">
                    Inicio · SIGABU
                </h1>
                <p class="text-gray-500" style="font-size: clamp(0.7rem, 1.5vh, 0.85rem);">Portal de Bienestar Universitario — UCEVA</p>
            </div>

            <div class="flex items-center gap-3">
                <button class="relative p-2.5 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-xl transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-amber-500 rounded-full"></span>
                </button>
            </div>
        </div>

        <!-- 2. Contenido Principal -->
        <div class="w-full max-w-7xl mx-auto flex-1 flex flex-col lg:overflow-hidden" style="padding: 2.2vh 1.5rem; gap: 2.2vh;">

            <!-- Saludo + Métricas -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 shrink-0">
                <div>
                    <h2 class="font-bold text-[#007F2F] flex items-center gap-2" style="font-size: clamp(1.3rem, 3.2vh, 2rem);">
                        {{ $saludo }}, {{ Auth::user()->name }} 👋
                    </h2>
                    <p class="text-gray-500" style="font-size: clamp(0.75rem, 1.6vh, 0.9rem); margin-top: 0.4vh;">Administración del Sistema — SIGABU</p>
                </div>

                <div class="grid grid-cols-2 lg:flex lg:items-center gap-3">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm flex items-center gap-2.5" style="padding: 1.3vh 1.1rem;">
                        <div class="rounded-lg bg-pink-50 text-pink-600 font-bold" style="padding: 0.5vh 0.8rem; font-size: clamp(0.9rem, 1.9vh, 1.1rem);">{{ $indicadores['usuarios'] }}</div>
                        <span class="text-gray-600 font-medium" style="font-size: clamp(0.72rem, 1.55vh, 0.87rem);">Usuarios del sistema</span>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm flex items-center gap-2.5" style="padding: 1.3vh 1.1rem;">
                        <div class="rounded-lg bg-emerald-50 text-emerald-600 font-bold" style="padding: 0.5vh 0.8rem; font-size: clamp(0.9rem, 1.9vh, 1.1rem);">{{ $indicadores['casos'] }}</div>
                        <span class="text-gray-600 font-medium" style="font-size: clamp(0.72rem, 1.55vh, 0.87rem);">Casos totales</span>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm flex items-center gap-2.5" style="padding: 1.3vh 1.1rem;">
                        <div class="rounded-lg bg-sky-50 text-sky-600 font-bold" style="padding: 0.5vh 0.8rem; font-size: clamp(0.9rem, 1.9vh, 1.1rem);">{{ $indicadores['modulos'] }}</div>
                        <span class="text-gray-600 font-medium" style="font-size: clamp(0.72rem, 1.55vh, 0.87rem);">Módulos activos</span>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm flex items-center gap-2.5" style="padding: 1.3vh 1.1rem;">
                        <div class="rounded-lg bg-amber-50 text-amber-600 font-bold" style="padding: 0.5vh 0.8rem; font-size: clamp(0.9rem, 1.9vh, 1.1rem);">{{ $indicadores['auditoriaHoy'] }}</div>
                        <span class="text-gray-600 font-medium" style="font-size: clamp(0.72rem, 1.55vh, 0.87rem);">Registros de auditoría hoy</span>
                    </div>
                </div>
            </div>

            <!-- 3. Banner -->
            <div style="background-color: #0E4720; padding: 4.5vh 2.4rem;" class="relative overflow-hidden rounded-2xl text-white shadow-sm shrink-0">
                <div class="absolute inset-0 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px] opacity-10"></div>

                <div class="relative z-10 max-w-3xl" style="display:flex; flex-direction:column; gap:1.1vh;">
                    <span class="inline-block bg-white/10 text-white font-semibold rounded-md tracking-wider uppercase backdrop-blur-sm border border-white/20" style="padding: 0.6vh 0.8rem; font-size: clamp(0.65rem, 1.4vh, 0.75rem); width: fit-content;">
                        Salud Mental
                    </span>

                    <blockquote class="font-bold leading-snug text-white" style="font-size: clamp(1.15rem, 3vh, 1.75rem);">
                        "Cuidar la salud mental no es un lujo, es una necesidad. Tu bienestar importa."
                    </blockquote>

                    <p class="text-green-200 font-medium" style="font-size: clamp(0.72rem, 1.55vh, 0.87rem);">
                        — Bienestar Universitario UCEVA
                    </p>
                </div>
            </div>

            <!-- 4. Accesos Rápidos -->
            <div class="shrink-0">
                <h3 class="font-bold text-gray-900" style="font-size: clamp(0.88rem, 1.9vh, 1.05rem); margin-bottom: 1.3vh;">Accesos rápidos</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-row w-full gap-3 lg:gap-4">
                    <a href="{{ route('admin.administracion') }}" class="flex-1 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all flex items-center gap-3 group min-w-0" style="padding: 2.3vh 1.2rem;">
                        <div class="rounded-xl bg-purple-50 text-purple-600 group-hover:scale-105 transition-transform shrink-0" style="padding: 0.8vh; font-size: clamp(1rem, 2.1vh, 1.25rem);">👥</div>
                        <div class="truncate">
                            <h4 class="font-bold text-gray-900 group-hover:text-[#007F2F] transition-colors truncate" style="font-size: clamp(0.78rem, 1.65vh, 0.95rem);">Usuarios y roles</h4>
                            <p class="text-gray-400 truncate" style="font-size: clamp(0.65rem, 1.4vh, 0.78rem); margin-top: 0.25vh;">Accesos y permisos</p>
                        </div>
                    </a>

                    <a href="{{ route('admin.asignaciones.index') }}" class="flex-1 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all flex items-center gap-3 group min-w-0" style="padding: 2.3vh 1.2rem;">
                        <div class="rounded-xl bg-amber-50 text-amber-600 group-hover:scale-105 transition-transform shrink-0" style="padding: 0.8vh; font-size: clamp(1rem, 2.1vh, 1.25rem);">📁</div>
                        <div class="truncate">
                            <h4 class="font-bold text-gray-900 group-hover:text-[#007F2F] transition-colors truncate" style="font-size: clamp(0.78rem, 1.65vh, 0.95rem);">Catálogos</h4>
                            <p class="text-gray-400 truncate" style="font-size: clamp(0.65rem, 1.4vh, 0.78rem); margin-top: 0.25vh;">Facultades y asignaciones</p>
                        </div>
                    </a>

                    <a href="{{ route('admin.consentimientos.index') }}" class="flex-1 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all flex items-center gap-3 group min-w-0" style="padding: 2.3vh 1.2rem;">
                        <div class="rounded-xl bg-teal-50 text-teal-600 group-hover:scale-105 transition-transform shrink-0" style="padding: 0.8vh; font-size: clamp(1rem, 2.1vh, 1.25rem);">⚙️</div>
                        <div class="truncate">
                            <h4 class="font-bold text-gray-900 group-hover:text-[#007F2F] transition-colors truncate" style="font-size: clamp(0.78rem, 1.65vh, 0.95rem);">Flujos de trabajo</h4>
                            <p class="text-gray-400 truncate" style="font-size: clamp(0.65rem, 1.4vh, 0.78rem); margin-top: 0.25vh;">Consentimientos informados</p>
                        </div>
                    </a>

                    <a href="{{ route('admin.auditoria') }}" class="flex-1 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all flex items-center gap-3 group min-w-0" style="padding: 2.3vh 1.2rem;">
                        <div class="rounded-xl bg-sky-50 text-sky-600 group-hover:scale-105 transition-transform shrink-0" style="padding: 0.8vh; font-size: clamp(1rem, 2.1vh, 1.25rem);">🔍</div>
                        <div class="truncate">
                            <h4 class="font-bold text-gray-900 group-hover:text-[#007F2F] transition-colors truncate" style="font-size: clamp(0.78rem, 1.65vh, 0.95rem);">Auditoría</h4>
                            <p class="text-gray-400 truncate" style="font-size: clamp(0.65rem, 1.4vh, 0.78rem); margin-top: 0.25vh;">Trazabilidad</p>
                        </div>
                    </a>

                    <a href="{{ route('admin.analitica') }}" class="flex-1 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all flex items-center gap-3 group min-w-0" style="padding: 2.3vh 1.2rem;">
                        <div class="rounded-xl bg-indigo-50 text-indigo-600 group-hover:scale-105 transition-transform shrink-0" style="padding: 0.8vh; font-size: clamp(1rem, 2.1vh, 1.25rem);">📊</div>
                        <div class="truncate">
                            <h4 class="font-bold text-gray-900 group-hover:text-[#007F2F] transition-colors truncate" style="font-size: clamp(0.78rem, 1.65vh, 0.95rem);">Analítica inst.</h4>
                            <p class="text-gray-400 truncate" style="font-size: clamp(0.65rem, 1.4vh, 0.78rem); margin-top: 0.25vh;">Dashboards</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- 5. Sección Inferior (llena el espacio restante, ahora más chica) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1" style="min-height: 0;">

                <!-- Reflexión del día -->
                <div style="background-color: #0E4720; padding: 2vh 1.5rem;" class="relative overflow-hidden rounded-2xl text-white flex flex-col justify-end shadow-sm">
                    <div class="absolute inset-0 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px] opacity-10"></div>

                    <div class="relative z-10" style="display:flex; flex-direction:column; gap:0.6vh;">
                        <span class="font-bold tracking-wider text-green-300 uppercase" style="font-size: clamp(0.72rem, 1.6vh, 0.88rem);">Reflexión del día</span>
                        <blockquote class="font-medium leading-relaxed text-white" style="font-size: clamp(1rem, 2.2vh, 1.25rem);">
                            "No tienes que resolver todo hoy. Un paso a la vez es suficiente para avanzar."
                        </blockquote>
                    </div>
                </div>

                <!-- Panel de Control -->
                <div class="bg-white rounded-2xl border border-gray-100 flex flex-col justify-between shadow-sm" style="padding: 2vh 1.5rem;">
                    <div style="display:flex; flex-direction:column; gap:0.6vh;">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-indigo-50 text-indigo-600" style="padding: 0.6vh; font-size: clamp(1rem, 2.1vh, 1.25rem);">📊</div>
                            <h4 class="font-bold text-gray-900" style="font-size: clamp(1rem, 2.1vh, 1.2rem);">Panel de control</h4>
                        </div>
                        <p class="text-gray-500 leading-relaxed pr-4" style="font-size: clamp(0.85rem, 1.8vh, 1.05rem);">
                            Consulta indicadores en tiempo real, alertas de riesgo y resumen.
                        </p>
                    </div>

                    <div>
                        <a href="{{ route('admin.global') }}" class="inline-block rounded-lg bg-[#00A838] hover:bg-[#008F30] text-white font-semibold transition-colors shadow-sm" style="padding: 1.3vh 1.6rem; font-size: clamp(0.85rem, 1.8vh, 1.05rem);">
                            Ir al Dashboard
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
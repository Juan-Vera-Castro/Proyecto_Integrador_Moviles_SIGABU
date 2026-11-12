@php
    $rolActual = Auth::user()->role;
    $portal = match ($rolActual) {
        'admin' => 'ADMINISTRADOR',
        'profesional' => 'PROFESIONAL',
        'almacen' => 'ALMACÉN',
        'vbg' => 'VBG',
        default => 'ESTUDIANTIL',
    };
    $etiquetaRol = match ($rolActual) {
        'admin' => 'Admin',
        'profesional' => Auth::user()->etiquetaArea() ?? 'Profesional',
        'almacen' => 'Personal de Almacén',
        'vbg' => 'Profesional VBG',
        default => 'Estudiante',
    };
    $claseItem = fn (string $ruta) => request()->routeIs($ruta)
        ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold'
        : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium';
@endphp
<aside id="menu-principal" aria-label="Menú principal" class="menu-lateral w-64 alto-app bg-[#042B14] text-white flex flex-col justify-between shrink-0 lg:relative font-sans border-r border-[#0A3319]" :class="{ 'abierto': menuAbierto }">

    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- 1. Encabezado / Logo -->
        <div class="p-6 flex items-center gap-3 shrink-0 border-b border-white/5">
            <div class="bg-white p-1 rounded-lg shadow-sm shrink-0 w-9 h-9 flex items-center justify-center">
                <img src="{{ asset('images/logo-uceva-icon.png') }}" alt="UCEVA" style="max-width:100%; max-height:100%; object-fit:contain; display:block;">
            </div>
            <div class="min-w-0">
                <h1 class="font-bold text-xl leading-none tracking-wide text-white">SIGABU</h1>
                <p class="text-[9px] text-[#39FF14] font-bold tracking-wider mt-1 uppercase leading-tight">Vicerrectoría de Bienestar Universitario</p>
            </div>
            <button type="button" x-ref="cerrarMenu" @click="cerrarMenu()"
                    class="lg:hidden ml-auto -mr-3 w-11 h-11 shrink-0 flex items-center justify-center rounded-lg text-gray-200 hover:bg-white/10 transition-colors"
                    aria-label="Cerrar menú">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- 2. Badge del Portal -->
        <div class="px-5 pt-5 pb-3 shrink-0">
            <div class="border border-[#007F2F] text-[#39FF14] text-[10px] py-2 px-3 rounded-lg flex items-center justify-center font-bold tracking-wider uppercase bg-[#007F2F]/10 shadow-inner">
                PORTAL {{ $portal }}
            </div>
        </div>

        <!-- 3. Menú de Navegación -->
        <nav class="flex-1 mt-2 flex flex-col space-y-1 overflow-y-auto pb-4">
            
            @if(Auth::user()->role === 'admin')
                <!-- ============================== -->
                <!-- MENÚ EXCLUSIVO ADMINISTRADOR   -->
                <!-- ============================== -->
                <!-- Inicio (Ahora se ilumina al estar en admin.dashboard) -->
<a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
    <span class="text-sm">Inicio</span>
</a>

<!-- Dashboard global (Ahora apuntará a admin.global) -->
<a href="{{ route('admin.global') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('admin.global') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
    <span class="text-sm">Dashboard global</span>
</a>

                <a href="{{ route('indicadores.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('indicadores.*') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 00-2-2z"></path></svg>
                    <span class="text-sm">Analítica institucional</span>
                </a>

                <!-- Administración -->
<a href="{{ route('admin.administracion') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('admin.administracion') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
    <span class="text-sm">Administración</span>
</a>

<!-- Modelos de consentimiento (HU-009) -->
<a href="{{ route('admin.consentimientos.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('admin.consentimientos.*') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
    <span class="text-sm">Consentimientos</span>
</a>

<!-- Formatos institucionales: código, versión y fecha de Calidad (D-17) -->
<a href="{{ route('admin.formatos.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('admin.formatos.*') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2zM9 13h6M9 17h3"></path></svg>
    <span class="text-sm">Formatos</span>
</a>

<!-- Asignación de profesionales por facultad y jornada -->
<a href="{{ route('admin.asignaciones.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('admin.asignaciones.*') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
    <span class="text-sm">Asignaciones</span>
</a>

<!-- Reapertura de casos de Inclusión cerrados con constancia -->
<a href="{{ route('admin.casos.reapertura') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('admin.casos.*') }}">
    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M5.1 15a8 8 0 0013.5 3M18.9 9A8 8 0 005.4 6"/></svg>
    <span class="text-sm">Reabrir casos</span>
</a>

<!-- Auditoría de expedientes: trazabilidad de acciones sensibles -->
<a href="{{ route('admin.auditoria') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('admin.auditoria') }}">
    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
    <span class="text-sm">Auditoría</span>
</a>

                <a href="{{ route('repositorio.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('repositorio.*') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span class="text-sm">Repositorio</span>
                </a>

            @elseif(Auth::user()->role === 'profesional')
                <!-- ============================== -->
                <!-- MENÚ PROFESIONAL               -->
                <!-- ============================== -->
                <a href="{{ route('profesional.home') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('profesional.home') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span class="text-sm">Inicio</span>
                </a>
                
                <a href="{{ route('citas.agenda') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('citas.agenda') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    <span class="text-sm">Mi agenda</span>
                </a>

                @php($solicitudesPendientes = \App\Models\Caso::porAtenderDe(Auth::user()))
                <a href="{{ route('solicitudes.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('solicitudes.*') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    <span class="text-sm">Solicitudes de cita</span>
                    @if ($solicitudesPendientes > 0)
                        <span class="ml-auto min-w-[20px] px-1.5 py-0.5 rounded-full bg-amber-400 text-[#042B14] text-[10px] font-black text-center" aria-label="{{ $solicitudesPendientes }} pendientes">{{ $solicitudesPendientes }}</span>
                    @endif
                </a>

                @if (Auth::user()->atiendeServicio('Salud Mental'))
                <a href="{{ route('profesional.salud-mental') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('profesional.salud-mental') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <span class="text-[20px] shrink-0">🧠</span>
                    <span class="text-sm">Salud Mental</span>
                </a>
                @endif

                @if (Auth::user()->atiendeServicio('Inclusión'))
                <a href="{{ route('profesional.inclusion') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('profesional.inclusion') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <span class="text-[20px] shrink-0 text-[#7bb9fc]">♿</span>
                    <span class="text-sm">Inclusión Univ.</span>
                </a>
                <a href="{{ route('inclusion.recursos.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('inclusion.recursos.*') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.25C10.8 5.5 9 5 7 5S3.5 5.5 3 6v13c.5-.5 2-1 4-1s3.8.5 5 1.25m0-13C13.2 5.5 15 5 17 5s3.5.5 4 1v13c-.5-.5-2-1-4-1s-3.8.5-5 1.25m0-13v13"/></svg>
                    <span class="text-sm">Recursos de inclusión</span>
                </a>
                @endif

                <a href="{{ route('profesional.vbg') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('profesional.vbg*') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span class="text-sm">Violencias de Género</span>
                </a>

                <a href="{{ route('profesional.expedientes') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('profesional.expedientes') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                    <span class="text-sm">Expedientes</span>
                </a>

                <a href="{{ route('profesional.analitica') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('profesional.analitica') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 00-2-2z"></path></svg>
                    <span class="text-sm">Analítica</span>
                </a>
                {{-- HU-032: solo los profesionales que el administrador autorizó --}}
                @if (Auth::user()->puedeVerIndicadores())
                    <a href="{{ route('indicadores.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('indicadores.*') }}">
                        <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-sm">Indicadores</span>
                    </a>
                @endif

                <a href="{{ route('repositorio.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('repositorio.*') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span class="text-sm">Repositorio</span>
                </a>

            @elseif($rolActual === 'almacen')
                <!-- ============================== -->
                <!-- MENÚ ALMACÉN                   -->
                <!-- ============================== -->
                <a href="{{ route('almacen.home') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('almacen.home') }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span class="text-sm">Donaciones alimentarias</span>
                </a>
                <a href="{{ route('repositorio.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('repositorio.*') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span class="text-sm">Repositorio</span>
                </a>

            @elseif($rolActual === 'vbg')
                <!-- ============================== -->
                <!-- MENÚ VBG                       -->
                <!-- ============================== -->
                <a href="{{ route('vbg.home') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('vbg.*') }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span class="text-sm">Reportes y casos VBG</span>
                </a>
                <a href="{{ route('repositorio.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('repositorio.*') }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span class="text-sm">Repositorio</span>
                </a>

            @else
                <!-- ============================== -->
                <!-- MENÚ ESTUDIANTE                -->
                <!-- ============================== -->
                <a href="{{ route('estudiante.home') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('estudiante.home') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span class="text-sm">Inicio</span>
                </a>

                <a href="{{ route('citas.solicitar') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('citas.solicitar') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span class="text-sm">Solicitar cita</span>
                </a>

                <a href="{{ route('citas.miscitas') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('citas.miscitas') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="text-sm">Mis citas</span>
                </a>

                <a href="{{ route('reportes.crear') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('reportes.crear') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span class="text-sm">Reportar situación</span>
                </a>

                <a href="{{ route('alimentario.crear') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('alimentario.crear') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span class="text-sm">Apoyo alimentario</span>
                </a>

                <a href="{{ route('recursos.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ request()->routeIs('recursos.index') ? 'bg-[#0a4220] border-[#39FF14] text-[#39FF14] font-bold' : 'border-transparent text-gray-200 hover:bg-white/5 hover:text-white font-medium' }}">
                    <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477-4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    <span class="text-sm">Recursos</span>
                </a>
            @endif

            {{-- Avisos de la campana: para todos los roles --}}
            @php($avisosSinLeer ??= 0)
            <a href="{{ route('notificaciones.index') }}" class="flex items-center gap-3 px-6 py-3 border-l-4 transition-colors {{ $claseItem('notificaciones.*') }}">
                <svg class="w-5 h-5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span class="text-sm">Notificaciones</span>
                @if ($avisosSinLeer > 0)
                    <span class="ml-auto min-w-[20px] px-1.5 py-0.5 rounded-full bg-amber-400 text-[#042B14] text-[10px] font-black text-center" aria-label="{{ $avisosSinLeer }} sin leer">{{ $avisosSinLeer > 99 ? '99+' : $avisosSinLeer }}</span>
                @endif
            </a>
        </nav>
    </div>

    <!-- 4. Perfil de Usuario y Logout -->
    <div class="p-5 border-t border-[#0A3319] bg-[#021A0C] shrink-0">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 rounded-full bg-[#39FF14] flex items-center justify-center text-black font-bold text-sm shrink-0 uppercase">
                    {{ substr(Auth::user()->name, 0, 2) }}
                </div>
                <div class="truncate">
                    <p class="text-sm font-bold text-white truncate max-w-[120px]">{{ Auth::user()->name }}</p>
                    <p class="text-[11px] text-[#39FF14] truncate opacity-80">{{ $etiquetaRol }}</p>
                </div>
            </div>
            
            <form method="POST" action="{{ route('logout') }}" class="shrink-0 ml-2">
                @csrf
                <button type="submit" class="p-2.5 text-gray-300 hover:text-[#39FF14] hover:bg-white/5 rounded-lg transition-colors cursor-pointer" title="Cerrar sesión" aria-label="Cerrar sesión">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#042B14">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SIGABU') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900 bg-gray-50 flex alto-app overflow-hidden"
          x-data="{
              menuAbierto: false,
              abrirMenu() {
                  this.menuAbierto = true;
                  this.$nextTick(() => this.$refs.cerrarMenu.focus());
              },
              cerrarMenu() {
                  if (! this.menuAbierto) return;
                  this.menuAbierto = false;
                  this.$nextTick(() => this.$refs.botonMenu.focus());
              },
          }"
          @keydown.escape.window="cerrarMenu()"
          @resize.window="if (window.innerWidth >= 1024) menuAbierto = false"
          @pageshow.window="menuAbierto = false">

        <!-- Fondo oscuro detrás del menú en celular y tableta -->
        <div x-show="menuAbierto" x-cloak x-transition.opacity @click="cerrarMenu()"
             class="fixed inset-0 z-30 bg-black/50 lg:hidden" aria-hidden="true"></div>

        <!-- Menú Lateral (Sidebar) -->
        @include('layouts.navigation')

        <!-- Contenedor Principal (Panel Derecho) -->
        <div class="flex-1 min-w-0 flex flex-col alto-app" :inert="menuAbierto">
            <!-- Barra superior: solo en celular y tableta, donde el menú va oculto -->
            <header class="lg:hidden h-14 shrink-0 bg-[#042B14] text-white flex items-center gap-2 px-2">
                <button type="button" x-ref="botonMenu" @click="abrirMenu()"
                        class="w-11 h-11 flex items-center justify-center rounded-lg hover:bg-white/10 transition-colors"
                        aria-label="Abrir menú" aria-controls="menu-principal" :aria-expanded="menuAbierto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="bg-white p-0.5 rounded-md shrink-0 w-7 h-7 flex items-center justify-center">
                    <img src="{{ asset('images/logo-uceva-icon.png') }}" alt="" class="max-w-full max-h-full object-contain">
                </div>
                <span class="font-bold text-lg leading-none tracking-wide">SIGABU</span>
                <a href="{{ route('notificaciones.index') }}"
                   class="relative ml-auto w-11 h-11 flex items-center justify-center rounded-lg hover:bg-white/10 transition-colors"
                   aria-label="Notificaciones{{ $avisosSinLeer > 0 ? ": {$avisosSinLeer} sin leer" : '' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    @if ($avisosSinLeer > 0)
                        <span class="absolute top-1.5 right-1.5 min-w-[18px] px-1 rounded-full bg-amber-400 text-[#042B14] text-[10px] font-black text-center leading-[18px]" aria-hidden="true">{{ $avisosSinLeer > 99 ? '99+' : $avisosSinLeer }}</span>
                    @endif
                </a>
            </header>

            <!-- Contenido de la Página -->
            <div class="flex-1 min-h-0 overflow-y-auto">
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

    </body>
</html>

<x-guest-layout>
    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- ============ PANEL IZQUIERDO (institucional) ============ -->
        <div class="lg:w-[58%] relative overflow-hidden px-6 py-6 lg:px-14 lg:py-12 flex flex-col justify-center"
             style="background: linear-gradient(135deg, #0A8A3C 0%, #046B2C 55%, #024D1F 100%);">

            <!-- Círculo decorativo -->
            <div class="hidden lg:block absolute -top-24 -right-24 w-[420px] h-[420px] rounded-full"
                 style="background: rgba(255,255,255,0.05);"></div>

            <div class="relative z-10 max-w-xl">

                <!-- Logo UCEVA -->
               <div class="inline-block bg-white rounded-lg shadow-sm" style="padding:10px 18px;">
    <img src="{{ asset('images/logo-uceva.png') }}" alt="UCEVA" style="height:56px; width:auto; display:block;">
</div>
                <p class="mt-3 text-xs tracking-[0.18em] font-semibold text-white/70 uppercase">
                    Vicerrectoría de Bienestar Universitario
                </p>

                <!-- Badge -->
                <div class="hidden lg:inline-flex mt-8 items-center gap-2 rounded-full px-4 py-1.5"
                     style="background: rgba(138,255,0,0.14); border:1px solid rgba(138,255,0,0.35);">
                    <span class="h-2 w-2 rounded-full" style="background:#8AFF00;"></span>
                    <span class="text-sm font-semibold" style="color:#8AFF00;">Sistema Institucional</span>
                </div>

                <!-- Título -->
                    <h1 class="mt-4 lg:mt-6 text-3xl lg:text-[50px]" style="font-weight:800; color:#ffffff; line-height:1.1;">
                    SIGABU
                   </h1>
                    <h2 class="text-2xl lg:text-[42px]" style="font-weight:800; color:#8AFF00; line-height:1.15;">
                     Bienestar Universitario
                    </h2>

                <p class="hidden lg:block" style="margin-top:20px; font-size:17px; line-height:1.65; color:rgba(255,255,255,0.88); max-width:460px;">
                    Tu portal de bienestar en la UCEVA. Solicita acompañamiento psicológico,
                    apoyos de inclusión y accede a recursos de salud mental.
                </p>

                <!-- Tarjetas de servicios -->
                <div class="hidden lg:block mt-8 space-y-3">
                    @php
                        $servicios = [
                            ['icono' => '📅', 'titulo' => 'Solicita tu cita',       'desc' => 'Psicología y acompañamiento emocional'],
                            ['icono' => '♿', 'titulo' => 'Apoyos de inclusión',    'desc' => 'Caracterización y ajustes razonables'],
                            ['icono' => '📚', 'titulo' => 'Recursos de bienestar', 'desc' => 'Material psicoeducativo gratuito'],
                            ['icono' => '🔒', 'titulo' => 'Confidencial',           'desc' => 'Tu información está protegida'],
                        ];
                    @endphp

                    @foreach ($servicios as $servicio)
                        <div class="flex items-center gap-4 rounded-xl px-4 py-3"
                             style="background: rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.08);">
                            <span class="text-2xl leading-none">{{ $servicio['icono'] }}</span>
                            <div>
                                <p class="text-white font-semibold text-sm">{{ $servicio['titulo'] }}</p>
                                <p class="text-white/65 text-xs mt-0.5">{{ $servicio['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- ============ PANEL DERECHO (formulario) ============ -->
        <div class="flex-1 lg:flex-none lg:w-[42%] bg-white flex items-start lg:items-center justify-center px-6 py-8 lg:py-12 lg:px-12">
            <div class="w-full max-w-md">

                <!-- Logo SiBE -->
                <div class="flex items-center gap-4 pb-5 border-b border-gray-200">
                    <img src="{{ asset('images/logo-sibe-mark.png') }}" alt="SiBE" style="height:48px; width:auto; display:block;">
                    <p class="text-sm font-bold leading-tight" style="color:#1F2A5B;">
                        SISTEMA DE<br>BIENESTAR<br>UNIVERSITARIO
                    </p>
                </div>

                <!-- Encabezado -->
                <div class="mt-8">
                    <h2 class="text-3xl font-bold text-gray-800">Bienvenido/a</h2>
                    <p class="text-gray-500 mt-1">Ingresa con tu correo institucional</p>
                </div>

                <x-auth-session-status class="mt-4" :status="session('status')" />

                <!-- Formulario -->
                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Correo institucional</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                               autocomplete="username" placeholder="nombre@uceva.edu.co"
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-offset-0"
                               style="border-color:#D1D5DB;" onfocus="this.style.borderColor='#007F2F'" onblur="this.style.borderColor='#D1D5DB'">
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Contraseña</label>
                        <input id="password" type="password" name="password" required
                               autocomplete="current-password" placeholder="Tu contraseña"
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-offset-0"
                               style="border-color:#D1D5DB;" onfocus="this.style.borderColor='#007F2F'" onblur="this.style.borderColor='#D1D5DB'">
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label for="remember_me" class="inline-flex items-center">
                            <input id="remember_me" type="checkbox" name="remember"
                                   class="rounded border-gray-300 shadow-sm" style="color:#007F2F;">
                            <span class="ms-2 text-sm text-gray-600">Recordarme</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm hover:underline" style="color:#007F2F;">
                                ¿Olvidaste tu contraseña?
                            </a>
                        @endif
                    </div>

                    <button type="submit"
                            class="w-full text-white font-semibold py-3 rounded-lg transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2"
                            style="background-color:#007F2F;">
                        Iniciar sesión
                    </button>
                </form>

                <!-- Nota de confidencialidad -->
                <div class="mt-6 rounded-xl px-4 py-3.5 text-sm leading-relaxed"
                     style="background:#EAF7EF; border:1px solid #BFE6CE; color:#1F5134;">
                    <span class="font-semibold">Confidencialidad garantizada.</span>
                    La información que compartas será tratada con reserva profesional conforme
                    a las políticas institucionales de la UCEVA.
                </div>

                <p class="mt-5 text-center text-sm text-gray-500">
                    ¿Problemas de acceso?
                    <a href="mailto:bienestar@uceva.edu.co" class="hover:underline" style="color:#007F2F;">bienestar@uceva.edu.co</a>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
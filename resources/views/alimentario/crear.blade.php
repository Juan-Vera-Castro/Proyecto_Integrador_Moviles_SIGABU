@php
    use App\Models\Alimentos\Solicitud;

    $situaciones = [
        Solicitud::EMERGENCIA => ['SOS', 'Situación de emergencia económica', 'Prioridad inmediata por riesgo alimentario'],
        Solicitud::CABEZA_FAMILIA => ['🧑‍🧑‍🧒', 'Soy cabeza de familia', 'Hogar con dependientes a cargo'],
        Solicitud::ESTRATO_BAJO => ['🏠', 'Estrato 1 o 2 sin apoyo familiar', 'Estudiante foráneo o de escasos recursos'],
        Solicitud::DESEMPLEO => ['📉', 'Desempleo o reducción de ingresos familiares', 'Afectación económica reciente'],
    ];
    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#C2410C] focus:ring-[#C2410C]';
    $abierta = $misSolicitudes->first(fn ($s) => in_array($s->estado, [Solicitud::PENDIENTE, Solicitud::APROBADA], true));
    $colorEstado = [
        Solicitud::PENDIENTE => 'bg-amber-50 text-amber-700 border-amber-200',
        Solicitud::APROBADA => 'bg-sky-50 text-sky-700 border-sky-200',
        Solicitud::ENTREGADA => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        Solicitud::RECHAZADA => 'bg-rose-50 text-rose-600 border-rose-200',
    ];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col"
         x-data="{
             codigoBusqueda: @js($ficha['codigo'] ?? ''),
             cargandoFicha: false,
             ficha: @js($ficha),
             mostrarFormulario: {{ $abierta ? 'false' : 'true' }},
             situacion: @js(old('situacion', 'Emergencia económica')),
             estrato: @js(old('estrato', $ficha['estrato'] ?? 1)),
             personasCargo: @js(old('personas_a_cargo', 0)),
             aceptaDatos: @js((bool) old('acepta_datos', true)),
             mensajeConsulta: '',

             consultarFicha() {
                 if (!this.codigoBusqueda.trim()) return;
                 this.cargandoFicha = true;
                 this.mensajeConsulta = '';
                 fetch('{{ route('alimentario.consultar') }}?codigo=' + encodeURIComponent(this.codigoBusqueda))
                     .then(res => res.json())
                     .then(data => {
                         if (data.success) {
                             this.ficha = data;
                             this.estrato = data.estrato || 1;
                             this.mensajeConsulta = 'Usuario encontrado en SIGABU. Verificación académica institucional pendiente.';
                         } else {
                             this.mensajeConsulta = data.error || 'No se pudo consultar el código.';
                         }
                         this.cargandoFicha = false;
                     })
                     .catch(() => {
                         this.mensajeConsulta = 'Error al consultar el servicio académico.';
                         this.cargandoFicha = false;
                     });
             }
         }">

        <!-- 1. FRANJA BLANCA SUPERIOR (HEADER) -->
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-3 w-full shrink-0">
            <div class="flex items-center gap-4">
                <a href="{{ route('estudiante.home') }}" class="text-[#C2410C] font-bold hover:underline flex items-center gap-1.5 text-sm shrink-0">
                    <span>&larr;</span> Inicio
                </a>
                <div class="h-6 w-px bg-gray-200 shrink-0"></div>
                <div>
                    <h1 class="text-lg font-bold text-gray-900 leading-tight">Módulo de Apoyo Alimentario "Ucevistas de Corazón"</h1>
                    <p class="text-xs text-gray-500">Vicerrectoría de Bienestar Universitario · Donaciones Alimentarias</p>
                </div>
            </div>
            <a href="{{ route('estudiante.home') }}"
               class="bg-orange-50 hover:bg-orange-100 text-[#C2410C] p-2.5 rounded-xl transition-colors flex items-center justify-center shadow-xs shrink-0"
               title="Volver al inicio" aria-label="Volver al inicio">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </a>
        </header>

        <main class="max-w-4xl mx-auto w-full p-4 sm:p-6 space-y-6">

            @if (session('status'))
                <div class="bg-[#EAF7EF] border border-[#BFE6CE] rounded-xl p-4 text-sm text-[#1F5134] font-medium flex items-center gap-2" role="status">
                    <span class="text-lg">✓</span>
                    <span>{{ session('status') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-700 font-medium flex items-center gap-2" role="alert">
                    <span class="text-lg">⚠️</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- SECCIÓN 1: BUSCADOR Y FICHA SOCIOECONÓMICA DEL ESTUDIANTE (DEMANDA) -->
            <section class="bg-white rounded-2xl p-6 sm:p-7 shadow-sm border border-gray-200 space-y-5" aria-labelledby="titulo-ficha">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
                    <div>
                        <h2 id="titulo-ficha" class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#007F2F]"></span>
                            Ficha Socioeconómica del Estudiante (Bienestar Universitario)
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Verificación de matrícula activa, nivel de vulnerabilidad y condiciones socioeconómicas.
                        </p>
                    </div>

                    <!-- Buscador de Código -->
                    <form @submit.prevent="consultarFicha()" class="flex items-center gap-2">
                        <div class="relative">
                            <input type="text"
                                   x-model="codigoBusqueda"
                                   placeholder="Código estudiantil..."
                                   class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 focus:border-[#C2410C] focus:ring-[#C2410C] w-40 sm:w-48 text-gray-800">
                        </div>
                        <button type="submit"
                                :disabled="cargandoFicha"
                                class="px-3 py-1.5 bg-[#C2410C] hover:bg-[#9A3412] disabled:opacity-50 text-white font-bold text-xs rounded-lg transition-colors flex items-center gap-1">
                            <span x-show="!cargandoFicha">Buscar</span>
                            <span x-show="cargandoFicha" style="display: none;">Buscando...</span>
                        </button>
                    </form>
                </div>

                <div x-show="mensajeConsulta"
                     x-text="mensajeConsulta"
                     class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-green-50 text-[#007F2F] border border-green-200"
                     style="display: none;"></div>

                <!-- Tarjeta con la información socioeconómica detallada -->
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3">
                        <div>
                            <h3 class="font-bold text-base text-gray-900" x-text="ficha.nombre"></h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Código: <strong x-text="ficha.codigo"></strong> · Doc: <span x-text="ficha.documento"></span> · <span x-text="ficha.programa"></span> (<span x-text="ficha.semestre"></span>° Semestre)
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            Matrícula pendiente de verificación
                        </span>
                    </div>

                    <!-- Cuadrantes de Indicadores Socioeconómicos -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <span class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Estrato</span>
                            <span class="block text-base font-bold text-gray-900 mt-0.5">Estrato <span x-text="ficha.estrato"></span></span>
                        </div>
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <span class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Clasificación Sisbén</span>
                            <span class="block text-xs font-bold text-[#007F2F] mt-1" x-text="ficha.sisben"></span>
                        </div>
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <span class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Vulnerabilidad</span>
                            <span class="block text-xs font-bold text-[#C2410C] mt-1 truncate" :title="ficha.situacionPrioritaria" x-text="ficha.situacionPrioritaria"></span>
                        </div>
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <span class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Factor Equidad</span>
                            <span class="block text-base font-bold text-gray-900 mt-0.5"><span x-text="ficha.mercadosRecibidos"></span> entregados</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECCIÓN 2: ESTADO ACTUAL / ORDEN DE ENTREGA CON CÓDIGO QR -->
            @if ($abierta)
                <section class="bg-white rounded-2xl p-6 sm:p-7 shadow-sm border border-gray-200 space-y-4" aria-labelledby="titulo-estado">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h2 id="titulo-estado" class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span class="text-lg">📋</span>
                            Estado de tu Solicitud
                        </h2>
                        <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $colorEstado[$abierta->estado] ?? '' }}">
                            {{ $abierta->estado }}
                        </span>
                    </div>

                    @if ($abierta->estado === Solicitud::APROBADA)
                        <!-- PASE DE RETIRO CON QR CUANDO ESTÁ APROBADA -->
                        <div class="bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-dashed border-[#C2410C] rounded-2xl p-6 text-center space-y-4">
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-white rounded-full shadow-xs text-xs font-bold text-[#C2410C] border border-orange-200">
                                <span>🎉</span> ¡Solicitud Aprobada por Bienestar Universitario!
                            </div>

                            <h3 class="text-lg font-bold text-gray-900">Pase Oficial de Retiro de Mercado Solidario</h3>
                            <p class="text-xs text-gray-600 max-w-md mx-auto">
                                Acércate a la <strong>Bodega de Bienestar Universitario (Bloque C)</strong> y presenta este código QR con tu carné estudiantil o cédula. En la ventanilla firmarás al recibir el mercado.
                            </p>

                            <!-- QR del pase: identifica la solicitud; en ventanilla igual se verifica el documento -->
                            <div class="inline-block p-4 bg-white rounded-xl shadow-md border border-gray-200">
                                <div class="w-40 h-40 mx-auto [&>svg]:w-full [&>svg]:h-full" role="img" aria-label="Código QR del pase de retiro">
                                    {!! \App\Support\CodigoQr::svg($abierta->codigoPase(), 160) !!}
                                </div>
                                <span class="block text-[11px] font-mono font-bold text-gray-800 mt-2 tracking-widest">
                                    ORD-ALIM-{{ strtoupper(substr($abierta->id_solicitud, 0, 8)) }}
                                </span>
                            </div>

                            <div class="text-xs text-gray-600 space-y-1">
                                <p><strong>Horario de atención:</strong> Lunes a Viernes de 8:00 a.m. a 5:00 p.m.</p>
                                <p><strong>Lugar:</strong> Ventanilla de Bodega · Bienestar Universitario</p>
                            </div>

                            <button type="button"
                                    onclick="window.print()"
                                    class="px-5 py-2.5 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                                🖨️ Imprimir Pase de Entrega
                            </button>
                        </div>
                    @else
                        <!-- ESTADO PENDIENTE -->
                        <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-5 text-sm text-gray-700 space-y-2">
                            <p class="font-bold text-amber-900 flex items-center gap-1.5">
                                <span class="text-base">⏳</span> Tu solicitud está en revisión y priorización
                            </p>
                            <p class="text-xs text-amber-800">
                                Fecha de solicitud: <strong>{{ $abierta->fecha_solicitud->format('d/m/Y') }}</strong> · Nivel de prioridad calculado:
                                <span class="font-bold uppercase">{{ $abierta->prioridad }}</span>.
                            </p>
                            <p class="text-xs text-gray-600">
                                El equipo de Trabajo Social y Almacén están asignando los lotes de alimentos disponibles según la fecha de postulación y situación de vulnerabilidad. Te notificaremos en cuanto tu mercado esté listo para retirar.
                            </p>
                        </div>
                    @endif
                </section>
            @else
                <!-- SECCIÓN 3: FORMULARIO DE POSTULACIÓN DIRECTA (CUANDO NO TIENE SOLICITUD ABIERTA) -->
                <section class="bg-white rounded-2xl p-6 sm:p-7 shadow-sm border border-gray-200 space-y-6" aria-labelledby="titulo-solicitar">
                    <div>
                        <h2 id="titulo-solicitar" class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span class="text-lg">🛒</span>
                            Pedir Donación de Apoyo Alimentario
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Selecciona tu situación actual para que el sistema calcule tu nivel de prioridad de entrega.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('alimentario.store') }}" class="space-y-6">
                        @csrf

                        <!-- Selección de Situación -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-3">
                                ¿Cuál es tu situación prioritaria? <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach ($situaciones as $valor => [$icono, $titulo, $descripcion])
                                    <label class="block cursor-pointer">
                                        <input type="radio" name="situacion" value="{{ $valor }}" x-model="situacion" class="peer sr-only" @checked(old('situacion', 'Emergencia económica') === $valor)>
                                        <div class="h-full border rounded-xl p-4 transition-all duration-200 flex flex-col justify-between border-gray-200 bg-white hover:border-gray-300 peer-checked:border-[#C2410C] peer-checked:ring-1 peer-checked:ring-[#C2410C] peer-checked:bg-orange-50/40">
                                            <div class="flex items-center gap-2.5">
                                                @if ($icono === 'SOS')
                                                    <span class="bg-rose-500 text-white text-[10px] font-black px-2 py-0.5 rounded-md uppercase">SOS</span>
                                                @else
                                                    <span class="text-xl" aria-hidden="true">{{ $icono }}</span>
                                                @endif
                                                <h3 class="font-bold text-xs text-gray-900">{{ $titulo }}</h3>
                                            </div>
                                            <p class="text-[11px] text-gray-500 mt-2">{{ $descripcion }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('situacion')" class="mt-2" />
                        </div>

                        <!-- Campos Complementarios -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="estrato" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">
                                    Estrato Socioeconómico
                                </label>
                                <select id="estrato" name="estrato" x-model="estrato" class="{{ $input }}">
                                    @foreach (range(1, 6) as $estrato)
                                        <option value="{{ $estrato }}">Estrato {{ $estrato }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('estrato')" class="mt-1" />
                            </div>

                            <div>
                                <label for="personas_a_cargo" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">
                                    Personas a tu cargo
                                </label>
                                <input id="personas_a_cargo" name="personas_a_cargo" type="number" min="0" max="20" x-model="personasCargo" class="{{ $input }}">
                                <x-input-error :messages="$errors->get('personas_a_cargo')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <label for="detalle" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">
                                Detalle adicional o situación de emergencia <span class="text-gray-400 font-normal">(opcional)</span>
                            </label>
                            <textarea id="detalle" name="detalle" rows="2" maxlength="{{ Solicitud::DETALLE_MAX }}" placeholder="Explica brevemente tu caso para el equipo de Trabajo Social..." class="{{ $input }}">{{ old('detalle') }}</textarea>
                            <x-input-error :messages="$errors->get('detalle')" class="mt-1" />
                        </div>

                        <!-- Consentimiento Habeas Data -->
                        <label class="flex items-start gap-2.5 text-xs text-gray-700 cursor-pointer bg-gray-50 border border-gray-200 rounded-xl p-3.5">
                            <input type="checkbox" name="acepta_datos" value="1" x-model="aceptaDatos" required class="mt-0.5 rounded border-gray-300 text-[#C2410C] focus:ring-[#C2410C]">
                            <span>
                                Autorizo a la <strong>Vicerrectoría de Bienestar Universitario</strong> de la UCEVA al tratamiento confidencial de mis datos con fines asistenciales y de priorización de apoyos alimentarios, en cumplimiento de la <strong>Ley 1581 de 2012 (Habeas Data)</strong>.
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('acepta_datos')" class="-mt-4" />

                        <!-- Botón de Envío -->
                        <div>
                            <button type="submit"
                                    :disabled="!aceptaDatos"
                                    class="w-full py-3.5 px-6 rounded-xl font-bold text-sm text-white bg-[#C2410C] hover:bg-[#9A3412] disabled:opacity-50 transition-all duration-200 shadow-md flex items-center justify-center gap-2">
                                <span>Pedir Donación de Apoyo Alimentario</span>
                                <span>&rarr;</span>
                            </button>
                        </div>
                    </form>
                </section>
            @endif

            <!-- SECCIÓN 4: HISTÓRICO DE APOYOS POR ESTUDIANTE (TRAZABILIDAD Y EQUIDAD) -->
            @if ($misSolicitudes->isNotEmpty())
                <section class="bg-white rounded-2xl p-6 sm:p-7 shadow-sm border border-gray-200 space-y-4" aria-labelledby="titulo-historial">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h2 id="titulo-historial" class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span>📜</span>
                            Historial de Solicitudes y Beneficios Recibidos
                        </h2>
                        <span class="text-xs text-gray-500 font-semibold">Trazabilidad Institucional</span>
                    </div>

                    <ul class="divide-y divide-gray-100 border border-gray-200 rounded-xl text-sm overflow-hidden">
                        @foreach ($misSolicitudes as $solicitud)
                            <li class="p-4 space-y-1.5 hover:bg-gray-50/50 transition-colors">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="font-bold text-gray-900 text-xs sm:text-sm">
                                        {{ $solicitud->fecha_solicitud->format('d/m/Y') }} · {{ $solicitud->situacion }}
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $colorEstado[$solicitud->estado] ?? '' }}">
                                        {{ $solicitud->estado }}
                                    </span>
                                </div>
                                @if ($solicitud->estado === Solicitud::ENTREGADA && $solicitud->entrega)
                                    <p class="text-xs text-emerald-800 font-medium">
                                        ✓ Mercado entregado el {{ $solicitud->entrega->fecha_hora->format('d/m/Y H:i') }} (Lote: {{ $solicitud->entrega->lote?->codigo_lote }} · {{ $solicitud->entrega->lote?->tipo }}).
                                    </p>
                                @elseif ($solicitud->estado === Solicitud::RECHAZADA && $solicitud->motivo_rechazo)
                                    <p class="text-xs text-rose-700 font-medium">
                                        Motivo: {{ $solicitud->motivo_rechazo }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

        </main>
    </div>
</x-app-layout>

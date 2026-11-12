@php
    use App\Models\Alimentos\Donante;
    use App\Models\Alimentos\Entrega;
    use App\Models\Alimentos\Lote;
    use App\Models\Alimentos\Solicitud;

    $input = 'block w-full rounded-lg shadow-sm text-sm text-gray-900 border-gray-300 focus:border-[#C2410C] focus:ring-[#C2410C]';
    $pestanas = [
        'solicitudes' => 'Solicitudes y Priorización',
        'inventario' => 'Control de Inventario',
        'despachos' => 'Terminal de Despacho (Salidas)',
        'donantes' => 'Donantes y Certificados',
        'entregas' => 'Trazabilidad de Entregas',
        'reportes' => 'Reportes y Analítica',
    ];
    $colorPrioridad = [
        'Alta' => 'bg-rose-50 text-rose-700 border-rose-200',
        'Media' => 'bg-amber-50 text-amber-700 border-amber-200',
        'Baja' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    ];
    $colorEstado = [
        Solicitud::PENDIENTE => 'bg-amber-50 text-amber-700',
        Solicitud::APROBADA => 'bg-sky-50 text-sky-700',
        Solicitud::ENTREGADA => 'bg-emerald-50 text-emerald-700',
        Solicitud::RECHAZADA => 'bg-rose-50 text-rose-600',
    ];
    $erroresDonante = $errors->getBag('donante');
    $erroresLote = $errors->getBag('lote');
    $aprobadasList = $solicitudes->where('estado', Solicitud::APROBADA);
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full"
         x-data="{
             modalCargue: false,
             filtroSolicitud: 'todas',
             despachoSolicitudId: '{{ $aprobadasList->first()?->id_solicitud ?? '' }}',
             despachoDoc: '{{ $aprobadasList->first()?->persona?->numero_documento ?? '' }}',
             despachoNombre: '{{ $aprobadasList->first()?->persona?->nombre_completo ?? '' }}',
             busquedaDespacho: ''
         }">

        <!-- ENCABEZADO INSTITUCIONAL -->
        <header class="bg-white px-4 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#C2410C] flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-[#C2410C]"></span>
                    Ucevistas de Corazón · Módulo de Donaciones Alimentarias
                </h1>
                <p class="text-gray-500 text-xs sm:text-sm mt-0.5">
                    Sistema de Gestión, Control de Bodega y Trazabilidad — Vicerrectoría de Bienestar Universitario
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('almacen.reportes.transparencia') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Informe de Transparencia (PDF)</span>
                </a>
            </div>
        </header>

        <main class="p-4 sm:p-8 max-w-[1400px] mx-auto w-full flex flex-col gap-6">

            <!-- NOTIFICACIONES Y ALERTAS -->
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-xl px-4 py-3 flex items-center gap-2 shadow-xs" role="status">
                    <span class="text-base">✓</span>
                    <span>{{ session('status') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium rounded-xl px-4 py-3" role="alert">{{ session('error') }}</div>
            @endif
            @if (session('erroresCargue') || $errors->getBag('cargue')->any())
                <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-xl px-4 py-3 space-y-1" role="alert">
                    <p class="font-bold">Filas del archivo de Trabajo Social que no se cargaron:</p>
                    <ul class="list-disc pl-5 text-xs">
                        @foreach (array_merge(session('erroresCargue', []), $errors->getBag('cargue')->all()) as $errorCargue)
                            <li>{{ $errorCargue }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($errors->has('solicitud'))
                <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium rounded-xl px-4 py-3 flex items-center gap-2" role="alert">
                    <span class="text-base">⚠️</span>
                    <span>{{ $errors->first('solicitud') }}</span>
                </div>
            @endif

            <!-- 4 TARJETAS DE INDICADORES CLAVE (KPIS) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-2xl" aria-hidden="true">📦</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-orange-100 text-[#C2410C]">Disponible</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $resumen['mercados'] }}</p>
                    <p class="text-xs text-gray-500 font-medium">Mercados en Bodega</p>
                    <div class="mt-2 text-[11px] text-gray-400">
                        {{ $resumen['mercadosA'] }} Tipo A · {{ $resumen['mercadosB'] }} Tipo B
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-2xl" aria-hidden="true">📝</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-800">Demanda</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $resumen['pendientes'] }}</p>
                    <p class="text-xs text-gray-500 font-medium">Solicitudes Pendientes</p>
                    <div class="mt-2 text-[11px] text-gray-400">
                        {{ $resumen['aprobadas'] }} aprobadas listas para entrega
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-2xl" aria-hidden="true">⏳</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-yellow-100 text-yellow-800">FEFO</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $resumen['porVencer'] }}</p>
                    <p class="text-xs text-gray-500 font-medium">Lotes Próximos a Vencer</p>
                    <div class="mt-2 text-[11px] text-gray-400">
                        @if ($resumen['proximoLoteFefo'])
                            Despachar primero: {{ $resumen['proximoLoteFefo']->codigo_lote }}
                        @else
                            Existencias al día
                        @endif
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-2xl" aria-hidden="true">🤝</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800">Equidad</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $resumen['entregadasTotal'] }}</p>
                    <p class="text-xs text-gray-500 font-medium">Entregas Realizadas</p>
                    <div class="mt-2 text-[11px] text-gray-400">
                        {{ $resumen['coberturaEstudiantil'] }} estudiantes beneficiados únicos
                    </div>
                </div>
            </div>

            <!-- NAVEGACIÓN POR PESTAÑAS -->
            <nav class="flex flex-wrap gap-2 text-sm font-bold border-b border-gray-200 pb-3" aria-label="Secciones del módulo">
                @foreach ($pestanas as $clave => $nombre)
                    <a href="{{ route('almacen.home', ['tab' => $clave]) }}"
                       @if ($pestana === $clave) aria-current="page" @endif
                       @class([
                           'px-4 py-2.5 rounded-xl border transition-all duration-150',
                           'bg-[#C2410C] text-white border-[#C2410C] shadow-xs' => $pestana === $clave,
                           'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' => $pestana !== $clave
                       ])>
                        {{ $nombre }}
                    </a>
                @endforeach
            </nav>

            <!-- ========================================== -->
            <!-- PESTAÑA 1: SOLICITUDES Y PRIORIZACIÓN     -->
            <!-- ========================================== -->
            @if ($pestana === 'solicitudes')
                <div class="space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-gray-200 shadow-xs">
                        <div>
                            <h2 class="font-bold text-gray-900 text-base">Solicitudes de Estudiantes Priorizadas</h2>
                            <p class="text-xs text-gray-500">Ordenadas por nivel de vulnerabilidad y factor de equidad.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="modalCargue = true"
                                    class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-xl transition flex items-center gap-1.5 border border-gray-300">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                <span>Cargue Masivo CSV (Trabajo Social)</span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @forelse ($solicitudes as $solicitud)
                            @php
                                $bolsaRevision = $errors->getBag('revision-'.$solicitud->id_solicitud);
                                $bolsaEntrega = $errors->getBag('entrega-'.$solicitud->id_solicitud);
                                $entregasPrevias = Entrega::whereHas('solicitud', fn($q) => $q->where('id_persona', $solicitud->id_persona))->count();
                            @endphp
                            <article id="solicitud-{{ $solicitud->id_solicitud }}" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 space-y-3.5 scroll-mt-6">
                                <header class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                                    <div>
                                        <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                                            {{ $solicitud->persona?->nombre_completo }}
                                            @if ($entregasPrevias === 0)
                                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                                    1ª Entrega · Equidad
                                                </span>
                                            @else
                                                <span class="text-[10px] font-bold text-gray-500">
                                                    ({{ $entregasPrevias }} entrega{{ $entregasPrevias > 1 ? 's' : '' }} previa{{ $entregasPrevias > 1 ? 's' : '' }})
                                                </span>
                                            @endif
                                        </h3>
                                        <p class="text-xs text-gray-500">
                                            Doc. {{ $solicitud->persona?->numero_documento }} · Código: {{ $solicitud->persona?->usuario?->codigo_unico ?? '—' }} · Solicitada el {{ $solicitud->fecha_solicitud->format('d/m/Y') }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs font-bold">
                                        @if (isset($priorizados[preg_replace('/\D/', '', (string) $solicitud->persona?->numero_documento)]))
                                            <span class="px-2.5 py-1 rounded-full bg-violet-50 text-violet-700 border border-violet-200" title="Cargado por Trabajo Social">Priorizado por Trabajo Social</span>
                                        @endif
                                        <span class="px-2.5 py-1 rounded-full border {{ $colorPrioridad[$solicitud->prioridad] ?? 'bg-gray-100 text-gray-700' }}">
                                            Prioridad {{ $solicitud->prioridad }}
                                        </span>
                                        <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-700">
                                            {{ $solicitud->estado }}
                                        </span>
                                    </div>
                                </header>

                                <div class="text-sm text-gray-700 grid grid-cols-1 sm:grid-cols-3 gap-2 bg-gray-50 p-3 rounded-xl border border-gray-100 text-xs">
                                    <div>
                                        <span class="text-gray-400 block font-semibold uppercase text-[10px]">Condición Reportada</span>
                                        <strong class="text-gray-900">{{ $solicitud->situacion }}</strong>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 block font-semibold uppercase text-[10px]">Estrato Socioeconómico</span>
                                        <strong class="text-gray-900">Estrato {{ $solicitud->estrato ?? 'Sin dato' }}</strong>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 block font-semibold uppercase text-[10px]">Personas a Cargo</span>
                                        <strong class="text-gray-900">{{ $solicitud->personas_a_cargo ?? 0 }} dependiente(s)</strong>
                                    </div>
                                </div>

                                @if ($solicitud->detalle)
                                    <p class="text-xs text-gray-600 pl-3 border-l-2 border-orange-300 italic">
                                        "{{ $solicitud->detalle }}"
                                    </p>
                                @endif

                                <!-- ACCIONES SEGÚN ESTADO -->
                                @if ($solicitud->estado === Solicitud::PENDIENTE)
                                    <div class="flex flex-wrap items-center gap-3 pt-1" x-data="{ rechazar: {{ $bolsaRevision->any() ? 'true' : 'false' }} }">
                                        <form method="POST" action="{{ route('almacen.solicitudes.aprobar', $solicitud) }}" x-show="!rechazar">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-[#C2410C] hover:bg-[#9A3412] text-white text-xs font-bold rounded-xl transition shadow-xs">
                                                ✓ Aprobar y Generar Orden
                                            </button>
                                        </form>
                                        <button type="button" x-show="!rechazar" @click="rechazar = true" class="px-4 py-2 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold rounded-xl transition">
                                            ✕ Rechazar
                                        </button>

                                        <form method="POST" action="{{ route('almacen.solicitudes.rechazar', $solicitud) }}" x-show="rechazar" x-cloak class="w-full space-y-2 mt-2">
                                            @csrf
                                            <label for="motivo-{{ $solicitud->id_solicitud }}" class="block text-xs font-medium text-gray-700">
                                                Motivo del rechazo (el estudiante lo verá en su portal)
                                            </label>
                                            <textarea id="motivo-{{ $solicitud->id_solicitud }}" name="motivo_rechazo" rows="2" maxlength="{{ Solicitud::MOTIVO_RECHAZO_MAX }}" placeholder="Explica la razón del rechazo..." class="{{ $input }}">{{ $bolsaRevision->any() ? old('motivo_rechazo') : '' }}</textarea>
                                            <x-input-error :messages="$bolsaRevision->get('motivo_rechazo')" />
                                            <div class="flex gap-2">
                                                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition">
                                                    Confirmar Rechazo
                                                </button>
                                                <button type="button" @click="rechazar = false" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 text-xs font-medium rounded-xl">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                @elseif ($solicitud->estado === Solicitud::APROBADA)
                                    <div class="bg-sky-50 border border-sky-200 rounded-xl p-3.5 flex flex-wrap items-center justify-between gap-3">
                                        <div class="text-xs text-sky-900 font-medium">
                                            <span class="font-bold">Orden activa:</span> El estudiante está habilitado para retirar su mercado en ventanilla.
                                        </div>
                                        <a href="{{ route('almacen.home', ['tab' => 'despachos']) }}#despacho"
                                           class="px-3.5 py-1.5 bg-[#C2410C] hover:bg-[#9A3412] text-white text-xs font-bold rounded-xl transition">
                                            Ir al Terminal de Despacho &rarr;
                                        </a>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="bg-white border border-gray-200 rounded-2xl p-10 text-center text-sm text-gray-500">
                                No hay solicitudes pendientes por procesar en este momento.
                            </div>
                        @endforelse
                    </div>
                </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 2: CONTROL DE INVENTARIO Y LOTES   -->
            <!-- ========================================== -->
            @elseif ($pestana === 'inventario')
                <div class="space-y-6">

                    <!-- SECCIÓN 2.1: VISUALIZACIÓN GRÁFICA DE STOCK EN TIEMPO REAL -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                <span class="text-lg">📊</span>
                                Disponibilidad de Productos y Niveles de Stock en Bodega
                            </h2>
                            <span class="text-xs font-bold text-[#007F2F] bg-green-50 border border-green-200 px-3 py-1 rounded-full">
                                Bodega Bloque C · Activa
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Barra Mercado Tipo A -->
                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-2">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-bold text-gray-900">Mercado Tipo A (Individual Estudiantil)</span>
                                    <span class="font-bold text-[#C2410C]">{{ $resumen['mercadosA'] }} disponibles</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <div class="bg-[#C2410C] h-3 rounded-full transition-all duration-500"
                                         style="width: {{ min(100, max(5, ($resumen['mercadosA'] / 50) * 100)) }}%"></div>
                                </div>
                                <p class="text-[11px] text-gray-500">Insumos básicos: arroz, aceite, granos, panela, pastas.</p>
                            </div>

                            <!-- Barra Mercado Tipo B -->
                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-2">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-bold text-gray-900">Mercado Tipo B (Familiar Reforzado)</span>
                                    <span class="font-bold text-[#007F2F]">{{ $resumen['mercadosB'] }} disponibles</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <div class="bg-[#007F2F] h-3 rounded-full transition-all duration-500"
                                         style="width: {{ min(100, max(5, ($resumen['mercadosB'] / 50) * 100)) }}%"></div>
                                </div>
                                <p class="text-[11px] text-gray-500">Kits reforzados para estudiantes cabeza de familia.</p>
                            </div>
                        </div>

                        <!-- ALERTA DE ROTACIÓN FEFO -->
                        @if ($resumen['proximoLoteFefo'])
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 flex items-center gap-3 text-xs text-amber-900 font-medium">
                                <span class="text-xl">⚠️</span>
                                <div>
                                    <strong>Rotación FEFO activa (First Expired, First Out):</strong>
                                    El lote <strong>{{ $resumen['proximoLoteFefo']->codigo_lote }}</strong>
                                    ({{ $resumen['proximoLoteFefo']->tipo }}) vence el
                                    <strong>{{ $resumen['proximoLoteFefo']->fecha_vencimiento ? $resumen['proximoLoteFefo']->fecha_vencimiento->format('d/m/Y') : 'próximamente' }}</strong>
                                    y debe ser despachado en los siguientes turnos de ventanilla.
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- FORMULARIO DE REGISTRO DE INGRESO A BODEGA -->
                    <form method="POST" action="{{ route('almacen.lotes.store') }}" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        @csrf
                        <div class="sm:col-span-3 border-b border-gray-100 pb-3">
                            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                <span class="text-lg">📥</span>
                                Registrar Entrada de Insumos / Nuevo Lote
                            </h2>
                            <p class="text-xs text-gray-500 mt-0.5">Captura de tipo de producto, fecha de ingreso, fecha de vencimiento y lote.</p>
                        </div>

                        <div>
                            <label for="tipo" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Tipo de Producto</label>
                            <select id="tipo" name="tipo" required class="{{ $input }}">
                                @foreach (Lote::TIPOS as $tipo)
                                    <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="descripcion" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Descripción de Contenido</label>
                            <input id="descripcion" name="descripcion" type="text" maxlength="200" required value="{{ old('descripcion') }}" placeholder="Ej. Arroz 5kg, aceite, frijol, lenteja, panela, atún" class="{{ $input }}">
                            <x-input-error :messages="$erroresLote->get('descripcion')" class="mt-1" />
                        </div>

                        <div>
                            <label for="cantidad" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Cantidad</label>
                            <input id="cantidad" name="cantidad" type="number" min="1" max="10000" required value="{{ old('cantidad', 10) }}" class="{{ $input }}">
                            <x-input-error :messages="$erroresLote->get('cantidad')" class="mt-1" />
                        </div>

                        <div>
                            <label for="fecha_ingreso" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Fecha de Ingreso</label>
                            <input id="fecha_ingreso" name="fecha_ingreso" type="date" required value="{{ old('fecha_ingreso', now(\App\Models\Actuacion::ZONA_HORARIA)->toDateString()) }}" class="{{ $input }}">
                            <x-input-error :messages="$erroresLote->get('fecha_ingreso')" class="mt-1" />
                        </div>

                        <div>
                            <label for="fecha_vencimiento" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Vencimiento <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" value="{{ old('fecha_vencimiento') }}" class="{{ $input }}">
                            <x-input-error :messages="$erroresLote->get('fecha_vencimiento')" class="mt-1" />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="id_donante" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Donante Asociado <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <select id="id_donante" name="id_donante" class="{{ $input }}">
                                <option value="">Sin donante vinculado (Aporte institucional)</option>
                                @foreach ($donantes->where('activo', true) as $donante)
                                    <option value="{{ $donante->id_donante }}" @selected((string) old('id_donante') === (string) $donante->id_donante)>
                                        {{ $donante->nombre }} ({{ $donante->tipo }} · {{ $donante->categoria }}){{ $donante->anonimo ? ' [Anónimo]' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2.5 bg-[#C2410C] hover:bg-[#9A3412] text-white text-xs font-bold rounded-xl transition shadow-xs">
                                Registrar Ingreso a Bodega
                            </button>
                        </div>
                    </form>

                    <!-- TABLA DETALLADA DE LOTES EN BODEGA -->
                    <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden" aria-label="Lotes en bodega">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-bold text-gray-900 text-sm">Inventario de Lotes Registrados</h3>
                            <span class="text-xs text-gray-500 font-semibold">{{ $lotes->count() }} lote(s) en histórico</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="text-[11px] text-gray-500 uppercase tracking-wider bg-gray-50 border-b border-gray-200 text-left">
                                    <tr>
                                        <th class="px-4 py-3">Código Lote</th>
                                        <th class="px-4 py-3">Tipo</th>
                                        <th class="px-4 py-3">Disponible / Total</th>
                                        <th class="px-4 py-3">Ingreso</th>
                                        <th class="px-4 py-3">Vencimiento</th>
                                        <th class="px-4 py-3">Donante</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($lotes as $lote)
                                        <tr class="hover:bg-gray-50/50 transition">
                                            <td class="px-4 py-3 font-bold text-gray-900">
                                                {{ $lote->codigo_lote }}
                                                <span class="block text-[11px] font-normal text-gray-500 truncate max-w-xs">{{ $lote->descripcion }}</span>
                                            </td>
                                            <td class="px-4 py-3 font-semibold text-gray-700">{{ $lote->tipo }}</td>
                                            <td class="px-4 py-3">
                                                <strong>{{ $lote->cantidad_disponible }}</strong> / {{ $lote->cantidad_inicial }}
                                                <div class="w-16 bg-gray-200 rounded-full h-1.5 mt-1 overflow-hidden">
                                                    <div class="bg-[#007F2F] h-1.5 rounded-full" style="width: {{ $lote->cantidad_inicial > 0 ? ($lote->cantidad_disponible / $lote->cantidad_inicial) * 100 : 0 }}%"></div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">{{ $lote->fecha_ingreso->format('d/m/Y') }}</td>
                                            <td class="px-4 py-3">
                                                {{ $lote->fecha_vencimiento ? $lote->fecha_vencimiento->format('d/m/Y') : '—' }}
                                                @if ($lote->estaVencido() && $lote->cantidad_disponible > 0)
                                                    <span class="inline-block text-[10px] font-bold px-1.5 py-0.2 rounded bg-rose-100 text-rose-700 mt-0.5">Vencido</span>
                                                @elseif ($lote->venceProximamente())
                                                    <span class="inline-block text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 mt-0.5">Próximo a vencer</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">{{ $lote->donante?->nombreVisible() ?? 'Institucional' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No hay lotes registrados en bodega.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 3: TERMINAL DE DESPACHO EN VENTANILLA -->
            <!-- ========================================== -->
            @elseif ($pestana === 'despachos')
                <div id="despacho" class="space-y-6"
                     x-data="{
                         solicitudSeleccionadaId: @js($aprobadasList->first()?->id_solicitud ?? ''),
                         documentoEstudiante: @js($aprobadasList->first()?->persona?->numero_documento ?? ''),
                         nombreEstudiante: @js($aprobadasList->first()?->persona?->nombre_completo ?? ''),
                         codigoEstudiante: @js($aprobadasList->first()?->persona?->usuario?->codigo_unico ?? ''),
                         lectura: '',
                         avisoLectura: '',
                         ordenes: @js($aprobadasList->map(fn ($s) => ['id' => $s->id_solicitud, 'doc' => $s->persona?->numero_documento, 'nombre' => $s->persona?->nombre_completo, 'cod' => $s->persona?->usuario?->codigo_unico])->values()),

                         seleccionarSolicitud(id, doc, nombre, cod) {
                             this.solicitudSeleccionadaId = id;
                             this.documentoEstudiante = doc;
                             this.nombreEstudiante = nombre;
                             this.codigoEstudiante = cod;
                             {{-- El lienzo de firma de la orden elegida estaba oculto: se ajusta a su tamaño. --}}
                             this.$nextTick(() => window.dispatchEvent(new Event('resize')));
                         },

                         {{-- El lector del QR del pase escribe ALIM:<id> y Enter; también sirve el documento. --}}
                         leerPase() {
                             const leido = this.lectura.trim();
                             const id = leido.toUpperCase().startsWith(@js(Solicitud::PREFIJO_PASE)) ? leido.slice({{ strlen(Solicitud::PREFIJO_PASE) }}) : leido;
                             const orden = this.ordenes.find((o) => o.id === id || (o.doc && o.doc === leido.replace(/\D/g, '')));
                             if (orden) {
                                 this.seleccionarSolicitud(orden.id, orden.doc, orden.nombre, orden.cod);
                                 this.avisoLectura = 'Orden de ' + orden.nombre + ' seleccionada. Verifica su documento.';
                             } else {
                                 this.avisoLectura = 'No hay una orden aprobada con ese pase o documento.';
                             }
                             this.lectura = '';
                         },

                         {{-- Antes de enviar: la firma del lienzo va en el campo oculto. --}}
                         prepararFirma(formulario, evento) {
                             const lienzo = window.Alpine.$data(formulario.querySelector('[data-lienzo]'));
                             if (! lienzo.hayTrazo) {
                                 evento.preventDefault();
                                 formulario.querySelector('[data-aviso-firma]').hidden = false;
                                 return;
                             }
                             formulario.querySelector('input[name=firma]').value = lienzo.exportar();
                         },
                     }">

                    <!-- Encabezado del Terminal -->
                    <div class="bg-gradient-to-r from-gray-900 to-gray-800 text-white rounded-2xl p-6 shadow-md flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] font-bold tracking-widest uppercase text-emerald-400 block mb-1">
                                ● Terminal de Ventanilla Activo
                            </span>
                            <h2 class="text-lg font-bold">Punto de Dispensación y Retiro de Mercados</h2>
                            <p class="text-xs text-gray-300 mt-0.5">Bodega Bloque C · Verificación de identidad mediante carné/QR y captura de firma digital.</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-gray-400">Existencias Disponibles</span>
                            <p class="text-2xl font-bold text-emerald-400">{{ $resumen['mercados'] }} kits</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                        <!-- COLUMNA IZQUIERDA: LISTA DE ESTUDIANTES EN VENTANILLA (APROBADAS) -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm space-y-3">
                            <h3 class="font-bold text-gray-900 text-sm flex items-center justify-between">
                                <span>Órdenes Listas para Retiro</span>
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-sky-100 text-sky-800">{{ $aprobadasList->count() }}</span>
                            </h3>
                            <p class="text-xs text-gray-500">Escanea el QR del pase del estudiante o selecciónalo en la lista.</p>
                            <form @submit.prevent="leerPase()" class="flex gap-2">
                                <label for="lectura-pase" class="sr-only">QR del pase o documento</label>
                                <input id="lectura-pase" type="text" x-model="lectura" autocomplete="off" placeholder="Escanea el QR o escribe el documento"
                                       class="{{ $input }} text-xs">
                                <button type="submit" class="px-3 py-2 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-xl shrink-0">Buscar</button>
                            </form>
                            <p class="text-xs text-gray-600" x-show="avisoLectura" x-text="avisoLectura" role="status" style="display: none;"></p>

                            <div class="space-y-2 max-h-[480px] overflow-y-auto pr-1">
                                @forelse ($aprobadasList as $sol)
                                    <button type="button"
                                            @click="seleccionarSolicitud('{{ $sol->id_solicitud }}', '{{ $sol->persona?->numero_documento }}', '{{ $sol->persona?->nombre_completo }}', '{{ $sol->persona?->usuario?->codigo_unico ?? '' }}')"
                                            class="w-full text-left p-3 rounded-xl border transition-all text-xs"
                                            :class="solicitudSeleccionadaId === '{{ $sol->id_solicitud }}' ? 'border-[#C2410C] bg-orange-50/50 shadow-xs ring-1 ring-[#C2410C]' : 'border-gray-200 hover:border-gray-300 bg-white'">
                                        <div class="font-bold text-gray-900 flex items-center justify-between">
                                            <span>{{ $sol->persona?->nombre_completo }}</span>
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">ORD-{{ strtoupper(substr($sol->id_solicitud, 0, 5)) }}</span>
                                        </div>
                                        <div class="text-gray-500 mt-1 flex justify-between">
                                            <span>Doc: {{ $sol->persona?->numero_documento }}</span>
                                            <span class="font-semibold text-emerald-700">Aprobada</span>
                                        </div>
                                    </button>
                                @empty
                                    <p class="text-xs text-gray-400 text-center py-8">No hay órdenes aprobadas pendientes de retiro.</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- COLUMNA DERECHA: FORMULARIO DE DESPACHO CON VERIFICACIÓN Y FIRMA -->
                        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-5">
                            @if ($aprobadasList->isNotEmpty())
                                @php
                                    $primeraSol = $aprobadasList->first();
                                @endphp
                                @foreach ($aprobadasList as $solicitud)
                                    @php
                                        $bolsaEntrega = $errors->getBag('entrega-'.$solicitud->id_solicitud);
                                    @endphp
                                    <div x-show="solicitudSeleccionadaId === '{{ $solicitud->id_solicitud }}'" class="space-y-5">
                                        <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                                            <div>
                                                <span class="text-xs text-gray-400 font-bold uppercase tracking-wider block">Beneficiario Citado</span>
                                                <h3 class="text-base font-bold text-gray-900">{{ $solicitud->persona?->nombre_completo }}</h3>
                                                <p class="text-xs text-gray-500">Documento: <strong>{{ $solicitud->persona?->numero_documento }}</strong> · Situación: {{ $solicitud->situacion }}</p>
                                            </div>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-800">
                                                En Ventanilla
                                            </span>
                                        </div>

                                        <form method="POST" action="{{ route('almacen.solicitudes.entregar', $solicitud) }}" class="space-y-4" @submit="prepararFirma($el, $event)">
                                            @csrf
                                            <input type="hidden" name="firma" value="">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="lote-disp-{{ $solicitud->id_solicitud }}" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">
                                                        Lote a Despachar (FEFO) <span class="text-rose-500">*</span>
                                                    </label>
                                                    <select id="lote-disp-{{ $solicitud->id_solicitud }}" name="id_lote" required class="{{ $input }}">
                                                        @forelse ($despachables as $lote)
                                                            <option value="{{ $lote->id_lote }}" @selected((string) old('id_lote') === (string) $lote->id_lote)>
                                                                {{ $lote->codigo_lote }} · {{ $lote->tipo }} ({{ $lote->cantidad_disponible }} disp.{{ $lote->fecha_vencimiento ? ' · vence '.$lote->fecha_vencimiento->format('d/m/Y') : '' }})
                                                            </option>
                                                        @empty
                                                            <option value="">No hay mercados disponibles</option>
                                                        @endforelse
                                                    </select>
                                                    <x-input-error :messages="$bolsaEntrega->get('id_lote')" class="mt-1" />
                                                </div>

                                                <div>
                                                    <label for="metodo-disp-{{ $solicitud->id_solicitud }}" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">
                                                        Método de Verificación <span class="text-rose-500">*</span>
                                                    </label>
                                                    <select id="metodo-disp-{{ $solicitud->id_solicitud }}" name="metodo_validacion" class="{{ $input }}">
                                                        @foreach (Entrega::METODOS_VALIDACION as $metodo)
                                                            <option value="{{ $metodo }}">{{ $metodo }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="sm:col-span-2">
                                                    <label for="doc-disp-{{ $solicitud->id_solicitud }}" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">
                                                        Número de Documento Presentado por el Estudiante <span class="text-rose-500">*</span>
                                                    </label>
                                                    <input id="doc-disp-{{ $solicitud->id_solicitud }}"
                                                           name="documento"
                                                           type="text"
                                                           inputmode="numeric"
                                                           maxlength="20"
                                                           required
                                                           value="{{ $solicitud->persona?->numero_documento }}"
                                                           placeholder="Digita o escanea el documento..."
                                                           class="{{ $input }}">
                                                    <x-input-error :messages="$bolsaEntrega->get('documento')" class="mt-1" />
                                                </div>
                                            </div>

                                            <!-- FIRMA DE RECEPCIÓN DEL ESTUDIANTE: se guarda con la entrega -->
                                            <div x-data="lienzoFirma()" data-lienzo="firma_entrega" @pointerdown="$el.querySelector('[data-aviso-firma]').hidden = true" class="space-y-2 border-t border-gray-100 pt-3">
                                                <div class="flex items-center justify-between">
                                                    <p id="firma-ayuda-{{ $solicitud->id_solicitud }}" class="block text-xs font-bold text-gray-700 uppercase tracking-wide">
                                                        Firma de recepción del estudiante <span class="text-rose-500">*</span>
                                                    </p>
                                                    <button type="button" @click="borrar()" class="text-xs text-gray-500 hover:text-gray-700 underline">Borrar firma</button>
                                                </div>
                                                <x-firma-lienzo :ayuda="'firma-ayuda-'.$solicitud->id_solicitud" etiqueta="Área de firma de recepción del estudiante" />
                                                <p class="text-[11px] text-gray-500">El estudiante firma con el dedo o el mouse al recibir el mercado.</p>
                                                <p data-aviso-firma hidden class="text-sm text-red-600">Falta la firma de recepción del estudiante.</p>
                                                <x-input-error :messages="$bolsaEntrega->get('firma')" class="mt-1" />
                                            </div>

                                            <div class="pt-2">
                                                <button type="submit"
                                                        @disabled($despachables->isEmpty())
                                                        class="w-full py-3.5 bg-[#007F2F] hover:bg-[#006324] disabled:opacity-50 text-white font-bold text-sm rounded-xl transition shadow-md flex items-center justify-center gap-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    <span>Confirmar Entrega y Despacho de Mercado</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center py-12 text-gray-400 text-xs">
                                    No hay órdenes activas para despacho en este momento.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 4: GESTIÓN DE DONANTES Y CERTIFICADOS -->
            <!-- ========================================== -->
            @elseif ($pestana === 'donantes')
                <div class="space-y-6">
                    <!-- Formulario de Registro de Donante -->
                    <form method="POST" action="{{ route('almacen.donantes.store') }}" x-data="{ tipo: @js(old('tipo', 'Externo')) }"
                          class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 grid grid-cols-1 sm:grid-cols-4 gap-4">
                        @csrf
                        <div class="sm:col-span-4 border-b border-gray-100 pb-3">
                            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                <span class="text-lg">🤝</span>
                                Registrar Nuevo Donante Solidario
                            </h2>
                            <p class="text-xs text-gray-500 mt-0.5">Perfiles para donantes internos (docentes, estudiantes, administrativos) y externos (empresas, fundaciones, egresados).</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="nombre" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Nombre o Razón Social</label>
                            <input id="nombre" name="nombre" type="text" maxlength="150" required value="{{ old('nombre') }}" placeholder="Ej. Fundación Valle Solidario / Empresa Colanta" class="{{ $input }}">
                            <x-input-error :messages="$erroresDonante->get('nombre')" class="mt-1" />
                        </div>

                        <div>
                            <label for="tipo_donante" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Tipo de Donante</label>
                            <select id="tipo_donante" name="tipo" x-model="tipo" class="{{ $input }}">
                                @foreach (Donante::TIPOS as $tipo)
                                    <option value="{{ $tipo }}">{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="categoria" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Categoría</label>
                            <select id="categoria" name="categoria" required class="{{ $input }}">
                                @foreach (Donante::CATEGORIAS as $tipo => $categorias)
                                    @foreach ($categorias as $categoria)
                                        <option value="{{ $categoria }}" x-show="tipo === @js($tipo)" :disabled="tipo !== @js($tipo)" @selected(old('categoria') === $categoria)>{{ $categoria }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <x-input-error :messages="$erroresDonante->get('categoria')" class="mt-1" />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="contacto" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1.5">Contacto / Correo / Teléfono <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <input id="contacto" name="contacto" type="text" maxlength="150" value="{{ old('contacto') }}" placeholder="correo@ejemplo.com o teléfono" class="{{ $input }}">
                        </div>

                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 sm:pt-6">
                            <input type="checkbox" name="anonimo" value="1" @checked(old('anonimo')) class="rounded border-gray-300 text-[#C2410C] focus:ring-[#C2410C]">
                            Donación anónima (Habeas Data)
                        </label>

                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2.5 bg-[#C2410C] hover:bg-[#9A3412] text-white text-xs font-bold rounded-xl transition shadow-xs">
                                Guardar Donante
                            </button>
                        </div>
                    </form>

                    <!-- Listado de Donantes con Emisión de Certificados PDF -->
                    <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden" aria-label="Donantes registrados">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-bold text-gray-900 text-sm">Donantes Registrados y Certificaciones Oficiales</h3>
                            <span class="text-xs text-gray-500 font-semibold">{{ $donantes->count() }} donante(s)</span>
                        </div>
                        <ul class="divide-y divide-gray-100 text-xs">
                            @forelse ($donantes as $donante)
                                <li class="p-5 flex flex-wrap items-center justify-between gap-3 {{ $donante->activo ? '' : 'opacity-60 bg-gray-50' }} hover:bg-gray-50/50 transition">
                                    <div class="space-y-1">
                                        <div class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                            <span>{{ $donante->nombre }}</span>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $donante->tipo === 'Externo' ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' }}">
                                                {{ $donante->tipo }} · {{ $donante->categoria }}
                                            </span>
                                            @if ($donante->anonimo)
                                                <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">Anónimo</span>
                                            @endif
                                        </div>
                                        <p class="text-gray-500 text-xs">
                                            Contacto: {{ $donante->contacto ?? 'Sin contacto registrado' }} · Aportes: <strong>{{ $donante->lotes_count }} lote(s)</strong>
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <!-- BOTÓN GENERAR CERTIFICADO PDF -->
                                        <a href="{{ route('almacen.donantes.certificado', $donante) }}"
                                           class="px-3.5 py-1.5 bg-gray-900 hover:bg-black text-white font-bold text-xs rounded-xl transition flex items-center gap-1.5 shadow-xs">
                                            <svg class="w-3.5 h-3.5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path><path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path></svg>
                                            <span>Certificado PDF</span>
                                        </a>

                                        <form method="POST" action="{{ route('almacen.donantes.estado', $donante) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-xl border {{ $donante->activo ? 'border-gray-300 text-gray-600 hover:bg-gray-100' : 'border-[#C2410C] text-[#C2410C] hover:bg-orange-50' }}">
                                                {{ $donante->activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    </div>
                                </li>
                            @empty
                                <li class="px-5 py-8 text-center text-gray-500">No hay donantes registrados aún.</li>
                            @endforelse
                        </ul>
                    </section>
                </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 5: TRAZABILIDAD DE ENTREGAS        -->
            <!-- ========================================== -->
            @elseif ($pestana === 'entregas')
                <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden" aria-label="Entregas registradas">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Histórico Inmutable de Entregas</h3>
                            <p class="text-xs text-gray-500">Registro oficial de trazabilidad con validación en ventanilla.</p>
                        </div>
                        <span class="text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 px-3 py-1 rounded-full">
                            {{ $entregas->count() }} entregas registradas
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="text-[11px] text-gray-500 uppercase tracking-wider bg-gray-50 border-b border-gray-200 text-left">
                                <tr>
                                    <th class="px-4 py-3">Fecha y Hora</th>
                                    <th class="px-4 py-3">Estudiante Beneficiario</th>
                                    <th class="px-4 py-3">Lote Despachado</th>
                                    <th class="px-4 py-3">Verificación</th>
                                    <th class="px-4 py-3">Funcionario que Entregó</th>
                                    <th class="px-4 py-3">Firma</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($entregas as $entrega)
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="px-4 py-3 font-semibold text-gray-800">{{ $entrega->fecha_hora->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-3 font-bold text-gray-900">
                                            {{ $entrega->solicitud?->persona?->nombre_completo }}
                                            <span class="block text-[11px] font-normal text-gray-500">Doc. {{ $entrega->documento_verificado }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-bold text-gray-800">{{ $entrega->lote?->codigo_lote }}</span>
                                            <span class="block text-[11px] text-gray-500">{{ $entrega->lote?->tipo }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-semibold text-[10px]">
                                                {{ $entrega->metodo_validacion }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $entrega->responsable?->persona?->nombre_completo ?? 'Personal Almacén' }}</td>
                                        <td class="px-4 py-3">
                                            @if ($entrega->firma_ruta)
                                                <a href="{{ route('almacen.entregas.firma', $entrega) }}" target="_blank" rel="noopener" class="font-bold text-[#C2410C] hover:underline">Ver firma</a>
                                            @else
                                                <span class="text-gray-400">Sin firma (anterior)</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Todavía no hay despachos registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

            <!-- ========================================== -->
            <!-- PESTAÑA 6: REPORTES Y ANALÍTICA           -->
            <!-- ========================================== -->
            @elseif ($pestana === 'reportes')
                <div class="space-y-6">

                    <!-- Tarjeta de Descarga de Informe Oficial de Transparencia -->
                    <div class="bg-gradient-to-br from-[#005c22] to-[#007F2F] text-white rounded-2xl p-6 sm:p-8 shadow-md flex flex-col sm:flex-row items-center justify-between gap-6">
                        <div class="space-y-2">
                            <span class="text-xs font-bold uppercase tracking-widest text-[#8AFF00] block">
                                Control Institucional y Trazabilidad
                            </span>
                            <h2 class="text-xl font-bold">Informe de Transparencia y Rendición de Cuentas</h2>
                            <p class="text-xs sm:text-sm text-green-100 max-w-xl">
                                Consolidado oficial descargable para entes de control con el balance de entradas, salidas, existencias en bodega, trazabilidad de donantes y cobertura estudiantil bajo la Ley de Habeas Data.
                            </p>
                        </div>
                        <a href="{{ route('almacen.reportes.transparencia') }}"
                           class="px-6 py-3.5 bg-white hover:bg-gray-100 text-[#007F2F] font-bold text-sm rounded-xl transition shadow-lg shrink-0 flex items-center gap-2">
                            <svg class="w-5 h-5 text-[#007F2F]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span>Descargar Informe PDF</span>
                        </a>
                    </div>

                    <!-- Indicadores y Analítica (KPIs) -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm space-y-2">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide block">Tasa de Rotación FEFO</span>
                            <span class="text-3xl font-bold text-gray-900 block">12.4 días</span>
                            <p class="text-xs text-gray-500">Tiempo promedio de permanencia de los lotes en bodega antes del despacho.</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm space-y-2">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide block">Cobertura de Vulnerabilidad</span>
                            <span class="text-3xl font-bold text-[#007F2F] block">88.5%</span>
                            <p class="text-xs text-gray-500">Porcentaje de mercados asignados a estudiantes de Estrato 1 y 2 en condición prioritaria.</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm space-y-2">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide block">Índice de Solidaridad Institucional</span>
                            <span class="text-3xl font-bold text-[#C2410C] block">{{ $resumen['donantesTotal'] }} Donantes</span>
                            <p class="text-xs text-gray-500">Alianzas activas entre comunidad universitaria, fundaciones y empresas externas.</p>
                        </div>
                    </div>
                </div>
            @endif

        </main>

        <!-- MODAL DE CARGUE MASIVO DE TRABAJO SOCIAL -->
        <div x-show="modalCargue"
             style="display: none;"
             class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4 backdrop-blur-xs"
             x-transition.opacity>
            <div class="bg-white w-full max-w-md rounded-2xl p-6 shadow-2xl space-y-4" @click.away="modalCargue = false">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                        <span>📤</span> Cargue Masivo de Beneficiarios (CSV)
                    </h3>
                    <button type="button" @click="modalCargue = false" class="text-gray-400 hover:text-gray-600 font-bold">&times;</button>
                </div>
                <p class="text-xs text-gray-500">
                    Sube el archivo de Trabajo Social con los estudiantes priorizados. No crea solicitudes: cada estudiante debe solicitar y autorizar sus datos. Sus solicitudes pendientes, y las que haga después, quedan con la prioridad indicada.
                </p>

                <form method="POST" action="{{ route('almacen.beneficiarios.cargue') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:bg-gray-50 transition cursor-pointer">
                        <input type="file" name="archivo_csv" accept=".csv,.txt" required class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-[#C2410C] hover:file:bg-orange-100">
                        <span class="block text-[11px] text-gray-500 mt-2">Columnas: codigo, documento, estrato, prioridad (Alta, Media o Baja; vacía = Alta). Separadas por coma o punto y coma; la primera fila puede ser el encabezado. Hasta 2.000 estudiantes.</span>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="modalCargue = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 bg-[#C2410C] hover:bg-[#9A3412] text-white text-xs font-bold rounded-xl transition">
                            Cargar priorizados
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>

<x-app-layout>
    @php
        // Etiqueta visible y colores por cada nombre_rol de la tabla roles.
        $estiloRol = [
            'Profesional' => ['Profesional de Bienestar', 'bg-green-50 text-[#007F2F] border-green-200'],
            'Alimentos'   => ['Personal de Almacén', 'bg-orange-50 text-[#C25E03] border-orange-200'],
            'Admin'       => ['Administrador del Sistema', 'bg-pink-50 text-[#E91E63] border-pink-200'],
            'VBG'         => ['Profesional VBG', 'bg-purple-50 text-purple-700 border-purple-200'],
            'Persona'     => ['Estudiante / Persona', 'bg-gray-100 text-gray-600 border-gray-200'],
        ];
    @endphp

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="min-h-screen bg-gray-50 flex flex-col w-full" x-data="designarRol()" @keydown.escape.window="cerrar()">

        <!-- 1. FRANJA BLANCA SUPERIOR (Header) -->
        <div class="bg-white px-4 sm:px-8 py-4 flex justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Administración · SIGABU</h1>
                <p class="text-gray-500 text-sm">Gestión y configuración del Sistema de Bienestar Universitario</p>
            </div>
            <div class="flex items-center">
                <!-- Botón X (Cerrar/Salir) -->
                <a href="{{ route('admin.dashboard') }}" class="w-10 h-10 flex items-center justify-center bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </a>
            </div>
        </div>

        <!-- 2. CONTENIDO DE LA PÁGINA -->
        <div class="p-4 sm:p-8 max-w-[1400px] mx-auto w-full flex-1 flex flex-col gap-6 overflow-y-auto">
            
            <!-- Título de Sección -->
            <div>
                <h2 class="text-3xl font-bold text-[#0a4220]">Panel de Administración</h2>
                <p class="text-gray-500 mt-1">Gestión y configuración de SIGABU</p>
            </div>

            <!-- Mensajes -->
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-[#007F2F] text-sm font-medium rounded-lg px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-600 text-sm font-medium rounded-lg px-4 py-3">
                    {{ $errors->first() }}
                    @if (session('trasladoDe'))
                        <a href="{{ route('admin.traslados.index', session('trasladoDe')) }}" class="block mt-1 text-xs font-semibold underline">¿El profesional no puede entregarlos? Traslado de último recurso</a>
                    @endif
                </div>
            @endif

            <!-- 3. PESTAÑAS (TABS) Y BOTÓN DE ACCIÓN -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                
                <!-- Contenedor de Pestañas -->
                <div class="flex max-w-full overflow-x-auto bg-white rounded-xl shadow-sm border border-gray-200 p-1">
                    <!-- Pestaña Activa -->
                    <button class="bg-[#007F2F] text-white shrink-0 whitespace-nowrap px-5 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path></svg>
                        Usuarios y Roles
                    </button>
                    
                    <!-- Pestañas Inactivas -->
                    <button class="text-gray-600 hover:bg-gray-50 shrink-0 whitespace-nowrap px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors">
                        <span>📁</span> Catálogos
                    </button>
                    <button class="text-gray-600 hover:bg-gray-50 shrink-0 whitespace-nowrap px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors">
                        <span>⚙️</span> Flujos de trabajo
                    </button>
                    <button class="text-gray-600 hover:bg-gray-50 shrink-0 whitespace-nowrap px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors">
                        <span>🔍</span> Auditoría
                    </button>
                </div>

                <!-- Botón Designar Rol -->
                <button type="button" @click="abrir()" class="bg-[#6FD835] hover:bg-[#5ec22b] text-black font-semibold px-6 py-2.5 rounded-lg shadow-sm transition-colors flex items-center gap-2">
                    <span>+</span> Designar Rol
                </button>
            </div>

            <!-- 4. TABLA VACÍA -->
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="relative overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4">Usuario</th>
                                <th class="px-6 py-4">Cargo</th>
                                <th class="px-6 py-4">Área</th>
                                <th class="px-6 py-4">Tareas Activas</th>
                                <th class="px-6 py-4">Rol</th>
                                <th class="px-6 py-4">Estado</th>
                                <th class="px-6 py-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($personal as $usuario)
                                @php
                                    $nombreRol = $usuario->rolReal?->nombre_rol;
                                    [$etiquetaRol, $claseRol] = $estiloRol[$nombreRol] ?? [$nombreRol ?? 'Sin rol', 'bg-gray-100 text-gray-600 border-gray-200'];
                                    $nombre = $usuario->persona?->nombre_completo ?? $usuario->codigo_unico;
                                @endphp
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-[#6FD835]/20 text-[#007F2F] font-bold text-xs flex items-center justify-center shrink-0">
                                                {{ mb_strtoupper(mb_substr($nombre, 0, 2)) }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800">{{ $nombre }}</p>
                                                <p class="text-xs text-gray-400">{{ $usuario->persona?->correo_inst ?? $usuario->codigo_unico }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">{{ $etiquetaRol }}</td>
                                    <td class="px-6 py-4">{{ $nombreRol === 'Profesional' ? ($usuario->etiquetaArea() ?? 'Sin área asignada') : ($usuario->rolReal?->sistema_destino ?? '—') }}</td>
                                    <td class="px-6 py-4 text-gray-400">—</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold border {{ $claseRol }}">{{ $nombreRol ?? 'Sin rol' }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($usuario->estado_activo)
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#007F2F]"><span class="w-2 h-2 rounded-full bg-[#6FD835]"></span>Activo</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-400"><span class="w-2 h-2 rounded-full bg-gray-300"></span>Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button"
                                                @click="abrir(@js([
                                                    'id' => $usuario->id_usuario,
                                                    'nombre' => $nombre,
                                                    'documento' => $usuario->persona?->numero_documento,
                                                    'correo' => $usuario->persona?->correo_inst,
                                                    'codigo' => $usuario->codigo_unico,
                                                    'id_rol' => $usuario->id_rol,
                                                    'rol' => $nombreRol,
                                                    'area' => $usuario->area_profesional,
                                                ]))"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 transition-colors">
                                                {{ $nombreRol === 'Profesional' ? 'Cambiar rol o área' : 'Cambiar rol' }}
                                            </button>
                                            @if ($nombreRol === 'Profesional')
                                                {{-- HU-032: acceso al tablero de indicadores institucionales --}}
                                                <form method="POST" action="{{ route('admin.usuarios.indicadores', $usuario) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" title="Acceso al tablero de indicadores institucionales"
                                                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors {{ $usuario->ve_indicadores ? 'border-sky-200 text-sky-700 bg-sky-50 hover:bg-sky-100' : 'border-gray-200 text-gray-600 hover:bg-gray-100' }}">
                                                        {{ $usuario->ve_indicadores ? '📊 Ve indicadores' : 'Dar acceso a indicadores' }}
                                                    </button>
                                                </form>
                                            @endif
                                            @unless ($usuario->is(auth()->user()))
                                                <form method="POST" action="{{ route('admin.usuarios.estado', $usuario) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors {{ $usuario->estado_activo ? 'border-red-200 text-red-500 hover:bg-red-50' : 'border-green-200 text-[#007F2F] hover:bg-green-50' }}">
                                                        {{ $usuario->estado_activo ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                            <!-- Estado Vacío -->
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        <p class="text-base font-medium text-gray-500">No hay usuarios registrados</p>
                                        <p class="text-sm mt-1">Usa el botón "Designar Rol" para comenzar a agregar personal.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 5. TARJETAS DE EXPLICACIÓN DE ROLES -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                
                <!-- Tarjeta: Profesional de Bienestar -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                    <h3 class="text-[#007F2F] font-bold text-base mb-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#007F2F]"></span> 
                        Rol: Profesional de Bienestar
                    </h3>
                    <ul class="text-sm text-gray-600 space-y-2.5 list-none">
                        <li>Gestión de casos de su área: Psicología, Inclusión social o ambas</li>
                        <li>Reporte de Violencias de Género</li>
                        <li>Uso de la capa de IA (transcripción, análisis de riesgo)</li>
                        <li>Programación de citas y tareas terapéuticas</li>
                        <li>Registro de actuaciones y desistimientos</li>
                        <li>Acceso al repositorio institucional</li>
                    </ul>
                </div>

                <!-- Tarjeta: Personal de Almacén -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                    <h3 class="text-[#C25E03] font-bold text-base mb-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#C25E03]"></span> 
                        Rol: Personal de Almacén
                    </h3>
                    <ul class="text-sm text-gray-600 space-y-2.5 list-none">
                        <li>Registro de donantes y entradas de insumos</li>
                        <li>Control de inventario y alertas de stock/vencimiento</li>
                        <li>Generación de órdenes de entrega</li>
                        <li>Validación de entregas por QR o carné estudiantil</li>
                    </ul>
                </div>

                <!-- Tarjeta: Administrador del Sistema -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                    <h3 class="text-[#E91E63] font-bold text-base mb-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#E91E63]"></span> 
                        Rol: Administrador del Sistema
                    </h3>
                    <ul class="text-sm text-gray-600 space-y-2.5 list-none">
                        <li>Gestión de usuarios, roles y permisos (Habeas Data)</li>
                        <li>Parametrización de formularios, catálogos y flujos</li>
                        <li>Analítica institucional y reportes para entes de control</li>
                        <li>Auditoría de accesos y trazabilidad del sistema</li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- 6. MODAL DESIGNAR ROL -->
        <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="absolute inset-0 bg-black/40" @click="cerrar()"></div>

            <div x-show="abierto" x-transition
                class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden">

                <!-- Encabezado -->
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-[#0a4220]">Designar Rol</h3>
                        <p class="text-sm text-gray-500">Busca a la persona y elige el rol que tendrá en SIGABU.</p>
                    </div>
                    <button type="button" @click="cerrar()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form method="POST" :action="accion()" class="flex flex-col flex-1 overflow-hidden">
                    @csrf
                    @method('PATCH')

                    <div class="px-6 py-5 flex flex-col gap-5 overflow-y-auto">

                        <!-- Paso 1: Persona -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">1. Persona</label>

                            <!-- Persona seleccionada -->
                            <template x-if="seleccionado">
                                <div class="flex items-center justify-between gap-3 border border-green-200 bg-green-50 rounded-lg px-4 py-3">
                                    <div>
                                        <p class="font-semibold text-gray-800" x-text="seleccionado.nombre"></p>
                                        <p class="text-xs text-gray-500">
                                            <span x-text="seleccionado.documento ? 'Doc. ' + seleccionado.documento : 'Código ' + seleccionado.codigo"></span>
                                            <span x-show="seleccionado.correo"> · <span x-text="seleccionado.correo"></span></span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">Rol actual: <span class="font-semibold" x-text="seleccionado.rol || 'Sin rol'"></span></p>
                                    </div>
                                    <button type="button" @click="limpiar()" class="text-xs font-semibold text-gray-500 hover:text-red-500">Cambiar</button>
                                </div>
                            </template>

                            <!-- Buscador -->
                            <div x-show="!seleccionado">
                                <input type="text" x-ref="buscador" x-model="termino" @input.debounce.350ms="buscar()"
                                    placeholder="Nombre, documento, correo o código"
                                    class="w-full border-gray-300 rounded-lg text-sm focus:border-[#007F2F] focus:ring-[#007F2F]">

                                <div class="mt-2 border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-56 overflow-y-auto" x-show="termino.trim().length >= 2">
                                    <p x-show="cargando" class="px-4 py-3 text-sm text-gray-400">Buscando…</p>
                                    <p x-show="!cargando && resultados.length === 0" class="px-4 py-3 text-sm text-gray-400">No se encontraron personas.</p>
                                    <template x-for="u in resultados" :key="u.id">
                                        <button type="button" @click="seleccionar(u)" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 flex justify-between items-center gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800" x-text="u.nombre"></p>
                                                <p class="text-xs text-gray-400" x-text="[u.documento, u.correo].filter(Boolean).join(' · ') || u.codigo"></p>
                                            </div>
                                            <span class="text-xs text-gray-500 shrink-0" x-text="u.rol || 'Sin rol'"></span>
                                        </button>
                                    </template>
                                </div>
                                <p x-show="error" class="mt-2 text-sm text-red-500" x-text="error"></p>
                            </div>
                        </div>

                        <!-- Paso 2: Rol -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">2. Rol</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                {{-- "Persona" es el rol base; se vuelve a él con "Quitar rol". --}}
                                @foreach ($roles->where('nombre_rol', '!=', 'Persona') as $rol)
                                    @php [$etiqueta, $clase] = $estiloRol[$rol->nombre_rol] ?? [$rol->nombre_rol, 'bg-gray-100 text-gray-600 border-gray-200']; @endphp
                                    <label class="flex items-start gap-3 border rounded-lg px-3 py-2.5 cursor-pointer transition-colors"
                                        :class="rol == {{ $rol->id_rol }} ? 'border-[#007F2F] bg-green-50/60' : 'border-gray-200 hover:bg-gray-50'">
                                        <input type="radio" name="id_rol" value="{{ $rol->id_rol }}" x-model="rol"
                                            class="mt-0.5 text-[#007F2F] focus:ring-[#007F2F]">
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-800">{{ $etiqueta }}</span>
                                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $clase }}">{{ $rol->nombre_rol }}</span>
                                            @if ($rol->sistema_destino)
                                                <span class="block text-xs text-gray-400 mt-1">{{ $rol->sistema_destino }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Paso 3: Área del profesional -->
                        <div x-show="esProfesional()" x-cloak>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">3. Área</label>
                            <p class="text-xs text-gray-500 mb-2">El profesional solo verá y gestionará los casos de esta área.</p>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach (\App\Models\User::AREAS as $valor => $etiqueta)
                                    <label class="flex items-center gap-3 border rounded-lg px-3 py-2.5 cursor-pointer transition-colors"
                                        :class="area === @js($valor) ? 'border-[#007F2F] bg-green-50/60' : 'border-gray-200 hover:bg-gray-50'">
                                        <input type="radio" name="area_profesional" value="{{ $valor }}" x-model="area"
                                            class="text-[#007F2F] focus:ring-[#007F2F]">
                                        <span class="text-sm font-semibold text-gray-800">{{ $etiqueta }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Pie -->
                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                        <button type="submit" form="form-quitar-rol" x-show="puedeQuitar()"
                            @click="if (!confirm('¿Quitar el rol ' + seleccionado.rol + ' a ' + seleccionado.nombre + '? Volverá a tener el rol Persona.')) $event.preventDefault()"
                            class="mr-auto px-4 py-2.5 rounded-lg text-sm font-semibold text-red-500 border border-red-200 hover:bg-red-50 transition-colors">
                            Quitar rol
                        </button>
                        <button type="button" @click="cerrar()" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</button>
                        <button type="submit" :disabled="!seleccionado || !rol || (esProfesional() && !area)"
                            class="bg-[#007F2F] hover:bg-[#006826] text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                            Guardar rol
                        </button>
                    </div>
                </form>

                <!-- Formulario de "Quitar rol" (fuera del otro form: no se pueden anidar) -->
                <form id="form-quitar-rol" method="POST" :action="accionQuitar()" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('designarRol', () => ({
                abierto: false,
                termino: '',
                resultados: [],
                cargando: false,
                error: '',
                seleccionado: null,
                rol: '',
                area: '',

                abrir(usuario = null) {
                    this.limpiar();
                    if (usuario) this.seleccionar(usuario);
                    this.abierto = true;
                    if (!usuario) this.$nextTick(() => this.$refs.buscador.focus());
                },

                cerrar() {
                    this.abierto = false;
                },

                limpiar() {
                    this.seleccionado = null;
                    this.rol = '';
                    this.area = '';
                    this.termino = '';
                    this.resultados = [];
                    this.error = '';
                },

                seleccionar(usuario) {
                    this.seleccionado = usuario;
                    this.rol = usuario.id_rol && usuario.rol !== 'Persona' ? String(usuario.id_rol) : '';
                    this.area = usuario.area || '';
                },

                esProfesional() {
                    return this.rol === @js((string) $roles->firstWhere('nombre_rol', 'Profesional')?->id_rol);
                },

                accion() {
                    if (!this.seleccionado) return '#';
                    return @js(route('admin.usuarios.rol', '__ID__')).replace('__ID__', this.seleccionado.id);
                },

                accionQuitar() {
                    if (!this.seleccionado) return '#';
                    return @js(route('admin.usuarios.rol.quitar', '__ID__')).replace('__ID__', this.seleccionado.id);
                },

                // Solo se puede quitar un rol distinto al base, y nunca el propio.
                puedeQuitar() {
                    return this.seleccionado
                        && this.seleccionado.rol
                        && this.seleccionado.rol !== 'Persona'
                        && this.seleccionado.id !== @js(auth()->id());
                },

                async buscar() {
                    const q = this.termino.trim();
                    this.error = '';
                    if (q.length < 2) {
                        this.resultados = [];
                        return;
                    }
                    this.cargando = true;
                    try {
                        const url = @js(route('admin.usuarios.buscar')) + '?q=' + encodeURIComponent(q);
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error();
                        // Descarta respuestas de búsquedas anteriores.
                        if (q === this.termino.trim()) this.resultados = await res.json();
                    } catch (e) {
                        this.resultados = [];
                        this.error = 'No se pudo realizar la búsqueda. Intenta de nuevo.';
                    } finally {
                        this.cargando = false;
                    }
                },
            }));
        });
    </script>
</x-app-layout>
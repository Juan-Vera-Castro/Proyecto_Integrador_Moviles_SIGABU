@php
    $modulos = [
        ['🧠', 'Salud Mental', '#007F2F', route('admin.analitica'), [
            ['Casos activos', $servicios['Salud Mental']['activos']],
            ['Sin profesional', $servicios['Salud Mental']['sinAsignar']],
            ['Riesgo alto', $servicios['Salud Mental']['riesgoAlto']],
            ['Citas este mes', $servicios['Salud Mental']['citasMes']],
            ['Cerrados este mes', $servicios['Salud Mental']['cerradosMes']],
        ]],
        ['♿', 'Inclusión Universitaria', '#2563EB', route('admin.analitica'), [
            ['Casos activos', $servicios['Inclusión']['activos']],
            ['Sin profesional', $servicios['Inclusión']['sinAsignar']],
            ['Riesgo alto', $servicios['Inclusión']['riesgoAlto']],
            ['Citas este mes', $servicios['Inclusión']['citasMes']],
            ['Cerrados este mes', $servicios['Inclusión']['cerradosMes']],
        ]],
        ['🛡️', 'Violencias de Género', '#7E22CE', route('admin.analitica'), [
            ['Reportes por tomar', $vbg['recibidos']],
            ['En atención', $vbg['enAtencion']],
            ['Riesgo alto abiertos', $vbg['riesgoAlto']],
            ['Cerrados este mes', $vbg['cerradosMes']],
        ]],
        ['🍎', 'Donación Alimentaria', '#C2410C', route('admin.analitica'), [
            ['Solicitudes pendientes', $alimentos['pendientes']],
            ['Aprobadas por entregar', $alimentos['aprobadas']],
            ['Mercados disponibles', $alimentos['mercados']],
            ['Entregas este mes', $alimentos['entregasMes']],
        ]],
    ];
@endphp

<x-app-layout>
    <div class="min-h-screen bg-gray-50 flex flex-col w-full">

        <div class="bg-white px-4 sm:px-8 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-200 shrink-0">
            <div>
                <h1 class="text-xl font-bold text-[#007F2F]">Dashboard global</h1>
                <p class="text-gray-500 text-sm">Estado de los módulos de Bienestar Universitario, con datos al momento</p>
            </div>
            <a href="{{ route('admin.analitica') }}" class="px-4 py-2 bg-[#007F2F] hover:bg-[#006324] text-white text-xs font-bold rounded-xl transition">Ver analítica institucional →</a>
        </div>

        <div class="p-4 sm:p-8 max-w-[1400px] mx-auto w-full flex flex-col gap-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ($modulos as [$icono, $nombre, $color, $enlace, $datos])
                    <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5" aria-label="{{ $nombre }}">
                        <h2 class="text-base font-bold flex items-center gap-2" style="color: {{ $color }}"><span aria-hidden="true">{{ $icono }}</span> {{ $nombre }}</h2>
                        <dl class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-4">
                            @foreach ($datos as [$etiqueta, $valor])
                                <div class="bg-gray-50 rounded-xl p-3">
                                    <dd class="text-2xl font-bold text-gray-900">{{ $valor }}</dd>
                                    <dt class="text-[11px] text-gray-500 font-medium leading-tight">{{ $etiqueta }}</dt>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>

            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5" aria-labelledby="usuarios-titulo">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 id="usuarios-titulo" class="text-base font-bold text-gray-900">Usuarios por rol</h2>
                    <a href="{{ route('admin.administracion') }}" class="text-xs font-bold text-[#007F2F] hover:underline">Gestionar roles →</a>
                </div>
                <dl class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    @foreach ($usuariosPorRol as $rol => $cantidad)
                        <div class="bg-gray-50 rounded-xl p-3">
                            <dd class="text-2xl font-bold text-gray-900">{{ $cantidad }}</dd>
                            <dt class="text-[11px] text-gray-500 font-medium">{{ $rol === 'Persona' ? 'Persona (estudiante)' : $rol }}</dt>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>
    </div>
</x-app-layout>

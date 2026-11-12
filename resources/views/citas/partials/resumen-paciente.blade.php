{{-- Resumen del paciente del caso seleccionado al agendar: datos, citas, historial clínico
     y otros casos. Recibe $caso con solicitud.persona.casos, citas, tareas y aceptacionesConsentimiento. --}}
@php
    use App\Models\Actuacion;
    use App\Models\TareaTerapeutica;

    $persona = $caso->solicitud?->persona;
    $ahora = now(Actuacion::ZONA_HORARIA);
    $edad = $persona?->edadEn($ahora);
    $realizadas = $caso->citas->reject(fn ($cita) => $cita->estaCancelada() || $cita->esProxima());
    $asistidas = $realizadas->where('estado_asistencia', Actuacion::ASISTIO)->count();
    $inasistencias = $realizadas->where('estado_asistencia', Actuacion::NO_ASISTIO)->count();
    $proxima = $caso->proximaCita();
    $historial = $realizadas->sortByDesc('fecha_hora');
    $tareasPendientes = $caso->tareas->filter(fn ($tarea) => $tarea->estaPendiente());
    $tareasCumplidas = $caso->tareas->where('estado', TareaTerapeutica::ESTADO_CUMPLIDA)->count();
    $otrosCasos = ($persona?->casos ?? collect())->reject(fn ($otro) => $otro->id_caso === $caso->id_caso)->sortByDesc('fecha_apertura');
    $riesgo = [
        'Alto' => 'bg-rose-50 text-rose-600',
        'Medio' => 'bg-amber-50 text-amber-700',
        'Bajo' => 'bg-emerald-50 text-emerald-700',
    ];
    $asistencia = [
        Actuacion::ASISTIO => 'bg-emerald-50 text-emerald-700',
        Actuacion::NO_ASISTIO => 'bg-rose-50 text-rose-600',
    ];
@endphp

<section class="bg-gray-50 border border-gray-200 rounded-xl p-4 sm:p-5 space-y-4" aria-label="Resumen del paciente {{ $persona?->nombre_completo }}">
    <!-- Encabezado: paciente e información importante -->
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-bold text-gray-900 text-base break-words">{{ $persona?->nombre_completo }}</p>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ $persona?->tipo_documento ?? 'Doc.' }} {{ $persona?->numero_documento }}
                @if ($edad !== null) · {{ $edad }} años @endif
                @if ($persona?->sexo) · Sexo {{ $persona->sexo }} @endif
                @if ($persona?->correo_inst) · {{ $persona->correo_inst }} @endif
            </p>
        </div>
        <a href="{{ route('casos.show', $caso) }}" target="_blank" rel="noopener"
           class="text-xs font-bold text-[#007F2F] hover:underline focus:outline-none focus:ring-2 focus:ring-[#007F2F] focus:ring-offset-2 rounded shrink-0">
            Ver expediente completo ↗
        </a>
    </div>

    <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
        <span class="px-2.5 py-1 rounded-full bg-white border border-gray-200 text-gray-700">{{ $caso->codigo_expediente }} · {{ $caso->solicitud?->tipo_servicio }}</span>
        <span class="px-2.5 py-1 rounded-full bg-white border border-gray-200 text-gray-700">{{ $caso->estado_caso }} desde {{ $caso->fecha_apertura?->format('Y-m-d') }}</span>
        <span class="px-2.5 py-1 rounded-full {{ $riesgo[$caso->nivel_riesgo] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $caso->nivel_riesgo ? 'Riesgo '.mb_strtolower($caso->nivel_riesgo) : 'Riesgo sin definir' }}
        </span>
        @if ($edad !== null && $persona->esMenorEn($ahora))
            <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700">⚠ Menor de edad</span>
        @endif
        @if ($caso->requiereConsentimiento())
            @if ($caso->consentimientoVigente())
                <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700">✓ Consentimiento firmado</span>
            @else
                <span class="px-2.5 py-1 rounded-full bg-rose-50 text-rose-600">⚠ Sin consentimiento firmado</span>
            @endif
        @endif
    </div>

    @if ($caso->solicitud?->descripcion)
        <div>
            <p class="text-xs font-medium text-gray-500 mb-1">Motivo de consulta</p>
            <p class="pl-3 border-l-2 border-gray-300 text-xs sm:text-sm text-gray-700 whitespace-pre-line break-words">{{ $caso->solicitud->descripcion }}</p>
        </div>
    @endif

    <!-- Citas -->
    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
        <div class="bg-white rounded-lg border border-gray-200 p-2">
            <dt class="text-[11px] text-gray-500">Citas realizadas</dt>
            <dd class="text-lg font-bold text-gray-900">{{ $realizadas->count() }}</dd>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-2">
            <dt class="text-[11px] text-gray-500">Asistió</dt>
            <dd class="text-lg font-bold text-emerald-700">{{ $asistidas }}</dd>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-2">
            <dt class="text-[11px] text-gray-500">No asistió</dt>
            <dd class="text-lg font-bold text-rose-600">{{ $inasistencias }}</dd>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-2">
            <dt class="text-[11px] text-gray-500">Tareas pendientes</dt>
            <dd class="text-lg font-bold text-amber-700">{{ $tareasPendientes->count() }}</dd>
        </div>
    </dl>

    <p class="text-xs text-gray-600">
        @if ($proxima)
            📅 Ya tiene una próxima cita el {{ $proxima->fecha_hora->format('Y-m-d') }} a las {{ $proxima->fecha_hora->format('g:i a') }}.
        @elseif ($realizadas->isNotEmpty())
            🔁 No tiene próxima cita agendada: asígnale la siguiente abajo.
        @else
            Aún no ha tenido citas en este caso.
        @endif
        @if ($caso->tareas->isNotEmpty())
            · Tareas terapéuticas: {{ $tareasCumplidas }} cumplida(s) de {{ $caso->tareas->count() }}.
        @endif
    </p>

    <!-- Historial clínico del caso -->
    <div>
        <p class="text-xs font-medium text-gray-500 mb-1">Historial clínico del caso</p>
        @if ($historial->isEmpty())
            <p class="text-xs text-gray-400 bg-white border border-gray-200 rounded-lg p-3">Sin sesiones registradas todavía.</p>
        @else
            <ol class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-56 overflow-y-auto">
                @foreach ($historial as $cita)
                    <li class="p-3 text-xs">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-gray-900">{{ $cita->fecha_hora->format('Y-m-d') }} · {{ $cita->fecha_hora->format('g:i a') }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $asistencia[$cita->estado_asistencia] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $cita->asistenciaPendiente() ? 'Asistencia sin registrar' : $cita->estado_asistencia }}
                            </span>
                        </div>
                        @if ($cita->descripcion)
                            <p class="mt-1 text-gray-600 whitespace-pre-line break-words">{{ $cita->descripcion }}</p>
                        @endif
                        @if ($cita->motivo_inasistencia)
                            <p class="mt-1 text-gray-500 break-words">Motivo de inasistencia: {{ $cita->motivo_inasistencia }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    @if ($tareasPendientes->isNotEmpty())
        <div>
            <p class="text-xs font-medium text-gray-500 mb-1">Tareas terapéuticas por evaluar</p>
            <ul class="list-disc ms-5 text-xs text-gray-700 space-y-0.5">
                @foreach ($tareasPendientes as $tarea)
                    <li class="break-words">{{ $tarea->titulo }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Otros casos del mismo paciente -->
    <div>
        <p class="text-xs font-medium text-gray-500 mb-1">Otros casos del paciente</p>
        @if ($otrosCasos->isEmpty())
            <p class="text-xs text-gray-400">Es el único caso registrado para este paciente.</p>
        @else
            <ul class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100 text-xs">
                @foreach ($otrosCasos as $otro)
                    <li class="p-3 flex flex-wrap gap-x-2 gap-y-0.5 text-gray-600">
                        <span class="font-semibold text-gray-900">{{ $otro->codigo_expediente }}</span>
                        <span>· {{ $otro->solicitud?->tipo_servicio }}</span>
                        <span>· {{ $otro->estado_caso }}</span>
                        <span>· Abierto el {{ $otro->fecha_apertura?->format('Y-m-d') }}</span>
                        <span>· {{ $otro->citas_count }} {{ $otro->citas_count === 1 ? 'cita' : 'citas' }}</span>
                        @if ($otro->profesional?->persona)
                            <span>· {{ $otro->profesional->persona->nombre_completo }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>

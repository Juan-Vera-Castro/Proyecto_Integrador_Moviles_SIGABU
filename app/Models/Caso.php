<?php

namespace App\Models;

use App\Models\Inclusion\Bitacora;
use App\Models\Inclusion\EstadoDocumento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Los casos no se insertan desde Laravel: los crea el trigger de
 * solicitudes_atencion, que también genera el codigo_expediente.
 */
class Caso extends Model
{
    public const ESTADO_ABIERTO = 'Abierto';

    public const ESTADO_CERRADO = 'Cerrado';

    /**
     * Filtro de los listados con los casos que el profesional remitió (HU-007). No es un estado:
     * quien remitió ve el estado real del caso con el profesional actual.
     */
    public const FILTRO_REMITIDOS = 'Remitidos por mí';

    /**
     * Estados por los que se filtran los listados de casos, en el orden en que se muestran.
     */
    public const ESTADOS_LISTADO = [self::ESTADO_ABIERTO, 'En Seguimiento', 'Pendiente Asignación', self::ESTADO_CERRADO, self::FILTRO_REMITIDOS];

    /**
     * Estado con el que el trigger crea el caso: aún no tiene profesional asignado.
     */
    public const ESTADO_PENDIENTE = 'Pendiente Asignación';

    /**
     * Estados de ck_casos_estado en los que el caso está en atención y admite citas.
     * "Pendiente Asignación" aún no tiene profesional y "Cerrado" ya terminó.
     */
    public const ESTADOS_ACTIVOS = [self::ESTADO_ABIERTO, 'En Seguimiento'];

    public const NIVELES_RIESGO = ['Bajo', 'Medio', 'Alto'];

    /**
     * Factores de riesgo que el profesional puede marcar en el expediente.
     */
    public const FACTORES_RIESGO = [
        'Ausencia o conflicto en red de apoyo',
        'Dificultades académicas o riesgo de deserción',
        'Antecedentes de ideación o comportamiento autolesivo',
        'Consumo problemático de sustancias',
        'Situación de vulnerabilidad socioeconómica',
        'Eventos estresantes o traumáticos recientes',
    ];

    /**
     * Factores de riesgo que, marcados, generan una alerta temprana (HU-026).
     */
    public const FACTORES_CRITICOS = [
        'Antecedentes de ideación o comportamiento autolesivo',
        'Consumo problemático de sustancias',
    ];

    public const FACTORES_PROTECTORES = [
        'Red de apoyo familiar o social sólida',
        'Buena motivación y compromiso académico',
        'Adherencia a procesos terapéuticos previos',
        'Habilidades de afrontamiento y resiliencia',
        'Participación en actividades extracurriculares o deportivas',
        'Acceso a servicios de salud y bienestar',
    ];

    /**
     * Servicios cuyos casos requieren consentimiento informado firmado (RQ-1, numeral 6.1).
     */
    public const SERVICIOS_CON_CONSENTIMIENTO = ['Salud Mental', 'Inclusión'];

    public const SERVICIO_SALUD_MENTAL = 'Salud Mental';

    protected $table = 'casos';

    protected $primaryKey = 'id_caso';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'estado_caso',
        'nivel_riesgo',
        'id_profesional',
        'factores_riesgo',
        'factores_protectores',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'factores_riesgo' => 'array',
            'factores_protectores' => 'array',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudAtencion::class, 'id_solicitud_origen', 'id_solicitud');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Citas de asesoría psicológica del caso (HU-011), en orden cronológico.
     */
    public function citas(): HasMany
    {
        return $this->hasMany(Actuacion::class, 'id_caso', 'id_caso')
            ->where('tipo_actuacion', Actuacion::TIPO_CITA)
            ->orderBy('fecha_hora');
    }

    /**
     * Tareas terapéuticas del caso (HU-013), la más reciente primero.
     */
    public function tareas(): HasMany
    {
        return $this->hasMany(TareaTerapeutica::class, 'id_caso', 'id_caso')
            ->orderByDesc('fecha_asignacion');
    }

    /**
     * Consentimientos informados aceptados en el caso (HU-009).
     */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class, 'id_caso', 'id_caso')
            ->orderBy('fecha_hora_aceptacion');
    }

    /**
     * Atenciones registradas en el expediente, la más reciente primero.
     */
    public function atenciones(): HasMany
    {
        return $this->hasMany(Atencion::class, 'id_caso', 'id_caso')
            ->orderByDesc('fecha_atencion');
    }

    /**
     * Remisiones del caso (HU-007), la más reciente primero.
     */
    public function remisiones(): HasMany
    {
        return $this->hasMany(Actuacion::class, 'id_caso', 'id_caso')
            ->where('tipo_actuacion', Actuacion::TIPO_REMISION)
            ->orderByDesc('fecha_hora');
    }

    /**
     * Ajustes razonables del caso de Inclusión, el más reciente primero (HU-018).
     */
    public function ajustes(): HasMany
    {
        return $this->hasMany(AjusteRazonable::class, 'id_caso', 'id_caso')->orderByDesc('fecha_registro');
    }

    /**
     * Bitácora de orientación y acompañamiento del caso de Inclusión.
     */
    public function bitacora(): HasOne
    {
        return $this->hasOne(Bitacora::class, 'id_caso', 'id_caso');
    }

    /**
     * Estado del consentimiento en el expediente, o null si no hay ninguna decisión.
     */
    public function estadoConsentimiento(): ?string
    {
        $ultima = $this->aceptacionesConsentimiento->first();

        return match (true) {
            $ultima === null => null,
            ! $ultima->esAceptado() => $ultima->etiquetaDecision(),
            $ultima->faltaFirmaProfesional() => EstadoDocumento::COMPLETADO,
            default => EstadoDocumento::FIRMADO,
        };
    }

    public function esDeInclusion(): bool
    {
        return $this->solicitud?->tipo_servicio === 'Inclusión';
    }

    /**
     * Horarios que el psicólogo propuso y el estudiante aún puede elegir.
     */
    public function propuestasVigentes(): HasMany
    {
        return $this->hasMany(PropuestaCita::class, 'id_caso', 'id_caso')
            ->where('estado', PropuestaCita::PROPUESTA)
            ->where('fecha_hora', '>', now(Actuacion::ZONA_HORARIA)->toDateTimeString())
            ->orderBy('fecha_hora');
    }

    /**
     * Desistimientos registrados en el caso, el más reciente primero.
     */
    public function desistimientos(): HasMany
    {
        return $this->hasMany(Desistimiento::class, 'id_caso', 'id_caso')->orderByDesc('fecha_hora');
    }

    /**
     * Notas de evolución de las citas asistidas (HU-015), en orden cronológico.
     */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoEvolucion::class, 'id_caso', 'id_caso')->orderBy('fecha_hora');
    }

    /**
     * Historia clínica psicológica del expediente: una por caso de Salud Mental.
     */
    public function historiaClinica(): HasOne
    {
        return $this->hasOne(HistoriaClinica::class, 'id_caso', 'id_caso');
    }

    /**
     * Decisiones sobre el consentimiento informado con firma, la más reciente primero.
     * Las casillas de consentimientos() son anteriores y no cuentan como vigentes (D-05).
     */
    public function aceptacionesConsentimiento(): HasMany
    {
        return $this->hasMany(ConsentimientoAceptacion::class, 'id_caso', 'id_caso')
            ->orderByDesc('fecha_hora');
    }

    public function estaActivo(): bool
    {
        return in_array($this->estado_caso, self::ESTADOS_ACTIVOS, true);
    }

    /**
     * Salud Mental e Inclusión exigen consentimiento firmado antes de atender (RQ-1).
     */
    public function requiereConsentimiento(): bool
    {
        return in_array($this->solicitud?->tipo_servicio, self::SERVICIOS_CON_CONSENTIMIENTO, true);
    }

    /**
     * La última decisión del caso, si es una aceptación (RN-02). Una versión retirada
     * después sigue valiendo para el caso (D-09); un rechazo posterior la anula.
     */
    public function consentimientoVigente(): ?ConsentimientoAceptacion
    {
        $ultima = $this->aceptacionesConsentimiento->first();

        return $ultima?->esAceptado() ? $ultima : null;
    }

    /**
     * Si hoy se exige la firma para atender este caso (D-28, D-29): el interruptor está
     * encendido, el caso no está eximido por fecha y el sistema permite firmarlo.
     */
    public function exigeConsentimiento(): bool
    {
        return $this->requiereConsentimiento()
            && config('sigabu.consentimiento.exigir')
            && ! $this->eximidoPorFecha()
            && $this->faltantesParaFirmar() === [];
    }

    /**
     * Los casos abiertos antes de la fecha desde la que se exige la firma quedan eximidos (D-29).
     */
    public function eximidoPorFecha(): bool
    {
        $desde = config('sigabu.consentimiento.desde');

        return filled($desde) && $this->fecha_apertura?->lt(Carbon::parse($desde)->startOfDay());
    }

    /**
     * Lo que impide tomar la firma en este caso. Si falta algo, el caso no se bloquea:
     * nunca queda trabado sin forma de firmar (D-28).
     *
     * @return list<string>
     */
    public function faltantesParaFirmar(): array
    {
        $servicio = (string) $this->solicitud?->tipo_servicio;
        $menor = (bool) $this->solicitud?->persona?->esMenorEn(now(Actuacion::ZONA_HORARIA));
        $poblacion = $menor ? ModeloConsentimiento::POBLACION_MENORES : ModeloConsentimiento::POBLACION_GENERAL;

        return array_values(array_filter([
            ModeloConsentimiento::versionVigenteDe($servicio, $poblacion) ? null
                : "el texto de consentimiento publicado para {$servicio}".($menor ? ' (menores de edad)' : ''),
            ConsentimientoAceptacion::almacenamientoListo() ? null : 'las claves del almacenamiento de firmas',
        ]));
    }

    /**
     * Si se puede atender al estudiante: registrar "Asistió", asignar tareas o remitir (D-21).
     * Un rechazo registrado bloquea siempre; la falta de firma, solo si hoy se exige.
     */
    public function admiteAtencion(): bool
    {
        if (! $this->requiereConsentimiento()) {
            return true;
        }

        $ultima = $this->aceptacionesConsentimiento->first();

        if ($ultima && ! $ultima->esAceptado()) {
            return false;
        }

        return $ultima !== null || ! $this->exigeConsentimiento();
    }

    /**
     * Por qué no se puede atender al estudiante (RN-01), o null si se puede.
     */
    public function motivoSinAtencion(): ?string
    {
        if ($this->admiteAtencion()) {
            return null;
        }

        $ultima = $this->aceptacionesConsentimiento->first();

        if ($ultima?->esRevocacion()) {
            return "La persona revocó el consentimiento informado el {$ultima->fecha_hora->format('Y-m-d H:i')}: no se puede prestar la atención.";
        }

        return $ultima
            ? 'El '.($ultima->loOtorgaAcudiente() ? 'acudiente' : 'estudiante')." no aceptó el consentimiento informado el {$ultima->fecha_hora->format('Y-m-d H:i')}: no se puede prestar la atención."
            : 'Registra el consentimiento informado firmado antes de atender al estudiante.';
    }

    /**
     * Por qué la bitácora no admite registros nuevos (RN-14, RN-21), o null si los admite.
     */
    public function motivoSinRegistroInclusion(): ?string
    {
        return $this->estaActivo()
            ? $this->motivoSinAtencion()
            : "El caso {$this->codigo_expediente} está cerrado: no admite registros nuevos.";
    }

    /**
     * Deja sin efecto lo pendiente al cerrar: las citas próximas se cancelan, las pasadas sin
     * registro quedan como "No asistió" y las tareas pendientes se cancelan.
     */
    public function cancelarPendientes(string $ahora, string $motivoInasistencia): void
    {
        $pendientes = Actuacion::citas()->where('id_caso', $this->id_caso)->where('estado_asistencia', Actuacion::ASISTENCIA_PENDIENTE);

        (clone $pendientes)->where('fecha_hora', '>', $ahora)->update(['estado_asistencia' => Actuacion::CANCELADA]);
        (clone $pendientes)->where('fecha_hora', '<=', $ahora)->update(['estado_asistencia' => Actuacion::NO_ASISTIO, 'motivo_inasistencia' => $motivoInasistencia]);
        TareaTerapeutica::where('id_caso', $this->id_caso)->where('estado', TareaTerapeutica::ESTADO_PENDIENTE)->update(['estado' => TareaTerapeutica::ESTADO_CANCELADA]);
    }

    public function esDeSaludMental(): bool
    {
        return $this->solicitud?->tipo_servicio === self::SERVICIO_SALUD_MENTAL;
    }

    /**
     * Por qué no se puede registrar ni actualizar la historia clínica, o null si se puede.
     * A diferencia de admiteAtencion(), exige siempre un consentimiento aceptado: no basta
     * con que hoy no se exija la firma.
     */
    public function motivoSinHistoriaClinica(): ?string
    {
        if (! $this->esDeSaludMental()) {
            return 'La historia clínica psicológica solo se registra en casos de Salud Mental.';
        }

        if (! $this->estaActivo()) {
            return "La historia clínica solo se registra en casos activos. El caso {$this->codigo_expediente} está en estado \"{$this->estado_caso}\".";
        }

        if ($this->consentimientoVigente()) {
            return null;
        }

        $ultima = $this->aceptacionesConsentimiento->first();

        return $ultima
            ? 'El '.($ultima->loOtorgaAcudiente() ? 'acudiente' : 'estudiante')." no aceptó el consentimiento informado el {$ultima->fecha_hora->format('Y-m-d H:i')}: no se puede registrar la historia clínica."
            : 'Falta el consentimiento informado aceptado: el estudiante debe firmarlo desde su cuenta antes de registrar la historia clínica.';
    }

    /**
     * Si $usuario es el estudiante que pidió la atención: él firma su consentimiento.
     */
    public function esDelEstudiante(User $usuario): bool
    {
        $idUsuario = $this->solicitud?->persona?->id_usuario;

        return $idUsuario !== null && $idUsuario === $usuario->id_usuario;
    }

    /**
     * Quién puede ver la firma y la evidencia del consentimiento (CA-04.2): el profesional
     * asignado, si el caso es de su área, y el administrador. Ni quien remitió el caso ni el
     * estudiante (D-13).
     */
    public function puedeConsultarEvidencia(User $usuario): bool
    {
        return $usuario->role === 'admin'
            || ($this->id_profesional === $usuario->id_usuario && $usuario->atiendeServicio($this->solicitud?->tipo_servicio));
    }

    /**
     * Historia clínica tal como estaba cuando se hizo $remision: sin las anotaciones posteriores.
     * Es lo que conserva quien remitió el caso a otro profesional. Null si la historia se
     * registró después de la remisión. Requiere historiaClinica.anotaciones cargada.
     */
    public function historiaClinicaAlRemitir(Actuacion $remision): ?HistoriaClinica
    {
        $historia = $this->historiaClinica;

        if (! $historia || $historia->fecha_registro?->gt($remision->fecha_hora)) {
            return null;
        }

        return $historia->setRelation('anotaciones', $historia->anotaciones
            ->filter(fn (HistoriaClinicaAnotacion $anotacion) => $anotacion->fecha_hora?->lte($remision->fecha_hora))
            ->values());
    }

    /**
     * Remisión a una entidad externa con la que se cerró el caso, si fue la última.
     */
    public function remisionExternaDeCierre(): ?Actuacion
    {
        $ultima = $this->remisiones->first();

        return $ultima && ! $ultima->esRemisionInterna() && ! $this->estaActivo() ? $ultima : null;
    }

    /**
     * Última cita a la que asistió el estudiante: la "atención" del formato de remisión (D-10).
     */
    public function ultimaCitaAsistida(): ?Actuacion
    {
        return $this->citas
            ->where('estado_asistencia', Actuacion::ASISTIO)
            ->reject(fn (Actuacion $cita) => $cita->esProxima())
            ->last();
    }

    /**
     * Remisión con la que $profesional dejó el caso: a otro profesional (ya no es el asignado)
     * o a una entidad externa (el caso quedó cerrado).
     */
    public function remisionHechaPor(User $profesional): ?Actuacion
    {
        if ($this->id_profesional === $profesional->id_usuario && $this->estaActivo()) {
            return null;
        }

        return $this->remisiones->firstWhere('id_profesional', $profesional->id_usuario);
    }

    /**
     * Citas a las que el estudiante asistió.
     */
    public function sesionesRealizadas(): int
    {
        return $this->citas->where('estado_asistencia', Actuacion::ASISTIO)->count();
    }

    /**
     * Siguiente cita pendiente del caso, si la hay.
     */
    public function proximaCita(): ?Actuacion
    {
        return $this->citas->first(fn (Actuacion $cita) => $cita->asistenciaPendiente() && $cita->esProxima());
    }

    /**
     * Última cita que ya se realizó (su hora pasó y no fue cancelada), haya asistido o no.
     */
    public function ultimaCitaRealizada(): ?Actuacion
    {
        return $this->citas
            ->reject(fn (Actuacion $cita) => $cita->estaCancelada() || $cita->esProxima())
            ->last();
    }

    /**
     * El caso sigue activo, ya tuvo al menos una cita y no tiene la siguiente agendada:
     * el profesional debe asignarle una nueva fecha y hora.
     */
    public function necesitaNuevaCita(): bool
    {
        return $this->estaActivo()
            && $this->ultimaCitaRealizada() !== null
            && ! $this->citas->contains(fn (Actuacion $cita) => ! $cita->estaCancelada() && $cita->esProxima());
    }

    /**
     * Profesionales que atienden el servicio del caso en la facultad del estudiante, en
     * cualquier jornada: a ellos se traslada el caso sin más trámite. Null si la solicitud no
     * registró facultad (solicitudes anteriores): entonces no hay restricción por facultad.
     *
     * @return list<string>|null
     */
    public function idsProfesionalesMismaFacultad(): ?array
    {
        $solicitud = $this->solicitud;

        if (! $solicitud?->id_facultad) {
            return null;
        }

        return AsignacionProfesional::profesionalesDe($solicitud->tipo_servicio, $solicitud->id_facultad)
            ->pluck('id_usuario')
            ->all();
    }

    /**
     * Si trasladar el caso a $idProfesional es una excepción: otro profesional fuera de la
     * facultad del estudiante, que exige indicar quién lo autorizó.
     */
    public function trasladoEsExcepcion(string $idProfesional): bool
    {
        $mismaFacultad = $this->idsProfesionalesMismaFacultad();

        return $mismaFacultad !== null && ! in_array($idProfesional, $mismaFacultad, true);
    }

    /**
     * Remisión interna con la que el profesional asignado recibió el caso.
     */
    public function remisionRecibida(): ?Actuacion
    {
        return $this->remisiones->first(
            fn (Actuacion $remision) => $remision->esRemisionInterna() && $remision->id_profesional_destino === $this->id_profesional
        );
    }

    /**
     * Estado del caso en palabras del estudiante, para su panel.
     */
    public function etiquetaParaEstudiante(): string
    {
        return match (true) {
            // Sin día y hora agendados la cita está pendiente, tenga o no profesional todavía.
            $this->estado_caso === self::ESTADO_PENDIENTE => self::CITA_PENDIENTE,
            (bool) $this->solicitud?->fueRechazada() => 'Rechazada',
            $this->estaActivo() && ! $this->proximaCita() && $this->propuestasVigentes->isNotEmpty() => self::ELIGE_HORARIO,
            $this->estaActivo() && ! $this->proximaCita() => self::CITA_PENDIENTE,
            $this->estaActivo() => 'En atención',
            default => 'Finalizado',
        };
    }

    public const CITA_PENDIENTE = 'Cita pendiente';

    /**
     * El psicólogo propuso horarios y el estudiante debe elegir uno.
     */
    public const ELIGE_HORARIO = 'Elige tu horario';

    /**
     * Solicitudes que llegaron sin profesional, porque nadie atiende ese servicio en esa
     * facultad y jornada: cualquier profesional de esa área puede asignárselas.
     */
    public function scopeSinAsignar(Builder $query): Builder
    {
        return $query->where('estado_caso', self::ESTADO_PENDIENTE)->whereNull('id_profesional');
    }

    /**
     * Casos de los servicios que cubre el área de $profesional.
     */
    public function scopeDeAreaDe(Builder $query, User $profesional): Builder
    {
        return $query->whereHas('solicitud', fn (Builder $solicitud) => $solicitud->whereIn('tipo_servicio', $profesional->serviciosQueAtiende()));
    }

    /**
     * Casos activos de $profesional que todavía no tienen ninguna cita: los que le acaban
     * de llegar y en los que debe acordar con el estudiante la primera.
     */
    public function scopeNuevosDe(Builder $query, User $profesional): Builder
    {
        return $query->activos()
            ->deAreaDe($profesional)
            ->where('id_profesional', $profesional->id_usuario)
            ->whereDoesntHave('citas');
    }

    /**
     * Lo que el profesional tiene por atender en "Solicitudes": casos nuevos sin primera
     * cita y solicitudes sin profesional asignado.
     */
    public static function porAtenderDe(User $profesional): int
    {
        return static::nuevosDe($profesional)->count() + static::sinAsignar()->deAreaDe($profesional)->count();
    }

    /**
     * Casos cuya solicitud el estudiante marcó como emergencia (atención urgente).
     */
    public function scopeEmergencias(Builder $query): Builder
    {
        return $query->whereHas('solicitud', fn (Builder $solicitud) => $solicitud->whereRaw('solicitudes_atencion.es_emergencia'));
    }

    public function esEmergencia(): bool
    {
        return (bool) $this->solicitud?->es_emergencia;
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereIn('estado_caso', self::ESTADOS_ACTIVOS);
    }

    /**
     * Casos activos de $profesional cuya última cita ya pasó y que no tienen otra
     * agendada: los pacientes a los que hay que asignar una nueva fecha y hora.
     */
    public function scopePorReagendarDe(Builder $query, User $profesional): Builder
    {
        $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();
        $citasVigentes = fn (Builder $citas) => $citas->where('estado_asistencia', '<>', Actuacion::CANCELADA);

        return $query->activos()
            ->deAreaDe($profesional)
            ->where('id_profesional', $profesional->id_usuario)
            ->whereHas('citas', fn (Builder $citas) => $citasVigentes($citas)->where('fecha_hora', '<=', $ahora))
            ->whereDoesntHave('citas', fn (Builder $citas) => $citasVigentes($citas)->where('fecha_hora', '>', $ahora));
    }

    /**
     * Casos que el administrador le trasladó a $profesional y aún no tienen próxima cita: sus
     * citas se cancelaron con el traslado, así que no salen en los casos nuevos ni por reagendar.
     */
    public function scopeTrasladadosSinCitaDe(Builder $query, User $profesional): Builder
    {
        $ahora = now(Actuacion::ZONA_HORARIA)->toDateTimeString();

        return $query->activos()
            ->deAreaDe($profesional)
            ->where('id_profesional', $profesional->id_usuario)
            ->whereHas('remisiones', fn (Builder $remision) => $remision->whereNotNull('id_admin_traslado')->where('id_profesional_destino', $profesional->id_usuario))
            ->whereDoesntHave('citas', fn (Builder $citas) => $citas->where('estado_asistencia', '<>', Actuacion::CANCELADA)->where('fecha_hora', '>', $ahora));
    }

    /**
     * Casos de su área que $profesional tiene asignados o que él remitió (estos, en solo lectura).
     */
    public function scopeDelProfesional(Builder $query, User $profesional): Builder
    {
        return $query->deAreaDe($profesional)->where(fn (Builder $casos) => $casos
            ->where('id_profesional', $profesional->id_usuario)
            ->orWhereHas('remisiones', fn (Builder $remision) => $remision->where('id_profesional', $profesional->id_usuario))
        );
    }

    /**
     * Alertas tempranas del caso (HU-026), la más reciente primero.
     */
    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaTemprana::class, 'id_caso', 'id_caso')->orderByDesc('fecha_generacion');
    }

    public function tieneAlertaPendiente(): bool
    {
        return $this->alertas()->pendientes()->exists();
    }
}

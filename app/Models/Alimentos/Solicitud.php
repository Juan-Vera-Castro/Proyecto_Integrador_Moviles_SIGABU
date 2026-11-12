<?php

namespace App\Models\Alimentos;

use App\Casts\Booleano;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Solicitud de apoyo alimentario de un estudiante. Su prioridad se calcula al registrarla
 * con la situación, el estrato y las entregas que ya recibió, para repartir con equidad.
 */
class Solicitud extends Model
{
    use HasUuids;

    public const EMERGENCIA = 'Emergencia económica';

    public const CABEZA_FAMILIA = 'Cabeza de familia';

    public const ESTRATO_BAJO = 'Estrato 1 o 2 sin apoyo familiar';

    public const DESEMPLEO = 'Desempleo o reducción de ingresos';

    public const SITUACIONES = [self::EMERGENCIA, self::CABEZA_FAMILIA, self::ESTRATO_BAJO, self::DESEMPLEO];

    public const PENDIENTE = 'Pendiente';

    public const APROBADA = 'Aprobada';

    public const ENTREGADA = 'Entregada';

    public const RECHAZADA = 'Rechazada';

    public const ESTADOS = [self::PENDIENTE, self::APROBADA, self::ENTREGADA, self::RECHAZADA];

    public const PRIORIDADES = ['Alta', 'Media', 'Baja'];

    /**
     * Entregas recibidas en los últimos días que bajan la prioridad de una nueva solicitud.
     */
    public const DIAS_ENTREGAS_RECIENTES = 90;

    public const DETALLE_MAX = 1000;

    public const MOTIVO_RECHAZO_MAX = 300;

    /**
     * Prefijo del texto del QR del pase de retiro: "ALIM:" seguido del id de la solicitud.
     */
    public const PREFIJO_PASE = 'ALIM:';

    protected $table = 'alim_solicitudes';

    protected $primaryKey = 'id_solicitud';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_persona',
        'situacion',
        'estrato',
        'personas_a_cargo',
        'detalle',
        'acepta_datos',
        'prioridad',
        'estado',
        'motivo_rechazo',
        'revisada_por',
        'fecha_revision',
        'fecha_solicitud',
    ];

    protected $attributes = [
        'estado' => self::PENDIENTE,
    ];

    protected function casts(): array
    {
        return [
            'estrato' => 'integer',
            'personas_a_cargo' => 'integer',
            'acepta_datos' => Booleano::class,
            'fecha_revision' => 'datetime',
            'fecha_solicitud' => 'datetime',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por', 'id_usuario');
    }

    public function entrega(): HasOne
    {
        return $this->hasOne(Entrega::class, 'id_solicitud', 'id_solicitud');
    }

    /**
     * Texto del QR del pase de retiro. Solo identifica la solicitud: en la ventanilla se sigue
     * verificando el documento del estudiante antes de entregar.
     */
    public function codigoPase(): string
    {
        return self::PREFIJO_PASE.$this->id_solicitud;
    }

    /**
     * Id de la solicitud a partir de lo que lee el escáner (el QR del pase) o se escribe.
     */
    public static function idDesdePase(string $leido): string
    {
        $leido = trim($leido);

        return str_starts_with(strtoupper($leido), self::PREFIJO_PASE) ? substr($leido, strlen(self::PREFIJO_PASE)) : $leido;
    }

    /**
     * Pendientes o aprobadas: el estudiante tiene a lo sumo una a la vez.
     */
    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereIn('estado', [self::PENDIENTE, self::APROBADA]);
    }

    /**
     * Las de mayor prioridad primero; entre iguales, la más antigua.
     */
    public function scopePorPrioridad(Builder $query): Builder
    {
        return $query->orderByRaw("CASE prioridad WHEN 'Alta' THEN 1 WHEN 'Media' THEN 2 ELSE 3 END")->orderBy('fecha_solicitud');
    }

    /**
     * Prioridad: alta en emergencia económica o si es cabeza de familia de estrato 1 o 2;
     * media si es cabeza de familia, de estrato 1 o 2 o perdió ingresos; baja en otro caso.
     * Si ya recibió dos o más mercados en los últimos 90 días, baja un nivel.
     */
    public static function calcularPrioridad(string $situacion, ?int $estrato, int $entregasRecientes): string
    {
        $estratoBajo = $estrato !== null && $estrato <= 2;

        $nivel = match (true) {
            $situacion === self::EMERGENCIA, $situacion === self::CABEZA_FAMILIA && $estratoBajo => 0,
            in_array($situacion, [self::CABEZA_FAMILIA, self::ESTRATO_BAJO, self::DESEMPLEO], true) || $estratoBajo => 1,
            default => 2,
        };

        if ($entregasRecientes >= 2) {
            $nivel = min($nivel + 1, 2);
        }

        return self::PRIORIDADES[$nivel];
    }
}

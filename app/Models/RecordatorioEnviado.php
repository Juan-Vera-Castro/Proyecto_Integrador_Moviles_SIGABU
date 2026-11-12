<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Recordatorio automático que ya se envió. La restricción única (tipo, referencia) hace que cada
 * recordatorio salga una sola vez, aunque el programador de tareas se ejecute dos veces.
 */
class RecordatorioEnviado extends Model
{
    public const CONSENTIMIENTO_ESTUDIANTE = 'consentimiento_estudiante';

    public const CONSENTIMIENTO_PROFESIONAL = 'consentimiento_profesional';

    public const TAREA_VENCIDA = 'tarea_vencida';

    protected $table = 'recordatorios_enviados';

    protected $primaryKey = 'id_recordatorio';

    public $timestamps = false;

    protected $fillable = ['tipo', 'referencia', 'enviado_en'];

    protected function casts(): array
    {
        return [
            'enviado_en' => 'datetime',
        ];
    }

    /**
     * Tipo del recordatorio de cita según su anticipación, p. ej. "cita_24h".
     */
    public static function tipoCita(int $horas): string
    {
        return "cita_{$horas}h";
    }

    /**
     * Reserva el envío de un recordatorio. Devuelve false si ya se había reservado: así dos
     * ejecuciones simultáneas no envían el mismo aviso dos veces.
     */
    public static function reservar(string $tipo, string $referencia): bool
    {
        return static::query()->insertOrIgnore([
            'tipo' => $tipo,
            'referencia' => $referencia,
            'enviado_en' => now(),
        ]) === 1;
    }
}

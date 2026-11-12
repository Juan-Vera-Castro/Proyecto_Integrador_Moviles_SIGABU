<?php

namespace App\Models\Alimentos;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Model;

/**
 * Estudiante priorizado por Trabajo Social para el apoyo alimentario, cargado por archivo.
 * No es una solicitud: el estudiante debe solicitar y autorizar sus datos. Cuando lo hace (o si
 * ya tiene una solicitud pendiente), su prioridad sube a la que indicó Trabajo Social.
 */
class Priorizado extends Model
{
    public const COLUMNAS = ['documento', 'codigo', 'estrato', 'prioridad'];

    public const MAX_FILAS = 2000;

    protected $table = 'alim_priorizados';

    protected $primaryKey = 'id_priorizado';

    public $timestamps = false;

    protected $fillable = ['documento', 'codigo', 'estrato', 'prioridad', 'cargado_por', 'fecha_cargue'];

    protected function casts(): array
    {
        return [
            'estrato' => 'integer',
            'fecha_cargue' => 'datetime',
        ];
    }

    /**
     * El registro de Trabajo Social de una persona, por su documento.
     */
    public static function de(?Persona $persona): ?self
    {
        $documento = self::normalizarDocumento($persona?->numero_documento);

        return $documento ? self::where('documento', $documento)->first() : null;
    }

    public static function normalizarDocumento(?string $documento): ?string
    {
        return preg_replace('/\D/', '', (string) $documento) ?: null;
    }

    /**
     * La más alta entre dos prioridades (Alta > Media > Baja).
     */
    public static function mayor(string $una, string $otra): string
    {
        $orden = array_flip(Solicitud::PRIORIDADES);

        return $orden[$una] <= $orden[$otra] ? $una : $otra;
    }
}

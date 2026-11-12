<?php

namespace App\Models\Inclusion;

use App\Casts\Booleano;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Borrador de un formulario: uno activo por usuario, caso y documento. No guarda firmas. Al
 * enviar el formulario se marca inactivo, porque sigabu_app no borra filas.
 */
class Borrador extends Model
{
    use HasUuids;

    public const CONSENTIMIENTO = 'consentimiento';

    public const BITACORA = 'bitacora';

    public const PLANTILLA = 'plantilla';

    public const DOCUMENTOS = [self::CONSENTIMIENTO, self::BITACORA, self::PLANTILLA];

    protected $table = 'inclusion_borradores';

    protected $primaryKey = 'id_borrador';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_usuario',
        'documento',
        'datos',
        'activo',
        'fecha_actualizacion',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'activo' => Booleano::class,
            'fecha_actualizacion' => 'datetime',
        ];
    }

    /**
     * Borrador activo de $usuario para ese documento del caso, o null.
     */
    public static function de(Caso $caso, User $usuario, string $documento): ?self
    {
        return static::activos()
            ->where('id_caso', $caso->id_caso)
            ->where('id_usuario', $usuario->id_usuario)
            ->where('documento', $documento)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function guardar(Caso $caso, User $usuario, string $documento, array $datos): self
    {
        $borrador = static::de($caso, $usuario, $documento) ?? new static([
            'id_caso' => $caso->id_caso,
            'id_usuario' => $usuario->id_usuario,
            'documento' => $documento,
            'activo' => true,
        ]);

        $borrador->fill(['datos' => $datos, 'fecha_actualizacion' => now(Actuacion::ZONA_HORARIA)->toDateTimeString()])->save();

        return $borrador;
    }

    /**
     * Al enviar el formulario el borrador deja de mostrarse (CA-10.5).
     */
    public static function terminar(Caso $caso, User $usuario, string $documento): void
    {
        static::de($caso, $usuario, $documento)?->update(['activo' => false]);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', Booleano::valor(true, $query->getConnection()->getDriverName()));
    }
}

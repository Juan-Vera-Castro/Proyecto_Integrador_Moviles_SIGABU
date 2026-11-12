<?php

namespace App\Models;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo de consentimiento de un proceso (HU-009). Su texto vive en versiones:
 * solo una está publicada (vigente) y es la que se acepta al abrir un caso.
 */
class ModeloConsentimiento extends Model
{
    /**
     * Modelo al que la migración de HU-009 pasó los consentimientos anteriores.
     * Es histórico: no admite versiones nuevas ni se puede activar.
     */
    public const CODIGO_LEGADO = 'LEGADO';

    public const SERVICIOS = ['Salud Mental', 'Inclusión', 'VBG', 'Apoyo Alimentario'];

    /**
     * Población a la que aplica el modelo (ck_modelos_poblacion): el de menores de edad
     * lo firma el acudiente (formato GBU-GPGR-F-019).
     */
    public const POBLACION_GENERAL = 'General';

    public const POBLACION_MENORES = 'Menores';

    public const POBLACIONES = [self::POBLACION_GENERAL, self::POBLACION_MENORES];

    protected $table = 'modelos_consentimiento';

    protected $primaryKey = 'id_modelo';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo_servicio',
        'descripcion',
        'activo',
        'poblacion',
    ];

    protected $attributes = [
        'poblacion' => self::POBLACION_GENERAL,
    ];

    protected function casts(): array
    {
        return [
            'activo' => Booleano::class,
        ];
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(VersionConsentimiento::class, 'id_modelo', 'id_modelo')
            ->orderByDesc('numero_version');
    }

    public function versionVigente(): HasOne
    {
        return $this->hasOne(VersionConsentimiento::class, 'id_modelo', 'id_modelo')
            ->where('estado', VersionConsentimiento::PUBLICADA);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', Booleano::valor(true, $query->getConnection()->getDriverName()));
    }

    public function esLegado(): bool
    {
        return $this->codigo === self::CODIGO_LEGADO;
    }

    public function esDeMenores(): bool
    {
        return $this->poblacion === self::POBLACION_MENORES;
    }

    /**
     * Versión publicada del modelo activo de un servicio para una población, o null si
     * todavía no hay consentimiento parametrizado. Sin población, la general.
     */
    public static function versionVigenteDe(string $servicio, string $poblacion = self::POBLACION_GENERAL): ?VersionConsentimiento
    {
        return VersionConsentimiento::query()
            ->where('estado', VersionConsentimiento::PUBLICADA)
            ->whereHas('modelo', fn (Builder $q) => $q->activos()->where('tipo_servicio', $servicio)->where('poblacion', $poblacion))
            ->with('modelo')
            ->first();
    }
}

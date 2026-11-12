<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Versión del texto de un modelo de consentimiento (HU-009).
 * borrador → publicada → retirada. Solo el borrador se puede editar; en la base,
 * trg_proteger_version_publicada impide cambiar el texto de una versión ya publicada.
 */
class VersionConsentimiento extends Model
{
    public const BORRADOR = 'borrador';

    public const PUBLICADA = 'publicada';

    public const RETIRADA = 'retirada';

    public const TEXTO_MAX = 10000;

    protected $table = 'versiones_consentimiento';

    protected $primaryKey = 'id_version';

    public $timestamps = false;

    protected $fillable = [
        'id_modelo',
        'numero_version',
        'texto',
        'estado',
        'fecha_publicacion',
        'creado_por',
    ];

    protected $attributes = [
        'estado' => self::BORRADOR,
    ];

    protected function casts(): array
    {
        return [
            'numero_version' => 'integer',
            'fecha_publicacion' => 'datetime',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloConsentimiento::class, 'id_modelo', 'id_modelo');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'id_usuario');
    }

    /**
     * Decisiones firmadas con esta versión: cada una conserva su versión aunque se publique otra.
     */
    public function aceptaciones(): HasMany
    {
        return $this->hasMany(ConsentimientoAceptacion::class, 'id_version', 'id_version');
    }

    /**
     * Casillas de aceptación anteriores a la firma táctil que quedaron ligadas a esta versión.
     */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class, 'id_version', 'id_version');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }

    /**
     * Identificador legible que se guarda en consentimientos.version_formato, p. ej. "SM-CONSENT-v2".
     * Es el mismo formato que arma el trigger completar_version_formato().
     */
    public function etiqueta(): string
    {
        return $this->modelo->codigo.'-v'.$this->numero_version;
    }

    /**
     * Publica este borrador y retira la versión vigente del modelo en un solo paso,
     * igual que publicar_version_consentimiento() en la base.
     */
    public function publicar(): void
    {
        DB::transaction(function () {
            $version = static::whereKey($this->id_version)->lockForUpdate()->firstOrFail();

            if (! $version->esBorrador()) {
                throw new \DomainException("La versión {$version->numero_version} ya no está en borrador.");
            }

            static::where('id_modelo', $version->id_modelo)
                ->where('estado', self::PUBLICADA)
                ->update(['estado' => self::RETIRADA]);

            $version->update(['estado' => self::PUBLICADA, 'fecha_publicacion' => now()]);
        });

        $this->refresh();
    }
}

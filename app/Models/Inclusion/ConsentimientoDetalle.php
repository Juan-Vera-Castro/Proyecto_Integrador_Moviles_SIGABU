<?php

namespace App\Models\Inclusion;

use App\Casts\Booleano;
use App\Documentos\ConsentimientoInclusion;
use App\Models\Concerns\EsInmutable;
use App\Models\ConsentimientoAceptacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo propio del formato de Inclusión sobre una decisión de consentimiento: las 4 casillas, la
 * revocación y la firma en papel. Como la decisión a la que pertenece, solo se inserta.
 */
class ConsentimientoDetalle extends Model
{
    use EsInmutable;

    public const LEYENDA_PAPEL = 'Firmado en papel: la persona no pudo firmar por la plataforma';

    public const MOTIVO_PAPEL_MAX = 1000;

    protected $table = 'inclusion_consentimientos';

    protected $primaryKey = 'id_aceptacion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_aceptacion',
        'decision_acompanamiento',
        'decision_datos_generales',
        'decision_datos_sensibles',
        'decision_compartir',
        'nombre_persona',
        'documento_persona',
        'numero_contacto',
        'vinculacion_detalle',
        'es_revocacion',
        'motivo_papel',
        'fecha_firma_papel',
    ];

    protected function casts(): array
    {
        return [
            'decision_acompanamiento' => Booleano::class,
            'decision_datos_generales' => Booleano::class,
            'decision_datos_sensibles' => Booleano::class,
            'decision_compartir' => Booleano::class,
            'es_revocacion' => Booleano::class,
            'fecha_firma_papel' => 'date',
        ];
    }

    public function aceptacion(): BelongsTo
    {
        return $this->belongsTo(ConsentimientoAceptacion::class, 'id_aceptacion', 'id_aceptacion');
    }

    /**
     * Las 4 decisiones: texto de la casilla => si se marcó.
     *
     * @return array<string, bool>
     */
    public function decisiones(): array
    {
        return collect(ConsentimientoInclusion::DECISIONES)
            ->mapWithKeys(fn (string $texto, string $campo) => [$texto => (bool) $this->{$campo}])
            ->all();
    }
}

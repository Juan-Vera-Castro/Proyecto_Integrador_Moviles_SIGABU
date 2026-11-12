<?php

namespace App\Models;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aceptación del consentimiento informado de un caso, ligada a la versión exacta
 * del texto que se aceptó (HU-009).
 */
class Consentimiento extends Model
{
    protected $table = 'consentimientos';

    protected $primaryKey = 'id_consentimiento';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'aceptado',
        'fecha_hora_aceptacion',
        'version_formato',
        'id_version',
    ];

    protected function casts(): array
    {
        return [
            'aceptado' => Booleano::class,
            'fecha_hora_aceptacion' => 'datetime',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(VersionConsentimiento::class, 'id_version', 'id_version');
    }
}

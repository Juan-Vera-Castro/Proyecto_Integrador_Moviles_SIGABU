<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Firma del profesional tratante sobre un consentimiento aceptado, como en el formato en
 * papel (GBU-GPGR-F-032 y F-019). Una por aceptación; nunca se edita ni se borra.
 */
class FirmaProfesionalConsentimiento extends Model
{
    use EsInmutable;

    protected $table = 'consentimiento_firmas_profesional';

    protected $primaryKey = 'id_aceptacion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_aceptacion',
        'id_profesional',
        'fecha_hora',
        'firma_ruta',
        'firma_sha256',
        'ip_origen',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function aceptacion(): BelongsTo
    {
        return $this->belongsTo(ConsentimientoAceptacion::class, 'id_aceptacion', 'id_aceptacion');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }
}

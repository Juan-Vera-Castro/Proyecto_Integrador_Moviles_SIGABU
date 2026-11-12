<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Corrección o complemento de la historia clínica, con fecha y autor. Puede referirse a
 * un apartado de la historia o a la historia en general. Solo se insertan registros.
 */
class HistoriaClinicaAnotacion extends Model
{
    use EsInmutable, HasUuids;

    protected $table = 'historia_clinica_anotaciones';

    protected $primaryKey = 'id_anotacion';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_historia',
        'id_profesional',
        'apartado',
        'texto',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class, 'id_historia', 'id_historia');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Nombre del apartado al que se refiere, o null si es sobre la historia en general.
     */
    public function etiquetaApartado(): ?string
    {
        return $this->apartado ? (HistoriaClinica::APARTADOS[$this->apartado][0] ?? $this->apartado) : null;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\EsInmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Historia clínica psicológica del expediente: una por caso de Salud Mental. La registra
 * el psicólogo del caso con consentimiento aceptado y no se modifica (Res. 1995 de 1999):
 * las correcciones y los complementos se agregan como anotaciones.
 */
class HistoriaClinica extends Model
{
    use EsInmutable, HasUuids;

    public const TEXTO_MAX = 5000;

    /**
     * Apartados de la historia en el orden del formulario: campo => [etiqueta, obligatorio].
     */
    public const APARTADOS = [
        'motivo_consulta' => ['Motivo de consulta', true],
        'historia_problema' => ['Historia del problema actual', false],
        'antecedentes_personales' => ['Antecedentes personales', false],
        'antecedentes_familiares' => ['Antecedentes familiares', false],
        'antecedentes_medicos' => ['Antecedentes médicos y psiquiátricos', false],
        'examen_mental' => ['Examen mental', false],
        'impresion_diagnostica' => ['Impresión diagnóstica', true],
        'plan_intervencion' => ['Plan de intervención', true],
        'observaciones' => ['Observaciones', false],
    ];

    protected $table = 'historias_clinicas';

    protected $primaryKey = 'id_historia';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_profesional',
        'motivo_consulta',
        'historia_problema',
        'antecedentes_personales',
        'antecedentes_familiares',
        'antecedentes_medicos',
        'examen_mental',
        'impresion_diagnostica',
        'plan_intervencion',
        'observaciones',
        'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'fecha_registro' => 'datetime',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Correcciones y complementos de la historia, en orden cronológico.
     */
    public function anotaciones(): HasMany
    {
        return $this->hasMany(HistoriaClinicaAnotacion::class, 'id_historia', 'id_historia')
            ->orderBy('fecha_hora');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código, versión y fecha vigentes de un formato de Calidad (D-17). Se editan desde
 * Administración; cada documento generado guarda los valores con que se generó.
 */
class FormatoInstitucional extends Model
{
    public const MESES = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    protected $table = 'formatos_institucionales';

    protected $primaryKey = 'id_formato';

    public $timestamps = false;

    protected $fillable = [
        'proceso',
        'nombre',
        'codigo',
        'version',
        'fecha_formato',
        'actualizado_por',
        'fecha_actualizacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_formato' => 'date',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public static function deClave(string $clave): self
    {
        return static::where('clave', $clave)->firstOrFail();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por', 'id_usuario');
    }

    /**
     * Fecha como la escribe Calidad en los formatos, p. ej. "18/ABR/2024".
     */
    public static function fechaComoFormato(\DateTimeInterface $fecha): string
    {
        return $fecha->format('d').'/'.self::MESES[(int) $fecha->format('n') - 1].'/'.$fecha->format('Y');
    }
}

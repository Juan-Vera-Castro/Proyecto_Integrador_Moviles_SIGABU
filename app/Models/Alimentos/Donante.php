<?php

namespace App\Models\Alimentos;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persona o entidad que dona a "Ucevistas de Corazón". Puede pedir quedar anónima: su nombre
 * no aparece en el informe de transparencia. No se borra; se desactiva.
 */
class Donante extends Model
{
    public const TIPOS = ['Interno', 'Externo'];

    /**
     * Categorías por tipo: internos de la comunidad universitaria, externos de fuera.
     */
    public const CATEGORIAS = [
        'Interno' => ['Docente', 'Administrativo', 'Estudiante'],
        'Externo' => ['Empresa', 'Fundación', 'Egresado', 'Otro'],
    ];

    protected $table = 'alim_donantes';

    protected $primaryKey = 'id_donante';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'tipo',
        'categoria',
        'anonimo',
        'contacto',
        'activo',
        'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'anonimo' => Booleano::class,
            'activo' => Booleano::class,
            'fecha_registro' => 'datetime',
        ];
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'id_donante', 'id_donante');
    }

    public function nombreVisible(): string
    {
        return $this->anonimo ? 'Donante anónimo' : $this->nombre;
    }
}

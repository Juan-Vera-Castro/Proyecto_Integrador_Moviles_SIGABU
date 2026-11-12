<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    public $timestamps = false;

    protected $fillable = [
        'nombre_rol',
        'sistema_destino',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'id_rol', 'id_rol');
    }
}

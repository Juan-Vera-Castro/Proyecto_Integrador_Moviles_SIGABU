<?php

namespace App\Models\Inclusion;

use App\Models\Caso;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bitácora de orientación y acompañamiento de un caso de Inclusión (una por caso). La
 * caracterización se corrige mientras el caso esté abierto, y cada cambio queda registrado.
 */
class Bitacora extends Model
{
    use HasUuids;

    /**
     * Nota fija de la sección III del formato.
     */
    public const NOTA = 'La Unidad de Inclusión actúa como facilitadora y asesora técnica; la ejecución de ajustes académicos o laborales es competencia del área respectiva.';

    /**
     * Campos de la caracterización: campo => [etiqueta, obligatorio, tamaño máximo].
     */
    public const CARACTERIZACION = [
        'rol_institucional' => ['Rol institucional', true, 30],
        'programa_dependencia' => ['Programa académico / Dependencia', true, 150],
        'situacion' => ['Situación o discapacidad', true, 2000],
        'ruta_activada' => ['Ruta activada por', true, 5000],
        'soportes_texto' => ['Soportes recibidos', false, 5000],
        'orientacion' => ['Orientación brindada', false, 5000],
    ];

    protected $table = 'inclusion_bitacoras';

    protected $primaryKey = 'id_bitacora';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_profesional',
        'rol_institucional',
        'programa_dependencia',
        'situacion',
        'ruta_activada',
        'soportes_texto',
        'orientacion',
        'fecha_registro',
        'fecha_actualizacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_registro' => 'datetime',
            'fecha_actualizacion' => 'datetime',
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
     * Matriz de seguimiento, en orden cronológico.
     */
    public function entradas(): HasMany
    {
        return $this->hasMany(EntradaBitacora::class, 'id_bitacora', 'id_bitacora')->orderBy('numero');
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(BitacoraCambio::class, 'id_bitacora', 'id_bitacora')->orderByDesc('fecha_hora');
    }

    /**
     * Constancias de gestión, la más reciente primero: una por cada cierre.
     */
    public function constancias(): HasMany
    {
        return $this->hasMany(Constancia::class, 'id_bitacora', 'id_bitacora')->orderByDesc('fecha_hora');
    }

    public function reaperturas(): HasMany
    {
        return $this->hasMany(Reapertura::class, 'id_bitacora', 'id_bitacora')->orderBy('fecha_hora');
    }

    public function soportes(): HasMany
    {
        return $this->hasMany(Evidencia::class, 'id_destino', 'id_bitacora')
            ->where('destino', Evidencia::DESTINO_SOPORTE)
            ->where('estado', Evidencia::VINCULADA);
    }

    /**
     * Última entrada de Cierre que aún no tiene constancia: sin ella no se cierra el caso.
     */
    public function cierrePendiente(): ?EntradaBitacora
    {
        $firmadas = $this->constancias()->pluck('id_entrada_cierre');

        return $this->entradas()
            ->where('tipo', EntradaBitacora::CIERRE)
            ->whereNotIn('id_entrada', $firmadas)
            ->get()
            ->last();
    }

    /**
     * Firmado si su último cierre sigue vigente; una reapertura lo deja en curso otra vez.
     */
    public function estadoDocumento(): string
    {
        $ultima = $this->constancias->first();

        return $ultima && ! $this->reaperturas->contains('id_constancia', $ultima->id_constancia)
            ? EstadoDocumento::FIRMADO
            : EstadoDocumento::COMPLETADO;
    }
}

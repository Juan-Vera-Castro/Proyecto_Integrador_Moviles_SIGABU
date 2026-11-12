<?php

namespace App\Models;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento del repositorio de Bienestar (ley, protocolo, guía, formato o material
 * psicoeducativo), publicado como enlace para los profesionales o para todos.
 */
class RepositorioDocumento extends Model
{
    public const CATEGORIAS = ['Ley o norma', 'Protocolo', 'Guía', 'Formato', 'Material psicoeducativo'];

    public const PROFESIONALES = 'Profesionales';

    public const TODOS = 'Todos';

    public const AUDIENCIAS = [self::PROFESIONALES, self::TODOS];

    protected $table = 'repositorio_documentos';

    protected $primaryKey = 'id_documento';

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'categoria',
        'descripcion',
        'url',
        'audiencia',
        'activo',
        'publicado_por',
        'fecha_publicacion',
        'archivo_ruta',
        'archivo_nombre',
        'archivo_mime',
        'archivo_bytes',
        'archivo_sha256',
    ];

    /**
     * Disco de los archivos subidos (bucket S3 o carpeta privada del servidor).
     */
    public const DISCO = 'repositorio';

    /**
     * Tamaño máximo de un archivo, en kilobytes (10 MB).
     */
    public const ARCHIVO_MAX_KB = 10240;

    /**
     * Extensiones que se aceptan: documentos de oficina, PDF, imágenes y texto.
     */
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'txt', 'jpg', 'jpeg', 'png'];

    /**
     * Roles que publican en el repositorio: el administrador y los equipos de Bienestar.
     */
    public const ROLES_QUE_PUBLICAN = ['admin', 'profesional', 'vbg', 'almacen'];

    protected function casts(): array
    {
        return [
            'activo' => Booleano::class,
            'fecha_publicacion' => 'datetime',
            'archivo_bytes' => 'integer',
        ];
    }

    public function tieneArchivo(): bool
    {
        return $this->archivo_ruta !== null;
    }

    /**
     * Lo retira el administrador o quien lo publicó.
     */
    public function puedeRetirarlo(User $usuario): bool
    {
        return $usuario->role === 'admin' || $this->publicado_por === $usuario->id_usuario;
    }

    /**
     * Quién puede abrirlo: los equipos, si está activo; los estudiantes, si además es para todos.
     */
    public function puedeVerlo(User $usuario): bool
    {
        return $usuario->role === 'admin'
            || ($this->activo && (in_array($usuario->role, self::ROLES_QUE_PUBLICAN, true) || $this->audiencia === self::TODOS));
    }

    /**
     * Tamaño legible, p. ej. "1,2 MB".
     */
    public function tamanoLegible(): ?string
    {
        if (! $this->archivo_bytes) {
            return null;
        }

        return $this->archivo_bytes >= 1048576
            ? number_format($this->archivo_bytes / 1048576, 1, ',', '.').' MB'
            : max(1, (int) round($this->archivo_bytes / 1024)).' KB';
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por', 'id_usuario');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', Booleano::valor(true, $query->getConnection()->getDriverName()));
    }

    /**
     * Lo que ve la comunidad (estudiantes): solo lo publicado para todos.
     */
    public function scopeParaTodos(Builder $query): Builder
    {
        return $query->activos()->where('audiencia', self::TODOS);
    }
}

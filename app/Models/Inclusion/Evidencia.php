<?php

namespace App\Models\Inclusion;

use App\Models\Caso;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Archivo o link de un formato de Inclusión. Nace temporal mientras se llena el formulario,
 * queda vinculada al guardar y desde entonces no cambia. La descartada conserva su fila porque
 * sigabu_app no borra; lo que se borra es el objeto del almacenamiento.
 */
class Evidencia extends Model
{
    use HasUuids;

    /**
     * Disco privado de los archivos (bucket S3 o carpeta privada del servidor).
     */
    public const DISCO = 'inclusion';

    public const TIPO_ARCHIVO = 'archivo';

    public const TIPO_ENLACE = 'enlace';

    public const TEMPORAL = 'temporal';

    public const VINCULADA = 'vinculada';

    public const DESCARTADA = 'descartada';

    public const DESTINO_SOPORTE = 'soporte';

    public const DESTINO_ENTRADA = 'entrada';

    public const DESTINO_ANOTACION = 'anotacion';

    public const DESTINO_AJUSTE = 'ajuste';

    public const DESTINOS = [self::DESTINO_SOPORTE, self::DESTINO_ENTRADA, self::DESTINO_ANOTACION, self::DESTINO_AJUSTE];

    public const ARCHIVO_MAX_KB = 10240;

    public const MAX_POR_REGISTRO = 10;

    /**
     * Extensión => tipos reales que se aceptan para ella.
     */
    public const TIPOS_PERMITIDOS = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    protected $table = 'inclusion_evidencias';

    protected $primaryKey = 'id_evidencia';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_evidencia',
        'id_caso',
        'tipo',
        'destino',
        'id_destino',
        'estado',
        'ruta',
        'nombre',
        'mime',
        'bytes',
        'sha256',
        'url',
        'subido_por',
        'fecha_registro',
    ];

    protected $attributes = [
        'estado' => self::TEMPORAL,
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'fecha_registro' => 'datetime',
        ];
    }

    /**
     * El trigger de la base rechaza estos cambios; aquí el error aparece antes y con un mensaje claro.
     */
    protected static function booted(): void
    {
        static::updating(function (Evidencia $evidencia) {
            if ($evidencia->getOriginal('estado') === self::VINCULADA) {
                throw new LogicException('Una evidencia vinculada no se puede modificar.');
            }
        });

        static::deleting(function () {
            throw new LogicException('Las evidencias no se borran: se descartan.');
        });
    }

    /**
     * Si el contenido real del archivo corresponde a su extensión.
     */
    public static function tipoRealPermitido(UploadedFile $archivo): bool
    {
        $permitidos = self::TIPOS_PERMITIDOS[strtolower($archivo->getClientOriginalExtension())] ?? [];

        return in_array($archivo->getMimeType(), $permitidos, true);
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'id_caso', 'id_caso');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por', 'id_usuario');
    }

    public function esArchivo(): bool
    {
        return $this->tipo === self::TIPO_ARCHIVO;
    }

    /**
     * Tamaño legible, p. ej. "1,2 MB".
     */
    public function tamanoLegible(): ?string
    {
        if (! $this->bytes) {
            return null;
        }

        return $this->bytes >= 1048576
            ? number_format($this->bytes / 1048576, 1, ',', '.').' MB'
            : max(1, (int) round($this->bytes / 1024)).' KB';
    }
}

<?php

namespace App\Models;

use App\Casts\Booleano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public const AREA_AMBAS = 'Ambas';

    /**
     * Áreas que el administrador designa a un profesional, con su nombre visible. Cada una
     * coincide con un tipo_servicio de las solicitudes, salvo "Ambas".
     */
    public const AREAS = [
        'Salud Mental' => 'Psicología',
        'Inclusión' => 'Inclusión social',
        self::AREA_AMBAS => 'Psicología e Inclusión social',
    ];

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo_unico',
        'contrasena_hash',
        'id_rol',
        'estado_activo',
        've_indicadores',
        'area_profesional',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'contrasena_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contrasena_hash' => 'hashed',
            'estado_activo' => Booleano::class,
            've_indicadores' => Booleano::class,
        ];
    }

    /**
     * Todo usuario nuevo nace con el rol base "Persona"; el administrador
     * le designa otro rol después desde el panel de administración.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->id_rol)) {
                $user->id_rol = Role::where('nombre_rol', 'Persona')->value('id_rol');
            }
        });
    }

    public function persona(): HasOne
    {
        return $this->hasOne(Persona::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Relación real hacia la tabla roles. No se llama "role" a propósito:
     * ese nombre ya lo usa getRoleAttribute() para el string heredado que
     * consumen las rutas/vistas por rol, y un accessor siempre gana sobre
     * el acceso a una relación como propiedad del mismo nombre.
     */
    public function rolReal(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_rol', 'id_rol');
    }

    /**
     * Casos a cargo de este profesional.
     */
    public function casosAsignados(): HasMany
    {
        return $this->hasMany(Caso::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Facultades, jornadas y servicios que atiende este profesional.
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionProfesional::class, 'id_profesional', 'id_usuario');
    }

    /**
     * Nombre completo de la persona asociada, para las vistas de Breeze.
     */
    public function getNameAttribute(): ?string
    {
        return $this->persona?->nombre_completo;
    }

    /**
     * Correo institucional de la persona asociada, para las vistas de Breeze.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->persona?->correo_inst;
    }

    /**
     * Cuenta activa cuyo correo institucional (personas.correo_inst) es $correo.
     * La tabla usuarios no tiene columna email: el correo vive en personas.
     */
    public static function activoPorCorreo(string $correo): ?self
    {
        $persona = Persona::whereRaw('lower(correo_inst) = ?', [mb_strtolower(trim($correo))])->first();
        $usuario = $persona?->usuario;

        return $usuario?->estado_activo ? $usuario : null;
    }

    /**
     * Cuentas activas con rol Profesional, p. ej. los destinos de una remisión interna (HU-007).
     */
    public function scopeProfesionalesActivos(Builder $query): Builder
    {
        return $query
            ->where('estado_activo', Booleano::valor(true, $query->getConnection()->getDriverName()))
            ->whereHas('rolReal', fn (Builder $rol) => $rol->where('nombre_rol', 'Profesional'));
    }

    /**
     * Profesionales cuya área incluye $servicio.
     */
    public function scopeQueAtienden(Builder $query, ?string $servicio): Builder
    {
        return $query->whereIn('area_profesional', [$servicio, self::AREA_AMBAS]);
    }

    /**
     * Servicios que cubre un área. Sin área no cubre ninguno: no se ve nada por defecto.
     *
     * @return list<string>
     */
    public static function serviciosDeArea(?string $area): array
    {
        return $area === self::AREA_AMBAS
            ? SolicitudAtencion::SERVICIOS
            : array_values(array_intersect(SolicitudAtencion::SERVICIOS, [$area]));
    }

    /**
     * @return list<string>
     */
    public function serviciosQueAtiende(): array
    {
        return self::serviciosDeArea($this->area_profesional);
    }

    public function atiendeServicio(?string $servicio): bool
    {
        return in_array($servicio, $this->serviciosQueAtiende(), true);
    }

    public function etiquetaArea(): ?string
    {
        return self::AREAS[$this->area_profesional] ?? null;
    }

    /**
     * Listado de casos al que vuelve el profesional: el de su área.
     */
    public function urlCasos(): string
    {
        return match (true) {
            $this->atiendeServicio(Caso::SERVICIO_SALUD_MENTAL) => route('profesional.salud-mental'),
            $this->atiendeServicio('Inclusión') => route('profesional.inclusion'),
            default => $this->getDashboardUrl(),
        };
    }

    public function getAuthPassword(): string
    {
        return $this->contrasena_hash;
    }

    /**
     * La tabla usuarios no tiene columna remember_token; se deshabilita
     * para que Laravel no intente persistirla en el "recuérdame" del login.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Puente entre los roles reales (tabla roles: Persona, Profesional,
     * Admin, Alimentos, VBG) y el vocabulario que usan las rutas y vistas
     * por rol (estudiante/profesional/almacen/vbg/admin).
     *
     * Se consulta una vez por petición (la relación queda cargada), así que un
     * rol designado desde el panel de administración aplica en el siguiente clic.
     */
    public function getRoleAttribute(): ?string
    {
        // Si id_rol cambió en esta petición, la relación cargada ya no aplica.
        if ($this->relationLoaded('rolReal') && (int) $this->rolReal?->id_rol !== (int) $this->id_rol) {
            $this->unsetRelation('rolReal');
        }

        return match ($this->rolReal?->nombre_rol) {
            'Persona' => 'estudiante',
            'Profesional' => 'profesional',
            'Alimentos' => 'almacen',
            'VBG' => 'vbg',
            'Admin' => 'admin',
            default => null,
        };
    }

    /**
     * HU-032: el tablero de indicadores institucionales lo ven el administrador (directivo) y
     * los profesionales que él autorice.
     */
    public function puedeVerIndicadores(): bool
    {
        return match ($this->role) {
            'admin' => true,
            'profesional' => (bool) $this->ve_indicadores,
            default => false,
        };
    }

    /**
     * Obtener la URL de la sección que corresponde al rol del usuario.
     */
    public function getDashboardUrl(): string
    {
        return match ($this->role) {
            'estudiante' => route('estudiante.home'),
            'profesional' => route('profesional.home'),
            'almacen' => route('almacen.home'),
            'vbg' => route('vbg.home'),
            'admin' => route('admin.dashboard'),
            default => route('dashboard'),
        };
    }
}

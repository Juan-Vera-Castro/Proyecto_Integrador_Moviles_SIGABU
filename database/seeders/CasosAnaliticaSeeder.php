<?php

namespace Database\Seeders;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\Persona;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Casos de ejemplo para la analítica del profesional de prueba (cmunoz@uceva.edu.co):
 * dos activos y uno finalizado. Se puede correr varias veces: si el solicitante ya tiene
 * una solicitud con el mismo motivo, ese caso no se vuelve a crear.
 *
 *   php artisan db:seed --class=CasosAnaliticaSeeder
 */
class CasosAnaliticaSeeder extends Seeder
{
    private const CORREO_PROFESIONAL = 'cmunoz@uceva.edu.co';

    private const CASOS = [
        [
            'documento' => '1116200101',
            'nombre' => 'Laura Valentina Ospina Rojas',
            'correo' => 'lospina@uceva.edu.co',
            'servicio' => 'Salud Mental',
            'motivo' => 'Síntomas de ansiedad y dificultades para dormir durante el periodo de parciales.',
            'riesgo' => 'Medio',
            'estado' => 'En Seguimiento',
            'dias' => 45,
            'sesiones' => [Actuacion::ASISTIO, Actuacion::ASISTIO, Actuacion::NO_ASISTIO],
        ],
        [
            'documento' => '1116200102',
            'nombre' => 'Andrés Felipe Cardona Mejía',
            'correo' => 'acardona@uceva.edu.co',
            'servicio' => 'Inclusión',
            'motivo' => 'Requiere ajustes razonables en evaluaciones por diagnóstico de dislexia.',
            'riesgo' => 'Bajo',
            'estado' => Caso::ESTADO_ABIERTO,
            'dias' => 8,
            'sesiones' => [Actuacion::ASISTIO],
        ],
        [
            'documento' => '1116200103',
            'nombre' => 'Daniela Restrepo Gil',
            'correo' => 'drestrepo@uceva.edu.co',
            'servicio' => 'Salud Mental',
            'motivo' => 'Duelo por pérdida familiar con afectación del rendimiento académico.',
            'riesgo' => 'Alto',
            'estado' => Caso::ESTADO_CERRADO,
            'dias' => 120,
            'cerrado_hace' => 15,
            'sesiones' => [Actuacion::ASISTIO, Actuacion::ASISTIO, Actuacion::ASISTIO, Actuacion::ASISTIO, Actuacion::ASISTIO],
        ],
    ];

    public function run(): void
    {
        $profesional = User::whereHas('persona', fn ($persona) => $persona->where('correo_inst', self::CORREO_PROFESIONAL))->first();

        if (! $profesional) {
            $this->command?->error('No existe el profesional '.self::CORREO_PROFESIONAL.'.');

            return;
        }

        foreach (self::CASOS as $datos) {
            DB::transaction(fn () => $this->crearCaso($datos, $profesional));
        }
    }

    private function crearCaso(array $datos, User $profesional): void
    {
        $persona = $this->solicitante($datos);

        if ($persona->solicitudes()->where('descripcion', $datos['motivo'])->exists()) {
            $this->command?->line("Ya existe el caso de {$datos['nombre']}; se omite.");

            return;
        }

        $apertura = now(Actuacion::ZONA_HORARIA)->subDays($datos['dias'])->setTime(9, 0);

        $solicitud = $persona->solicitudes()->create([
            'tipo_servicio' => $datos['servicio'],
            'descripcion' => $datos['motivo'],
        ]);

        // El trigger de solicitudes_atencion crea el caso con su código de expediente.
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();

        $caso->forceFill([
            'estado_caso' => $datos['estado'],
            'nivel_riesgo' => $datos['riesgo'],
            'id_profesional' => $profesional->id_usuario,
            'fecha_apertura' => $apertura->toDateTimeString(),
            'fecha_cierre' => isset($datos['cerrado_hace'])
                ? now(Actuacion::ZONA_HORARIA)->subDays($datos['cerrado_hace'])->setTime(16, 0)->toDateTimeString()
                : null,
        ])->save();

        // Citas pasadas, una por semana desde la apertura, con su asistencia registrada.
        foreach ($datos['sesiones'] as $i => $asistencia) {
            DB::table('actuaciones')->insert([
                'id_actuacion' => (string) Str::uuid(),
                'id_caso' => $caso->id_caso,
                'id_profesional' => $profesional->id_usuario,
                'tipo_actuacion' => Actuacion::TIPO_CITA,
                'fecha_hora' => $apertura->copy()->addDays(1 + 7 * $i)->setTime(10, 0)->toDateTimeString(),
                'descripcion' => 'Sesión de seguimiento',
                'estado_asistencia' => $asistencia,
                'motivo_inasistencia' => $asistencia === Actuacion::NO_ASISTIO ? 'No se presentó ni avisó.' : null,
            ]);
        }

        $this->command?->info("Caso {$caso->codigo_expediente} ({$datos['estado']}) creado para {$datos['nombre']}.");
    }

    /**
     * Reutiliza la persona con esa cédula o le crea su cuenta de estudiante (rol Persona).
     */
    private function solicitante(array $datos): Persona
    {
        $persona = Persona::where('numero_documento', $datos['documento'])->first();

        if ($persona) {
            return $persona;
        }

        $usuario = User::create([
            'codigo_unico' => $datos['documento'],
            'contrasena_hash' => 'Prueba123',
            'id_rol' => Role::where('nombre_rol', 'Persona')->value('id_rol'),
            'estado_activo' => true,
        ]);

        return Persona::create([
            'id_usuario' => $usuario->id_usuario,
            'numero_documento' => $datos['documento'],
            'nombre_completo' => $datos['nombre'],
            'correo_inst' => $datos['correo'],
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HU-013: actividades que el profesional asigna al estudiante entre sesiones.
     * Como las demás tablas del núcleo, vive en la base compartida de Supabase.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tareas_terapeuticas (
                id_tarea UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso UUID NOT NULL REFERENCES casos(id_caso),
                id_profesional UUID REFERENCES usuarios(id_usuario),
                titulo VARCHAR(150) NOT NULL,
                descripcion TEXT NOT NULL,
                fecha_limite DATE,
                fecha_asignacion TIMESTAMP NOT NULL DEFAULT now(),
                estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente de evaluación',
                CONSTRAINT ck_tareas_estado CHECK (estado IN ('Pendiente de evaluación', 'Cumplida', 'No cumplida'))
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS tareas_terapeuticas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitud de cita del estudiante: la fecha, la hora, la modalidad y el profesional que prefiere.
     * Son opcionales porque las solicitudes que registra el profesional al abrir un caso no las traen.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('solicitudes_atencion')) {
            return;
        }

        DB::statement('ALTER TABLE solicitudes_atencion ADD COLUMN IF NOT EXISTS fecha_preferida DATE');
        DB::statement('ALTER TABLE solicitudes_atencion ADD COLUMN IF NOT EXISTS hora_preferida VARCHAR(5)');
        DB::statement('ALTER TABLE solicitudes_atencion ADD COLUMN IF NOT EXISTS modalidad VARCHAR(12)');
        DB::statement('ALTER TABLE solicitudes_atencion ADD COLUMN IF NOT EXISTS id_profesional_preferido UUID REFERENCES usuarios(id_usuario)');
        DB::statement('ALTER TABLE solicitudes_atencion DROP CONSTRAINT IF EXISTS ck_solicitudes_modalidad');
        DB::statement("ALTER TABLE solicitudes_atencion ADD CONSTRAINT ck_solicitudes_modalidad CHECK (modalidad IN ('Presencial', 'Virtual'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('solicitudes_atencion')) {
            return;
        }

        DB::statement('ALTER TABLE solicitudes_atencion DROP CONSTRAINT IF EXISTS ck_solicitudes_modalidad');
        DB::statement('ALTER TABLE solicitudes_atencion DROP COLUMN IF EXISTS id_profesional_preferido');
        DB::statement('ALTER TABLE solicitudes_atencion DROP COLUMN IF EXISTS modalidad');
        DB::statement('ALTER TABLE solicitudes_atencion DROP COLUMN IF EXISTS hora_preferida');
        DB::statement('ALTER TABLE solicitudes_atencion DROP COLUMN IF EXISTS fecha_preferida');
    }
};

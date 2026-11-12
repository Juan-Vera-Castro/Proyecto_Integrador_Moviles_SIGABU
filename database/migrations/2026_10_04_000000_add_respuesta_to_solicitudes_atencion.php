<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Respuesta del profesional a la solicitud de cita del estudiante: cuándo la respondió
     * y, si la rechazó, el motivo que ve el estudiante.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('solicitudes_atencion')) {
            return;
        }

        DB::statement('ALTER TABLE solicitudes_atencion ADD COLUMN IF NOT EXISTS fecha_respuesta TIMESTAMP');
        DB::statement('ALTER TABLE solicitudes_atencion ADD COLUMN IF NOT EXISTS motivo_rechazo TEXT');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('solicitudes_atencion')) {
            return;
        }

        DB::statement('ALTER TABLE solicitudes_atencion DROP COLUMN IF EXISTS motivo_rechazo');
        DB::statement('ALTER TABLE solicitudes_atencion DROP COLUMN IF EXISTS fecha_respuesta');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-012: la asistencia a cada cita queda en la propia actuación.
     * Las citas existentes arrancan como "Pendiente".
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('actuaciones')) {
            return;
        }

        DB::statement("ALTER TABLE actuaciones ADD COLUMN IF NOT EXISTS estado_asistencia VARCHAR(15) NOT NULL DEFAULT 'Pendiente'");
        DB::statement('ALTER TABLE actuaciones ADD COLUMN IF NOT EXISTS motivo_inasistencia TEXT');
        DB::statement('ALTER TABLE actuaciones DROP CONSTRAINT IF EXISTS ck_actuaciones_asistencia');
        DB::statement("ALTER TABLE actuaciones ADD CONSTRAINT ck_actuaciones_asistencia CHECK (estado_asistencia IN ('Pendiente', 'Asistió', 'No asistió'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('actuaciones')) {
            return;
        }

        DB::statement('ALTER TABLE actuaciones DROP CONSTRAINT IF EXISTS ck_actuaciones_asistencia');
        DB::statement('ALTER TABLE actuaciones DROP COLUMN IF EXISTS motivo_inasistencia');
        DB::statement('ALTER TABLE actuaciones DROP COLUMN IF EXISTS estado_asistencia');
    }
};

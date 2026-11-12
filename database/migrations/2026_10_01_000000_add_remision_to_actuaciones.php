<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-007: la remisión es una actuación de tipo "Remisión" con su destino: otro
     * profesional (interna) o una entidad externa. Las citas y tareas pendientes que
     * la remisión deja sin efecto quedan enlazadas a ella en id_remision.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('actuaciones')) {
            return;
        }

        DB::statement('ALTER TABLE actuaciones ADD COLUMN IF NOT EXISTS destino_remision VARCHAR(10)');
        DB::statement('ALTER TABLE actuaciones ADD COLUMN IF NOT EXISTS id_profesional_destino UUID REFERENCES usuarios(id_usuario)');
        DB::statement('ALTER TABLE actuaciones ADD COLUMN IF NOT EXISTS entidad_destino VARCHAR(150)');
        DB::statement('ALTER TABLE actuaciones ADD COLUMN IF NOT EXISTS id_remision UUID REFERENCES actuaciones(id_actuacion)');

        // NOT VALID: exige el destino a las remisiones nuevas sin rechazar filas antiguas.
        DB::statement('ALTER TABLE actuaciones DROP CONSTRAINT IF EXISTS ck_actuaciones_remision');
        DB::statement(<<<'SQL'
            ALTER TABLE actuaciones ADD CONSTRAINT ck_actuaciones_remision CHECK (
                (tipo_actuacion = 'Remisión' AND (
                    (destino_remision = 'Interna' AND id_profesional_destino IS NOT NULL AND entidad_destino IS NULL)
                    OR (destino_remision = 'Externa' AND entidad_destino IS NOT NULL AND id_profesional_destino IS NULL)
                ))
                OR (tipo_actuacion <> 'Remisión' AND destino_remision IS NULL AND id_profesional_destino IS NULL AND entidad_destino IS NULL)
            ) NOT VALID
        SQL);

        DB::statement('ALTER TABLE actuaciones DROP CONSTRAINT IF EXISTS ck_actuaciones_asistencia');
        DB::statement("ALTER TABLE actuaciones ADD CONSTRAINT ck_actuaciones_asistencia CHECK (estado_asistencia IN ('Pendiente', 'Asistió', 'No asistió', 'Cancelada'))");

        if (Schema::hasTable('tareas_terapeuticas')) {
            DB::statement('ALTER TABLE tareas_terapeuticas ADD COLUMN IF NOT EXISTS id_remision UUID REFERENCES actuaciones(id_actuacion)');
            DB::statement('ALTER TABLE tareas_terapeuticas DROP CONSTRAINT IF EXISTS ck_tareas_estado');
            DB::statement("ALTER TABLE tareas_terapeuticas ADD CONSTRAINT ck_tareas_estado CHECK (estado IN ('Pendiente de evaluación', 'Cumplida', 'No cumplida', 'Cancelada'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('actuaciones')) {
            return;
        }

        if (Schema::hasTable('tareas_terapeuticas')) {
            DB::statement("UPDATE tareas_terapeuticas SET estado = 'Pendiente de evaluación' WHERE estado = 'Cancelada'");
            DB::statement('ALTER TABLE tareas_terapeuticas DROP CONSTRAINT IF EXISTS ck_tareas_estado');
            DB::statement("ALTER TABLE tareas_terapeuticas ADD CONSTRAINT ck_tareas_estado CHECK (estado IN ('Pendiente de evaluación', 'Cumplida', 'No cumplida'))");
            DB::statement('ALTER TABLE tareas_terapeuticas DROP COLUMN IF EXISTS id_remision');
        }

        DB::statement("UPDATE actuaciones SET estado_asistencia = 'Pendiente' WHERE estado_asistencia = 'Cancelada'");
        DB::statement('ALTER TABLE actuaciones DROP CONSTRAINT IF EXISTS ck_actuaciones_asistencia');
        DB::statement("ALTER TABLE actuaciones ADD CONSTRAINT ck_actuaciones_asistencia CHECK (estado_asistencia IN ('Pendiente', 'Asistió', 'No asistió'))");

        DB::statement('ALTER TABLE actuaciones DROP CONSTRAINT IF EXISTS ck_actuaciones_remision');
        DB::statement('ALTER TABLE actuaciones DROP COLUMN IF EXISTS id_remision');
        DB::statement('ALTER TABLE actuaciones DROP COLUMN IF EXISTS entidad_destino');
        DB::statement('ALTER TABLE actuaciones DROP COLUMN IF EXISTS id_profesional_destino');
        DB::statement('ALTER TABLE actuaciones DROP COLUMN IF EXISTS destino_remision');
    }
};

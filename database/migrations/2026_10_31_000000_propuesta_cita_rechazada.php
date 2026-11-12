<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El estudiante puede rechazar la solicitud de cita que le envía el profesional. Solo amplía
     * los estados permitidos: no cambia ni borra ninguna propuesta existente.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('propuestas_cita')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.propuestas_cita
                DROP CONSTRAINT IF EXISTS ck_propuesta_estado,
                ADD CONSTRAINT ck_propuesta_estado CHECK (estado IN ('Propuesta', 'Elegida', 'Descartada', 'Rechazada'));
        SQL);
    }

    /**
     * No borra nada.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horarios que el psicólogo propone para una cita: el estudiante elige el que le sirve y ese
     * se vuelve la cita; los demás quedan descartados. Tabla nueva: no toca ninguna existente.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.propuestas_cita (
                id_propuesta    uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso         uuid        NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_profesional  uuid        NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_hora      timestamp   NOT NULL,
                estado          varchar(12) NOT NULL DEFAULT 'Propuesta'
                                CONSTRAINT ck_propuesta_estado CHECK (estado IN ('Propuesta', 'Elegida', 'Descartada')),
                id_cita         uuid REFERENCES public.actuaciones (id_actuacion) ON DELETE RESTRICT,
                fecha_propuesta timestamp   NOT NULL DEFAULT now(),
                fecha_respuesta timestamp,
                CONSTRAINT ck_propuesta_elegida CHECK (estado <> 'Elegida' OR id_cita IS NOT NULL)
            );
            CREATE INDEX IF NOT EXISTS idx_propuestas_caso ON public.propuestas_cita (id_caso, estado);

            ALTER TABLE public.propuestas_cita ENABLE ROW LEVEL SECURITY;
            DROP POLICY IF EXISTS sin_acceso_api ON public.propuestas_cita;
            CREATE POLICY sin_acceso_api ON public.propuestas_cita
                AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);
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

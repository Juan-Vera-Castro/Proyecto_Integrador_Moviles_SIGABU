<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inclusión Universitaria: ajustes razonables del caso (HU-018) y su seguimiento (HU-019).
     * Cada seguimiento registra la fecha, el nivel de cumplimiento y las observaciones; el
     * historial es inmutable. Tablas nuevas: no toca ninguna existente.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.ajustes_razonables (
                id_ajuste      uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso        uuid         NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_profesional uuid         NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                tipo           varchar(30)  NOT NULL
                               CONSTRAINT ck_ajuste_tipo CHECK (tipo IN ('Académico o de evaluación', 'Metodológico', 'Tecnológico o de acceso',
                                                                         'De infraestructura', 'De tiempo', 'De comunicación', 'Otro')),
                barrera        text         NOT NULL,
                descripcion    text         NOT NULL,
                responsable    varchar(150),
                fecha_inicio   date         NOT NULL,
                estado         varchar(12)  NOT NULL DEFAULT 'Vigente'
                               CONSTRAINT ck_ajuste_estado CHECK (estado IN ('Vigente', 'Cumplido', 'Suspendido')),
                fecha_registro timestamp    NOT NULL DEFAULT now()
            );
            CREATE INDEX IF NOT EXISTS idx_ajustes_caso ON public.ajustes_razonables (id_caso, fecha_registro);

            CREATE TABLE IF NOT EXISTS public.seguimientos_ajuste (
                id_seguimiento   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_ajuste        uuid         NOT NULL REFERENCES public.ajustes_razonables (id_ajuste) ON DELETE RESTRICT,
                id_profesional   uuid         NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha            date         NOT NULL,
                cumplimiento     varchar(25)  NOT NULL
                                 CONSTRAINT ck_seguimiento_cumplimiento CHECK (cumplimiento IN ('Se cumple', 'Se cumple parcialmente', 'No se cumple')),
                observaciones    text         NOT NULL,
                estado_resultante varchar(12)
                                 CONSTRAINT ck_seguimiento_estado CHECK (estado_resultante IN ('Vigente', 'Cumplido', 'Suspendido')),
                fecha_registro   timestamp    NOT NULL DEFAULT now()
            );
            CREATE INDEX IF NOT EXISTS idx_seguimientos_ajuste ON public.seguimientos_ajuste (id_ajuste, fecha);

            -- El historial de seguimiento no se modifica ni se borra (impedir_cambios() ya existe)
            DROP TRIGGER IF EXISTS trg_seguimientos_ajuste_inmutables ON public.seguimientos_ajuste;
            CREATE TRIGGER trg_seguimientos_ajuste_inmutables BEFORE UPDATE OR DELETE ON public.seguimientos_ajuste
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

            DO $$
            DECLARE
                t text;
            BEGIN
                FOREACH t IN ARRAY ARRAY['ajustes_razonables', 'seguimientos_ajuste'] LOOP
                    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t);
                    EXECUTE format('DROP POLICY IF EXISTS sin_acceso_api ON public.%I', t);
                    EXECUTE format('CREATE POLICY sin_acceso_api ON public.%I AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false)', t);
                END LOOP;
            END $$;
        SQL);
    }

    /**
     * No borra nada: el historial de seguimiento es evidencia del proceso.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

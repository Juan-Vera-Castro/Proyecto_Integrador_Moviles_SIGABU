<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Donaciones alimentarias (rama feature/modulo-donaciones-alimentarias), hechas de verdad:
     * - Firma de recepción del mercado: alim_entregas guarda la ruta del PNG y su huella.
     * - Priorizados de Trabajo Social: la lista que se carga por archivo. No crea solicitudes
     *   (el estudiante debe autorizar sus datos al solicitar); cuando un priorizado solicita,
     *   o si ya tiene una solicitud pendiente, su prioridad sube a la indicada.
     * Aditiva: columnas y tabla nuevas; las entregas ya registradas no cambian.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('alim_entregas')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- La tabla es inmutable (trigger): estas columnas solo se llenan al insertar.
            ALTER TABLE public.alim_entregas
                ADD COLUMN IF NOT EXISTS firma_ruta varchar(255),
                ADD COLUMN IF NOT EXISTS firma_sha256 char(64);

            CREATE TABLE IF NOT EXISTS public.alim_priorizados (
                id_priorizado   integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                documento       varchar(20) NOT NULL UNIQUE,
                codigo          varchar(30),
                estrato         smallint CONSTRAINT ck_alim_priorizado_estrato CHECK (estrato BETWEEN 1 AND 6),
                prioridad       varchar(5)  NOT NULL CONSTRAINT ck_alim_priorizado_prioridad CHECK (prioridad IN ('Alta', 'Media', 'Baja')),
                cargado_por     uuid        NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_cargue    timestamp   NOT NULL DEFAULT now()
            );

            -- Solo en Supabase: bloquea el acceso por su API pública, como las demás tablas.
            DO $$ BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
                    ALTER TABLE public.alim_priorizados ENABLE ROW LEVEL SECURITY;
                    DROP POLICY IF EXISTS sin_acceso_api ON public.alim_priorizados;
                    CREATE POLICY sin_acceso_api ON public.alim_priorizados AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);
                END IF;
            END $$;
        SQL);
    }

    /**
     * No borra nada: las firmas de entrega son evidencia.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

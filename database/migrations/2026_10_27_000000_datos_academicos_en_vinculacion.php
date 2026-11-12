<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos académicos del estudiante en su vinculación, para que la solicitud de cita no los
     * pregunte: la facultad (referencia al catálogo), la jornada y el semestre. Aditiva: columnas
     * nuevas; la columna de texto "facultad" se conserva, y donde su texto coincide con una
     * facultad del catálogo se completa la referencia.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('vinculacion') || ! Schema::hasTable('facultades')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.vinculacion
                ADD COLUMN IF NOT EXISTS id_facultad integer REFERENCES public.facultades (id_facultad) ON DELETE RESTRICT,
                ADD COLUMN IF NOT EXISTS jornada     varchar(10),
                ADD COLUMN IF NOT EXISTS semestre    smallint;
            ALTER TABLE public.vinculacion DROP CONSTRAINT IF EXISTS ck_vinculacion_jornada;
            ALTER TABLE public.vinculacion ADD  CONSTRAINT ck_vinculacion_jornada CHECK (jornada IN ('Diurna', 'Nocturna'));
            ALTER TABLE public.vinculacion DROP CONSTRAINT IF EXISTS ck_vinculacion_semestre;
            ALTER TABLE public.vinculacion ADD  CONSTRAINT ck_vinculacion_semestre CHECK (semestre BETWEEN 1 AND 12);

            -- Completa la referencia donde el texto ya coincide con el catálogo (con o sin "Facultad de ").
            UPDATE public.vinculacion v
               SET id_facultad = f.id_facultad
              FROM public.facultades f
             WHERE v.id_facultad IS NULL
               AND v.facultad IS NOT NULL
               AND (lower(f.nombre) = lower(trim(v.facultad)) OR lower(f.nombre) = lower('Facultad de ' || trim(v.facultad)));
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

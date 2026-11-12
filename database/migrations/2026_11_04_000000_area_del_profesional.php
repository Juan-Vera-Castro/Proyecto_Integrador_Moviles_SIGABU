<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Área del profesional: Psicología ('Salud Mental'), Inclusión social ('Inclusión') o 'Ambas'.
     * La designa el administrador. A los profesionales existentes se les deduce de sus
     * asignaciones y casos activos: si solo trabajan en un área quedan en ella; si trabajan
     * en las dos o no tienen datos, en 'Ambas'. Aditiva: una columna nueva.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('usuarios')) {
            return;
        }

        DB::statement('ALTER TABLE public.usuarios ADD COLUMN IF NOT EXISTS area_profesional varchar(20)');
        DB::statement('ALTER TABLE public.usuarios DROP CONSTRAINT IF EXISTS ck_usuarios_area_profesional');
        DB::statement("ALTER TABLE public.usuarios ADD CONSTRAINT ck_usuarios_area_profesional CHECK (area_profesional IN ('Salud Mental', 'Inclusión', 'Ambas'))");

        DB::statement(<<<'SQL'
            WITH trabajo AS (
                SELECT id_profesional, tipo_servicio
                FROM public.asignaciones_profesional
                WHERE activa
                UNION
                SELECT c.id_profesional, s.tipo_servicio
                FROM public.casos c
                JOIN public.solicitudes_atencion s ON s.id_solicitud = c.id_solicitud_origen
                WHERE c.id_profesional IS NOT NULL
                  AND c.estado_caso IN ('Abierto', 'En Seguimiento')
            )
            UPDATE public.usuarios u
            SET area_profesional = COALESCE((
                SELECT CASE WHEN count(*) = 1 THEN min(t.tipo_servicio) ELSE 'Ambas' END
                FROM trabajo t
                WHERE t.id_profesional = u.id_usuario
                GROUP BY t.id_profesional
            ), 'Ambas')
            WHERE u.area_profesional IS NULL
              AND u.id_rol = (SELECT id_rol FROM public.roles WHERE nombre_rol = 'Profesional')
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

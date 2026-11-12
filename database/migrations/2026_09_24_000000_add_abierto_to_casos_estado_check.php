<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-004: un caso abierto por un profesional queda en estado "Abierto".
     * La tabla casos vive en la base compartida de Supabase y su CHECK
     * ck_casos_estado no incluía ese valor; se amplía sin quitar ninguno.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::statement('ALTER TABLE casos DROP CONSTRAINT IF EXISTS ck_casos_estado');
        DB::statement("ALTER TABLE casos ADD CONSTRAINT ck_casos_estado CHECK (estado_caso IN ('Abierto', 'Pendiente Asignación', 'En Seguimiento', 'Cerrado'))");
    }

    /**
     * Falla si ya existen casos en estado "Abierto": hay que cambiarlos antes de revertir.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::statement('ALTER TABLE casos DROP CONSTRAINT IF EXISTS ck_casos_estado');
        DB::statement("ALTER TABLE casos ADD CONSTRAINT ck_casos_estado CHECK (estado_caso IN ('Pendiente Asignación', 'En Seguimiento', 'Cerrado'))");
    }
};

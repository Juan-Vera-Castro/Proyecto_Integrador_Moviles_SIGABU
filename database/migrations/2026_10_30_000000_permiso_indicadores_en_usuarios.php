<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-032: el tablero de indicadores institucionales lo ven el administrador (directivo) y
     * los profesionales que él autorice. Aditiva: una columna nueva, apagada por defecto.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('usuarios')) {
            return;
        }

        DB::statement('ALTER TABLE public.usuarios ADD COLUMN IF NOT EXISTS ve_indicadores boolean NOT NULL DEFAULT false');
    }

    /**
     * No borra nada.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

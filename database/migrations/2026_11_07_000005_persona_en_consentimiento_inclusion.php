<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre y documento tal como los dejó la persona en el formato: puede corregirlos sin que
     * cambie su cuenta. Son columnas nuevas y vacías: las filas existentes no se modifican.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('inclusion_consentimientos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.inclusion_consentimientos
                ADD COLUMN IF NOT EXISTS nombre_persona varchar(150),
                ADD COLUMN IF NOT EXISTS documento_persona varchar(20);
        SQL);
    }

    /**
     * No borra nada: los consentimientos son evidencia.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

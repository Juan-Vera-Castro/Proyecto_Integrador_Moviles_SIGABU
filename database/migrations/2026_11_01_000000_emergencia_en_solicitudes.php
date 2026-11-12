<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El estudiante puede marcar su solicitud como caso de emergencia (atención urgente); el
     * psicólogo la ve resaltada. Aditiva: una columna nueva, apagada por defecto.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('solicitudes_atencion')) {
            return;
        }

        DB::statement('ALTER TABLE public.solicitudes_atencion ADD COLUMN IF NOT EXISTS es_emergencia boolean NOT NULL DEFAULT false');
    }

    /**
     * No borra nada.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

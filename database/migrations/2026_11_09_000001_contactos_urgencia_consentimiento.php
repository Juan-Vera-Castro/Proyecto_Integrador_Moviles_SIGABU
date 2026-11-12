<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contactos de urgencia del consentimiento: nombre, teléfono y parentesco de una o varias
     * personas (contactos_urgencia, jsonb). contacto_urgencia se conserva como resumen legible
     * que llena el texto del consentimiento, y se amplía para que quepan varias personas.
     * Aditiva: una columna nueva y una columna más ancha; no cambia ni borra lo ya firmado.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('consentimiento_aceptaciones')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.consentimiento_aceptaciones
                ADD COLUMN IF NOT EXISTS contactos_urgencia jsonb;

            ALTER TABLE public.consentimiento_aceptaciones
                ALTER COLUMN contacto_urgencia TYPE varchar(500);
        SQL);
    }

    /**
     * No borra nada: la columna nueva puede tener datos de consentimientos firmados.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

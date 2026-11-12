<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consentimiento de un menor de edad: además del acudiente, firma el propio estudiante.
     * firma_ruta / firma_sha256 siguen siendo la firma de quien otorga (el acudiente); se
     * agregan la firma del estudiante menor y el teléfono del acudiente.
     * Aditiva: columnas nuevas; lo ya firmado no cambia.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('consentimiento_aceptaciones')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.consentimiento_aceptaciones
                ADD COLUMN IF NOT EXISTS acudiente_telefono varchar(20),
                ADD COLUMN IF NOT EXISTS firma_estudiante_ruta varchar(255),
                ADD COLUMN IF NOT EXISTS firma_estudiante_sha256 char(64);
        SQL);
    }

    /**
     * No borra nada: las columnas pueden tener firmas registradas.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

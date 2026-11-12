<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los documentos del repositorio pueden ser un archivo subido (no solo un enlace), y los
     * publican también los equipos VBG, Alimentos y los profesionales. Aditiva: columnas nuevas,
     * el enlace pasa a ser opcional (todo documento tiene enlace o archivo) y un bucket privado
     * nuevo en Supabase Storage. No toca los documentos existentes.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('repositorio_documentos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.repositorio_documentos
                ADD COLUMN IF NOT EXISTS archivo_ruta   varchar(255),
                ADD COLUMN IF NOT EXISTS archivo_nombre varchar(200),
                ADD COLUMN IF NOT EXISTS archivo_mime   varchar(100),
                ADD COLUMN IF NOT EXISTS archivo_bytes  integer,
                ADD COLUMN IF NOT EXISTS archivo_sha256 char(64);
            ALTER TABLE public.repositorio_documentos ALTER COLUMN url DROP NOT NULL;
            ALTER TABLE public.repositorio_documentos DROP CONSTRAINT IF EXISTS ck_repositorio_contenido;
            ALTER TABLE public.repositorio_documentos ADD  CONSTRAINT ck_repositorio_contenido
                CHECK (url IS NOT NULL OR archivo_ruta IS NOT NULL);

            -- Bucket privado para los archivos (se usa cuando estén las claves S3 en el .env).
            -- Sin políticas en storage.objects: solo Laravel entra, con las claves del proyecto.
            INSERT INTO storage.buckets (id, name, public, file_size_limit)
            VALUES ('repositorio-documentos', 'repositorio-documentos', false, 10485760)
            ON CONFLICT (id) DO NOTHING;
        SQL);
    }

    /**
     * No borra nada: quitar las columnas perdería los archivos ya publicados.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

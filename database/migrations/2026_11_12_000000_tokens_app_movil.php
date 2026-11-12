<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens de acceso de la app móvil del estudiante (Laravel Sanctum). tokenable_id es el
     * UUID del usuario, por eso uuidMorphs.
     *
     * Aditiva: solo crea una tabla nueva. Corre en PostgreSQL y en SQLite (pruebas y E2E).
     * En PostgreSQL, sigabu_app recibe además DELETE: cerrar sesión en la app borra su token.
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->uuidMorphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DO $$ BEGIN
                    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'sigabu_app') THEN
                        GRANT SELECT, INSERT, UPDATE, DELETE ON public.personal_access_tokens TO sigabu_app;
                        GRANT USAGE, SELECT ON SEQUENCE public.personal_access_tokens_id_seq TO sigabu_app;
                    END IF;

                    -- Solo en Supabase: bloquea el acceso por su API pública, como las demás tablas.
                    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
                        ALTER TABLE public.personal_access_tokens ENABLE ROW LEVEL SECURITY;
                        DROP POLICY IF EXISTS sin_acceso_api ON public.personal_access_tokens;
                        CREATE POLICY sin_acceso_api ON public.personal_access_tokens AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);
                    END IF;
                END $$;
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};

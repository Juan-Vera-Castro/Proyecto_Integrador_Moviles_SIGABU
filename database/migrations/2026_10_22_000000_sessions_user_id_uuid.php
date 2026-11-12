<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En Supabase, sessions.user_id quedó como bigint (la creó una versión anterior de la
     * migración, cuando el login usaba la tabla users). Los id de usuarios son UUID, así que
     * al iniciar sesión PostgreSQL rechaza guardar el usuario en la sesión. Se cambia a texto
     * conservando los valores que ya tenga. No borra filas ni columnas.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('sessions')) {
            return;
        }

        $tipo = DB::table('information_schema.columns')
            ->where('table_schema', 'public')->where('table_name', 'sessions')->where('column_name', 'user_id')
            ->value('data_type');

        if (in_array($tipo, ['bigint', 'integer', 'smallint'], true)) {
            DB::statement('ALTER TABLE public.sessions ALTER COLUMN user_id TYPE varchar(36) USING user_id::text');
        }
    }

    /**
     * No vuelve a bigint: los UUID de las sesiones abiertas no caben en un número.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

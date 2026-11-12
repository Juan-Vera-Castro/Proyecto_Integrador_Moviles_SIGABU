<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avisos de la campana (tabla estándar de Laravel; notifiable_id es el UUID del usuario) y
     * registro de los recordatorios ya enviados, para no repetirlos: la restricción única
     * (tipo, referencia) garantiza un solo aviso aunque el programador corra dos veces.
     *
     * Aditiva: solo crea tablas nuevas. Corre en PostgreSQL y en SQLite (pruebas).
     * En PostgreSQL propio, sigabu_app recibe SELECT, INSERT y UPDATE por los privilegios por
     * defecto de 03_permisos.sql: no puede borrar avisos ni el registro de recordatorios.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->uuidMorphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['notifiable_id', 'read_at'], 'notifications_no_leidas_idx');
            });
        }

        if (! Schema::hasTable('recordatorios_enviados')) {
            Schema::create('recordatorios_enviados', function (Blueprint $table) {
                $table->id('id_recordatorio');
                $table->string('tipo', 40);
                $table->string('referencia', 64);
                $table->timestamp('enviado_en')->useCurrent();

                $table->unique(['tipo', 'referencia'], 'uq_recordatorios_tipo_referencia');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // Solo en Supabase: bloquea el acceso por su API pública, como las demás tablas.
            DB::unprepared(<<<'SQL'
                DO $$ BEGIN
                    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
                        ALTER TABLE public.notifications ENABLE ROW LEVEL SECURITY;
                        ALTER TABLE public.recordatorios_enviados ENABLE ROW LEVEL SECURITY;
                        DROP POLICY IF EXISTS sin_acceso_api ON public.notifications;
                        DROP POLICY IF EXISTS sin_acceso_api ON public.recordatorios_enviados;
                        CREATE POLICY sin_acceso_api ON public.notifications AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);
                        CREATE POLICY sin_acceso_api ON public.recordatorios_enviados AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);
                    END IF;
                END $$;
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios_enviados');
        Schema::dropIfExists('notifications');
    }
};

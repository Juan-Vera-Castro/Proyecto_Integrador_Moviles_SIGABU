<?php

use App\Documentos\ConsentimientoInclusion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consentimiento del Proceso de Inclusión Universitaria. Sigue usando
     * consentimiento_aceptaciones, para conservar la firma, la contrafirma y el bloqueo de la
     * atención; lo propio del formato (las 4 decisiones, la revocación y la firma en papel) va
     * en inclusion_consentimientos.
     *
     * Las dos restricciones se amplían con NOT VALID: las filas existentes no se revisan de
     * nuevo y las nuevas sí, igual que ck_aceptacion_encabezado.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('consentimiento_aceptaciones')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- Quita los CHECK por su contenido: el nombre del de medio lo generó PostgreSQL
            DO $$
            DECLARE
                r record;
            BEGIN
                FOR r IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = 'public.consentimiento_aceptaciones'::regclass
                      AND contype = 'c'
                      AND (pg_get_constraintdef(oid) LIKE '%firma_tactil_enlace%' OR conname = 'ck_aceptacion_datos_texto')
                LOOP
                    EXECUTE format('ALTER TABLE public.consentimiento_aceptaciones DROP CONSTRAINT %I', r.conname);
                END LOOP;
            END $$;

            ALTER TABLE public.consentimiento_aceptaciones ADD CONSTRAINT consentimiento_aceptaciones_medio_check
                CHECK (medio IN ('firma_tactil_expediente', 'firma_tactil_enlace', 'firma_tactil_inclusion', 'firma_papel_inclusion')) NOT VALID;

            -- El formato de Inclusión no pide lugar de expedición ni EPS
            ALTER TABLE public.consentimiento_aceptaciones ADD CONSTRAINT ck_aceptacion_datos_texto CHECK (
                decision = 'Rechazado'
             OR medio IN ('firma_tactil_inclusion', 'firma_papel_inclusion')
             OR (otorgante = 'Estudiante' AND lugar_expedicion IS NOT NULL AND eps_ips IS NOT NULL AND contacto_urgencia IS NOT NULL)
             OR (otorgante = 'Acudiente' AND acudiente_lugar_expedicion IS NOT NULL AND eps_ips IS NOT NULL)
            ) NOT VALID;

            CREATE TABLE IF NOT EXISTS public.inclusion_consentimientos (
                id_aceptacion            uuid PRIMARY KEY REFERENCES public.consentimiento_aceptaciones (id_aceptacion) ON DELETE RESTRICT,
                decision_acompanamiento  boolean      NOT NULL DEFAULT false,
                decision_datos_generales boolean      NOT NULL DEFAULT false,
                decision_datos_sensibles boolean      NOT NULL DEFAULT false,
                decision_compartir       boolean      NOT NULL DEFAULT false,
                numero_contacto          varchar(15),
                vinculacion_detalle      varchar(30),
                es_revocacion            boolean      NOT NULL DEFAULT false,
                motivo_papel             text,
                fecha_firma_papel        date
            );

            -- impedir_cambios() la creó la migración de consentimientos
            DROP TRIGGER IF EXISTS trg_inclusion_consentimientos_inmutables ON public.inclusion_consentimientos;
            CREATE TRIGGER trg_inclusion_consentimientos_inmutables
                BEFORE UPDATE OR DELETE ON public.inclusion_consentimientos
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'sigabu_app') THEN
                    GRANT SELECT, INSERT, UPDATE ON public.inclusion_consentimientos TO sigabu_app;
                END IF;
            END $$;
        SQL);

        ConsentimientoInclusion::cargar();
    }

    /**
     * No borra nada: los consentimientos son evidencia.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

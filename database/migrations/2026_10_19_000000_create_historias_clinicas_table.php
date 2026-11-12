<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historia clínica psicológica de los casos de Salud Mental: una por expediente.
     * La registra el psicólogo del caso cuando hay consentimiento aceptado. Como exige la
     * Res. 1995 de 1999, no se modifica ni se borra: las correcciones y los complementos son
     * anotaciones con fecha y autor, también inmutables. Cada registro queda en
     * auditoria_expedientes. Las tablas nuevas cierran la API pública de Supabase con RLS.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.historias_clinicas (
                id_historia             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso                 uuid      NOT NULL UNIQUE REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_profesional          uuid      NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                motivo_consulta         text      NOT NULL,
                historia_problema       text,
                antecedentes_personales text,
                antecedentes_familiares text,
                antecedentes_medicos    text,
                examen_mental           text,
                impresion_diagnostica   text      NOT NULL,
                plan_intervencion       text      NOT NULL,
                observaciones           text,
                fecha_registro          timestamp NOT NULL DEFAULT now()
            );

            CREATE TABLE IF NOT EXISTS public.historia_clinica_anotaciones (
                id_anotacion   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_historia    uuid        NOT NULL REFERENCES public.historias_clinicas (id_historia) ON DELETE RESTRICT,
                id_profesional uuid        NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                apartado       varchar(40) CHECK (apartado IN (
                                   'motivo_consulta', 'historia_problema', 'antecedentes_personales', 'antecedentes_familiares',
                                   'antecedentes_medicos', 'examen_mental', 'impresion_diagnostica', 'plan_intervencion', 'observaciones')),
                texto          text        NOT NULL,
                fecha_hora     timestamp   NOT NULL DEFAULT now()
            );
            CREATE INDEX IF NOT EXISTS idx_anotaciones_historia_fecha
                ON public.historia_clinica_anotaciones (id_historia, fecha_hora);

            -- Inmutabilidad (RN-06): impedir_cambios() la creó la migración de consentimientos
            DROP TRIGGER IF EXISTS trg_historias_clinicas_inmutables ON public.historias_clinicas;
            CREATE TRIGGER trg_historias_clinicas_inmutables
                BEFORE UPDATE OR DELETE ON public.historias_clinicas
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();
            DROP TRIGGER IF EXISTS trg_historia_anotaciones_inmutables ON public.historia_clinica_anotaciones;
            CREATE TRIGGER trg_historia_anotaciones_inmutables
                BEFORE UPDATE OR DELETE ON public.historia_clinica_anotaciones
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

            DO $$
            DECLARE
                t text;
            BEGIN
                FOREACH t IN ARRAY ARRAY['historias_clinicas', 'historia_clinica_anotaciones'] LOOP
                    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t);
                    EXECUTE format('DROP POLICY IF EXISTS sin_acceso_api ON public.%I', t);
                    EXECUTE format('CREATE POLICY sin_acceso_api ON public.%I AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false)', t);
                END LOOP;
            END $$;

            -- Acciones de auditoría de la historia clínica: se reemplaza el CHECK de accion,
            -- cualquiera sea el nombre que Postgres le dio al crearlo en línea.
            DO $$
            DECLARE
                c text;
            BEGIN
                FOR c IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = 'public.auditoria_expedientes'::regclass AND contype = 'c'
                      AND pg_get_constraintdef(oid) LIKE '%accion%'
                LOOP
                    EXECUTE format('ALTER TABLE public.auditoria_expedientes DROP CONSTRAINT %I', c);
                END LOOP;
            END $$;
            ALTER TABLE public.auditoria_expedientes ADD  CONSTRAINT ck_auditoria_accion CHECK (accion IN (
                'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                'datos_estudiante_actualizados', 'formato_actualizado',
                'historia_clinica_registrada', 'historia_clinica_anotada'));
        SQL);
    }

    /**
     * Solo debe usarse sin historias registradas: la auditoría no se puede borrar, así que
     * si ya tiene acciones de historia clínica, restaurar el CHECK anterior falla.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.auditoria_expedientes DROP CONSTRAINT IF EXISTS ck_auditoria_accion;
            ALTER TABLE public.auditoria_expedientes ADD  CONSTRAINT auditoria_expedientes_accion_check CHECK (accion IN (
                'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                'datos_estudiante_actualizados', 'formato_actualizado'));

            DROP TRIGGER IF EXISTS trg_historia_anotaciones_inmutables ON public.historia_clinica_anotaciones;
            DROP TRIGGER IF EXISTS trg_historias_clinicas_inmutables ON public.historias_clinicas;
            DROP TABLE IF EXISTS public.historia_clinica_anotaciones;
            DROP TABLE IF EXISTS public.historias_clinicas;
        SQL);
    }
};

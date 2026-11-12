<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que pide el formato en papel (GBU-GPGR-F-032 y F-019) y faltaba en la evidencia:
     * el encabezado (vinculación, programa académico, semestre y dependencia o facultad) y la
     * firma del profesional tratante, que contrafirma cada consentimiento aceptado. La firma
     * del profesional es inmutable como el resto de la evidencia y cierra la API de Supabase con RLS.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('consentimiento_aceptaciones')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- 1. Encabezado del formato. NOT VALID: lo exige a los consentimientos nuevos sin
            --    rechazar los que se aceptaron antes de pedirlo.
            ALTER TABLE public.consentimiento_aceptaciones
                ADD COLUMN IF NOT EXISTS vinculacion          varchar(15),
                ADD COLUMN IF NOT EXISTS programa_academico   varchar(150),
                ADD COLUMN IF NOT EXISTS semestre             smallint,
                ADD COLUMN IF NOT EXISTS dependencia_facultad varchar(150);
            ALTER TABLE public.consentimiento_aceptaciones DROP CONSTRAINT IF EXISTS ck_aceptacion_vinculacion;
            ALTER TABLE public.consentimiento_aceptaciones ADD  CONSTRAINT ck_aceptacion_vinculacion
                CHECK (vinculacion IN ('Estudiante', 'Docente TC', 'Docente TCO', 'Docente HC', 'Funcionario', 'Otro'));
            ALTER TABLE public.consentimiento_aceptaciones DROP CONSTRAINT IF EXISTS ck_aceptacion_semestre;
            ALTER TABLE public.consentimiento_aceptaciones ADD  CONSTRAINT ck_aceptacion_semestre CHECK (semestre BETWEEN 1 AND 12);
            ALTER TABLE public.consentimiento_aceptaciones DROP CONSTRAINT IF EXISTS ck_aceptacion_encabezado;
            ALTER TABLE public.consentimiento_aceptaciones ADD  CONSTRAINT ck_aceptacion_encabezado CHECK (
                decision = 'Rechazado'
             OR (vinculacion IS NOT NULL AND dependencia_facultad IS NOT NULL
                 AND (vinculacion <> 'Estudiante' OR (programa_academico IS NOT NULL AND semestre IS NOT NULL)))
            ) NOT VALID;

            -- 2. Firma del profesional tratante: una por consentimiento aceptado
            CREATE TABLE IF NOT EXISTS public.consentimiento_firmas_profesional (
                id_aceptacion  uuid PRIMARY KEY REFERENCES public.consentimiento_aceptaciones (id_aceptacion) ON DELETE RESTRICT,
                id_profesional uuid         NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_hora     timestamp    NOT NULL DEFAULT now(),
                firma_ruta     varchar(255) NOT NULL,
                firma_sha256   char(64)     NOT NULL,
                ip_origen      varchar(45),
                user_agent     varchar(255)
            );

            -- Solo sobre una aceptación, y la firma el profesional que nombra el texto
            CREATE OR REPLACE FUNCTION public.validar_firma_profesional()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM public.consentimiento_aceptaciones
                    WHERE id_aceptacion = NEW.id_aceptacion AND decision = 'Aceptado' AND id_profesional = NEW.id_profesional
                ) THEN
                    RAISE EXCEPTION 'Solo el profesional tratante firma un consentimiento aceptado (%).', NEW.id_aceptacion;
                END IF;
                RETURN NEW;
            END $$;
            DROP TRIGGER IF EXISTS trg_validar_firma_profesional ON public.consentimiento_firmas_profesional;
            CREATE TRIGGER trg_validar_firma_profesional
                BEFORE INSERT ON public.consentimiento_firmas_profesional
                FOR EACH ROW EXECUTE FUNCTION public.validar_firma_profesional();

            -- Inmutabilidad (RN-06): impedir_cambios() la creó la migración de consentimientos
            DROP TRIGGER IF EXISTS trg_firmas_profesional_inmutables ON public.consentimiento_firmas_profesional;
            CREATE TRIGGER trg_firmas_profesional_inmutables
                BEFORE UPDATE OR DELETE ON public.consentimiento_firmas_profesional
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

            ALTER TABLE public.consentimiento_firmas_profesional ENABLE ROW LEVEL SECURITY;
            DROP POLICY IF EXISTS sin_acceso_api ON public.consentimiento_firmas_profesional;
            CREATE POLICY sin_acceso_api ON public.consentimiento_firmas_profesional
                AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);

            -- 3. Acción de auditoría de la firma del profesional
            ALTER TABLE public.auditoria_expedientes DROP CONSTRAINT IF EXISTS ck_auditoria_accion;
            ALTER TABLE public.auditoria_expedientes ADD  CONSTRAINT ck_auditoria_accion CHECK (accion IN (
                'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                'datos_estudiante_actualizados', 'formato_actualizado',
                'historia_clinica_registrada', 'historia_clinica_anotada',
                'consentimiento_firmado_profesional'));
        SQL);
    }

    /**
     * Solo debe usarse sin firmas de profesional registradas: la tabla es inmutable y la
     * auditoría no se puede borrar, así que con datos el CHECK anterior falla.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('consentimiento_aceptaciones')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.auditoria_expedientes DROP CONSTRAINT IF EXISTS ck_auditoria_accion;
            ALTER TABLE public.auditoria_expedientes ADD  CONSTRAINT ck_auditoria_accion CHECK (accion IN (
                'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                'datos_estudiante_actualizados', 'formato_actualizado',
                'historia_clinica_registrada', 'historia_clinica_anotada'));

            DROP TRIGGER  IF EXISTS trg_firmas_profesional_inmutables ON public.consentimiento_firmas_profesional;
            DROP TRIGGER  IF EXISTS trg_validar_firma_profesional ON public.consentimiento_firmas_profesional;
            DROP TABLE    IF EXISTS public.consentimiento_firmas_profesional;
            DROP FUNCTION IF EXISTS public.validar_firma_profesional();

            ALTER TABLE public.consentimiento_aceptaciones DROP CONSTRAINT IF EXISTS ck_aceptacion_encabezado;
            ALTER TABLE public.consentimiento_aceptaciones DROP CONSTRAINT IF EXISTS ck_aceptacion_semestre;
            ALTER TABLE public.consentimiento_aceptaciones DROP CONSTRAINT IF EXISTS ck_aceptacion_vinculacion;
            ALTER TABLE public.consentimiento_aceptaciones
                DROP COLUMN IF EXISTS dependencia_facultad,
                DROP COLUMN IF EXISTS semestre,
                DROP COLUMN IF EXISTS programa_academico,
                DROP COLUMN IF EXISTS vinculacion;
        SQL);
    }
};

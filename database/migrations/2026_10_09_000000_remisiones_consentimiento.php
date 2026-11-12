<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consentimiento informado con firma táctil y remisión externa con el formato
     * GBU-GPGR-F-016 (specs/remisiones-consentimiento/design.md, sección 2.2).
     *
     * Cambios aditivos sobre tablas existentes (personas, modelos_consentimiento) y cuatro
     * tablas nuevas: la evidencia del consentimiento, el detalle de la remisión externa y la
     * auditoría son inmutables. Las tablas nuevas cierran la API pública de Supabase con RLS;
     * el backend entra como postgres. Corre en una transacción: si algo falla, no se aplica nada.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            -- 1. Datos del estudiante que pide el formato de remisión (D-01)
            ALTER TABLE public.personas
                ADD COLUMN IF NOT EXISTS tipo_documento   varchar(2),
                ADD COLUMN IF NOT EXISTS sexo             char(1),
                ADD COLUMN IF NOT EXISTS fecha_nacimiento date;
            ALTER TABLE public.personas DROP CONSTRAINT IF EXISTS ck_personas_tipo_documento;
            ALTER TABLE public.personas ADD  CONSTRAINT ck_personas_tipo_documento CHECK (tipo_documento IN ('TI', 'CC', 'CE'));
            ALTER TABLE public.personas DROP CONSTRAINT IF EXISTS ck_personas_sexo;
            ALTER TABLE public.personas ADD  CONSTRAINT ck_personas_sexo CHECK (sexo IN ('F', 'M'));
            ALTER TABLE public.personas DROP CONSTRAINT IF EXISTS ck_personas_fecha_nacimiento;
            ALTER TABLE public.personas ADD  CONSTRAINT ck_personas_fecha_nacimiento CHECK (fecha_nacimiento >= DATE '1900-01-01');

            -- 2. Población del modelo de consentimiento: general o menores de edad (D-03)
            ALTER TABLE public.modelos_consentimiento
                ADD COLUMN IF NOT EXISTS poblacion varchar(10) NOT NULL DEFAULT 'General';
            ALTER TABLE public.modelos_consentimiento DROP CONSTRAINT IF EXISTS ck_modelos_poblacion;
            ALTER TABLE public.modelos_consentimiento ADD  CONSTRAINT ck_modelos_poblacion CHECK (poblacion IN ('General', 'Menores'));

            -- 3. Evidencia del consentimiento (D-18, D-24, D-25): una fila por decisión
            CREATE TABLE IF NOT EXISTS public.consentimiento_aceptaciones (
                id_aceptacion              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso                    uuid        NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_version                 integer     NOT NULL REFERENCES public.versiones_consentimiento (id_version) ON DELETE RESTRICT,
                decision                   varchar(10) NOT NULL CHECK (decision IN ('Aceptado', 'Rechazado')),
                fecha_hora                 timestamp   NOT NULL DEFAULT now(),
                medio                      varchar(30) NOT NULL CHECK (medio IN ('firma_tactil_expediente', 'firma_tactil_enlace')),
                id_profesional             uuid        NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                otorgante                  varchar(10) NOT NULL DEFAULT 'Estudiante' CHECK (otorgante IN ('Estudiante', 'Acudiente')),
                acudiente_nombre           varchar(150),
                acudiente_tipo_documento   varchar(3)  CHECK (acudiente_tipo_documento IN ('CC', 'CE', 'TI', 'PA')),
                acudiente_numero_documento varchar(20),
                acudiente_parentesco       varchar(50),
                acudiente_lugar_expedicion varchar(100),
                lugar_expedicion           varchar(100),
                eps_ips                    varchar(150),
                contacto_urgencia          varchar(150),
                texto_presentado           text        NOT NULL,
                texto_sha256               char(64)    NOT NULL,
                firma_ruta                 varchar(255),
                firma_sha256               char(64),
                ip_origen                  varchar(45),
                user_agent                 varchar(255),
                CONSTRAINT ck_aceptacion_firma CHECK (
                    (decision = 'Aceptado'  AND firma_ruta IS NOT NULL AND firma_sha256 IS NOT NULL)
                 OR (decision = 'Rechazado' AND firma_ruta IS NULL     AND firma_sha256 IS NULL)),
                CONSTRAINT ck_aceptacion_acudiente CHECK (
                    (otorgante = 'Estudiante' AND acudiente_nombre IS NULL AND acudiente_tipo_documento IS NULL
                        AND acudiente_numero_documento IS NULL AND acudiente_parentesco IS NULL AND acudiente_lugar_expedicion IS NULL)
                 OR (otorgante = 'Acudiente'  AND acudiente_nombre IS NOT NULL AND acudiente_tipo_documento IS NOT NULL
                        AND acudiente_numero_documento IS NOT NULL AND acudiente_parentesco IS NOT NULL)),
                CONSTRAINT ck_aceptacion_datos_texto CHECK (
                    decision = 'Rechazado'
                 OR (otorgante = 'Estudiante' AND lugar_expedicion IS NOT NULL AND eps_ips IS NOT NULL AND contacto_urgencia IS NOT NULL)
                 OR (otorgante = 'Acudiente'  AND acudiente_lugar_expedicion IS NOT NULL AND eps_ips IS NOT NULL))
            );
            CREATE INDEX IF NOT EXISTS idx_aceptaciones_caso_fecha
                ON public.consentimiento_aceptaciones (id_caso, fecha_hora DESC);

            -- 4. Formatos institucionales configurables desde Administración (D-17)
            CREATE TABLE IF NOT EXISTS public.formatos_institucionales (
                id_formato          integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                clave               varchar(40)  NOT NULL UNIQUE,
                proceso             varchar(100) NOT NULL,
                nombre              varchar(100) NOT NULL,
                codigo              varchar(30)  NOT NULL,
                version             varchar(10)  NOT NULL,
                fecha_formato       date         NOT NULL,
                actualizado_por     uuid REFERENCES public.usuarios (id_usuario) ON DELETE SET NULL,
                fecha_actualizacion timestamp    NOT NULL DEFAULT now()
            );
            INSERT INTO public.formatos_institucionales (clave, proceso, nombre, codigo, version, fecha_formato)
            VALUES ('REMISION_PSICOLOGICA', 'GESTIÓN APOYO AL ESTUDIANTE', 'REMISIÓN PSICOLÓGICA', 'GBU-GPGR-F-016', '3', DATE '2024-04-18')
            ON CONFLICT (clave) DO NOTHING;

            -- 5. Detalle de la remisión externa (D-19): 1 a 1 con su actuación y copia inmutable (RN-09)
            CREATE TABLE IF NOT EXISTS public.remisiones_externas (
                id_actuacion                 uuid PRIMARY KEY REFERENCES public.actuaciones (id_actuacion) ON DELETE RESTRICT,
                categoria                    varchar(12)  NOT NULL CHECK (categoria IN ('URGENTE', 'PRIORITARIO')),
                estudiante_nombre            varchar(150) NOT NULL,
                estudiante_tipo_documento    varchar(2)   NOT NULL CHECK (estudiante_tipo_documento IN ('TI', 'CC', 'CE')),
                estudiante_numero_documento  varchar(20)  NOT NULL,
                estudiante_sexo              char(1)      NOT NULL CHECK (estudiante_sexo IN ('F', 'M')),
                estudiante_fecha_nacimiento  date         NOT NULL,
                estudiante_edad              smallint     NOT NULL CHECK (estudiante_edad BETWEEN 0 AND 120),
                id_cita                      uuid REFERENCES public.actuaciones (id_actuacion) ON DELETE RESTRICT,
                fecha_hora_atencion          timestamp    NOT NULL,
                profesional_nombre           varchar(150) NOT NULL,
                acompanante_nombre           varchar(150),
                acompanante_parentesco       varchar(50),
                acompanante_tipo_documento   varchar(3)   CHECK (acompanante_tipo_documento IN ('CC', 'CE', 'TI', 'PA')),
                acompanante_numero_documento varchar(20),
                acompanante_telefono         varchar(20),
                formato_codigo               varchar(30)  NOT NULL,
                formato_version              varchar(10)  NOT NULL,
                formato_fecha                date         NOT NULL,
                CONSTRAINT ck_remision_acompanante CHECK (
                    (categoria = 'URGENTE' AND acompanante_nombre IS NOT NULL AND acompanante_parentesco IS NOT NULL
                        AND acompanante_tipo_documento IS NOT NULL AND acompanante_numero_documento IS NOT NULL AND acompanante_telefono IS NOT NULL)
                 OR (categoria = 'PRIORITARIO' AND acompanante_nombre IS NULL AND acompanante_parentesco IS NULL
                        AND acompanante_tipo_documento IS NULL AND acompanante_numero_documento IS NULL AND acompanante_telefono IS NULL))
            );

            -- Solo se permite sobre una actuación de tipo Remisión con destino Externa
            CREATE OR REPLACE FUNCTION public.validar_remision_externa()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM public.actuaciones
                    WHERE id_actuacion = NEW.id_actuacion AND tipo_actuacion = 'Remisión' AND destino_remision = 'Externa'
                ) THEN
                    RAISE EXCEPTION 'La actuación % no es una remisión externa.', NEW.id_actuacion;
                END IF;
                RETURN NEW;
            END $$;
            DROP TRIGGER IF EXISTS trg_validar_remision_externa ON public.remisiones_externas;
            CREATE TRIGGER trg_validar_remision_externa
                BEFORE INSERT ON public.remisiones_externas
                FOR EACH ROW EXECUTE FUNCTION public.validar_remision_externa();

            -- 6. Auditoría de expedientes (D-22)
            CREATE TABLE IF NOT EXISTS public.auditoria_expedientes (
                id_auditoria bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                id_usuario   uuid REFERENCES public.usuarios (id_usuario) ON DELETE SET NULL,
                id_caso      uuid REFERENCES public.casos (id_caso) ON DELETE SET NULL,
                accion       varchar(40) NOT NULL CHECK (accion IN (
                                 'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                                 'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                                 'datos_estudiante_actualizados', 'formato_actualizado')),
                detalle      jsonb,
                fecha_hora   timestamp NOT NULL DEFAULT now(),
                ip_origen    varchar(45)
            );
            CREATE INDEX IF NOT EXISTS idx_auditoria_expedientes_caso
                ON public.auditoria_expedientes (id_caso, fecha_hora DESC);

            -- 7. Inmutabilidad de la evidencia, la remisión externa y la auditoría (RN-06)
            CREATE OR REPLACE FUNCTION public.impedir_cambios()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Los registros de % no se pueden modificar ni borrar.', TG_TABLE_NAME;
            END $$;
            DROP TRIGGER IF EXISTS trg_aceptaciones_inmutables ON public.consentimiento_aceptaciones;
            CREATE TRIGGER trg_aceptaciones_inmutables
                BEFORE UPDATE OR DELETE ON public.consentimiento_aceptaciones
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();
            DROP TRIGGER IF EXISTS trg_remisiones_externas_inmutables ON public.remisiones_externas;
            CREATE TRIGGER trg_remisiones_externas_inmutables
                BEFORE UPDATE OR DELETE ON public.remisiones_externas
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();
            DROP TRIGGER IF EXISTS trg_auditoria_expedientes_inmutable ON public.auditoria_expedientes;
            CREATE TRIGGER trg_auditoria_expedientes_inmutable
                BEFORE UPDATE OR DELETE ON public.auditoria_expedientes
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

            -- 8. RLS: la API pública de Supabase no ve las tablas nuevas
            DO $$
            DECLARE
                t text;
            BEGIN
                FOREACH t IN ARRAY ARRAY['consentimiento_aceptaciones', 'formatos_institucionales', 'remisiones_externas', 'auditoria_expedientes'] LOOP
                    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t);
                    EXECUTE format('DROP POLICY IF EXISTS sin_acceso_api ON public.%I', t);
                    EXECUTE format('CREATE POLICY sin_acceso_api ON public.%I AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false)', t);
                END LOOP;
            END $$;

            -- 9. Bucket privado para las firmas (D-02). Sin políticas en storage.objects:
            --    anon y authenticated no tienen acceso; Laravel entra con las claves S3 del proyecto.
            INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
            VALUES ('consentimientos-firmas', 'consentimientos-firmas', false, 262144, ARRAY['image/png'])
            ON CONFLICT (id) DO NOTHING;
        SQL);
    }

    /**
     * Deja la base como estaba. No borra el bucket de firmas, para no perder evidencia:
     * si hay que quitarlo, se hace a mano desde Supabase. Solo debe usarse con las tablas
     * nuevas vacías; se quitan primero los triggers de inmutabilidad para poder borrarlas.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_aceptaciones_inmutables ON public.consentimiento_aceptaciones;
            DROP TRIGGER IF EXISTS trg_remisiones_externas_inmutables ON public.remisiones_externas;
            DROP TRIGGER IF EXISTS trg_auditoria_expedientes_inmutable ON public.auditoria_expedientes;
            DROP TRIGGER IF EXISTS trg_validar_remision_externa ON public.remisiones_externas;

            DROP TABLE IF EXISTS public.auditoria_expedientes;
            DROP TABLE IF EXISTS public.remisiones_externas;
            DROP TABLE IF EXISTS public.formatos_institucionales;
            DROP TABLE IF EXISTS public.consentimiento_aceptaciones;

            DROP FUNCTION IF EXISTS public.impedir_cambios();
            DROP FUNCTION IF EXISTS public.validar_remision_externa();

            ALTER TABLE public.modelos_consentimiento DROP CONSTRAINT IF EXISTS ck_modelos_poblacion;
            ALTER TABLE public.modelos_consentimiento DROP COLUMN IF EXISTS poblacion;

            ALTER TABLE public.personas DROP CONSTRAINT IF EXISTS ck_personas_fecha_nacimiento;
            ALTER TABLE public.personas DROP CONSTRAINT IF EXISTS ck_personas_sexo;
            ALTER TABLE public.personas DROP CONSTRAINT IF EXISTS ck_personas_tipo_documento;
            ALTER TABLE public.personas DROP COLUMN IF EXISTS fecha_nacimiento;
            ALTER TABLE public.personas DROP COLUMN IF EXISTS sexo;
            ALTER TABLE public.personas DROP COLUMN IF EXISTS tipo_documento;
        SQL);
    }
};

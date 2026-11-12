<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expediente: registro de atenciones, factores de riesgo y protectores del caso, y alertas
     * tempranas (HU-026).
     *
     * Aditiva: agrega dos columnas a casos y crea dos tablas. Nada se borra en cascada: un caso
     * o un usuario con atenciones o alertas no se puede eliminar (ON DELETE RESTRICT). Las
     * atenciones son evidencia y no se modifican (impedir_cambios); de una alerta solo cambia
     * su estado al atenderla, y la base exige quién y cuándo. Solo una alerta pendiente por caso.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.casos
                ADD COLUMN IF NOT EXISTS factores_riesgo      jsonb,
                ADD COLUMN IF NOT EXISTS factores_protectores jsonb;

            CREATE TABLE IF NOT EXISTS public.atenciones (
                id_atencion          uuid         PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso              uuid         NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_profesional       uuid         NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                tipo_atencion        varchar(60)  NOT NULL CHECK (tipo_atencion IN (
                    'Sesión psicológica individual', 'Evaluación de necesidades de inclusión',
                    'Seguimiento terapéutico', 'Acompañamiento académico', 'Orientación psicosocial')),
                asistencia           varchar(12)  NOT NULL CHECK (asistencia IN ('Asistió', 'No asistió', 'Cancelada')),
                fecha_atencion       timestamp    NOT NULL,
                observaciones        text         NOT NULL,
                acuerdos_compromisos text,
                fecha_registro       timestamp    NOT NULL DEFAULT now()
            );
            CREATE INDEX IF NOT EXISTS idx_atenciones_caso ON public.atenciones (id_caso, fecha_atencion);

            -- impedir_cambios() la creó la migración de consentimientos
            DROP TRIGGER IF EXISTS trg_atenciones_inmutables ON public.atenciones;
            CREATE TRIGGER trg_atenciones_inmutables
                BEFORE UPDATE OR DELETE ON public.atenciones
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

            CREATE TABLE IF NOT EXISTS public.alertas_tempranas (
                id_alerta        uuid         PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso          uuid         NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                tipo_alerta      varchar(40)  NOT NULL CHECK (tipo_alerta IN ('Nivel de riesgo alto', 'Factor de riesgo crítico')),
                descripcion      text         NOT NULL,
                estado           varchar(10)  NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente', 'Atendida')),
                generada_por     uuid         REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_generacion timestamp    NOT NULL DEFAULT now(),
                atendida_por     uuid         REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_atencion   timestamp,
                CONSTRAINT ck_alerta_atencion CHECK (
                    (estado = 'Pendiente' AND atendida_por IS NULL AND fecha_atencion IS NULL)
                 OR (estado = 'Atendida' AND atendida_por IS NOT NULL AND fecha_atencion IS NOT NULL))
            );
            CREATE UNIQUE INDEX IF NOT EXISTS uq_alerta_pendiente_por_caso ON public.alertas_tempranas (id_caso) WHERE estado = 'Pendiente';

            -- Acciones nuevas de la auditoría del expediente
            ALTER TABLE public.auditoria_expedientes DROP CONSTRAINT IF EXISTS ck_auditoria_accion;
            ALTER TABLE public.auditoria_expedientes ADD  CONSTRAINT ck_auditoria_accion CHECK (accion IN (
                'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                'datos_estudiante_actualizados', 'formato_actualizado',
                'historia_clinica_registrada', 'historia_clinica_anotada',
                'consentimiento_firmado_profesional',
                'desistimiento_registrado', 'documento_desistimiento_pdf',
                'seguimiento_registrado', 'evolucion_consultada',
                'expediente_consultado', 'reporte_inclusion_pdf',
                'caso_trasladado_admin',
                'consentimiento_revocado', 'consentimiento_papel_registrado', 'consentimiento_inclusion_pdf',
                'bitacora_registrada', 'bitacora_actualizada', 'bitacora_entrada_registrada', 'bitacora_anotada', 'bitacora_pdf',
                'caso_cerrado_constancia', 'caso_reabierto',
                'ajuste_plantilla_registrado', 'plantilla_pdf', 'plantilla_compartir_pdf',
                'evidencia_descargada',
                'consentimiento_pdf',
                'atencion_registrada', 'factores_actualizados', 'alerta_generada', 'alerta_atendida')) NOT VALID;

            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'sigabu_app') THEN
                    GRANT SELECT, INSERT ON public.atenciones TO sigabu_app;
                    GRANT SELECT, INSERT, UPDATE ON public.alertas_tempranas TO sigabu_app;
                END IF;

                -- Solo en Supabase: bloquea el acceso por su API pública, como las demás tablas.
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
                    ALTER TABLE public.atenciones ENABLE ROW LEVEL SECURITY;
                    ALTER TABLE public.alertas_tempranas ENABLE ROW LEVEL SECURITY;
                    DROP POLICY IF EXISTS sin_acceso_api ON public.atenciones;
                    DROP POLICY IF EXISTS sin_acceso_api ON public.alertas_tempranas;
                    CREATE POLICY sin_acceso_api ON public.atenciones AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);
                    CREATE POLICY sin_acceso_api ON public.alertas_tempranas AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);
                END IF;
            END $$;
        SQL);
    }

    /**
     * No borra nada: atenciones y alertas son parte del expediente.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

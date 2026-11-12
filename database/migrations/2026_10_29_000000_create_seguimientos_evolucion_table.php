<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seguimiento de la evolución del caso (HU-015): una nota estructurada por cita a la que
     * el estudiante asistió, con el nivel de riesgo, la valoración del avance y, en Salud
     * Mental, el puntaje de una escala (GAD-7 o PHQ-9). Es la fuente de las métricas de
     * progreso. No se modifica ni se borra, como la historia clínica, y cierra la API pública
     * de Supabase con RLS. También agrega las acciones de auditoría de la evolución.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.seguimientos_evolucion (
                id_seguimiento    uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso           uuid        NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_actuacion      uuid        NOT NULL UNIQUE REFERENCES public.actuaciones (id_actuacion) ON DELETE RESTRICT,
                id_profesional    uuid        NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_hora        timestamp   NOT NULL DEFAULT now(),
                nivel_riesgo      varchar(10) NOT NULL CONSTRAINT ck_seguimientos_riesgo CHECK (nivel_riesgo IN ('Bajo', 'Medio', 'Alto')),
                valoracion_avance varchar(15) NOT NULL CONSTRAINT ck_seguimientos_valoracion CHECK (valoracion_avance IN ('Mejora', 'Estable', 'Retroceso')),
                instrumento       varchar(10) CONSTRAINT ck_seguimientos_instrumento CHECK (instrumento IN ('GAD-7', 'PHQ-9')),
                puntaje           smallint,
                nota              text        NOT NULL CONSTRAINT ck_seguimientos_nota CHECK (length(nota) BETWEEN 1 AND 2000),
                -- Los IS NOT NULL evitan que una comparación con NULL deje pasar el CHECK.
                CONSTRAINT ck_seguimientos_puntaje CHECK (
                    (instrumento IS NULL AND puntaje IS NULL)
                    OR (instrumento IS NOT NULL AND puntaje IS NOT NULL AND (
                        (instrumento = 'GAD-7' AND puntaje BETWEEN 0 AND 21)
                        OR (instrumento = 'PHQ-9' AND puntaje BETWEEN 0 AND 27))))
            );
            CREATE INDEX IF NOT EXISTS idx_seguimientos_caso_fecha
                ON public.seguimientos_evolucion (id_caso, fecha_hora DESC);

            -- Solo se registra sobre una cita del mismo caso a la que el estudiante asistió.
            CREATE OR REPLACE FUNCTION public.validar_seguimiento_evolucion() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM public.actuaciones
                    WHERE id_actuacion = NEW.id_actuacion AND id_caso = NEW.id_caso
                      AND tipo_actuacion = 'Consulta' AND estado_asistencia = 'Asistió'
                ) THEN
                    RAISE EXCEPTION 'El seguimiento debe corresponder a una cita del caso a la que el estudiante asistió.';
                END IF;
                RETURN NEW;
            END $$;
            DROP TRIGGER IF EXISTS trg_validar_seguimiento_evolucion ON public.seguimientos_evolucion;
            CREATE TRIGGER trg_validar_seguimiento_evolucion
                BEFORE INSERT ON public.seguimientos_evolucion
                FOR EACH ROW EXECUTE FUNCTION public.validar_seguimiento_evolucion();

            -- Inmutabilidad (RN-04): impedir_cambios() la creó la migración de consentimientos
            DROP TRIGGER IF EXISTS trg_seguimientos_inmutables ON public.seguimientos_evolucion;
            CREATE TRIGGER trg_seguimientos_inmutables
                BEFORE UPDATE OR DELETE ON public.seguimientos_evolucion
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

            ALTER TABLE public.seguimientos_evolucion ENABLE ROW LEVEL SECURITY;
            DROP POLICY IF EXISTS sin_acceso_api ON public.seguimientos_evolucion;
            CREATE POLICY sin_acceso_api ON public.seguimientos_evolucion AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);

            -- Índices de apoyo para armar la línea de tiempo del caso
            CREATE INDEX IF NOT EXISTS idx_actuaciones_caso_fecha ON public.actuaciones (id_caso, fecha_hora);
            CREATE INDEX IF NOT EXISTS idx_tareas_caso_fecha ON public.tareas_terapeuticas (id_caso, fecha_asignacion);

            ALTER TABLE public.auditoria_expedientes DROP CONSTRAINT IF EXISTS ck_auditoria_accion;
            ALTER TABLE public.auditoria_expedientes ADD  CONSTRAINT ck_auditoria_accion CHECK (accion IN (
                'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                'datos_estudiante_actualizados', 'formato_actualizado',
                'historia_clinica_registrada', 'historia_clinica_anotada',
                'consentimiento_firmado_profesional',
                'desistimiento_registrado', 'documento_desistimiento_pdf',
                'seguimiento_registrado', 'evolucion_consultada'));
        SQL);
    }

    /**
     * Solo debe usarse sin seguimientos registrados: la auditoría no se puede borrar, así que
     * si ya tiene acciones de la evolución, restaurar el CHECK anterior falla.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.auditoria_expedientes DROP CONSTRAINT IF EXISTS ck_auditoria_accion;
            ALTER TABLE public.auditoria_expedientes ADD  CONSTRAINT ck_auditoria_accion CHECK (accion IN (
                'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                'datos_estudiante_actualizados', 'formato_actualizado',
                'historia_clinica_registrada', 'historia_clinica_anotada',
                'consentimiento_firmado_profesional',
                'desistimiento_registrado', 'documento_desistimiento_pdf'));

            DROP INDEX IF EXISTS public.idx_tareas_caso_fecha;
            DROP INDEX IF EXISTS public.idx_actuaciones_caso_fecha;
            DROP TRIGGER IF EXISTS trg_seguimientos_inmutables ON public.seguimientos_evolucion;
            DROP TRIGGER IF EXISTS trg_validar_seguimiento_evolucion ON public.seguimientos_evolucion;
            DROP FUNCTION IF EXISTS public.validar_seguimiento_evolucion();
            DROP TABLE IF EXISTS public.seguimientos_evolucion;
        SQL);
    }
};

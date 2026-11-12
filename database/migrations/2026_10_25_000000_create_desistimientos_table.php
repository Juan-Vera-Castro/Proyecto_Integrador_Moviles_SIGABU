<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desistimiento de la atención (requisito de la reunión del 04/08/2026): la persona decide
     * no continuar o no contar lo sucedido, y queda un formato firmado como respaldo legal. El
     * registro guarda una copia de los datos del estudiante y del formato vigente, es inmutable
     * y cierra el caso.
     *
     * Aditiva: tabla nueva, un formato nuevo en formatos_institucionales (sin tocar los demás) y
     * dos acciones más en el CHECK de la auditoría (se amplía; no se quita ninguna).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.desistimientos (
                id_desistimiento            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso                     uuid         NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_profesional              uuid         NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                motivo                      varchar(40)  NOT NULL
                                            CONSTRAINT ck_desistimiento_motivo CHECK (motivo IN ('No desea continuar la atención', 'No desea contar lo sucedido',
                                                                                                 'Prefiere atención externa', 'Otro')),
                observaciones               text,
                fecha_hora                  timestamp    NOT NULL DEFAULT now(),
                estudiante_nombre           varchar(150) NOT NULL,
                estudiante_tipo_documento   varchar(2),
                estudiante_numero_documento varchar(20)  NOT NULL,
                profesional_nombre          varchar(150) NOT NULL,
                formato_codigo              varchar(30)  NOT NULL,
                formato_version             varchar(10)  NOT NULL,
                formato_fecha               date         NOT NULL,
                CONSTRAINT ck_desistimiento_otro CHECK (motivo <> 'Otro' OR observaciones IS NOT NULL)
            );
            CREATE INDEX IF NOT EXISTS idx_desistimientos_caso ON public.desistimientos (id_caso, fecha_hora);

            DROP TRIGGER IF EXISTS trg_desistimientos_inmutables ON public.desistimientos;
            CREATE TRIGGER trg_desistimientos_inmutables BEFORE UPDATE OR DELETE ON public.desistimientos
                FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

            ALTER TABLE public.desistimientos ENABLE ROW LEVEL SECURITY;
            DROP POLICY IF EXISTS sin_acceso_api ON public.desistimientos;
            CREATE POLICY sin_acceso_api ON public.desistimientos
                AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);

            -- Formato en Administración → Formatos; Calidad le asigna su código definitivo.
            INSERT INTO public.formatos_institucionales (clave, proceso, nombre, codigo, version, fecha_formato)
            VALUES ('DESISTIMIENTO', 'GESTIÓN APOYO AL ESTUDIANTE', 'DESISTIMIENTO DE LA ATENCIÓN', 'POR-ASIGNAR', '1', DATE '2026-10-21')
            ON CONFLICT (clave) DO NOTHING;

            -- Acciones de auditoría del desistimiento (se amplía la lista)
            ALTER TABLE public.auditoria_expedientes DROP CONSTRAINT IF EXISTS ck_auditoria_accion;
            ALTER TABLE public.auditoria_expedientes ADD  CONSTRAINT ck_auditoria_accion CHECK (accion IN (
                'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                'datos_estudiante_actualizados', 'formato_actualizado',
                'historia_clinica_registrada', 'historia_clinica_anotada',
                'consentimiento_firmado_profesional',
                'desistimiento_registrado', 'documento_desistimiento_pdf'));
        SQL);
    }

    /**
     * No borra nada: el desistimiento es evidencia legal.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

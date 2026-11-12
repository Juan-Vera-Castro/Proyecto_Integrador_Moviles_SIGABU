<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archivos y links de los formatos de Inclusión, y borradores de los formularios.
     * sigabu_app no puede borrar filas: una evidencia descartada y un borrador terminado se
     * marcan, y lo que se borra es el objeto del almacenamiento. Una evidencia vinculada a un
     * registro ya no cambia. Tablas nuevas: no toca ninguna existente.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.inclusion_evidencias (
                id_evidencia   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso        uuid         NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                tipo           varchar(10)  NOT NULL
                               CONSTRAINT ck_evidencia_tipo CHECK (tipo IN ('archivo', 'enlace')),
                destino        varchar(15)
                               CONSTRAINT ck_evidencia_destino CHECK (destino IN ('soporte', 'entrada', 'anotacion', 'ajuste')),
                id_destino     uuid,
                estado         varchar(12)  NOT NULL DEFAULT 'temporal'
                               CONSTRAINT ck_evidencia_estado CHECK (estado IN ('temporal', 'vinculada', 'descartada')),
                ruta           varchar(255),
                nombre         varchar(200),
                mime           varchar(120),
                bytes          bigint,
                sha256         char(64),
                url            varchar(500),
                subido_por     uuid         NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_registro timestamp    NOT NULL DEFAULT now(),
                CONSTRAINT ck_evidencia_contenido CHECK (
                    (tipo = 'archivo' AND ruta IS NOT NULL AND nombre IS NOT NULL AND sha256 IS NOT NULL)
                 OR (tipo = 'enlace' AND url IS NOT NULL)
                ),
                CONSTRAINT ck_evidencia_vinculo CHECK (
                    estado <> 'vinculada' OR (destino IS NOT NULL AND id_destino IS NOT NULL)
                )
            );
            CREATE INDEX IF NOT EXISTS idx_evidencias_caso ON public.inclusion_evidencias (id_caso, estado);
            CREATE INDEX IF NOT EXISTS idx_evidencias_destino ON public.inclusion_evidencias (destino, id_destino);

            CREATE OR REPLACE FUNCTION public.proteger_evidencia_vinculada()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.estado = 'vinculada' THEN
                    RAISE EXCEPTION 'Una evidencia vinculada no se puede modificar ni borrar (%).', OLD.id_evidencia;
                END IF;
                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END $$;
            DROP TRIGGER IF EXISTS trg_proteger_evidencia_vinculada ON public.inclusion_evidencias;
            CREATE TRIGGER trg_proteger_evidencia_vinculada
                BEFORE UPDATE OR DELETE ON public.inclusion_evidencias
                FOR EACH ROW EXECUTE FUNCTION public.proteger_evidencia_vinculada();

            CREATE TABLE IF NOT EXISTS public.inclusion_borradores (
                id_borrador         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso             uuid        NOT NULL REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_usuario          uuid        NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                documento           varchar(15) NOT NULL
                                    CONSTRAINT ck_borrador_documento CHECK (documento IN ('consentimiento', 'bitacora', 'plantilla')),
                datos               text        NOT NULL,
                activo              boolean     NOT NULL DEFAULT true,
                fecha_actualizacion timestamp   NOT NULL DEFAULT now()
            );
            CREATE UNIQUE INDEX IF NOT EXISTS uq_borrador_activo
                ON public.inclusion_borradores (id_caso, id_usuario, documento) WHERE activo;

            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'sigabu_app') THEN
                    GRANT SELECT, INSERT, UPDATE ON public.inclusion_evidencias, public.inclusion_borradores TO sigabu_app;
                END IF;
            END $$;
        SQL);
    }

    /**
     * No borra nada: las evidencias respaldan el proceso.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

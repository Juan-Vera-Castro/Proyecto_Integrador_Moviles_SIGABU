<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de orientación y acompañamiento de un caso de Inclusión: una por caso, con su
     * matriz de seguimiento. Las entradas, anotaciones, cambios, constancias y reaperturas solo
     * se insertan. La caracterización se puede corregir mientras el caso esté abierto, y cada
     * cambio queda en inclusion_bitacora_cambios. Tablas nuevas: no toca ninguna existente.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('casos')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.inclusion_bitacoras (
                id_bitacora          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_caso              uuid         NOT NULL UNIQUE REFERENCES public.casos (id_caso) ON DELETE RESTRICT,
                id_profesional       uuid         NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                rol_institucional    varchar(30)  NOT NULL,
                programa_dependencia varchar(150) NOT NULL,
                situacion            text         NOT NULL,
                ruta_activada        text         NOT NULL,
                soportes_texto       text,
                orientacion          text,
                fecha_registro       timestamp    NOT NULL DEFAULT now(),
                fecha_actualizacion  timestamp    NOT NULL DEFAULT now()
            );

            CREATE TABLE IF NOT EXISTS public.inclusion_bitacora_cambios (
                id_cambio      uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_bitacora    uuid        NOT NULL REFERENCES public.inclusion_bitacoras (id_bitacora) ON DELETE RESTRICT,
                campo          varchar(30) NOT NULL,
                valor_anterior text,
                valor_nuevo    text,
                id_profesional uuid        NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_hora     timestamp   NOT NULL DEFAULT now()
            );
            CREATE INDEX IF NOT EXISTS idx_bitacora_cambios ON public.inclusion_bitacora_cambios (id_bitacora, fecha_hora);

            CREATE TABLE IF NOT EXISTS public.inclusion_bitacora_entradas (
                id_entrada     uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_bitacora    uuid        NOT NULL REFERENCES public.inclusion_bitacoras (id_bitacora) ON DELETE RESTRICT,
                numero         integer     NOT NULL CONSTRAINT ck_entrada_numero CHECK (numero > 0),
                fecha_hora     timestamp   NOT NULL,
                tipo           varchar(20) NOT NULL
                               CONSTRAINT ck_entrada_tipo CHECK (tipo IN ('Atención inicial', 'Seguimiento', 'Cierre')),
                descripcion    text        NOT NULL,
                id_profesional uuid        NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_registro timestamp   NOT NULL DEFAULT now(),
                CONSTRAINT uq_entrada_numero UNIQUE (id_bitacora, numero)
            );
            CREATE UNIQUE INDEX IF NOT EXISTS uq_entrada_atencion_inicial
                ON public.inclusion_bitacora_entradas (id_bitacora) WHERE tipo = 'Atención inicial';

            CREATE TABLE IF NOT EXISTS public.inclusion_bitacora_anotaciones (
                id_anotacion   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_entrada     uuid      NOT NULL REFERENCES public.inclusion_bitacora_entradas (id_entrada) ON DELETE RESTRICT,
                texto          text      NOT NULL,
                id_profesional uuid      NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                fecha_hora     timestamp NOT NULL DEFAULT now()
            );
            CREATE INDEX IF NOT EXISTS idx_bitacora_anotaciones ON public.inclusion_bitacora_anotaciones (id_entrada, fecha_hora);

            CREATE TABLE IF NOT EXISTS public.inclusion_constancias (
                id_constancia      uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_bitacora        uuid         NOT NULL REFERENCES public.inclusion_bitacoras (id_bitacora) ON DELETE RESTRICT,
                id_entrada_cierre  uuid         NOT NULL UNIQUE REFERENCES public.inclusion_bitacora_entradas (id_entrada) ON DELETE RESTRICT,
                id_profesional     uuid         NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                nombre_profesional varchar(150) NOT NULL,
                texto_presentado   text         NOT NULL,
                firma_ruta         varchar(255) NOT NULL,
                firma_sha256       char(64)     NOT NULL,
                fecha_hora         timestamp    NOT NULL DEFAULT now()
            );
            CREATE INDEX IF NOT EXISTS idx_constancias_bitacora ON public.inclusion_constancias (id_bitacora, fecha_hora);

            CREATE TABLE IF NOT EXISTS public.inclusion_reaperturas (
                id_reapertura    uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                id_bitacora      uuid      NOT NULL REFERENCES public.inclusion_bitacoras (id_bitacora) ON DELETE RESTRICT,
                id_constancia    uuid      NOT NULL UNIQUE REFERENCES public.inclusion_constancias (id_constancia) ON DELETE RESTRICT,
                id_administrador uuid      NOT NULL REFERENCES public.usuarios (id_usuario) ON DELETE RESTRICT,
                motivo           text      NOT NULL,
                fecha_hora       timestamp NOT NULL DEFAULT now()
            );

            DO $$
            DECLARE
                t text;
            BEGIN
                FOREACH t IN ARRAY ARRAY['inclusion_bitacora_cambios', 'inclusion_bitacora_entradas', 'inclusion_bitacora_anotaciones',
                                         'inclusion_constancias', 'inclusion_reaperturas'] LOOP
                    EXECUTE format('DROP TRIGGER IF EXISTS trg_%s_inmutables ON public.%I', t, t);
                    EXECUTE format('CREATE TRIGGER trg_%s_inmutables BEFORE UPDATE OR DELETE ON public.%I FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios()', t, t);
                END LOOP;

                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'sigabu_app') THEN
                    FOREACH t IN ARRAY ARRAY['inclusion_bitacoras', 'inclusion_bitacora_cambios', 'inclusion_bitacora_entradas',
                                             'inclusion_bitacora_anotaciones', 'inclusion_constancias', 'inclusion_reaperturas'] LOOP
                        EXECUTE format('GRANT SELECT, INSERT, UPDATE ON public.%I TO sigabu_app', t);
                    END LOOP;
                END IF;
            END $$;
        SQL);
    }

    /**
     * No borra nada: la bitácora es evidencia del proceso.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

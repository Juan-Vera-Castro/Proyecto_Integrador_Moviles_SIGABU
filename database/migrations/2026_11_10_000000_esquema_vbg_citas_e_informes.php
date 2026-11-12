<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base propia del módulo de Violencias Basadas en Género (conexión "vbg", base sigabu_vbg),
     * integrado desde la rama VIOLENCIA_GENERO: citas de los casos, consentimiento firmado por
     * cita, audios de las sesiones con su transcripción e informes de análisis.
     *
     * Está separada de la base principal: se relaciona con ella solo por identificadores
     * (id_reporte de vbg_reportes e id_usuario de usuarios), sin llaves foráneas entre bases.
     * Aditiva y repetible: solo crea lo que falta. Se ejecuta con el dueño de sigabu_vbg.
     */
    protected $connection = 'vbg';

    public function up(): void
    {
        $esquema = Schema::connection('vbg');

        if (! $esquema->hasTable('vbg_citas')) {
            $esquema->create('vbg_citas', function (Blueprint $tabla) {
                $tabla->uuid('id_cita')->primary();
                $tabla->uuid('id_reporte')->index();
                $tabla->uuid('id_profesional');
                $tabla->date('fecha');
                $tabla->time('hora_inicio');
                $tabla->time('hora_fin');
                $tabla->string('modalidad', 12);
                $tabla->string('lugar', 200)->nullable();
                $tabla->text('motivo')->nullable();
                $tabla->text('notas_previas')->nullable();
                $tabla->string('estado', 12)->default('Programada');
                $tabla->timestamp('creado_en', 6)->useCurrent();
                $tabla->timestamp('actualizado_en', 6)->nullable();
                $tabla->index(['id_profesional', 'fecha']);
            });
        }

        if (! $esquema->hasTable('vbg_consentimientos_cita')) {
            $esquema->create('vbg_consentimientos_cita', function (Blueprint $tabla) {
                $tabla->uuid('id_consentimiento')->primary();
                $tabla->uuid('id_cita')->index();
                $tabla->foreign('id_cita')->references('id_cita')->on('vbg_citas')->restrictOnDelete();
                $tabla->string('decision', 10);
                $tabla->string('firmante_nombre', 150);
                $tabla->string('firmante_documento', 20);
                $tabla->boolean('es_menor')->default(false);
                $tabla->string('acudiente_nombre', 150)->nullable();
                $tabla->string('acudiente_telefono', 20)->nullable();
                $tabla->string('acudiente_documento', 20)->nullable();
                $tabla->string('acudiente_parentesco', 50)->nullable();
                $tabla->string('version', 20);
                $tabla->text('texto_presentado');
                $tabla->char('texto_sha256', 64);
                // Firma de quien otorga: la persona atendida o, si es menor, su acudiente.
                $tabla->string('firma_ruta', 255)->nullable();
                $tabla->char('firma_sha256', 64)->nullable();
                // Si es menor de edad, también firma la persona atendida.
                $tabla->string('firma_menor_ruta', 255)->nullable();
                $tabla->char('firma_menor_sha256', 64)->nullable();
                $tabla->uuid('id_profesional');
                $tabla->string('ip_origen', 45)->nullable();
                $tabla->string('user_agent', 255)->nullable();
                $tabla->timestamp('fecha_hora', 6)->useCurrent();
            });
        }

        if (! $esquema->hasTable('vbg_audios')) {
            $esquema->create('vbg_audios', function (Blueprint $tabla) {
                $tabla->uuid('id_audio')->primary();
                $tabla->uuid('id_cita')->index();
                $tabla->foreign('id_cita')->references('id_cita')->on('vbg_citas')->restrictOnDelete();
                $tabla->string('origen', 12)->default('Archivo');
                $tabla->string('formato', 5);
                $tabla->integer('duracion_segundos')->nullable();
                $tabla->string('ruta', 255);
                $tabla->string('nombre_original', 200);
                $tabla->bigInteger('bytes');
                $tabla->char('sha256', 64);
                $tabla->uuid('subido_por');
                $tabla->timestamp('creado_en', 6)->useCurrent();
            });
        }

        if (! $esquema->hasTable('vbg_transcripciones')) {
            $esquema->create('vbg_transcripciones', function (Blueprint $tabla) {
                $tabla->uuid('id_transcripcion')->primary();
                $tabla->uuid('id_audio')->unique();
                $tabla->foreign('id_audio')->references('id_audio')->on('vbg_audios')->restrictOnDelete();
                $tabla->text('texto')->nullable();
                $tabla->string('estado', 12);
                $tabla->string('origen', 10);
                $tabla->string('modelo', 60)->nullable();
                $tabla->decimal('segundos_proceso', 8, 2)->nullable();
                $tabla->timestamp('completado_en', 6)->nullable();
                $tabla->uuid('id_usuario')->nullable();
                $tabla->timestamp('actualizado_en', 6)->nullable();
            });
        }

        if (! $esquema->hasTable('vbg_informes')) {
            $esquema->create('vbg_informes', function (Blueprint $tabla) {
                $tabla->uuid('id_informe')->primary();
                $tabla->uuid('id_reporte')->index();
                $tabla->uuid('id_transcripcion')->nullable();
                $tabla->foreign('id_transcripcion')->references('id_transcripcion')->on('vbg_transcripciones')->restrictOnDelete();
                $tabla->text('resumen');
                $tabla->string('nivel_riesgo', 10);
                $tabla->jsonb('tipos_violencia');
                $tabla->jsonb('marco_legal');
                $tabla->jsonb('documentos_repositorio')->nullable();
                $tabla->jsonb('ruta_atencion');
                // Contemplado para la IA (rama VIOLENCIA_GENERO): hoy los informes son manuales.
                $tabla->string('origen', 10)->default('Manual');
                $tabla->string('modelo_llm', 60)->nullable();
                $tabla->text('prompt')->nullable();
                $tabla->boolean('editado_por_profesional')->default(true);
                $tabla->uuid('id_profesional');
                $tabla->timestamp('creado_en', 6)->useCurrent();
            });
        }

        if (! $esquema->hasTable('vbg_auditoria')) {
            $esquema->create('vbg_auditoria', function (Blueprint $tabla) {
                $tabla->bigIncrements('id_auditoria');
                $tabla->uuid('id_reporte')->index();
                $tabla->uuid('id_usuario');
                $tabla->string('accion', 40);
                $tabla->jsonb('detalle')->nullable();
                $tabla->timestamp('fecha_hora', 6)->useCurrent();
            });
        }

        if (DB::connection('vbg')->getDriverName() === 'pgsql') {
            $this->reglasPostgres();
        }
    }

    /**
     * Reglas que solo existen en PostgreSQL: valores permitidos y evidencia inmutable.
     */
    private function reglasPostgres(): void
    {
        DB::connection('vbg')->unprepared(<<<'SQL'
            ALTER TABLE public.vbg_citas DROP CONSTRAINT IF EXISTS ck_vbg_citas_modalidad;
            ALTER TABLE public.vbg_citas ADD CONSTRAINT ck_vbg_citas_modalidad CHECK (modalidad IN ('Presencial', 'Virtual'));
            ALTER TABLE public.vbg_citas DROP CONSTRAINT IF EXISTS ck_vbg_citas_estado;
            ALTER TABLE public.vbg_citas ADD CONSTRAINT ck_vbg_citas_estado CHECK (estado IN ('Programada', 'En curso', 'Completada', 'Cancelada'));
            ALTER TABLE public.vbg_citas DROP CONSTRAINT IF EXISTS ck_vbg_citas_horas;
            ALTER TABLE public.vbg_citas ADD CONSTRAINT ck_vbg_citas_horas CHECK (hora_fin > hora_inicio);

            ALTER TABLE public.vbg_consentimientos_cita DROP CONSTRAINT IF EXISTS ck_vbg_consentimiento_decision;
            ALTER TABLE public.vbg_consentimientos_cita ADD CONSTRAINT ck_vbg_consentimiento_decision CHECK (
                (decision = 'Aceptado' AND firma_ruta IS NOT NULL AND firma_sha256 IS NOT NULL
                    AND (NOT es_menor OR (firma_menor_ruta IS NOT NULL AND firma_menor_sha256 IS NOT NULL)))
                OR (decision = 'Rechazado' AND firma_ruta IS NULL AND firma_menor_ruta IS NULL));
            ALTER TABLE public.vbg_consentimientos_cita DROP CONSTRAINT IF EXISTS ck_vbg_consentimiento_menor;
            ALTER TABLE public.vbg_consentimientos_cita ADD CONSTRAINT ck_vbg_consentimiento_menor CHECK (
                NOT es_menor OR (acudiente_nombre IS NOT NULL AND acudiente_telefono IS NOT NULL
                    AND acudiente_documento IS NOT NULL AND acudiente_parentesco IS NOT NULL));

            ALTER TABLE public.vbg_audios DROP CONSTRAINT IF EXISTS ck_vbg_audios_duracion;
            ALTER TABLE public.vbg_audios ADD CONSTRAINT ck_vbg_audios_duracion CHECK (duracion_segundos IS NULL OR duracion_segundos BETWEEN 0 AND 7200);

            ALTER TABLE public.vbg_transcripciones DROP CONSTRAINT IF EXISTS ck_vbg_transcripciones;
            ALTER TABLE public.vbg_transcripciones ADD CONSTRAINT ck_vbg_transcripciones CHECK (
                estado IN ('En cola', 'Completada', 'Error') AND origen IN ('Manual', 'IA'));

            ALTER TABLE public.vbg_informes DROP CONSTRAINT IF EXISTS ck_vbg_informes;
            ALTER TABLE public.vbg_informes ADD CONSTRAINT ck_vbg_informes CHECK (
                nivel_riesgo IN ('Bajo', 'Medio', 'Alto') AND origen IN ('Manual', 'IA'));

            -- La evidencia no se modifica ni se borra: consentimientos, audios, informes y auditoría.
            CREATE OR REPLACE FUNCTION public.vbg_impedir_cambios() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Los registros de % no se pueden modificar ni borrar.', TG_TABLE_NAME;
            END;
            $$;

            DROP TRIGGER IF EXISTS trg_vbg_consentimientos_inmutables ON public.vbg_consentimientos_cita;
            CREATE TRIGGER trg_vbg_consentimientos_inmutables BEFORE UPDATE OR DELETE ON public.vbg_consentimientos_cita
                FOR EACH ROW EXECUTE FUNCTION public.vbg_impedir_cambios();
            DROP TRIGGER IF EXISTS trg_vbg_audios_inmutables ON public.vbg_audios;
            CREATE TRIGGER trg_vbg_audios_inmutables BEFORE UPDATE OR DELETE ON public.vbg_audios
                FOR EACH ROW EXECUTE FUNCTION public.vbg_impedir_cambios();
            DROP TRIGGER IF EXISTS trg_vbg_informes_inmutables ON public.vbg_informes;
            CREATE TRIGGER trg_vbg_informes_inmutables BEFORE UPDATE OR DELETE ON public.vbg_informes
                FOR EACH ROW EXECUTE FUNCTION public.vbg_impedir_cambios();
            DROP TRIGGER IF EXISTS trg_vbg_auditoria_inmutable ON public.vbg_auditoria;
            CREATE TRIGGER trg_vbg_auditoria_inmutable BEFORE UPDATE OR DELETE ON public.vbg_auditoria
                FOR EACH ROW EXECUTE FUNCTION public.vbg_impedir_cambios();
        SQL);
    }

    /**
     * No borra nada: estas tablas guardan evidencia de atenciones.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

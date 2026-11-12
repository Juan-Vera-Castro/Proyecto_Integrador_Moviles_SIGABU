<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código, versión y fecha de los tres formatos de Inclusión, para que Calidad los complete
     * desde Formatos, y acciones de auditoría del módulo. Aditiva: conserva las demás acciones.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('formatos_institucionales')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            INSERT INTO public.formatos_institucionales (clave, proceso, nombre, codigo, version, fecha_formato) VALUES
                ('CONSENTIMIENTO_INCLUSION', 'PROCESO DE INCLUSIÓN UNIVERSITARIA', 'CONSENTIMIENTO INFORMADO - PROCESO DE INCLUSIÓN UNIVERSITARIA', 'POR-ASIGNAR', '1', CURRENT_DATE),
                ('BITACORA_INCLUSION', 'GESTIÓN DE PERMANENCIA Y GRADUACIÓN - PROCESO INCLUSIÓN', 'BITÁCORA DE ORIENTACIÓN Y ACOMPAÑAMIENTO', 'POR-ASIGNAR', '1', CURRENT_DATE),
                ('RECURSO_INCLUSION', 'PROCESO DE INCLUSIÓN UNIVERSITARIA', 'PLANTILLA DE AJUSTES PEDAGÓGICOS', 'POR-ASIGNAR', '1', CURRENT_DATE)
            ON CONFLICT (clave) DO NOTHING;

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
                'evidencia_descargada')) NOT VALID;
        SQL);
    }

    /**
     * No restaura el CHECK anterior: la auditoría no se borra, y si ya registró estas acciones,
     * el CHECK anterior fallaría.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

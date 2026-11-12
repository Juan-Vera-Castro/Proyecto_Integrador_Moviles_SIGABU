<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quedan en la auditoría la consulta del expediente digital (HU-005) y la descarga del
     * reporte de seguimiento de Inclusión (HU-020). Solo amplía el CHECK de acciones con
     * 'expediente_consultado' y 'reporte_inclusion_pdf'; conserva todas las demás y no toca
     * ningún registro.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('auditoria_expedientes')) {
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
                'desistimiento_registrado', 'documento_desistimiento_pdf',
                'seguimiento_registrado', 'evolucion_consultada',
                'expediente_consultado', 'reporte_inclusion_pdf'));
        SQL);
    }

    /**
     * No restaura el CHECK anterior: la auditoría no se borra, y si ya registró consultas del
     * expediente o descargas del reporte, el CHECK anterior fallaría.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

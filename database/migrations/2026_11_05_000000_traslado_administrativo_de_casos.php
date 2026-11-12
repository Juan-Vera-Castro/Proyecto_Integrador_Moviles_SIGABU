<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traslado de último recurso: el administrador pasa los casos de un profesional que no puede
     * entregarlos. La remisión guarda quién la hizo, para que no parezca del profesional anterior,
     * y la auditoría admite 'caso_trasladado_admin'. Aditiva: conserva las demás acciones.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('actuaciones')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE public.actuaciones ADD COLUMN IF NOT EXISTS id_admin_traslado uuid REFERENCES public.usuarios(id_usuario);

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
                'caso_trasladado_admin'));
        SQL);
    }

    /**
     * No restaura el CHECK anterior: la auditoría no se borra, y si ya registró traslados, el
     * CHECK anterior fallaría.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

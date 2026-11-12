<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PDF del consentimiento firmado de Salud Mental: registra los dos formatos de Calidad que
     * reproduce (GBU-GPGR-F-032 general y GBU-GPGR-F-019 menores de edad) y amplía el CHECK de
     * la auditoría con 'consentimiento_pdf'. Solo agrega: no cambia ningún registro.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('formatos_institucionales')) {
            return;
        }

        DB::unprepared(<<<'SQL'
            INSERT INTO public.formatos_institucionales (clave, proceso, nombre, codigo, version, fecha_formato) VALUES
                ('CONSENTIMIENTO_ORIENTACION', 'GESTIÓN APOYO AL ESTUDIANTE', 'CONSENTIMIENTO INFORMADO ORIENTACIÓN PSICOLÓGICA', 'GBU-GPGR-F-032', '1', '2023-09-13'),
                ('CONSENTIMIENTO_MENORES', 'GESTIÓN APOYO AL ESTUDIANTE', 'CONSENTIMIENTO INFORMADO MENORES DE EDAD', 'GBU-GPGR-F-019', '1', '2021-09-07')
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
                'evidencia_descargada',
                'consentimiento_pdf')) NOT VALID;
        SQL);
    }

    /**
     * No restaura el CHECK anterior: la auditoría no se borra, y si ya registró descargas del
     * consentimiento, el CHECK anterior fallaría.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }
};

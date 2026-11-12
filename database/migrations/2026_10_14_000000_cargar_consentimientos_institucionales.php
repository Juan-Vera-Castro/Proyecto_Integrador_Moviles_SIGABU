<?php

use App\Documentos\ConsentimientosInstitucionales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HU-009: publica como versión 1 los consentimientos oficiales (GBU-GPGR-F-032 y
     * GBU-GPGR-F-019) de Salud Mental e Inclusión. Solo crea los que falten: no reemplaza
     * modelos que el administrador ya haya creado.
     */
    public function up(): void
    {
        if (! Schema::hasTable('modelos_consentimiento') || ! Schema::hasColumn('modelos_consentimiento', 'poblacion')) {
            return;
        }

        ConsentimientosInstitucionales::cargar();
    }

    /**
     * Quita solo los modelos cargados aquí que nadie haya usado ni modificado: si ya hay
     * consentimientos firmados o versiones nuevas, se conservan como evidencia.
     */
    public function down(): void
    {
        if (! Schema::hasTable('modelos_consentimiento')) {
            return;
        }

        $codigos = array_column(ConsentimientosInstitucionales::modelos(), 'codigo');

        foreach (DB::table('modelos_consentimiento')->whereIn('codigo', $codigos)->pluck('id_modelo') as $idModelo) {
            $versiones = DB::table('versiones_consentimiento')->where('id_modelo', $idModelo)->pluck('id_version');
            $usado = $versiones->count() > 1
                || (Schema::hasTable('consentimiento_aceptaciones') && DB::table('consentimiento_aceptaciones')->whereIn('id_version', $versiones)->exists())
                || (Schema::hasTable('consentimientos') && DB::table('consentimientos')->whereIn('id_version', $versiones)->exists());

            if (! $usado) {
                DB::table('versiones_consentimiento')->where('id_modelo', $idModelo)->delete();
                DB::table('modelos_consentimiento')->where('id_modelo', $idModelo)->delete();
            }
        }
    }
};

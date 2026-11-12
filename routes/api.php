<?php

use App\Http\Controllers\Alimentos\SolicitudController as AlimSolicitudController;
use App\Http\Controllers\Api\Movil\AlimentarioController;
use App\Http\Controllers\Api\Movil\AuthController;
use App\Http\Controllers\Api\Movil\CitasController;
use App\Http\Controllers\Api\Movil\ConsentimientoController;
use App\Http\Controllers\Api\Movil\InclusionController;
use App\Http\Controllers\Api\Movil\InicioController;
use App\Http\Controllers\Api\Movil\NotificacionesController;
use App\Http\Controllers\Api\Movil\PerfilController;
use App\Http\Controllers\Api\Movil\RecursosController;
use App\Http\Controllers\Api\Movil\ReportesController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ConsentimientoFirmaController;
use App\Http\Controllers\Inclusion\BorradorController;
use App\Http\Controllers\Inclusion\ConsentimientoController as ConsentimientoInclusionController;
use App\Http\Controllers\PropuestaCitaController;
use App\Http\Controllers\RepositorioController;
use App\Http\Controllers\SolicitudCitaController;
use App\Http\Controllers\Vbg\ReporteController as VbgReporteController;
use App\Models\Inclusion\Borrador;
use Illuminate\Support\Facades\Route;

/*
| API de la app móvil del estudiante (Flutter): /api/movil/...
|
| Se autentica con token de Sanctum (Authorization: Bearer ...). Las consultas usan los
| controladores de app/Http/Controllers/Api/Movil, que devuelven JSON; las acciones que
| guardan datos llaman a los mismos controladores de la web, así las reglas de negocio no
| se duplican, y el middleware json.movil convierte su redirección en JSON.
*/

Route::prefix('movil')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'estudiante.movil'])->group(function () {
        Route::get('/yo', [AuthController::class, 'yo']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Inicio: próxima cita y tareas terapéuticas pendientes (HU-013)
        Route::get('/inicio', [InicioController::class, 'index']);

        // Lecturas propias de la app
        Route::get('/citas', [CitasController::class, 'index']);
        Route::get('/citas/formulario', [CitasController::class, 'formulario']);
        Route::get('/casos/{caso}/consentimiento', [ConsentimientoController::class, 'formulario'])->whereUuid('caso');
        Route::get('/casos/{caso}/inclusion/consentimiento/formulario', [InclusionController::class, 'formulario'])->whereUuid('caso');
        Route::get('/casos/{caso}/inclusion/consentimiento', [InclusionController::class, 'ver'])->whereUuid('caso');
        Route::get('/reportes', [ReportesController::class, 'index']);
        Route::get('/alimentario', [AlimentarioController::class, 'index']);
        Route::get('/recursos', [RecursosController::class, 'index']);
        Route::get('/notificaciones', [NotificacionesController::class, 'index']);
        Route::post('/notificaciones/leer-todas', [NotificacionesController::class, 'leerTodas']);
        Route::post('/notificaciones/{notificacion}/leer', [NotificacionesController::class, 'leer'])->whereUuid('notificacion');
        Route::get('/perfil', [PerfilController::class, 'show']);
        Route::patch('/perfil', [PerfilController::class, 'update']);

        // Acciones y descargas de la web, con la respuesta en JSON
        Route::middleware('json.movil')->group(function () {
            Route::post('/citas', [SolicitudCitaController::class, 'store']);
            Route::post('/propuestas/{propuesta}/elegir', [PropuestaCitaController::class, 'elegir'])->whereUuid('propuesta');
            Route::post('/casos/{caso}/propuestas/rechazar', [PropuestaCitaController::class, 'rechazar'])->whereUuid('caso');

            Route::post('/casos/{caso}/consentimiento', [ConsentimientoFirmaController::class, 'store'])->whereUuid('caso');
            Route::get('/consentimientos/{aceptacion}/documento.pdf', [ConsentimientoFirmaController::class, 'pdf'])->whereUuid('aceptacion');

            Route::post('/casos/{caso}/inclusion/consentimiento', [ConsentimientoInclusionController::class, 'enviar'])->whereUuid('caso');
            Route::post('/casos/{caso}/inclusion/consentimiento/revocar', [ConsentimientoInclusionController::class, 'revocar'])->whereUuid('caso');
            Route::get('/casos/{caso}/inclusion/consentimiento.pdf', [ConsentimientoInclusionController::class, 'pdf'])->whereUuid('caso');
            Route::put('/casos/{caso}/inclusion/borradores/{documento}', [BorradorController::class, 'guardar'])->whereUuid('caso')->whereIn('documento', [Borrador::CONSENTIMIENTO]);

            Route::post('/reportes', [VbgReporteController::class, 'store']);

            Route::post('/alimentario', [AlimSolicitudController::class, 'store']);
            Route::get('/alimentario/consultar-ficha', [AlimSolicitudController::class, 'consultarFicha']);

            Route::get('/recursos/{documento}/archivo', [RepositorioController::class, 'archivo'])->whereNumber('documento');

            Route::put('/perfil/contrasena', [PasswordController::class, 'update']);
        });
    });
});

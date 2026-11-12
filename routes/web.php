<?php

use App\Http\Controllers\AnaliticaController;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\CasoController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ConsentimientoFirmaController;
use App\Http\Controllers\DatosEstudianteController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\EvolucionCasoController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\HistoriaClinicaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\Admin\AsignacionProfesionalController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\TrasladoCasosController;
use App\Http\Controllers\AjusteRazonableController;
use App\Http\Controllers\DesistimientoController;
use App\Http\Controllers\IndicadoresController;
use App\Http\Controllers\Admin\ReaperturaCasoController;
use App\Http\Controllers\Inclusion\BitacoraController;
use App\Http\Controllers\Inclusion\BorradorController;
use App\Http\Controllers\Inclusion\CierreCasoController;
use App\Http\Controllers\Inclusion\ConsentimientoController as ConsentimientoInclusionController;
use App\Http\Controllers\Inclusion\ConsentimientoPapelController;
use App\Http\Controllers\Inclusion\EntradaBitacoraController;
use App\Http\Controllers\Inclusion\EvidenciaController;
use App\Http\Controllers\Inclusion\PlantillaAjustesController;
use App\Http\Controllers\Inclusion\RecursoController;
use App\Http\Controllers\ProfesionalController;
use App\Http\Controllers\PropuestaCitaController;
use App\Http\Controllers\RepositorioController;
use App\Http\Controllers\Alimentos\AlmacenController;
use App\Http\Controllers\Alimentos\SolicitudController as AlimSolicitudController;
use App\Http\Controllers\Vbg\CasoController as VbgCasoController;
use App\Http\Controllers\Vbg\AudioController as VbgAudioController;
use App\Http\Controllers\Vbg\CitaController as VbgCitaController;
use App\Http\Controllers\Vbg\ConsentimientoCitaController as VbgConsentimientoCitaController;
use App\Http\Controllers\Vbg\InformeController as VbgInformeController;
use App\Http\Controllers\Vbg\ReporteController as VbgReporteController;
use App\Http\Controllers\Admin\ConsentimientoController;
use App\Http\Controllers\Admin\FormatoInstitucionalController;
use App\Http\Controllers\Admin\UsuarioRolController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RemisionController;
use App\Http\Controllers\RemisionDocumentoController;
use App\Http\Controllers\ReporteInclusionController;
use App\Http\Controllers\SeguimientoEvolucionController;
use App\Http\Controllers\SolicitudCitaController;
use App\Http\Controllers\SolicitudPendienteController;
use App\Http\Controllers\TareaTerapeuticaController;
use App\Models\Inclusion\Borrador;
use Illuminate\Support\Facades\Route;

// Redirección de la raíz al login
Route::get('/', function () {
    return redirect()->route('login');
});

// Rutas protegidas por autenticación
Route::middleware(['auth', 'verified'])->group(function () {

    // Punto de entrada genérico: envía a cada usuario a la sección de su rol.
    // Solo muestra la vista genérica si el rol no tiene sección propia.
    Route::get('/dashboard', function () {
        $user = auth()->user();

        return $user->role ? redirect($user->getDashboardUrl()) : view('dashboard');
    })->name('dashboard');

    // Firma del consentimiento: la ven el profesional del caso, quien lo remitió y el administrador.
    Route::get('/consentimientos/{aceptacion}/firma', [ConsentimientoFirmaController::class, 'firma'])->whereUuid('aceptacion')->name('consentimientos.firma');
    Route::get('/consentimientos/{aceptacion}/firma/{quien}', [ConsentimientoFirmaController::class, 'firma'])->whereUuid('aceptacion')->whereIn('quien', ['profesional', 'estudiante'])->name('consentimientos.firma-profesional');
    Route::get('/consentimientos/{aceptacion}/documento.pdf', [ConsentimientoFirmaController::class, 'pdf'])->whereUuid('aceptacion')->name('consentimientos.pdf');

    // Borradores de los formatos de Inclusión: los usan la persona y el profesional (lo valida el controlador)
    Route::put('/inclusion/casos/{caso}/borradores/{documento}', [BorradorController::class, 'guardar'])->whereUuid('caso')->whereIn('documento', Borrador::DOCUMENTOS)->name('inclusion.borradores.guardar');

    // Consentimiento de Inclusión ya registrado: lo ven la persona, el profesional y el administrador (lo valida el controlador)
    Route::get('/inclusion/casos/{caso}/consentimiento', [ConsentimientoInclusionController::class, 'ver'])->whereUuid('caso')->name('inclusion.consentimiento.ver');
    Route::get('/inclusion/consentimientos/{aceptacion}/papel', [ConsentimientoPapelController::class, 'archivo'])->whereUuid('aceptacion')->name('inclusion.consentimiento.papel.archivo');
    Route::get('/inclusion/casos/{caso}/consentimiento.pdf', [ConsentimientoInclusionController::class, 'pdf'])->whereUuid('caso')->name('inclusion.consentimiento.pdf');

    // Avisos de la campana: cada usuario, cualquiera sea su rol, ve y marca solo los suyos
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
    Route::post('/notificaciones/{notificacion}/abrir', [NotificacionController::class, 'abrir'])->whereUuid('notificacion')->name('notificaciones.abrir');

    // HU-032: tablero de indicadores institucionales (admin y profesionales autorizados)
    Route::get('/indicadores', [IndicadoresController::class, 'index'])->middleware('role:admin,profesional')->name('indicadores.index');

    // Repositorio de documentos: lo consultan y alimentan el administrador y los equipos de
    // Bienestar (archivos o enlaces). Cada uno retira lo suyo; el administrador, cualquiera.
    Route::middleware('role:admin,profesional,vbg,almacen')->group(function () {
        Route::get('/repositorio', [RepositorioController::class, 'index'])->name('repositorio.index');
        Route::post('/repositorio', [RepositorioController::class, 'store'])->name('repositorio.store');
        Route::patch('/repositorio/{documento}/estado', [RepositorioController::class, 'cambiarEstado'])->whereNumber('documento')->name('repositorio.estado');
    });
    // Descarga de un archivo: también los estudiantes, si se publicó para todos (lo valida el controlador)
    Route::get('/repositorio/{documento}/archivo', [RepositorioController::class, 'archivo'])->whereNumber('documento')->name('repositorio.archivo');

    // Panel Estudiante
    Route::middleware('role:estudiante')->group(function () {
        Route::get('/estudiante/home', [EstudianteController::class, 'home'])->name('estudiante.home');

        // Solicitud de cita del estudiante y seguimiento de sus citas
        Route::get('/estudiante/solicitar-cita', [SolicitudCitaController::class, 'create'])->name('citas.solicitar');
        Route::post('/estudiante/solicitar-cita', [SolicitudCitaController::class, 'store'])->name('citas.solicitar.store');
        Route::get('/estudiante/mis-citas', [EstudianteController::class, 'misCitas'])->name('citas.miscitas');
        Route::post('/estudiante/propuestas/{propuesta}/elegir', [PropuestaCitaController::class, 'elegir'])->whereUuid('propuesta')->name('propuestas.elegir');
        Route::post('/estudiante/casos/{caso}/propuestas/rechazar', [PropuestaCitaController::class, 'rechazar'])->whereUuid('caso')->name('propuestas.rechazar');

        // Consentimiento informado con firma táctil (RQ-1): lo firma el estudiante desde su cuenta
        Route::get('/estudiante/casos/{caso}/consentimiento', [ConsentimientoFirmaController::class, 'create'])->whereUuid('caso')->name('consentimientos.firmar');
        Route::post('/estudiante/casos/{caso}/consentimiento', [ConsentimientoFirmaController::class, 'store'])->whereUuid('caso')->name('consentimientos.registrar');

        // Consentimiento del Proceso de Inclusión: lo diligencia y revoca la persona
        Route::get('/estudiante/casos/{caso}/inclusion/consentimiento', [ConsentimientoInclusionController::class, 'formulario'])->whereUuid('caso')->name('inclusion.consentimiento.formulario');
        Route::post('/estudiante/casos/{caso}/inclusion/consentimiento', [ConsentimientoInclusionController::class, 'enviar'])->whereUuid('caso')->name('inclusion.consentimiento.enviar');
        Route::post('/estudiante/casos/{caso}/inclusion/consentimiento/revocar', [ConsentimientoInclusionController::class, 'revocar'])->whereUuid('caso')->name('inclusion.consentimiento.revocar');

        // Violencias Basadas en Género: reporte propio, de un tercero o anónimo
        Route::get('/estudiante/reportar-situacion', [VbgReporteController::class, 'create'])->name('reportes.crear');
        Route::post('/estudiante/reportar-situacion', [VbgReporteController::class, 'store'])->name('reportes.store');

        // Donación Alimentaria "Ucevistas de Corazón": solicitud de mercado y consulta de ficha
        Route::get('/estudiante/apoyo-alimentario', [AlimSolicitudController::class, 'create'])->name('alimentario.crear');
        Route::post('/estudiante/apoyo-alimentario', [AlimSolicitudController::class, 'store'])->name('alimentario.store');
        Route::get('/estudiante/apoyo-alimentario/consultar-ficha', [AlimSolicitudController::class, 'consultarFicha'])->name('alimentario.consultar');

        Route::get('/estudiante/recursos', function () {
            return view('recursos.index', [
                'documentos' => \App\Models\RepositorioDocumento::paraTodos()->orderByDesc('fecha_publicacion')->get(),
            ]);
        })->name('recursos.index');
    });

    // Panel Profesional: cada profesional solo entra a los casos de su área
    Route::middleware(['role:profesional', 'area'])->group(function () {
        Route::get('/profesional/home', [ProfesionalController::class, 'home'])->name('profesional.home');

        Route::get('/profesional/salud-mental', [CasoController::class, 'index'])->middleware('area:Salud Mental')->name('profesional.salud-mental');
        Route::get('/profesional/inclusion', [CasoController::class, 'inclusion'])->middleware('area:Inclusión')->name('profesional.inclusion');
        Route::get('/profesional/inclusion/recursos', [RecursoController::class, 'index'])->middleware('area:Inclusión')->name('inclusion.recursos.index');
        Route::get('/profesional/inclusion/recursos/{recurso}', [RecursoController::class, 'show'])->middleware('area:Inclusión')->whereNumber('recurso')->name('inclusion.recursos.show');

        // Violencias de Género: el profesional activa la ruta VBG en nombre de un estudiante
        Route::get('/profesional/violencias-genero', [VbgReporteController::class, 'profesional'])->name('profesional.vbg');
        Route::post('/profesional/violencias-genero', [VbgReporteController::class, 'store'])->name('profesional.vbg.store');

        Route::get('/profesional/expedientes', [CasoController::class, 'expedientes'])->name('profesional.expedientes');
        // Expediente digital único (HU-005): todo lo registrado en el caso, en una sola vista
        Route::get('/profesional/expedientes/{caso}', [ExpedienteController::class, 'show'])->whereUuid('caso')->name('profesional.expediente.show');
        // En el expediente: atenciones, factores de riesgo y protectores, y alertas tempranas (HU-026).
        // Solo el profesional a cargo del caso; a los demás, 404.
        Route::post('/profesional/expedientes/{caso}/atenciones', [AtencionController::class, 'store'])->whereUuid('caso')->name('atenciones.store');
        Route::put('/profesional/expedientes/{caso}/factores', [ExpedienteController::class, 'actualizarFactores'])->whereUuid('caso')->name('casos.actualizar-factores');
        Route::put('/profesional/alertas/{alerta}/atender', [ExpedienteController::class, 'atenderAlerta'])->whereUuid('alerta')->name('alertas.atender');

        // Estadística de los casos activos del profesional
        Route::get('/profesional/analitica', [AnaliticaController::class, 'index'])->name('profesional.analitica');

        // Apertura de casos de atención (HU-004)
        Route::get('/profesional/casos/nuevo', [CasoController::class, 'create'])->name('casos.create');
        Route::post('/profesional/casos', [CasoController::class, 'store'])->name('casos.store');
        Route::get('/profesional/casos/{caso}', [CasoController::class, 'show'])->whereUuid('caso')->name('casos.show');

        // Agenda y citas de asesoría psicológica (HU-011)
        Route::get('/profesional/agenda', [CitaController::class, 'index'])->name('citas.agenda');
        Route::get('/profesional/citas/nueva', [CitaController::class, 'create'])->name('citas.create');
        Route::post('/profesional/citas', [CitaController::class, 'store'])->name('citas.store');
        Route::get('/profesional/citas/disponibilidad', [CitaController::class, 'disponibilidad'])->name('citas.disponibilidad');

        // Solicitudes de cita que los estudiantes dirigen al profesional: aceptar o rechazar
        Route::get('/profesional/solicitudes', [SolicitudPendienteController::class, 'index'])->name('solicitudes.index');
        // Las solicitudes llegan al psicólogo de la facultad y jornada: él agenda la primera cita.
        // Las que llegan sin profesional (nadie atiende esa facultad y jornada) se las asigna uno.
        Route::post('/profesional/solicitudes/{caso}/agendar', [SolicitudPendienteController::class, 'agendar'])->whereUuid('caso')->name('solicitudes.agendar');
        Route::post('/profesional/solicitudes/{caso}/tomar', [SolicitudPendienteController::class, 'tomar'])->whereUuid('caso')->name('solicitudes.tomar');

        // Asistencia a citas (HU-012) y tareas terapéuticas (HU-013)
        Route::patch('/profesional/citas/{cita}/asistencia', [CitaController::class, 'registrarAsistencia'])->whereUuid('cita')->name('citas.asistencia');
        Route::post('/profesional/casos/{caso}/tareas', [TareaTerapeuticaController::class, 'store'])->whereUuid('caso')->name('tareas.store');

        // Inclusión: ajustes razonables del caso (HU-018), su seguimiento (HU-019) y el reporte (HU-020)
        Route::post('/profesional/casos/{caso}/ajustes', [AjusteRazonableController::class, 'store'])->whereUuid('caso')->name('ajustes.store');
        Route::get('/profesional/casos/{caso}/reporte-inclusion.pdf', [ReporteInclusionController::class, 'pdf'])->whereUuid('caso')->name('casos.reporte-inclusion');
        Route::post('/profesional/ajustes/{ajuste}/seguimientos', [AjusteRazonableController::class, 'seguimiento'])->whereUuid('ajuste')->name('ajustes.seguimiento');

        // Consentimiento de Inclusión firmado en papel
        Route::get('/profesional/casos/{caso}/inclusion/consentimiento-papel', [ConsentimientoPapelController::class, 'create'])->whereUuid('caso')->name('inclusion.consentimiento.papel');
        Route::get('/profesional/casos/{caso}/inclusion/consentimiento-papel/formato.pdf', [ConsentimientoPapelController::class, 'imprimible'])->whereUuid('caso')->name('inclusion.consentimiento.imprimible');
        Route::post('/profesional/casos/{caso}/inclusion/consentimiento-papel', [ConsentimientoPapelController::class, 'store'])->whereUuid('caso')->name('inclusion.consentimiento.papel.registrar');

        // Bitácora de orientación y acompañamiento
        Route::get('/profesional/casos/{caso}/inclusion/bitacora', [BitacoraController::class, 'show'])->whereUuid('caso')->name('inclusion.bitacora');
        Route::post('/profesional/casos/{caso}/inclusion/bitacora', [BitacoraController::class, 'guardar'])->whereUuid('caso')->name('inclusion.bitacora.guardar');
        Route::get('/profesional/casos/{caso}/inclusion/bitacora.pdf', [BitacoraController::class, 'pdf'])->whereUuid('caso')->name('inclusion.bitacora.pdf');
        Route::post('/profesional/casos/{caso}/inclusion/bitacora/entradas', [EntradaBitacoraController::class, 'store'])->whereUuid('caso')->name('inclusion.entradas.store');
        Route::post('/profesional/inclusion/entradas/{entrada}/anotaciones', [EntradaBitacoraController::class, 'anotar'])->whereUuid('entrada')->name('inclusion.entradas.anotar');
        Route::post('/profesional/casos/{caso}/inclusion/cierre', [CierreCasoController::class, 'store'])->whereUuid('caso')->name('inclusion.cierre');

        // Plantilla 5.8 de ajustes pedagógicos
        Route::get('/profesional/casos/{caso}/inclusion/plantilla', [PlantillaAjustesController::class, 'show'])->whereUuid('caso')->name('inclusion.plantilla');
        Route::post('/profesional/casos/{caso}/inclusion/plantilla', [PlantillaAjustesController::class, 'store'])->whereUuid('caso')->name('inclusion.plantilla.guardar');
        Route::get('/profesional/casos/{caso}/inclusion/plantilla.pdf', [PlantillaAjustesController::class, 'pdf'])->whereUuid('caso')->name('inclusion.plantilla.pdf');
        Route::get('/profesional/casos/{caso}/inclusion/plantilla-compartir.pdf', [PlantillaAjustesController::class, 'compartir'])->whereUuid('caso')->name('inclusion.plantilla.compartir');

        // Soportes y evidencias de los formatos de Inclusión
        Route::post('/profesional/casos/{caso}/inclusion/evidencias', [EvidenciaController::class, 'store'])->whereUuid('caso')->name('inclusion.evidencias.store');
        Route::delete('/profesional/inclusion/evidencias/{evidencia}', [EvidenciaController::class, 'quitar'])->whereUuid('evidencia')->name('inclusion.evidencias.quitar');
        Route::get('/profesional/inclusion/evidencias/{evidencia}/archivo', [EvidenciaController::class, 'archivo'])->whereUuid('evidencia')->name('inclusion.evidencias.archivo');

        // Horarios que el psicólogo propone; el estudiante elige uno y se vuelve la cita
        Route::post('/profesional/casos/{caso}/propuestas', [PropuestaCitaController::class, 'proponer'])->whereUuid('caso')->name('propuestas.store');
        Route::post('/profesional/casos/{caso}/propuestas/retirar', [PropuestaCitaController::class, 'retirar'])->whereUuid('caso')->name('propuestas.retirar');

        // Evolución del caso (HU-015): seguimiento de cada cita asistida y vista de evolución
        Route::post('/profesional/citas/{cita}/seguimiento', [SeguimientoEvolucionController::class, 'store'])->whereUuid('cita')->name('seguimientos.store');
        Route::get('/profesional/casos/{caso}/evolucion', [EvolucionCasoController::class, 'show'])->whereUuid('caso')->name('casos.evolucion');

        // Desistimiento de la atención: cierra el caso y deja el formato para firmar
        Route::post('/profesional/casos/{caso}/desistimiento', [DesistimientoController::class, 'store'])->whereUuid('caso')->name('desistimientos.store');
        Route::get('/profesional/desistimientos/{desistimiento}/pdf', [DesistimientoController::class, 'pdf'])->whereUuid('desistimiento')->name('desistimientos.pdf');

        // Firma del profesional tratante sobre el consentimiento que aceptó el estudiante
        Route::post('/profesional/consentimientos/{aceptacion}/firma', [ConsentimientoFirmaController::class, 'firmarProfesional'])->whereUuid('aceptacion')->name('consentimientos.firmar-profesional');

        // Historia clínica psicológica del caso de Salud Mental: se registra una vez y se corrige con anotaciones
        Route::post('/profesional/casos/{caso}/historia-clinica', [HistoriaClinicaController::class, 'store'])->whereUuid('caso')->name('historia-clinica.store');
        Route::post('/profesional/casos/{caso}/historia-clinica/anotaciones', [HistoriaClinicaController::class, 'anotar'])->whereUuid('caso')->name('historia-clinica.anotar');

        // Remisión del caso a otro profesional o a una entidad externa (HU-007)
        Route::post('/profesional/casos/{caso}/remisiones', [RemisionController::class, 'store'])->whereUuid('caso')->name('remisiones.store');

        // Documento de remisión externa con el formato GBU-GPGR-F-016 (RQ-3)
        Route::get('/profesional/remisiones/{remision}/documento.pdf', [RemisionDocumentoController::class, 'pdf'])->whereUuid('remision')->name('remisiones.documento.pdf');
        Route::get('/profesional/remisiones/{remision}/documento.docx', [RemisionDocumentoController::class, 'word'])->whereUuid('remision')->name('remisiones.documento.word');

        Route::patch('/profesional/casos/{caso}/estudiante', [DatosEstudianteController::class, 'update'])->whereUuid('caso')->name('estudiante.datos.update');
    });

    // Panel Almacén
    Route::middleware('role:almacen')->group(function () {
        Route::get('/almacen/home', [AlmacenController::class, 'index'])->name('almacen.home');
        Route::post('/almacen/donantes', [AlmacenController::class, 'storeDonante'])->name('almacen.donantes.store');
        Route::patch('/almacen/donantes/{donante}/estado', [AlmacenController::class, 'cambiarEstadoDonante'])->whereNumber('donante')->name('almacen.donantes.estado');
        Route::get('/almacen/donantes/{donante}/certificado.pdf', [AlmacenController::class, 'certificadoDonante'])->whereNumber('donante')->name('almacen.donantes.certificado');
        Route::post('/almacen/lotes', [AlmacenController::class, 'storeLote'])->name('almacen.lotes.store');
        Route::post('/almacen/solicitudes/{solicitud}/aprobar', [AlmacenController::class, 'aprobar'])->whereUuid('solicitud')->name('almacen.solicitudes.aprobar');
        Route::post('/almacen/solicitudes/{solicitud}/rechazar', [AlmacenController::class, 'rechazar'])->whereUuid('solicitud')->name('almacen.solicitudes.rechazar');
        Route::post('/almacen/solicitudes/{solicitud}/entregar', [AlmacenController::class, 'entregar'])->whereUuid('solicitud')->name('almacen.solicitudes.entregar');
        Route::post('/almacen/beneficiarios/cargue-masivo', [AlmacenController::class, 'cargueMasivo'])->name('almacen.beneficiarios.cargue');
        Route::get('/almacen/entregas/{entrega}/firma', [AlmacenController::class, 'firmaEntrega'])->whereUuid('entrega')->name('almacen.entregas.firma');
        Route::get('/almacen/informe-transparencia.pdf', [AlmacenController::class, 'informeTransparenciaPdf'])->name('almacen.reportes.transparencia');
    });

    // Panel VBG (Violencias Basadas en Género)
    Route::middleware('role:vbg')->group(function () {
        Route::get('/vbg/home', [VbgCasoController::class, 'index'])->name('vbg.home');
        Route::get('/vbg/reportes/{reporte}', [VbgCasoController::class, 'show'])->whereUuid('reporte')->name('vbg.reportes.show');
        Route::post('/vbg/reportes/{reporte}/tomar', [VbgCasoController::class, 'tomar'])->whereUuid('reporte')->name('vbg.reportes.tomar');
        Route::patch('/vbg/reportes/{reporte}/valoracion', [VbgCasoController::class, 'valorar'])->whereUuid('reporte')->name('vbg.reportes.valorar');
        Route::post('/vbg/reportes/{reporte}/acciones', [VbgCasoController::class, 'accion'])->whereUuid('reporte')->name('vbg.reportes.accion');
        Route::post('/vbg/reportes/{reporte}/cerrar', [VbgCasoController::class, 'cerrar'])->whereUuid('reporte')->name('vbg.reportes.cerrar');
        Route::post('/vbg/reportes/{reporte}/reabrir', [VbgCasoController::class, 'reabrir'])->whereUuid('reporte')->name('vbg.reportes.reabrir');

        // Citas, consentimiento por cita, audios e informes (integrado desde la rama VIOLENCIA_GENERO; base sigabu_vbg)
        Route::post('/vbg/reportes/{reporte}/citas', [VbgCitaController::class, 'store'])->whereUuid('reporte')->name('vbg.citas.store');
        Route::patch('/vbg/citas/{cita}/estado', [VbgCitaController::class, 'estado'])->whereUuid('cita')->name('vbg.citas.estado');
        Route::get('/vbg/citas/{cita}/consentimiento', [VbgConsentimientoCitaController::class, 'create'])->whereUuid('cita')->name('vbg.citas.consentimiento');
        Route::post('/vbg/citas/{cita}/consentimiento', [VbgConsentimientoCitaController::class, 'store'])->whereUuid('cita')->name('vbg.citas.consentimiento.store');
        Route::get('/vbg/consentimientos/{consentimiento}/firma/{quien?}', [VbgConsentimientoCitaController::class, 'firma'])->whereUuid('consentimiento')->whereIn('quien', ['menor'])->name('vbg.consentimientos.firma');
        Route::post('/vbg/citas/{cita}/audios', [VbgAudioController::class, 'store'])->whereUuid('cita')->name('vbg.audios.store');
        Route::get('/vbg/audios/{audio}', [VbgAudioController::class, 'show'])->whereUuid('audio')->name('vbg.audios.show');
        Route::put('/vbg/audios/{audio}/transcripcion', [VbgAudioController::class, 'transcripcion'])->whereUuid('audio')->name('vbg.audios.transcripcion');
        Route::get('/vbg/reportes/{reporte}/informe', [VbgInformeController::class, 'create'])->whereUuid('reporte')->name('vbg.informes.create');
        Route::post('/vbg/reportes/{reporte}/informe', [VbgInformeController::class, 'store'])->whereUuid('reporte')->name('vbg.informes.store');
        Route::get('/vbg/informes/{informe}/pdf', [VbgInformeController::class, 'pdf'])->whereUuid('informe')->name('vbg.informes.pdf');
    });

    // Panel Admin
    Route::middleware(['auth', 'role:admin'])->group(function () {
        // Panel del administrador con datos reales de los cuatro módulos
        Route::get('/admin/dashboard', [PanelController::class, 'inicio'])->name('admin.dashboard');
        Route::get('/admin/global', [PanelController::class, 'global'])->name('admin.global');
        // La analítica institucional es el tablero de indicadores (HU-032)
        Route::redirect('/admin/analitica', '/indicadores')->name('admin.analitica');
        Route::get('/admin/auditoria', [PanelController::class, 'auditoria'])->name('admin.auditoria');


        Route::get('/admin/administracion', [UsuarioRolController::class, 'index'])->name('admin.administracion');
        Route::get('/admin/usuarios/buscar', [UsuarioRolController::class, 'buscar'])->name('admin.usuarios.buscar');
        Route::patch('/admin/usuarios/{usuario}/rol', [UsuarioRolController::class, 'asignarRol'])->name('admin.usuarios.rol');
        Route::delete('/admin/usuarios/{usuario}/rol', [UsuarioRolController::class, 'quitarRol'])->name('admin.usuarios.rol.quitar');
        Route::patch('/admin/usuarios/{usuario}/estado', [UsuarioRolController::class, 'cambiarEstado'])->name('admin.usuarios.estado');
        Route::patch('/admin/usuarios/{usuario}/indicadores', [UsuarioRolController::class, 'cambiarIndicadores'])->name('admin.usuarios.indicadores');

        // Traslado de último recurso: casos de un profesional que no puede entregarlos
        Route::get('/admin/usuarios/{usuario}/traslado-casos', [TrasladoCasosController::class, 'index'])->whereUuid('usuario')->name('admin.traslados.index');
        Route::post('/admin/usuarios/{usuario}/traslado-casos', [TrasladoCasosController::class, 'store'])->whereUuid('usuario')->name('admin.traslados.store');

        // Reapertura de casos de Inclusión cerrados con constancia
        Route::get('/admin/casos/reapertura', [ReaperturaCasoController::class, 'index'])->name('admin.casos.reapertura');
        Route::post('/admin/casos/{caso}/reapertura', [ReaperturaCasoController::class, 'store'])->whereUuid('caso')->name('admin.casos.reabrir');

        // Modelos de consentimiento y sus versiones (HU-009)
        Route::get('/admin/consentimientos', [ConsentimientoController::class, 'index'])->name('admin.consentimientos.index');
        Route::post('/admin/consentimientos', [ConsentimientoController::class, 'store'])->name('admin.consentimientos.store');
        Route::get('/admin/consentimientos/{modelo}', [ConsentimientoController::class, 'show'])->whereNumber('modelo')->name('admin.consentimientos.show');
        Route::patch('/admin/consentimientos/{modelo}', [ConsentimientoController::class, 'update'])->whereNumber('modelo')->name('admin.consentimientos.update');
        Route::post('/admin/consentimientos/{modelo}/versiones', [ConsentimientoController::class, 'storeVersion'])->whereNumber('modelo')->name('admin.consentimientos.versiones.store');
        Route::patch('/admin/consentimientos/versiones/{version}', [ConsentimientoController::class, 'updateVersion'])->whereNumber('version')->name('admin.consentimientos.versiones.update');
        Route::post('/admin/consentimientos/versiones/{version}/publicar', [ConsentimientoController::class, 'publicar'])->whereNumber('version')->name('admin.consentimientos.versiones.publicar');

        // Formatos institucionales: código, versión y fecha de Calidad (D-17)
        Route::get('/admin/formatos', [FormatoInstitucionalController::class, 'index'])->name('admin.formatos.index');
        Route::patch('/admin/formatos/{formato}', [FormatoInstitucionalController::class, 'update'])->whereNumber('formato')->name('admin.formatos.update');

        // Asignación de profesionales por servicio, facultad y jornada (revisión del 06/10/2026)
        Route::get('/admin/asignaciones', [AsignacionProfesionalController::class, 'index'])->name('admin.asignaciones.index');
        Route::post('/admin/asignaciones', [AsignacionProfesionalController::class, 'store'])->name('admin.asignaciones.store');
        Route::patch('/admin/asignaciones/{asignacion}/estado', [AsignacionProfesionalController::class, 'cambiarEstado'])->whereNumber('asignacion')->name('admin.asignaciones.estado');
        Route::post('/admin/facultades', [AsignacionProfesionalController::class, 'storeFacultad'])->name('admin.facultades.store');
        Route::patch('/admin/facultades/{facultad}/estado', [AsignacionProfesionalController::class, 'cambiarEstadoFacultad'])->whereNumber('facultad')->name('admin.facultades.estado');
    });

    // Rutas de Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// API de lectura con la sesión web (no hay routes/api.php ni Sanctum). Sus errores salen en
// JSON (bootstrap/app.php) y el rol se valida en el controlador: 403 en lugar de la redirección de CheckRole.
Route::prefix('api/v1')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/casos/{caso}/evolucion', [EvolucionCasoController::class, 'json'])->whereUuid('caso')->name('api.casos.evolucion');
});

require __DIR__.'/auth.php';
<?php

namespace Tests\Concerns;

use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use App\Models\Persona;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Support\Facades\DB;

/**
 * Réplica en SQLite de las tablas de PostgreSQL que usa la apertura de casos
 * (roles, usuarios, personas, facultades con las asignaciones de profesionales,
 * solicitudes_atencion, casos, actuaciones con sus remisiones,
 * tareas_terapeuticas, las de consentimientos versionados y firmados (con la firma del
 * profesional), remisiones_externas,
 * formatos_institucionales, historias_clinicas con sus anotaciones, seguimientos_evolucion
 * y auditoria_expedientes),
 * con sus CHECK/UNIQUE y un trigger equivalente a fn_crear_caso_desde_solicitud(). Esas
 * tablas viven en la base compartida: las migraciones que las tocan solo corren en PostgreSQL.
 */
trait CreaEsquemaSigabu
{
    protected function crearEsquemaSigabu(): void
    {
        // Solo en pruebas: quien no indica área queda con ambas, para que cada prueba ejercite su
        // propio tema. AreaProfesionalTest fija el área de cada profesional.
        User::creating(fn (User $usuario) => $usuario->area_profesional ??= User::AREA_AMBAS);

        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS roles (
                id_rol INTEGER PRIMARY KEY,
                nombre_rol VARCHAR(50) NOT NULL UNIQUE,
                sistema_destino VARCHAR(30) NOT NULL
            );

            CREATE TABLE IF NOT EXISTS usuarios (
                id_usuario VARCHAR(36) PRIMARY KEY,
                codigo_unico VARCHAR(30) NOT NULL UNIQUE,
                contrasena_hash VARCHAR(255) NOT NULL,
                id_rol INTEGER NOT NULL REFERENCES roles(id_rol),
                estado_activo BOOLEAN NOT NULL DEFAULT 1,
                ve_indicadores BOOLEAN NOT NULL DEFAULT 0,
                area_profesional VARCHAR(20) DEFAULT 'Ambas' CHECK (area_profesional IN ('Salud Mental', 'Inclusión', 'Ambas'))
            );

            CREATE TABLE IF NOT EXISTS personas (
                id_persona VARCHAR(36) PRIMARY KEY,
                id_usuario VARCHAR(36) NOT NULL UNIQUE REFERENCES usuarios(id_usuario),
                numero_documento VARCHAR(20) NOT NULL UNIQUE,
                nombre_completo VARCHAR(150) NOT NULL,
                correo_inst VARCHAR(120) NOT NULL UNIQUE,
                tipo_documento VARCHAR(2) CHECK (tipo_documento IN ('TI', 'CC', 'CE')),
                sexo CHAR(1) CHECK (sexo IN ('F', 'M')),
                fecha_nacimiento DATE CHECK (fecha_nacimiento >= '1900-01-01')
            );

            CREATE TABLE IF NOT EXISTS vinculacion (
                id_vinculacion INTEGER PRIMARY KEY AUTOINCREMENT,
                id_persona VARCHAR(36) NOT NULL REFERENCES personas(id_persona) ON DELETE CASCADE,
                tipo_vinculacion VARCHAR(30) NOT NULL CHECK (tipo_vinculacion IN ('Estudiante', 'Docente', 'Administrativo')),
                facultad VARCHAR(100),
                programa_academico VARCHAR(150),
                id_facultad INTEGER REFERENCES facultades(id_facultad),
                jornada VARCHAR(10) CHECK (jornada IN ('Diurna', 'Nocturna')),
                semestre SMALLINT CHECK (semestre BETWEEN 1 AND 12)
            );

            CREATE TABLE IF NOT EXISTS facultades (
                id_facultad INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre VARCHAR(150) NOT NULL UNIQUE,
                activa BOOLEAN NOT NULL DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS asignaciones_profesional (
                id_asignacion INTEGER PRIMARY KEY AUTOINCREMENT,
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                tipo_servicio VARCHAR(20) NOT NULL CHECK (tipo_servicio IN ('Salud Mental', 'Inclusión')),
                id_facultad INTEGER NOT NULL REFERENCES facultades(id_facultad),
                jornada VARCHAR(10) NOT NULL CHECK (jornada IN ('Diurna', 'Nocturna')),
                activa BOOLEAN NOT NULL DEFAULT 1,
                UNIQUE (id_profesional, tipo_servicio, id_facultad, jornada)
            );

            CREATE TABLE IF NOT EXISTS solicitudes_atencion (
                id_solicitud VARCHAR(36) PRIMARY KEY,
                id_persona VARCHAR(36) NOT NULL REFERENCES personas(id_persona),
                tipo_servicio VARCHAR(20) NOT NULL CHECK (tipo_servicio IN ('Salud Mental', 'Inclusión')),
                descripcion TEXT,
                fecha_solicitud TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                estado_solicitud VARCHAR(20) NOT NULL DEFAULT 'Recibida' CHECK (estado_solicitud IN ('Recibida', 'Procesada')),
                fecha_preferida DATE,
                hora_preferida VARCHAR(5),
                modalidad VARCHAR(12) CHECK (modalidad IN ('Presencial', 'Virtual')),
                id_profesional_preferido VARCHAR(36) REFERENCES usuarios(id_usuario),
                fecha_respuesta TIMESTAMP,
                motivo_rechazo TEXT,
                id_facultad INTEGER REFERENCES facultades(id_facultad),
                jornada VARCHAR(10) CHECK (jornada IN ('Diurna', 'Nocturna')),
                franja_horaria VARCHAR(10) CHECK (franja_horaria IN ('Mañana', 'Tarde', 'Noche', 'Todas')),
                es_emergencia BOOLEAN NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS casos (
                id_caso VARCHAR(36) PRIMARY KEY,
                id_solicitud_origen VARCHAR(36) NOT NULL UNIQUE REFERENCES solicitudes_atencion(id_solicitud),
                codigo_expediente VARCHAR(20) NOT NULL UNIQUE,
                nivel_riesgo VARCHAR(10) CHECK (nivel_riesgo IN ('Bajo', 'Medio', 'Alto')),
                estado_caso VARCHAR(25) NOT NULL DEFAULT 'Pendiente Asignación'
                    CHECK (estado_caso IN ('Abierto', 'Pendiente Asignación', 'En Seguimiento', 'Cerrado')),
                id_profesional VARCHAR(36) REFERENCES usuarios(id_usuario),
                fecha_apertura TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fecha_cierre TIMESTAMP,
                factores_riesgo TEXT,
                factores_protectores TEXT
            );

            CREATE TABLE IF NOT EXISTS actuaciones (
                id_actuacion VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                id_profesional VARCHAR(36) REFERENCES usuarios(id_usuario),
                tipo_actuacion VARCHAR(30) NOT NULL CHECK (tipo_actuacion IN ('Consulta', 'Remisión', 'Entrevista')),
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                descripcion TEXT,
                estado_asistencia VARCHAR(15) NOT NULL DEFAULT 'Pendiente'
                    CHECK (estado_asistencia IN ('Pendiente', 'Asistió', 'No asistió', 'Cancelada')),
                motivo_inasistencia TEXT,
                destino_remision VARCHAR(10),
                id_profesional_destino VARCHAR(36) REFERENCES usuarios(id_usuario),
                entidad_destino VARCHAR(150),
                id_remision VARCHAR(36) REFERENCES actuaciones(id_actuacion),
                autorizacion_traslado VARCHAR(300),
                id_admin_traslado VARCHAR(36) REFERENCES usuarios(id_usuario),
                CHECK (
                    (tipo_actuacion = 'Remisión' AND (
                        (destino_remision = 'Interna' AND id_profesional_destino IS NOT NULL AND entidad_destino IS NULL)
                        OR (destino_remision = 'Externa' AND entidad_destino IS NOT NULL AND id_profesional_destino IS NULL)
                    ))
                    OR (tipo_actuacion <> 'Remisión' AND destino_remision IS NULL AND id_profesional_destino IS NULL AND entidad_destino IS NULL)
                )
            );

            CREATE TABLE IF NOT EXISTS tareas_terapeuticas (
                id_tarea VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                id_profesional VARCHAR(36) REFERENCES usuarios(id_usuario),
                titulo VARCHAR(150) NOT NULL,
                descripcion TEXT NOT NULL,
                fecha_limite DATE,
                fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente de evaluación'
                    CHECK (estado IN ('Pendiente de evaluación', 'Cumplida', 'No cumplida', 'Cancelada')),
                id_remision VARCHAR(36) REFERENCES actuaciones(id_actuacion)
            );

            CREATE TABLE IF NOT EXISTS modelos_consentimiento (
                id_modelo INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo VARCHAR(50) NOT NULL UNIQUE,
                nombre VARCHAR(150) NOT NULL,
                tipo_servicio VARCHAR(100),
                descripcion TEXT,
                activo BOOLEAN NOT NULL DEFAULT 1,
                poblacion VARCHAR(10) NOT NULL DEFAULT 'General' CHECK (poblacion IN ('General', 'Menores'))
            );

            CREATE TABLE IF NOT EXISTS versiones_consentimiento (
                id_version INTEGER PRIMARY KEY AUTOINCREMENT,
                id_modelo INTEGER NOT NULL REFERENCES modelos_consentimiento(id_modelo),
                numero_version INTEGER NOT NULL CHECK (numero_version > 0),
                texto TEXT NOT NULL,
                estado VARCHAR(20) NOT NULL DEFAULT 'borrador' CHECK (estado IN ('borrador', 'publicada', 'retirada')),
                fecha_publicacion TIMESTAMP,
                creado_por VARCHAR(36) REFERENCES usuarios(id_usuario),
                fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (id_modelo, numero_version)
            );

            CREATE UNIQUE INDEX IF NOT EXISTS uq_una_publicada_por_modelo
                ON versiones_consentimiento (id_modelo) WHERE estado = 'publicada';

            CREATE TABLE IF NOT EXISTS consentimientos (
                id_consentimiento INTEGER PRIMARY KEY AUTOINCREMENT,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                aceptado BOOLEAN NOT NULL DEFAULT 0,
                fecha_hora_aceptacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                version_formato VARCHAR(50) NOT NULL,
                id_version INTEGER REFERENCES versiones_consentimiento(id_version)
            );

            CREATE TABLE IF NOT EXISTS consentimiento_aceptaciones (
                id_aceptacion VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                id_version INTEGER NOT NULL REFERENCES versiones_consentimiento(id_version),
                decision VARCHAR(10) NOT NULL CHECK (decision IN ('Aceptado', 'Rechazado')),
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                medio VARCHAR(30) NOT NULL CHECK (medio IN ('firma_tactil_expediente', 'firma_tactil_enlace', 'firma_tactil_inclusion', 'firma_papel_inclusion')),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                otorgante VARCHAR(10) NOT NULL DEFAULT 'Estudiante' CHECK (otorgante IN ('Estudiante', 'Acudiente')),
                acudiente_nombre VARCHAR(150),
                acudiente_tipo_documento VARCHAR(3) CHECK (acudiente_tipo_documento IN ('CC', 'CE', 'TI', 'PA')),
                acudiente_numero_documento VARCHAR(20),
                acudiente_parentesco VARCHAR(50),
                acudiente_lugar_expedicion VARCHAR(100),
                acudiente_telefono VARCHAR(20),
                firma_estudiante_ruta VARCHAR(255),
                firma_estudiante_sha256 CHAR(64),
                lugar_expedicion VARCHAR(100),
                eps_ips VARCHAR(150),
                contacto_urgencia VARCHAR(500),
                contactos_urgencia TEXT,
                vinculacion VARCHAR(15) CHECK (vinculacion IN ('Estudiante', 'Docente TC', 'Docente TCO', 'Docente HC', 'Funcionario', 'Otro')),
                programa_academico VARCHAR(150),
                semestre SMALLINT CHECK (semestre BETWEEN 1 AND 12),
                dependencia_facultad VARCHAR(150),
                texto_presentado TEXT NOT NULL,
                texto_sha256 CHAR(64) NOT NULL,
                firma_ruta VARCHAR(255),
                firma_sha256 CHAR(64),
                ip_origen VARCHAR(45),
                user_agent VARCHAR(255),
                CHECK (
                    (decision = 'Aceptado' AND firma_ruta IS NOT NULL AND firma_sha256 IS NOT NULL)
                    OR (decision = 'Rechazado' AND firma_ruta IS NULL AND firma_sha256 IS NULL)),
                CHECK (
                    (otorgante = 'Estudiante' AND acudiente_nombre IS NULL AND acudiente_tipo_documento IS NULL
                        AND acudiente_numero_documento IS NULL AND acudiente_parentesco IS NULL AND acudiente_lugar_expedicion IS NULL)
                    OR (otorgante = 'Acudiente' AND acudiente_nombre IS NOT NULL AND acudiente_tipo_documento IS NOT NULL
                        AND acudiente_numero_documento IS NOT NULL AND acudiente_parentesco IS NOT NULL)),
                CHECK (
                    decision = 'Rechazado'
                    OR medio IN ('firma_tactil_inclusion', 'firma_papel_inclusion')
                    OR (otorgante = 'Estudiante' AND lugar_expedicion IS NOT NULL AND eps_ips IS NOT NULL AND contacto_urgencia IS NOT NULL)
                    OR (otorgante = 'Acudiente' AND acudiente_lugar_expedicion IS NOT NULL AND eps_ips IS NOT NULL)),
                CHECK (
                    decision = 'Rechazado'
                    OR (vinculacion IS NOT NULL AND dependencia_facultad IS NOT NULL
                        AND (vinculacion <> 'Estudiante' OR (programa_academico IS NOT NULL AND semestre IS NOT NULL))))
            );

            CREATE TABLE IF NOT EXISTS inclusion_consentimientos (
                id_aceptacion VARCHAR(36) PRIMARY KEY REFERENCES consentimiento_aceptaciones(id_aceptacion),
                decision_acompanamiento BOOLEAN NOT NULL DEFAULT 0,
                decision_datos_generales BOOLEAN NOT NULL DEFAULT 0,
                decision_datos_sensibles BOOLEAN NOT NULL DEFAULT 0,
                decision_compartir BOOLEAN NOT NULL DEFAULT 0,
                nombre_persona VARCHAR(150),
                documento_persona VARCHAR(20),
                numero_contacto VARCHAR(15),
                vinculacion_detalle VARCHAR(30),
                es_revocacion BOOLEAN NOT NULL DEFAULT 0,
                motivo_papel TEXT,
                fecha_firma_papel DATE
            );

            CREATE TABLE IF NOT EXISTS consentimiento_firmas_profesional (
                id_aceptacion VARCHAR(36) PRIMARY KEY REFERENCES consentimiento_aceptaciones(id_aceptacion),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                firma_ruta VARCHAR(255) NOT NULL,
                firma_sha256 CHAR(64) NOT NULL,
                ip_origen VARCHAR(45),
                user_agent VARCHAR(255)
            );

            CREATE TABLE IF NOT EXISTS formatos_institucionales (
                id_formato INTEGER PRIMARY KEY AUTOINCREMENT,
                clave VARCHAR(40) NOT NULL UNIQUE,
                proceso VARCHAR(100) NOT NULL,
                nombre VARCHAR(100) NOT NULL,
                codigo VARCHAR(30) NOT NULL,
                version VARCHAR(10) NOT NULL,
                fecha_formato DATE NOT NULL,
                actualizado_por VARCHAR(36) REFERENCES usuarios(id_usuario),
                fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS remisiones_externas (
                id_actuacion VARCHAR(36) PRIMARY KEY REFERENCES actuaciones(id_actuacion),
                categoria VARCHAR(12) NOT NULL CHECK (categoria IN ('URGENTE', 'PRIORITARIO')),
                estudiante_nombre VARCHAR(150) NOT NULL,
                estudiante_tipo_documento VARCHAR(2) NOT NULL CHECK (estudiante_tipo_documento IN ('TI', 'CC', 'CE')),
                estudiante_numero_documento VARCHAR(20) NOT NULL,
                estudiante_sexo CHAR(1) NOT NULL CHECK (estudiante_sexo IN ('F', 'M')),
                estudiante_fecha_nacimiento DATE NOT NULL,
                estudiante_edad SMALLINT NOT NULL CHECK (estudiante_edad BETWEEN 0 AND 120),
                id_cita VARCHAR(36) REFERENCES actuaciones(id_actuacion),
                fecha_hora_atencion TIMESTAMP NOT NULL,
                profesional_nombre VARCHAR(150) NOT NULL,
                acompanante_nombre VARCHAR(150),
                acompanante_parentesco VARCHAR(50),
                acompanante_tipo_documento VARCHAR(3) CHECK (acompanante_tipo_documento IN ('CC', 'CE', 'TI', 'PA')),
                acompanante_numero_documento VARCHAR(20),
                acompanante_telefono VARCHAR(20),
                formato_codigo VARCHAR(30) NOT NULL,
                formato_version VARCHAR(10) NOT NULL,
                formato_fecha DATE NOT NULL,
                CHECK (
                    (categoria = 'URGENTE' AND acompanante_nombre IS NOT NULL AND acompanante_parentesco IS NOT NULL
                        AND acompanante_tipo_documento IS NOT NULL AND acompanante_numero_documento IS NOT NULL AND acompanante_telefono IS NOT NULL)
                    OR (categoria = 'PRIORITARIO' AND acompanante_nombre IS NULL AND acompanante_parentesco IS NULL
                        AND acompanante_tipo_documento IS NULL AND acompanante_numero_documento IS NULL AND acompanante_telefono IS NULL))
            );

            CREATE TABLE IF NOT EXISTS auditoria_expedientes (
                id_auditoria INTEGER PRIMARY KEY AUTOINCREMENT,
                id_usuario VARCHAR(36) REFERENCES usuarios(id_usuario),
                id_caso VARCHAR(36) REFERENCES casos(id_caso),
                accion VARCHAR(40) NOT NULL CHECK (accion IN (
                    'consentimiento_aceptado', 'consentimiento_rechazado', 'firma_consultada',
                    'remision_externa_registrada', 'documento_remision_pdf', 'documento_remision_word',
                    'datos_estudiante_actualizados', 'formato_actualizado',
                    'historia_clinica_registrada', 'historia_clinica_anotada', 'consentimiento_firmado_profesional',
                    'desistimiento_registrado', 'documento_desistimiento_pdf',
                    'seguimiento_registrado', 'evolucion_consultada', 'expediente_consultado', 'reporte_inclusion_pdf',
                    'caso_trasladado_admin',
                    'consentimiento_revocado', 'consentimiento_papel_registrado', 'consentimiento_inclusion_pdf',
                    'bitacora_registrada', 'bitacora_actualizada', 'bitacora_entrada_registrada', 'bitacora_anotada', 'bitacora_pdf',
                    'caso_cerrado_constancia', 'caso_reabierto',
                    'ajuste_plantilla_registrado', 'plantilla_pdf', 'plantilla_compartir_pdf',
                    'evidencia_descargada', 'consentimiento_pdf',
                    'atencion_registrada', 'factores_actualizados', 'alerta_generada', 'alerta_atendida')),
                detalle TEXT,
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ip_origen VARCHAR(45)
            );

            CREATE TABLE IF NOT EXISTS historias_clinicas (
                id_historia VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL UNIQUE REFERENCES casos(id_caso),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                motivo_consulta TEXT NOT NULL,
                historia_problema TEXT,
                antecedentes_personales TEXT,
                antecedentes_familiares TEXT,
                antecedentes_medicos TEXT,
                examen_mental TEXT,
                impresion_diagnostica TEXT NOT NULL,
                plan_intervencion TEXT NOT NULL,
                observaciones TEXT,
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS historia_clinica_anotaciones (
                id_anotacion VARCHAR(36) PRIMARY KEY,
                id_historia VARCHAR(36) NOT NULL REFERENCES historias_clinicas(id_historia),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                apartado VARCHAR(40) CHECK (apartado IN (
                    'motivo_consulta', 'historia_problema', 'antecedentes_personales', 'antecedentes_familiares',
                    'antecedentes_medicos', 'examen_mental', 'impresion_diagnostica', 'plan_intervencion', 'observaciones')),
                texto TEXT NOT NULL,
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            -- Equivalentes de validar_remision_externa(), validar_firma_profesional() e impedir_cambios()
            CREATE TRIGGER IF NOT EXISTS trg_validar_firma_profesional BEFORE INSERT ON consentimiento_firmas_profesional
            WHEN NOT EXISTS (
                SELECT 1 FROM consentimiento_aceptaciones
                WHERE id_aceptacion = NEW.id_aceptacion AND decision = 'Aceptado' AND id_profesional = NEW.id_profesional
            )
            BEGIN
                SELECT RAISE(ABORT, 'Solo el profesional tratante firma un consentimiento aceptado.');
            END;

            CREATE TRIGGER IF NOT EXISTS trg_firmas_profesional_inmutables_upd BEFORE UPDATE ON consentimiento_firmas_profesional
            BEGIN SELECT RAISE(ABORT, 'Los registros de consentimiento_firmas_profesional no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_firmas_profesional_inmutables_del BEFORE DELETE ON consentimiento_firmas_profesional
            BEGIN SELECT RAISE(ABORT, 'Los registros de consentimiento_firmas_profesional no se pueden modificar ni borrar.'); END;

            CREATE TRIGGER IF NOT EXISTS trg_inclusion_consentimientos_inmutables_upd BEFORE UPDATE ON inclusion_consentimientos
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_consentimientos no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_consentimientos_inmutables_del BEFORE DELETE ON inclusion_consentimientos
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_consentimientos no se pueden modificar ni borrar.'); END;

            CREATE TRIGGER IF NOT EXISTS trg_validar_remision_externa BEFORE INSERT ON remisiones_externas
            WHEN NOT EXISTS (
                SELECT 1 FROM actuaciones
                WHERE id_actuacion = NEW.id_actuacion AND tipo_actuacion = 'Remisión' AND destino_remision = 'Externa'
            )
            BEGIN
                SELECT RAISE(ABORT, 'La actuación no es una remisión externa.');
            END;

            CREATE TRIGGER IF NOT EXISTS trg_aceptaciones_inmutables_upd BEFORE UPDATE ON consentimiento_aceptaciones
            BEGIN SELECT RAISE(ABORT, 'Los registros de consentimiento_aceptaciones no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_aceptaciones_inmutables_del BEFORE DELETE ON consentimiento_aceptaciones
            BEGIN SELECT RAISE(ABORT, 'Los registros de consentimiento_aceptaciones no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_remisiones_externas_inmutables_upd BEFORE UPDATE ON remisiones_externas
            BEGIN SELECT RAISE(ABORT, 'Los registros de remisiones_externas no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_remisiones_externas_inmutables_del BEFORE DELETE ON remisiones_externas
            BEGIN SELECT RAISE(ABORT, 'Los registros de remisiones_externas no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_historias_clinicas_inmutables_upd BEFORE UPDATE ON historias_clinicas
            BEGIN SELECT RAISE(ABORT, 'Los registros de historias_clinicas no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_historias_clinicas_inmutables_del BEFORE DELETE ON historias_clinicas
            BEGIN SELECT RAISE(ABORT, 'Los registros de historias_clinicas no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_historia_anotaciones_inmutables_upd BEFORE UPDATE ON historia_clinica_anotaciones
            BEGIN SELECT RAISE(ABORT, 'Los registros de historia_clinica_anotaciones no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_historia_anotaciones_inmutables_del BEFORE DELETE ON historia_clinica_anotaciones
            BEGIN SELECT RAISE(ABORT, 'Los registros de historia_clinica_anotaciones no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_auditoria_expedientes_inmutable_upd BEFORE UPDATE ON auditoria_expedientes
            BEGIN SELECT RAISE(ABORT, 'Los registros de auditoria_expedientes no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_auditoria_expedientes_inmutable_del BEFORE DELETE ON auditoria_expedientes
            BEGIN SELECT RAISE(ABORT, 'Los registros de auditoria_expedientes no se pueden modificar ni borrar.'); END;

            INSERT OR IGNORE INTO formatos_institucionales (clave, proceso, nombre, codigo, version, fecha_formato) VALUES
                ('REMISION_PSICOLOGICA', 'GESTIÓN APOYO AL ESTUDIANTE', 'REMISIÓN PSICOLÓGICA', 'GBU-GPGR-F-016', '3', '2024-04-18'),
                ('DESISTIMIENTO', 'GESTIÓN APOYO AL ESTUDIANTE', 'DESISTIMIENTO DE LA ATENCIÓN', 'POR-ASIGNAR', '1', '2026-10-21'),
                ('CONSENTIMIENTO_INCLUSION', 'PROCESO DE INCLUSIÓN UNIVERSITARIA', 'CONSENTIMIENTO INFORMADO - PROCESO DE INCLUSIÓN UNIVERSITARIA', 'POR-ASIGNAR', '1', '2026-11-07'),
                ('BITACORA_INCLUSION', 'GESTIÓN DE PERMANENCIA Y GRADUACIÓN - PROCESO INCLUSIÓN', 'BITÁCORA DE ORIENTACIÓN Y ACOMPAÑAMIENTO', 'POR-ASIGNAR', '1', '2026-11-07'),
                ('RECURSO_INCLUSION', 'PROCESO DE INCLUSIÓN UNIVERSITARIA', 'PLANTILLA DE AJUSTES PEDAGÓGICOS', 'POR-ASIGNAR', '1', '2026-11-07'),
                ('CONSENTIMIENTO_ORIENTACION', 'GESTIÓN APOYO AL ESTUDIANTE', 'CONSENTIMIENTO INFORMADO ORIENTACIÓN PSICOLÓGICA', 'GBU-GPGR-F-032', '1', '2023-09-13'),
                ('CONSENTIMIENTO_MENORES', 'GESTIÓN APOYO AL ESTUDIANTE', 'CONSENTIMIENTO INFORMADO MENORES DE EDAD', 'GBU-GPGR-F-019', '1', '2021-09-07');

            CREATE TABLE IF NOT EXISTS desistimientos (
                id_desistimiento VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                motivo VARCHAR(40) NOT NULL CHECK (motivo IN ('No desea continuar la atención', 'No desea contar lo sucedido', 'Prefiere atención externa', 'Otro')),
                observaciones TEXT,
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                estudiante_nombre VARCHAR(150) NOT NULL,
                estudiante_tipo_documento VARCHAR(2),
                estudiante_numero_documento VARCHAR(20) NOT NULL,
                profesional_nombre VARCHAR(150) NOT NULL,
                formato_codigo VARCHAR(30) NOT NULL,
                formato_version VARCHAR(10) NOT NULL,
                formato_fecha DATE NOT NULL,
                CHECK (motivo <> 'Otro' OR observaciones IS NOT NULL)
            );
            CREATE TRIGGER IF NOT EXISTS trg_desistimientos_inmutables_upd BEFORE UPDATE ON desistimientos
            BEGIN SELECT RAISE(ABORT, 'Los registros de desistimientos no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_desistimientos_inmutables_del BEFORE DELETE ON desistimientos
            BEGIN SELECT RAISE(ABORT, 'Los registros de desistimientos no se pueden modificar ni borrar.'); END;

            CREATE TABLE IF NOT EXISTS seguimientos_evolucion (
                id_seguimiento VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                id_actuacion VARCHAR(36) NOT NULL UNIQUE REFERENCES actuaciones(id_actuacion),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                nivel_riesgo VARCHAR(10) NOT NULL CHECK (nivel_riesgo IN ('Bajo', 'Medio', 'Alto')),
                valoracion_avance VARCHAR(15) NOT NULL CHECK (valoracion_avance IN ('Mejora', 'Estable', 'Retroceso')),
                instrumento VARCHAR(10) CHECK (instrumento IN ('GAD-7', 'PHQ-9')),
                puntaje SMALLINT,
                nota TEXT NOT NULL CHECK (length(nota) BETWEEN 1 AND 2000),
                CHECK (
                    (instrumento IS NULL AND puntaje IS NULL)
                    OR (instrumento IS NOT NULL AND puntaje IS NOT NULL AND (
                        (instrumento = 'GAD-7' AND puntaje BETWEEN 0 AND 21)
                        OR (instrumento = 'PHQ-9' AND puntaje BETWEEN 0 AND 27))))
            );
            -- Equivalente de validar_seguimiento_evolucion()
            CREATE TRIGGER IF NOT EXISTS trg_validar_seguimiento_evolucion BEFORE INSERT ON seguimientos_evolucion
            WHEN NOT EXISTS (
                SELECT 1 FROM actuaciones
                WHERE id_actuacion = NEW.id_actuacion AND id_caso = NEW.id_caso
                  AND tipo_actuacion = 'Consulta' AND estado_asistencia = 'Asistió'
            )
            BEGIN
                SELECT RAISE(ABORT, 'El seguimiento debe corresponder a una cita del caso a la que el estudiante asistió.');
            END;
            CREATE TRIGGER IF NOT EXISTS trg_seguimientos_inmutables_upd BEFORE UPDATE ON seguimientos_evolucion
            BEGIN SELECT RAISE(ABORT, 'Los registros de seguimientos_evolucion no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_seguimientos_inmutables_del BEFORE DELETE ON seguimientos_evolucion
            BEGIN SELECT RAISE(ABORT, 'Los registros de seguimientos_evolucion no se pueden modificar ni borrar.'); END;

            CREATE TRIGGER IF NOT EXISTS trg_solicitud_crea_caso AFTER INSERT ON solicitudes_atencion
            BEGIN
                INSERT INTO casos (id_caso, id_solicitud_origen, codigo_expediente, estado_caso, fecha_apertura)
                VALUES (
                    lower(hex(randomblob(4)) || '-' || hex(randomblob(2)) || '-4' || substr(hex(randomblob(2)), 2)
                        || '-a' || substr(hex(randomblob(2)), 2) || '-' || hex(randomblob(6))),
                    NEW.id_solicitud,
                    (CASE WHEN NEW.tipo_servicio = 'Salud Mental' THEN 'SM' ELSE 'IN' END) || '-' || strftime('%Y', 'now') || '-'
                        || printf('%04d', (SELECT count(*) + 1 FROM casos WHERE substr(codigo_expediente, 1, 2)
                            = (CASE WHEN NEW.tipo_servicio = 'Salud Mental' THEN 'SM' ELSE 'IN' END))),
                    'Pendiente Asignación',
                    CURRENT_TIMESTAMP
                );

                UPDATE solicitudes_atencion SET estado_solicitud = 'Procesada' WHERE id_solicitud = NEW.id_solicitud;
            END;

            CREATE TABLE IF NOT EXISTS ajustes_razonables (
                id_ajuste VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                tipo VARCHAR(30) CHECK (tipo IN ('Académico o de evaluación', 'Metodológico', 'Tecnológico o de acceso', 'De infraestructura', 'De tiempo', 'De comunicación', 'Otro')),
                barrera TEXT NOT NULL,
                descripcion TEXT NOT NULL,
                responsable VARCHAR(150),
                area VARCHAR(30) CHECK (area IN ('Instrucciones y metodología', 'Evaluación', 'Comunicación', 'Organización', 'Trabajo en grupo', 'Regulación emocional')),
                observaciones TEXT,
                id_recurso INTEGER REFERENCES inclusion_recursos(id_recurso),
                fecha_inicio DATE NOT NULL,
                estado VARCHAR(12) NOT NULL DEFAULT 'Vigente' CHECK (estado IN ('Vigente', 'Cumplido', 'Suspendido')),
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS seguimientos_ajuste (
                id_seguimiento VARCHAR(36) PRIMARY KEY,
                id_ajuste VARCHAR(36) NOT NULL REFERENCES ajustes_razonables(id_ajuste),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha DATE NOT NULL,
                cumplimiento VARCHAR(25) NOT NULL CHECK (cumplimiento IN ('Se cumple', 'Se cumple parcialmente', 'No se cumple')),
                observaciones TEXT NOT NULL,
                estado_resultante VARCHAR(12) CHECK (estado_resultante IN ('Vigente', 'Cumplido', 'Suspendido')),
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TRIGGER IF NOT EXISTS trg_seguimientos_ajuste_inmutables_upd BEFORE UPDATE ON seguimientos_ajuste
            BEGIN SELECT RAISE(ABORT, 'Los registros de seguimientos_ajuste no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_seguimientos_ajuste_inmutables_del BEFORE DELETE ON seguimientos_ajuste
            BEGIN SELECT RAISE(ABORT, 'Los registros de seguimientos_ajuste no se pueden modificar ni borrar.'); END;

            CREATE TABLE IF NOT EXISTS inclusion_recursos (
                id_recurso INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo VARCHAR(30) NOT NULL,
                condicion VARCHAR(100) NOT NULL,
                titulo VARCHAR(200) NOT NULL,
                version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0),
                contenido TEXT NOT NULL,
                activo BOOLEAN NOT NULL DEFAULT 1,
                UNIQUE (codigo, version)
            );

            CREATE TABLE IF NOT EXISTS inclusion_bitacoras (
                id_bitacora VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL UNIQUE REFERENCES casos(id_caso),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                rol_institucional VARCHAR(30) NOT NULL,
                programa_dependencia VARCHAR(150) NOT NULL,
                situacion TEXT NOT NULL,
                ruta_activada TEXT NOT NULL,
                soportes_texto TEXT,
                orientacion TEXT,
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS inclusion_bitacora_cambios (
                id_cambio VARCHAR(36) PRIMARY KEY,
                id_bitacora VARCHAR(36) NOT NULL REFERENCES inclusion_bitacoras(id_bitacora),
                campo VARCHAR(30) NOT NULL,
                valor_anterior TEXT,
                valor_nuevo TEXT,
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS inclusion_bitacora_entradas (
                id_entrada VARCHAR(36) PRIMARY KEY,
                id_bitacora VARCHAR(36) NOT NULL REFERENCES inclusion_bitacoras(id_bitacora),
                numero INTEGER NOT NULL CHECK (numero > 0),
                fecha_hora TIMESTAMP NOT NULL,
                tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('Atención inicial', 'Seguimiento', 'Cierre')),
                descripcion TEXT NOT NULL,
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (id_bitacora, numero)
            );
            CREATE UNIQUE INDEX IF NOT EXISTS uq_entrada_atencion_inicial
                ON inclusion_bitacora_entradas (id_bitacora) WHERE tipo = 'Atención inicial';

            CREATE TABLE IF NOT EXISTS inclusion_bitacora_anotaciones (
                id_anotacion VARCHAR(36) PRIMARY KEY,
                id_entrada VARCHAR(36) NOT NULL REFERENCES inclusion_bitacora_entradas(id_entrada),
                texto TEXT NOT NULL,
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS inclusion_constancias (
                id_constancia VARCHAR(36) PRIMARY KEY,
                id_bitacora VARCHAR(36) NOT NULL REFERENCES inclusion_bitacoras(id_bitacora),
                id_entrada_cierre VARCHAR(36) NOT NULL UNIQUE REFERENCES inclusion_bitacora_entradas(id_entrada),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                nombre_profesional VARCHAR(150) NOT NULL,
                texto_presentado TEXT NOT NULL,
                firma_ruta VARCHAR(255) NOT NULL,
                firma_sha256 CHAR(64) NOT NULL,
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS inclusion_reaperturas (
                id_reapertura VARCHAR(36) PRIMARY KEY,
                id_bitacora VARCHAR(36) NOT NULL REFERENCES inclusion_bitacoras(id_bitacora),
                id_constancia VARCHAR(36) NOT NULL UNIQUE REFERENCES inclusion_constancias(id_constancia),
                id_administrador VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                motivo TEXT NOT NULL,
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS inclusion_evidencias (
                id_evidencia VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                tipo VARCHAR(10) NOT NULL CHECK (tipo IN ('archivo', 'enlace')),
                destino VARCHAR(15) CHECK (destino IN ('soporte', 'entrada', 'anotacion', 'ajuste')),
                id_destino VARCHAR(36),
                estado VARCHAR(12) NOT NULL DEFAULT 'temporal' CHECK (estado IN ('temporal', 'vinculada', 'descartada')),
                ruta VARCHAR(255),
                nombre VARCHAR(200),
                mime VARCHAR(120),
                bytes BIGINT,
                sha256 CHAR(64),
                url VARCHAR(500),
                subido_por VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK ((tipo = 'archivo' AND ruta IS NOT NULL AND nombre IS NOT NULL AND sha256 IS NOT NULL) OR (tipo = 'enlace' AND url IS NOT NULL)),
                CHECK (estado <> 'vinculada' OR (destino IS NOT NULL AND id_destino IS NOT NULL))
            );
            CREATE TRIGGER IF NOT EXISTS trg_proteger_evidencia_upd BEFORE UPDATE ON inclusion_evidencias WHEN OLD.estado = 'vinculada'
            BEGIN SELECT RAISE(ABORT, 'Una evidencia vinculada no se puede modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_proteger_evidencia_del BEFORE DELETE ON inclusion_evidencias WHEN OLD.estado = 'vinculada'
            BEGIN SELECT RAISE(ABORT, 'Una evidencia vinculada no se puede modificar ni borrar.'); END;

            CREATE TABLE IF NOT EXISTS inclusion_borradores (
                id_borrador VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                id_usuario VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                documento VARCHAR(15) NOT NULL CHECK (documento IN ('consentimiento', 'bitacora', 'plantilla')),
                datos TEXT NOT NULL,
                activo BOOLEAN NOT NULL DEFAULT 1,
                fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE UNIQUE INDEX IF NOT EXISTS uq_borrador_activo ON inclusion_borradores (id_caso, id_usuario, documento) WHERE activo;

            CREATE TRIGGER IF NOT EXISTS trg_inclusion_cambios_inmutables_upd BEFORE UPDATE ON inclusion_bitacora_cambios
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_bitacora_cambios no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_cambios_inmutables_del BEFORE DELETE ON inclusion_bitacora_cambios
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_bitacora_cambios no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_entradas_inmutables_upd BEFORE UPDATE ON inclusion_bitacora_entradas
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_bitacora_entradas no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_entradas_inmutables_del BEFORE DELETE ON inclusion_bitacora_entradas
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_bitacora_entradas no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_anotaciones_inmutables_upd BEFORE UPDATE ON inclusion_bitacora_anotaciones
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_bitacora_anotaciones no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_anotaciones_inmutables_del BEFORE DELETE ON inclusion_bitacora_anotaciones
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_bitacora_anotaciones no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_constancias_inmutables_upd BEFORE UPDATE ON inclusion_constancias
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_constancias no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_constancias_inmutables_del BEFORE DELETE ON inclusion_constancias
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_constancias no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_reaperturas_inmutables_upd BEFORE UPDATE ON inclusion_reaperturas
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_reaperturas no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_inclusion_reaperturas_inmutables_del BEFORE DELETE ON inclusion_reaperturas
            BEGIN SELECT RAISE(ABORT, 'Los registros de inclusion_reaperturas no se pueden modificar ni borrar.'); END;

            CREATE TABLE IF NOT EXISTS propuestas_cita (
                id_propuesta VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso),
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_hora TIMESTAMP NOT NULL,
                estado VARCHAR(12) NOT NULL DEFAULT 'Propuesta' CHECK (estado IN ('Propuesta', 'Elegida', 'Descartada', 'Rechazada')),
                id_cita VARCHAR(36) REFERENCES actuaciones(id_actuacion),
                fecha_propuesta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fecha_respuesta TIMESTAMP,
                CHECK (estado <> 'Elegida' OR id_cita IS NOT NULL)
            );

            CREATE TABLE IF NOT EXISTS repositorio_documentos (
                id_documento INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo VARCHAR(150) NOT NULL,
                categoria VARCHAR(30) NOT NULL CHECK (categoria IN ('Ley o norma', 'Protocolo', 'Guía', 'Formato', 'Material psicoeducativo')),
                descripcion VARCHAR(500),
                url VARCHAR(500),
                audiencia VARCHAR(15) NOT NULL DEFAULT 'Profesionales' CHECK (audiencia IN ('Profesionales', 'Todos')),
                activo BOOLEAN NOT NULL DEFAULT 1,
                publicado_por VARCHAR(36) REFERENCES usuarios(id_usuario),
                fecha_publicacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                archivo_ruta VARCHAR(255),
                archivo_nombre VARCHAR(200),
                archivo_mime VARCHAR(100),
                archivo_bytes INTEGER,
                archivo_sha256 CHAR(64),
                CHECK (url IS NOT NULL OR archivo_ruta IS NOT NULL)
            );

            -- Violencias Basadas en Género
            CREATE TABLE IF NOT EXISTS vbg_reportes (
                id_reporte VARCHAR(36) PRIMARY KEY,
                codigo VARCHAR(20) NOT NULL UNIQUE,
                tipo_reportante VARCHAR(10) NOT NULL CHECK (tipo_reportante IN ('Afectada', 'Tercero', 'Anónimo')),
                id_persona_reporta VARCHAR(36) REFERENCES personas(id_persona),
                afectado_nombre VARCHAR(150),
                afectado_vinculo VARCHAR(100),
                relato TEXT NOT NULL,
                tipo_violencia VARCHAR(20) CHECK (tipo_violencia IN ('Física', 'Psicológica', 'Sexual', 'Económica', 'Digital', 'Otra')),
                lugar VARCHAR(150),
                fecha_hechos DATE,
                desea_contacto BOOLEAN NOT NULL DEFAULT 0,
                medio_contacto VARCHAR(150),
                acepta_datos BOOLEAN NOT NULL,
                fecha_reporte TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                estado VARCHAR(15) NOT NULL DEFAULT 'Recibido' CHECK (estado IN ('Recibido', 'En atención', 'Cerrado')),
                nivel_riesgo VARCHAR(10) CHECK (nivel_riesgo IN ('Bajo', 'Medio', 'Alto')),
                id_profesional VARCHAR(36) REFERENCES usuarios(id_usuario),
                fecha_cierre TIMESTAMP,
                CHECK (tipo_reportante <> 'Anónimo' OR (id_persona_reporta IS NULL AND desea_contacto = 0 AND medio_contacto IS NULL)),
                CHECK (tipo_reportante = 'Anónimo' OR id_persona_reporta IS NOT NULL),
                CHECK (tipo_reportante <> 'Tercero' OR afectado_nombre IS NOT NULL),
                CHECK (acepta_datos)
            );

            CREATE TABLE IF NOT EXISTS vbg_acciones (
                id_accion VARCHAR(36) PRIMARY KEY,
                id_reporte VARCHAR(36) NOT NULL REFERENCES vbg_reportes(id_reporte),
                id_usuario VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                tipo_accion VARCHAR(30) NOT NULL CHECK (tipo_accion IN ('Remisión jurídica', 'Denuncia ante Fiscalía', 'Seguimiento terapéutico',
                    'Remisión a Salud Mental', 'Medida de protección', 'Nota de seguimiento', 'Caso tomado', 'Caso cerrado', 'Caso reabierto')),
                descripcion TEXT NOT NULL,
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            -- Donación Alimentaria
            CREATE TABLE IF NOT EXISTS alim_donantes (
                id_donante INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre VARCHAR(150) NOT NULL,
                tipo VARCHAR(10) NOT NULL CHECK (tipo IN ('Interno', 'Externo')),
                categoria VARCHAR(20) NOT NULL CHECK (categoria IN ('Docente', 'Administrativo', 'Estudiante', 'Empresa', 'Fundación', 'Egresado', 'Otro')),
                anonimo BOOLEAN NOT NULL DEFAULT 0,
                contacto VARCHAR(150),
                activo BOOLEAN NOT NULL DEFAULT 1,
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS alim_lotes (
                id_lote INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo_lote VARCHAR(30) NOT NULL UNIQUE,
                tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('Mercado Tipo A', 'Mercado Tipo B', 'Elemento suelto')),
                descripcion VARCHAR(200) NOT NULL,
                cantidad_inicial INTEGER NOT NULL CHECK (cantidad_inicial > 0),
                cantidad_disponible INTEGER NOT NULL,
                fecha_ingreso DATE NOT NULL,
                fecha_vencimiento DATE,
                id_donante INTEGER REFERENCES alim_donantes(id_donante),
                registrado_por VARCHAR(36) REFERENCES usuarios(id_usuario),
                CHECK (cantidad_disponible BETWEEN 0 AND cantidad_inicial)
            );

            CREATE TABLE IF NOT EXISTS alim_solicitudes (
                id_solicitud VARCHAR(36) PRIMARY KEY,
                id_persona VARCHAR(36) NOT NULL REFERENCES personas(id_persona),
                situacion VARCHAR(40) NOT NULL CHECK (situacion IN ('Emergencia económica', 'Cabeza de familia', 'Estrato 1 o 2 sin apoyo familiar', 'Desempleo o reducción de ingresos')),
                estrato SMALLINT CHECK (estrato BETWEEN 1 AND 6),
                personas_a_cargo SMALLINT CHECK (personas_a_cargo BETWEEN 0 AND 20),
                detalle TEXT,
                acepta_datos BOOLEAN NOT NULL CHECK (acepta_datos),
                prioridad VARCHAR(5) NOT NULL CHECK (prioridad IN ('Alta', 'Media', 'Baja')),
                estado VARCHAR(10) NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente', 'Aprobada', 'Entregada', 'Rechazada')),
                motivo_rechazo VARCHAR(300),
                revisada_por VARCHAR(36) REFERENCES usuarios(id_usuario),
                fecha_revision TIMESTAMP,
                fecha_solicitud TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK (estado <> 'Rechazada' OR motivo_rechazo IS NOT NULL)
            );

            CREATE TABLE IF NOT EXISTS alim_entregas (
                id_entrega VARCHAR(36) PRIMARY KEY,
                id_solicitud VARCHAR(36) NOT NULL UNIQUE REFERENCES alim_solicitudes(id_solicitud),
                id_lote INTEGER NOT NULL REFERENCES alim_lotes(id_lote),
                cantidad SMALLINT NOT NULL DEFAULT 1 CHECK (cantidad > 0),
                metodo_validacion VARCHAR(20) NOT NULL CHECK (metodo_validacion IN ('Carné estudiantil', 'Cédula')),
                documento_verificado VARCHAR(20) NOT NULL,
                entregado_por VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                firma_ruta VARCHAR(255),
                firma_sha256 CHAR(64)
            );

            CREATE TABLE IF NOT EXISTS alim_priorizados (
                id_priorizado INTEGER PRIMARY KEY AUTOINCREMENT,
                documento VARCHAR(20) NOT NULL UNIQUE,
                codigo VARCHAR(30),
                estrato SMALLINT CHECK (estrato BETWEEN 1 AND 6),
                prioridad VARCHAR(5) NOT NULL CHECK (prioridad IN ('Alta', 'Media', 'Baja')),
                cargado_por VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario),
                fecha_cargue TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TRIGGER IF NOT EXISTS trg_vbg_acciones_inmutables_upd BEFORE UPDATE ON vbg_acciones
            BEGIN SELECT RAISE(ABORT, 'Los registros de vbg_acciones no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_vbg_acciones_inmutables_del BEFORE DELETE ON vbg_acciones
            BEGIN SELECT RAISE(ABORT, 'Los registros de vbg_acciones no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_alim_entregas_inmutables_upd BEFORE UPDATE ON alim_entregas
            BEGIN SELECT RAISE(ABORT, 'Los registros de alim_entregas no se pueden modificar ni borrar.'); END;
            CREATE TRIGGER IF NOT EXISTS trg_alim_entregas_inmutables_del BEFORE DELETE ON alim_entregas
            BEGIN SELECT RAISE(ABORT, 'Los registros de alim_entregas no se pueden modificar ni borrar.'); END;

            INSERT OR IGNORE INTO facultades (nombre) VALUES
                ('Facultad de Ciencias Administrativas, Contables, Económicas y Financieras'),
                ('Facultad de Ciencias Ambientales y Desarrollo Sostenible'),
                ('Facultad de Ciencias de la Educación'),
                ('Facultad de Ciencias de la Salud'),
                ('Facultad de Derecho, Ciencias Políticas y Sociales'),
                ('Facultad de Ingeniería');

            INSERT OR IGNORE INTO roles (id_rol, nombre_rol, sistema_destino) VALUES
                (1, 'Persona', 'PORTAL_USUARIO'),
                (2, 'Profesional', 'SIGABU_CORE'),
                (3, 'Admin', 'SIGABU_CORE'),
                (4, 'Alimentos', 'ALIMENTOS'),
                (5, 'VBG', 'VBG');

            -- Avisos de la campana y recordatorios enviados (migración 2026_11_10_000000). En
            -- PHPUnit ya los crea la migración; aquí son para la base ficticia de tests/e2e.
            CREATE TABLE IF NOT EXISTS notifications (
                id VARCHAR(36) PRIMARY KEY,
                type VARCHAR(255) NOT NULL,
                notifiable_type VARCHAR(255) NOT NULL,
                notifiable_id VARCHAR(36) NOT NULL,
                data TEXT NOT NULL,
                read_at TIMESTAMP,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS recordatorios_enviados (
                id_recordatorio INTEGER PRIMARY KEY AUTOINCREMENT,
                tipo VARCHAR(40) NOT NULL,
                referencia VARCHAR(64) NOT NULL,
                enviado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (tipo, referencia)
            );

            -- Atenciones del expediente y alertas tempranas (migración 2026_11_10_190000)
            CREATE TABLE IF NOT EXISTS atenciones (
                id_atencion VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso) ON DELETE RESTRICT,
                id_profesional VARCHAR(36) NOT NULL REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
                tipo_atencion VARCHAR(60) NOT NULL CHECK (tipo_atencion IN (
                    'Sesión psicológica individual', 'Evaluación de necesidades de inclusión',
                    'Seguimiento terapéutico', 'Acompañamiento académico', 'Orientación psicosocial')),
                asistencia VARCHAR(12) NOT NULL CHECK (asistencia IN ('Asistió', 'No asistió', 'Cancelada')),
                fecha_atencion TIMESTAMP NOT NULL,
                observaciones TEXT NOT NULL,
                acuerdos_compromisos TEXT,
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS alertas_tempranas (
                id_alerta VARCHAR(36) PRIMARY KEY,
                id_caso VARCHAR(36) NOT NULL REFERENCES casos(id_caso) ON DELETE RESTRICT,
                tipo_alerta VARCHAR(40) NOT NULL CHECK (tipo_alerta IN ('Nivel de riesgo alto', 'Factor de riesgo crítico')),
                descripcion TEXT NOT NULL,
                estado VARCHAR(10) NOT NULL DEFAULT 'Pendiente' CHECK (estado IN ('Pendiente', 'Atendida')),
                generada_por VARCHAR(36) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
                fecha_generacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atendida_por VARCHAR(36) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
                fecha_atencion TIMESTAMP,
                CHECK ((estado = 'Pendiente' AND atendida_por IS NULL AND fecha_atencion IS NULL)
                    OR (estado = 'Atendida' AND atendida_por IS NOT NULL AND fecha_atencion IS NOT NULL))
            );
            CREATE UNIQUE INDEX IF NOT EXISTS uq_alerta_pendiente_por_caso ON alertas_tempranas (id_caso) WHERE estado = 'Pendiente';
        SQL);
    }

    /**
     * Crea un usuario con su persona para el rol indicado (nombre_rol de la tabla roles).
     */
    protected function crearUsuarioConRol(string $nombreRol, array $persona = []): User
    {
        $usuario = User::create([
            'codigo_unico' => fake()->unique()->numerify('USR-#####'),
            'contrasena_hash' => 'password',
            'id_rol' => DB::table('roles')->where('nombre_rol', $nombreRol)->value('id_rol'),
            'estado_activo' => true,
        ]);

        Persona::create(array_merge([
            'id_usuario' => $usuario->id_usuario,
            'numero_documento' => fake()->unique()->numerify('##########'),
            'nombre_completo' => fake()->name(),
            'correo_inst' => fake()->unique()->userName().'@uceva.edu.co',
        ], $persona));

        return $usuario;
    }

    /**
     * Enciende la exigencia del consentimiento firmado (D-28) con lo necesario para firmar:
     * claves del almacenamiento y, si se pide, un texto publicado para Salud Mental (general).
     */
    protected function activarExigenciaConsentimiento(bool $publicarTexto = true, ?string $desde = null): void
    {
        config([
            'sigabu.consentimiento.exigir' => true,
            'sigabu.consentimiento.desde' => $desde,
            'filesystems.disks.firmas.key' => 'clave',
            'filesystems.disks.firmas.secret' => 'secreto',
            'filesystems.disks.firmas.endpoint' => 'https://ejemplo.supabase.co/storage/v1/s3',
        ]);

        if ($publicarTexto) {
            $modelo = ModeloConsentimiento::firstOrCreate(
                ['codigo' => 'EXIGENCIA-SM'],
                ['nombre' => 'Consentimiento de Salud Mental', 'tipo_servicio' => 'Salud Mental', 'poblacion' => 'General', 'activo' => true],
            );
            VersionConsentimiento::firstOrCreate(
                ['id_modelo' => $modelo->id_modelo, 'numero_version' => 1],
                ['texto' => 'Texto vigente de prueba', 'estado' => VersionConsentimiento::PUBLICADA],
            );
        }
    }

    /**
     * Deja el caso con un consentimiento firmado y aceptado (RN-01), para las pruebas
     * que atienden al estudiante: registrar "Asistió", asignar tareas o remitir.
     */
    protected function crearConsentimientoVigente(Caso $caso): ConsentimientoAceptacion
    {
        $modelo = ModeloConsentimiento::firstOrCreate(
            ['codigo' => 'PRUEBA-CONSENTIMIENTO'],
            ['nombre' => 'Consentimiento de prueba', 'tipo_servicio' => 'Salud Mental', 'activo' => false],
        );
        $version = VersionConsentimiento::firstOrCreate(
            ['id_modelo' => $modelo->id_modelo, 'numero_version' => 1],
            ['texto' => 'Texto de prueba', 'estado' => VersionConsentimiento::PUBLICADA],
        );

        return ConsentimientoAceptacion::create([
            'id_caso' => $caso->id_caso,
            'id_version' => $version->id_version,
            'decision' => ConsentimientoAceptacion::ACEPTADO,
            'fecha_hora' => now()->subDay(),
            'id_profesional' => $caso->id_profesional,
            'lugar_expedicion' => 'Tuluá',
            'eps_ips' => 'EPS de prueba',
            'contacto_urgencia' => 'Acudiente de prueba',
            'vinculacion' => 'Estudiante',
            'programa_academico' => 'Psicología',
            'semestre' => 3,
            'dependencia_facultad' => 'Facultad de Ciencias de la Salud',
            'texto_presentado' => 'Texto de prueba',
            'texto_sha256' => hash('sha256', 'Texto de prueba'),
            'firma_ruta' => "casos/{$caso->id_caso}/prueba.png",
            'firma_sha256' => hash('sha256', 'firma'),
        ]);
    }
}

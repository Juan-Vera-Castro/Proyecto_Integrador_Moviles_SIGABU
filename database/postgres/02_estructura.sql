-- SIGABU en PostgreSQL propio · Paso 2: estructura completa.
-- Estado final de las 29 migraciones del proyecto (hasta 2026_11_02_000000_auditoria_expediente_y_reporte_inclusion),
-- sin lo exclusivo de Supabase (políticas RLS para anon/authenticated y buckets de storage).
-- 40 tablas, 8 funciones y 17 triggers: la evidencia clínica (historias, anotaciones,
-- seguimientos, consentimientos, firmas, remisiones, desistimientos, acciones VBG, entregas
-- y auditoría) no se puede modificar ni borrar.
--
--   psql -U postgres -d sigabu -f 02_estructura.sql
--
-- Se ejecuta como superusuario, pero los objetos quedan a nombre de sigabu_owner.
-- Si el proyecto agrega migraciones nuevas, regenerar este archivo (ver README.md).

\set ON_ERROR_STOP on
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SELECT pg_catalog.set_config('search_path', '', false);

BEGIN;
SET ROLE sigabu_owner;

CREATE FUNCTION public.completar_version_formato() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF NEW.id_version IS NOT NULL AND NEW.version_formato IS NULL THEN
            SELECT m.codigo || '-v' || v.numero_version INTO NEW.version_formato
            FROM public.versiones_consentimiento v
            JOIN public.modelos_consentimiento m ON m.id_modelo = v.id_modelo
            WHERE v.id_version = NEW.id_version;
        END IF;
        RETURN NEW;
    END $$;

CREATE FUNCTION public.fn_crear_caso_desde_solicitud() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
        v_prefijo text := (CASE WHEN NEW.tipo_servicio = 'Salud Mental' THEN 'SM' ELSE 'IN' END)
                          || '-' || to_char(now(), 'YYYY') || '-';
        v_numero  integer;
    BEGIN
        PERFORM pg_advisory_xact_lock(hashtext('codigo_expediente:' || v_prefijo));

        SELECT COALESCE(MAX(substring(codigo_expediente FROM length(v_prefijo) + 1)::integer), 0) + 1
          INTO v_numero
          FROM public.casos
         WHERE codigo_expediente LIKE v_prefijo || '%';

        INSERT INTO public.casos (id_solicitud_origen, codigo_expediente, estado_caso, fecha_apertura)
        VALUES (NEW.id_solicitud, v_prefijo || lpad(v_numero::text, 4, '0'), 'Pendiente Asignación', now());

        UPDATE public.solicitudes_atencion SET estado_solicitud = 'Procesada' WHERE id_solicitud = NEW.id_solicitud;

        RETURN NEW;
    END $$;

CREATE FUNCTION public.impedir_cambios() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RAISE EXCEPTION 'Los registros de % no se pueden modificar ni borrar.', TG_TABLE_NAME;
    END $$;

CREATE FUNCTION public.proteger_version_publicada() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF OLD.estado <> 'borrador' THEN
            IF NEW.texto          IS DISTINCT FROM OLD.texto
            OR NEW.id_modelo      IS DISTINCT FROM OLD.id_modelo
            OR NEW.numero_version IS DISTINCT FROM OLD.numero_version THEN
                RAISE EXCEPTION 'La versión % ya fue publicada; cree una versión nueva en lugar de editarla.', OLD.id_version;
            END IF;
            IF NEW.estado = 'borrador' THEN
                RAISE EXCEPTION 'Una versión publicada o retirada no puede volver a borrador.';
            END IF;
        END IF;
        RETURN NEW;
    END $$;

CREATE FUNCTION public.publicar_version_consentimiento(p_id_version integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
    DECLARE
        v_modelo integer;
    BEGIN
        SELECT id_modelo INTO v_modelo
        FROM public.versiones_consentimiento
        WHERE id_version = p_id_version AND estado = 'borrador'
        FOR UPDATE;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'La versión % no existe o no está en borrador.', p_id_version;
        END IF;

        UPDATE public.versiones_consentimiento
           SET estado = 'retirada'
         WHERE id_modelo = v_modelo AND estado = 'publicada';

        UPDATE public.versiones_consentimiento
           SET estado = 'publicada', fecha_publicacion = now()
         WHERE id_version = p_id_version;
    END $$;

CREATE FUNCTION public.validar_firma_profesional() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF NOT EXISTS (
            SELECT 1 FROM public.consentimiento_aceptaciones
            WHERE id_aceptacion = NEW.id_aceptacion AND decision = 'Aceptado' AND id_profesional = NEW.id_profesional
        ) THEN
            RAISE EXCEPTION 'Solo el profesional tratante firma un consentimiento aceptado (%).', NEW.id_aceptacion;
        END IF;
        RETURN NEW;
    END $$;

CREATE FUNCTION public.validar_remision_externa() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF NOT EXISTS (
            SELECT 1 FROM public.actuaciones
            WHERE id_actuacion = NEW.id_actuacion AND tipo_actuacion = 'Remisión' AND destino_remision = 'Externa'
        ) THEN
            RAISE EXCEPTION 'La actuación % no es una remisión externa.', NEW.id_actuacion;
        END IF;
        RETURN NEW;
    END $$;

CREATE FUNCTION public.validar_seguimiento_evolucion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF NOT EXISTS (
            SELECT 1 FROM public.actuaciones
            WHERE id_actuacion = NEW.id_actuacion AND id_caso = NEW.id_caso
              AND tipo_actuacion = 'Consulta' AND estado_asistencia = 'Asistió'
        ) THEN
            RAISE EXCEPTION 'El seguimiento debe corresponder a una cita del caso a la que el estudiante asistió.';
        END IF;
        RETURN NEW;
    END $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

CREATE TABLE public.actuaciones (
    id_actuacion uuid DEFAULT gen_random_uuid() NOT NULL,
    id_caso uuid NOT NULL,
    id_profesional uuid,
    tipo_actuacion character varying(30) NOT NULL,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    descripcion text,
    estado_asistencia character varying(15) DEFAULT 'Pendiente'::character varying NOT NULL,
    motivo_inasistencia text,
    destino_remision character varying(10),
    id_profesional_destino uuid,
    entidad_destino character varying(150),
    id_remision uuid,
    autorizacion_traslado character varying(300),
    CONSTRAINT ck_actuaciones_asistencia CHECK (((estado_asistencia)::text = ANY ((ARRAY['Pendiente'::character varying, 'Asistió'::character varying, 'No asistió'::character varying, 'Cancelada'::character varying])::text[]))),
    CONSTRAINT ck_actuaciones_tipo CHECK (((tipo_actuacion)::text = ANY ((ARRAY['Consulta'::character varying, 'Remisión'::character varying, 'Entrevista'::character varying])::text[])))
);

CREATE TABLE public.ajustes_razonables (
    id_ajuste uuid DEFAULT gen_random_uuid() NOT NULL,
    id_caso uuid NOT NULL,
    id_profesional uuid NOT NULL,
    tipo character varying(30) NOT NULL,
    barrera text NOT NULL,
    descripcion text NOT NULL,
    responsable character varying(150),
    fecha_inicio date NOT NULL,
    estado character varying(12) DEFAULT 'Vigente'::character varying NOT NULL,
    fecha_registro timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT ck_ajuste_estado CHECK (((estado)::text = ANY ((ARRAY['Vigente'::character varying, 'Cumplido'::character varying, 'Suspendido'::character varying])::text[]))),
    CONSTRAINT ck_ajuste_tipo CHECK (((tipo)::text = ANY ((ARRAY['Académico o de evaluación'::character varying, 'Metodológico'::character varying, 'Tecnológico o de acceso'::character varying, 'De infraestructura'::character varying, 'De tiempo'::character varying, 'De comunicación'::character varying, 'Otro'::character varying])::text[])))
);

CREATE TABLE public.alim_donantes (
    id_donante integer NOT NULL,
    nombre character varying(150) NOT NULL,
    tipo character varying(10) NOT NULL,
    categoria character varying(20) NOT NULL,
    anonimo boolean DEFAULT false NOT NULL,
    contacto character varying(150),
    activo boolean DEFAULT true NOT NULL,
    fecha_registro timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT ck_alim_donante_categoria CHECK (((categoria)::text = ANY ((ARRAY['Docente'::character varying, 'Administrativo'::character varying, 'Estudiante'::character varying, 'Empresa'::character varying, 'Fundación'::character varying, 'Egresado'::character varying, 'Otro'::character varying])::text[]))),
    CONSTRAINT ck_alim_donante_tipo CHECK (((tipo)::text = ANY ((ARRAY['Interno'::character varying, 'Externo'::character varying])::text[])))
);

ALTER TABLE public.alim_donantes ALTER COLUMN id_donante ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.alim_donantes_id_donante_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.alim_entregas (
    id_entrega uuid DEFAULT gen_random_uuid() NOT NULL,
    id_solicitud uuid NOT NULL,
    id_lote integer NOT NULL,
    cantidad smallint DEFAULT 1 NOT NULL,
    metodo_validacion character varying(20) NOT NULL,
    documento_verificado character varying(20) NOT NULL,
    entregado_por uuid NOT NULL,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT ck_alim_entrega_cantidad CHECK ((cantidad > 0)),
    CONSTRAINT ck_alim_validacion CHECK (((metodo_validacion)::text = ANY ((ARRAY['Carné estudiantil'::character varying, 'Cédula'::character varying])::text[])))
);

CREATE TABLE public.alim_lotes (
    id_lote integer NOT NULL,
    codigo_lote character varying(30) NOT NULL,
    tipo character varying(20) NOT NULL,
    descripcion character varying(200) NOT NULL,
    cantidad_inicial integer NOT NULL,
    cantidad_disponible integer NOT NULL,
    fecha_ingreso date NOT NULL,
    fecha_vencimiento date,
    id_donante integer,
    registrado_por uuid,
    CONSTRAINT ck_alim_lote_disponible CHECK (((cantidad_disponible >= 0) AND (cantidad_disponible <= cantidad_inicial))),
    CONSTRAINT ck_alim_lote_inicial CHECK ((cantidad_inicial > 0)),
    CONSTRAINT ck_alim_lote_tipo CHECK (((tipo)::text = ANY ((ARRAY['Mercado Tipo A'::character varying, 'Mercado Tipo B'::character varying, 'Elemento suelto'::character varying])::text[])))
);

ALTER TABLE public.alim_lotes ALTER COLUMN id_lote ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.alim_lotes_id_lote_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.alim_solicitudes (
    id_solicitud uuid DEFAULT gen_random_uuid() NOT NULL,
    id_persona uuid NOT NULL,
    situacion character varying(40) NOT NULL,
    estrato smallint,
    personas_a_cargo smallint,
    detalle text,
    acepta_datos boolean NOT NULL,
    prioridad character varying(5) NOT NULL,
    estado character varying(10) DEFAULT 'Pendiente'::character varying NOT NULL,
    motivo_rechazo character varying(300),
    revisada_por uuid,
    fecha_revision timestamp without time zone,
    fecha_solicitud timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT ck_alim_a_cargo CHECK (((personas_a_cargo >= 0) AND (personas_a_cargo <= 20))),
    CONSTRAINT ck_alim_datos CHECK (acepta_datos),
    CONSTRAINT ck_alim_estado CHECK (((estado)::text = ANY ((ARRAY['Pendiente'::character varying, 'Aprobada'::character varying, 'Entregada'::character varying, 'Rechazada'::character varying])::text[]))),
    CONSTRAINT ck_alim_estrato CHECK (((estrato >= 1) AND (estrato <= 6))),
    CONSTRAINT ck_alim_prioridad CHECK (((prioridad)::text = ANY ((ARRAY['Alta'::character varying, 'Media'::character varying, 'Baja'::character varying])::text[]))),
    CONSTRAINT ck_alim_rechazo CHECK ((((estado)::text <> 'Rechazada'::text) OR (motivo_rechazo IS NOT NULL))),
    CONSTRAINT ck_alim_situacion CHECK (((situacion)::text = ANY ((ARRAY['Emergencia económica'::character varying, 'Cabeza de familia'::character varying, 'Estrato 1 o 2 sin apoyo familiar'::character varying, 'Desempleo o reducción de ingresos'::character varying])::text[])))
);

CREATE TABLE public.asignaciones_profesional (
    id_asignacion integer NOT NULL,
    id_profesional uuid NOT NULL,
    tipo_servicio character varying(20) NOT NULL,
    id_facultad integer NOT NULL,
    jornada character varying(10) NOT NULL,
    activa boolean DEFAULT true NOT NULL,
    CONSTRAINT ck_asignaciones_jornada CHECK (((jornada)::text = ANY ((ARRAY['Diurna'::character varying, 'Nocturna'::character varying])::text[]))),
    CONSTRAINT ck_asignaciones_servicio CHECK (((tipo_servicio)::text = ANY ((ARRAY['Salud Mental'::character varying, 'Inclusión'::character varying])::text[])))
);

ALTER TABLE public.asignaciones_profesional ALTER COLUMN id_asignacion ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.asignaciones_profesional_id_asignacion_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.auditoria_expedientes (
    id_auditoria bigint NOT NULL,
    id_usuario uuid,
    id_caso uuid,
    accion character varying(40) NOT NULL,
    detalle jsonb,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    ip_origen character varying(45),
    CONSTRAINT ck_auditoria_accion CHECK (((accion)::text = ANY ((ARRAY['consentimiento_aceptado'::character varying, 'consentimiento_rechazado'::character varying, 'firma_consultada'::character varying, 'remision_externa_registrada'::character varying, 'documento_remision_pdf'::character varying, 'documento_remision_word'::character varying, 'datos_estudiante_actualizados'::character varying, 'formato_actualizado'::character varying, 'historia_clinica_registrada'::character varying, 'historia_clinica_anotada'::character varying, 'consentimiento_firmado_profesional'::character varying, 'desistimiento_registrado'::character varying, 'documento_desistimiento_pdf'::character varying, 'seguimiento_registrado'::character varying, 'evolucion_consultada'::character varying, 'expediente_consultado'::character varying, 'reporte_inclusion_pdf'::character varying])::text[])))
);

ALTER TABLE public.auditoria_expedientes ALTER COLUMN id_auditoria ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.auditoria_expedientes_id_auditoria_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);

CREATE TABLE public.casos (
    id_caso uuid DEFAULT gen_random_uuid() NOT NULL,
    id_solicitud_origen uuid NOT NULL,
    codigo_expediente character varying(20) NOT NULL,
    nivel_riesgo character varying(10),
    estado_caso character varying(25) DEFAULT 'Pendiente Asignación'::character varying NOT NULL,
    id_profesional uuid,
    fecha_apertura timestamp without time zone DEFAULT now() NOT NULL,
    fecha_cierre timestamp without time zone,
    CONSTRAINT ck_casos_estado CHECK (((estado_caso)::text = ANY ((ARRAY['Abierto'::character varying, 'Pendiente Asignación'::character varying, 'En Seguimiento'::character varying, 'Cerrado'::character varying])::text[]))),
    CONSTRAINT ck_casos_riesgo CHECK (((nivel_riesgo)::text = ANY ((ARRAY['Bajo'::character varying, 'Medio'::character varying, 'Alto'::character varying])::text[])))
);

CREATE TABLE public.consentimiento_aceptaciones (
    id_aceptacion uuid DEFAULT gen_random_uuid() NOT NULL,
    id_caso uuid NOT NULL,
    id_version integer NOT NULL,
    decision character varying(10) NOT NULL,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    medio character varying(30) NOT NULL,
    id_profesional uuid NOT NULL,
    otorgante character varying(10) DEFAULT 'Estudiante'::character varying NOT NULL,
    acudiente_nombre character varying(150),
    acudiente_tipo_documento character varying(3),
    acudiente_numero_documento character varying(20),
    acudiente_parentesco character varying(50),
    acudiente_lugar_expedicion character varying(100),
    lugar_expedicion character varying(100),
    eps_ips character varying(150),
    contacto_urgencia character varying(150),
    texto_presentado text NOT NULL,
    texto_sha256 character(64) NOT NULL,
    firma_ruta character varying(255),
    firma_sha256 character(64),
    ip_origen character varying(45),
    user_agent character varying(255),
    vinculacion character varying(15),
    programa_academico character varying(150),
    semestre smallint,
    dependencia_facultad character varying(150),
    CONSTRAINT ck_aceptacion_acudiente CHECK (((((otorgante)::text = 'Estudiante'::text) AND (acudiente_nombre IS NULL) AND (acudiente_tipo_documento IS NULL) AND (acudiente_numero_documento IS NULL) AND (acudiente_parentesco IS NULL) AND (acudiente_lugar_expedicion IS NULL)) OR (((otorgante)::text = 'Acudiente'::text) AND (acudiente_nombre IS NOT NULL) AND (acudiente_tipo_documento IS NOT NULL) AND (acudiente_numero_documento IS NOT NULL) AND (acudiente_parentesco IS NOT NULL)))),
    CONSTRAINT ck_aceptacion_datos_texto CHECK ((((decision)::text = 'Rechazado'::text) OR (((otorgante)::text = 'Estudiante'::text) AND (lugar_expedicion IS NOT NULL) AND (eps_ips IS NOT NULL) AND (contacto_urgencia IS NOT NULL)) OR (((otorgante)::text = 'Acudiente'::text) AND (acudiente_lugar_expedicion IS NOT NULL) AND (eps_ips IS NOT NULL)))),
    CONSTRAINT ck_aceptacion_firma CHECK (((((decision)::text = 'Aceptado'::text) AND (firma_ruta IS NOT NULL) AND (firma_sha256 IS NOT NULL)) OR (((decision)::text = 'Rechazado'::text) AND (firma_ruta IS NULL) AND (firma_sha256 IS NULL)))),
    CONSTRAINT ck_aceptacion_semestre CHECK (((semestre >= 1) AND (semestre <= 12))),
    CONSTRAINT ck_aceptacion_vinculacion CHECK (((vinculacion)::text = ANY ((ARRAY['Estudiante'::character varying, 'Docente TC'::character varying, 'Docente TCO'::character varying, 'Docente HC'::character varying, 'Funcionario'::character varying, 'Otro'::character varying])::text[]))),
    CONSTRAINT consentimiento_aceptaciones_acudiente_tipo_documento_check CHECK (((acudiente_tipo_documento)::text = ANY ((ARRAY['CC'::character varying, 'CE'::character varying, 'TI'::character varying, 'PA'::character varying])::text[]))),
    CONSTRAINT consentimiento_aceptaciones_decision_check CHECK (((decision)::text = ANY ((ARRAY['Aceptado'::character varying, 'Rechazado'::character varying])::text[]))),
    CONSTRAINT consentimiento_aceptaciones_medio_check CHECK (((medio)::text = ANY ((ARRAY['firma_tactil_expediente'::character varying, 'firma_tactil_enlace'::character varying])::text[]))),
    CONSTRAINT consentimiento_aceptaciones_otorgante_check CHECK (((otorgante)::text = ANY ((ARRAY['Estudiante'::character varying, 'Acudiente'::character varying])::text[])))
);

CREATE TABLE public.consentimiento_firmas_profesional (
    id_aceptacion uuid NOT NULL,
    id_profesional uuid NOT NULL,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    firma_ruta character varying(255) NOT NULL,
    firma_sha256 character(64) NOT NULL,
    ip_origen character varying(45),
    user_agent character varying(255)
);

CREATE TABLE public.consentimientos (
    id_consentimiento integer NOT NULL,
    id_caso uuid NOT NULL,
    aceptado boolean DEFAULT false NOT NULL,
    fecha_hora_aceptacion timestamp without time zone DEFAULT now() NOT NULL,
    version_formato character varying(50) NOT NULL,
    id_version integer
);

ALTER TABLE public.consentimientos ALTER COLUMN id_consentimiento ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.consentimientos_id_consentimiento_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.desistimientos (
    id_desistimiento uuid DEFAULT gen_random_uuid() NOT NULL,
    id_caso uuid NOT NULL,
    id_profesional uuid NOT NULL,
    motivo character varying(40) NOT NULL,
    observaciones text,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    estudiante_nombre character varying(150) NOT NULL,
    estudiante_tipo_documento character varying(2),
    estudiante_numero_documento character varying(20) NOT NULL,
    profesional_nombre character varying(150) NOT NULL,
    formato_codigo character varying(30) NOT NULL,
    formato_version character varying(10) NOT NULL,
    formato_fecha date NOT NULL,
    CONSTRAINT ck_desistimiento_motivo CHECK (((motivo)::text = ANY ((ARRAY['No desea continuar la atención'::character varying, 'No desea contar lo sucedido'::character varying, 'Prefiere atención externa'::character varying, 'Otro'::character varying])::text[]))),
    CONSTRAINT ck_desistimiento_otro CHECK ((((motivo)::text <> 'Otro'::text) OR (observaciones IS NOT NULL)))
);

CREATE TABLE public.facultades (
    id_facultad integer NOT NULL,
    nombre character varying(150) NOT NULL,
    activa boolean DEFAULT true NOT NULL
);

ALTER TABLE public.facultades ALTER COLUMN id_facultad ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.facultades_id_facultad_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;

CREATE TABLE public.formatos_institucionales (
    id_formato integer NOT NULL,
    clave character varying(40) NOT NULL,
    proceso character varying(100) NOT NULL,
    nombre character varying(100) NOT NULL,
    codigo character varying(30) NOT NULL,
    version character varying(10) NOT NULL,
    fecha_formato date NOT NULL,
    actualizado_por uuid,
    fecha_actualizacion timestamp without time zone DEFAULT now() NOT NULL
);

ALTER TABLE public.formatos_institucionales ALTER COLUMN id_formato ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.formatos_institucionales_id_formato_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.historia_clinica_anotaciones (
    id_anotacion uuid DEFAULT gen_random_uuid() NOT NULL,
    id_historia uuid NOT NULL,
    id_profesional uuid NOT NULL,
    apartado character varying(40),
    texto text NOT NULL,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT historia_clinica_anotaciones_apartado_check CHECK (((apartado)::text = ANY ((ARRAY['motivo_consulta'::character varying, 'historia_problema'::character varying, 'antecedentes_personales'::character varying, 'antecedentes_familiares'::character varying, 'antecedentes_medicos'::character varying, 'examen_mental'::character varying, 'impresion_diagnostica'::character varying, 'plan_intervencion'::character varying, 'observaciones'::character varying])::text[])))
);

CREATE TABLE public.historias_clinicas (
    id_historia uuid DEFAULT gen_random_uuid() NOT NULL,
    id_caso uuid NOT NULL,
    id_profesional uuid NOT NULL,
    motivo_consulta text NOT NULL,
    historia_problema text,
    antecedentes_personales text,
    antecedentes_familiares text,
    antecedentes_medicos text,
    examen_mental text,
    impresion_diagnostica text NOT NULL,
    plan_intervencion text NOT NULL,
    observaciones text,
    fecha_registro timestamp without time zone DEFAULT now() NOT NULL
);

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;

CREATE TABLE public.modelos_consentimiento (
    id_modelo integer NOT NULL,
    codigo character varying(50) NOT NULL,
    nombre character varying(150) NOT NULL,
    tipo_servicio character varying(100),
    descripcion text,
    activo boolean DEFAULT true NOT NULL,
    poblacion character varying(10) DEFAULT 'General'::character varying NOT NULL,
    CONSTRAINT ck_modelos_poblacion CHECK (((poblacion)::text = ANY ((ARRAY['General'::character varying, 'Menores'::character varying])::text[])))
);

ALTER TABLE public.modelos_consentimiento ALTER COLUMN id_modelo ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.modelos_consentimiento_id_modelo_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);

CREATE TABLE public.personas (
    id_persona uuid DEFAULT gen_random_uuid() NOT NULL,
    id_usuario uuid NOT NULL,
    numero_documento character varying(20) NOT NULL,
    nombre_completo character varying(150) NOT NULL,
    correo_inst character varying(120) NOT NULL,
    tipo_documento character varying(2),
    sexo character(1),
    fecha_nacimiento date,
    CONSTRAINT ck_personas_fecha_nacimiento CHECK ((fecha_nacimiento >= '1900-01-01'::date)),
    CONSTRAINT ck_personas_sexo CHECK ((sexo = ANY (ARRAY['F'::bpchar, 'M'::bpchar]))),
    CONSTRAINT ck_personas_tipo_documento CHECK (((tipo_documento)::text = ANY ((ARRAY['TI'::character varying, 'CC'::character varying, 'CE'::character varying])::text[])))
);

CREATE TABLE public.propuestas_cita (
    id_propuesta uuid DEFAULT gen_random_uuid() NOT NULL,
    id_caso uuid NOT NULL,
    id_profesional uuid NOT NULL,
    fecha_hora timestamp without time zone NOT NULL,
    estado character varying(12) DEFAULT 'Propuesta'::character varying NOT NULL,
    id_cita uuid,
    fecha_propuesta timestamp without time zone DEFAULT now() NOT NULL,
    fecha_respuesta timestamp without time zone,
    CONSTRAINT ck_propuesta_elegida CHECK ((((estado)::text <> 'Elegida'::text) OR (id_cita IS NOT NULL))),
    CONSTRAINT ck_propuesta_estado CHECK (((estado)::text = ANY ((ARRAY['Propuesta'::character varying, 'Elegida'::character varying, 'Descartada'::character varying, 'Rechazada'::character varying])::text[])))
);

CREATE TABLE public.remisiones_externas (
    id_actuacion uuid NOT NULL,
    categoria character varying(12) NOT NULL,
    estudiante_nombre character varying(150) NOT NULL,
    estudiante_tipo_documento character varying(2) NOT NULL,
    estudiante_numero_documento character varying(20) NOT NULL,
    estudiante_sexo character(1) NOT NULL,
    estudiante_fecha_nacimiento date NOT NULL,
    estudiante_edad smallint NOT NULL,
    id_cita uuid,
    fecha_hora_atencion timestamp without time zone NOT NULL,
    profesional_nombre character varying(150) NOT NULL,
    acompanante_nombre character varying(150),
    acompanante_parentesco character varying(50),
    acompanante_tipo_documento character varying(3),
    acompanante_numero_documento character varying(20),
    acompanante_telefono character varying(20),
    formato_codigo character varying(30) NOT NULL,
    formato_version character varying(10) NOT NULL,
    formato_fecha date NOT NULL,
    CONSTRAINT ck_remision_acompanante CHECK (((((categoria)::text = 'URGENTE'::text) AND (acompanante_nombre IS NOT NULL) AND (acompanante_parentesco IS NOT NULL) AND (acompanante_tipo_documento IS NOT NULL) AND (acompanante_numero_documento IS NOT NULL) AND (acompanante_telefono IS NOT NULL)) OR (((categoria)::text = 'PRIORITARIO'::text) AND (acompanante_nombre IS NULL) AND (acompanante_parentesco IS NULL) AND (acompanante_tipo_documento IS NULL) AND (acompanante_numero_documento IS NULL) AND (acompanante_telefono IS NULL)))),
    CONSTRAINT remisiones_externas_acompanante_tipo_documento_check CHECK (((acompanante_tipo_documento)::text = ANY ((ARRAY['CC'::character varying, 'CE'::character varying, 'TI'::character varying, 'PA'::character varying])::text[]))),
    CONSTRAINT remisiones_externas_categoria_check CHECK (((categoria)::text = ANY ((ARRAY['URGENTE'::character varying, 'PRIORITARIO'::character varying])::text[]))),
    CONSTRAINT remisiones_externas_estudiante_edad_check CHECK (((estudiante_edad >= 0) AND (estudiante_edad <= 120))),
    CONSTRAINT remisiones_externas_estudiante_sexo_check CHECK ((estudiante_sexo = ANY (ARRAY['F'::bpchar, 'M'::bpchar]))),
    CONSTRAINT remisiones_externas_estudiante_tipo_documento_check CHECK (((estudiante_tipo_documento)::text = ANY ((ARRAY['TI'::character varying, 'CC'::character varying, 'CE'::character varying])::text[])))
);

CREATE TABLE public.repositorio_documentos (
    id_documento integer NOT NULL,
    titulo character varying(150) NOT NULL,
    categoria character varying(30) NOT NULL,
    descripcion character varying(500),
    url character varying(500),
    audiencia character varying(15) DEFAULT 'Profesionales'::character varying NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    publicado_por uuid,
    fecha_publicacion timestamp without time zone DEFAULT now() NOT NULL,
    archivo_ruta character varying(255),
    archivo_nombre character varying(200),
    archivo_mime character varying(100),
    archivo_bytes integer,
    archivo_sha256 character(64),
    CONSTRAINT ck_repositorio_audiencia CHECK (((audiencia)::text = ANY ((ARRAY['Profesionales'::character varying, 'Todos'::character varying])::text[]))),
    CONSTRAINT ck_repositorio_categoria CHECK (((categoria)::text = ANY ((ARRAY['Ley o norma'::character varying, 'Protocolo'::character varying, 'Guía'::character varying, 'Formato'::character varying, 'Material psicoeducativo'::character varying])::text[]))),
    CONSTRAINT ck_repositorio_contenido CHECK (((url IS NOT NULL) OR (archivo_ruta IS NOT NULL)))
);

ALTER TABLE public.repositorio_documentos ALTER COLUMN id_documento ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.repositorio_documentos_id_documento_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.roles (
    id_rol integer NOT NULL,
    nombre_rol character varying(50) NOT NULL,
    sistema_destino character varying(30) NOT NULL
);

ALTER TABLE public.roles ALTER COLUMN id_rol ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.roles_id_rol_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.seguimientos_ajuste (
    id_seguimiento uuid DEFAULT gen_random_uuid() NOT NULL,
    id_ajuste uuid NOT NULL,
    id_profesional uuid NOT NULL,
    fecha date NOT NULL,
    cumplimiento character varying(25) NOT NULL,
    observaciones text NOT NULL,
    estado_resultante character varying(12),
    fecha_registro timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT ck_seguimiento_cumplimiento CHECK (((cumplimiento)::text = ANY ((ARRAY['Se cumple'::character varying, 'Se cumple parcialmente'::character varying, 'No se cumple'::character varying])::text[]))),
    CONSTRAINT ck_seguimiento_estado CHECK (((estado_resultante)::text = ANY ((ARRAY['Vigente'::character varying, 'Cumplido'::character varying, 'Suspendido'::character varying])::text[])))
);

CREATE TABLE public.seguimientos_evolucion (
    id_seguimiento uuid DEFAULT gen_random_uuid() NOT NULL,
    id_caso uuid NOT NULL,
    id_actuacion uuid NOT NULL,
    id_profesional uuid NOT NULL,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    nivel_riesgo character varying(10) NOT NULL,
    valoracion_avance character varying(15) NOT NULL,
    instrumento character varying(10),
    puntaje smallint,
    nota text NOT NULL,
    CONSTRAINT ck_seguimientos_instrumento CHECK (((instrumento)::text = ANY ((ARRAY['GAD-7'::character varying, 'PHQ-9'::character varying])::text[]))),
    CONSTRAINT ck_seguimientos_nota CHECK (((length(nota) >= 1) AND (length(nota) <= 2000))),
    CONSTRAINT ck_seguimientos_puntaje CHECK ((((instrumento IS NULL) AND (puntaje IS NULL)) OR ((instrumento IS NOT NULL) AND (puntaje IS NOT NULL) AND ((((instrumento)::text = 'GAD-7'::text) AND ((puntaje >= 0) AND (puntaje <= 21))) OR (((instrumento)::text = 'PHQ-9'::text) AND ((puntaje >= 0) AND (puntaje <= 27))))))),
    CONSTRAINT ck_seguimientos_riesgo CHECK (((nivel_riesgo)::text = ANY ((ARRAY['Bajo'::character varying, 'Medio'::character varying, 'Alto'::character varying])::text[]))),
    CONSTRAINT ck_seguimientos_valoracion CHECK (((valoracion_avance)::text = ANY ((ARRAY['Mejora'::character varying, 'Estable'::character varying, 'Retroceso'::character varying])::text[])))
);

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id uuid,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);

CREATE TABLE public.solicitudes_atencion (
    id_solicitud uuid DEFAULT gen_random_uuid() NOT NULL,
    id_persona uuid NOT NULL,
    tipo_servicio character varying(20) NOT NULL,
    descripcion text,
    fecha_solicitud timestamp without time zone DEFAULT now() NOT NULL,
    estado_solicitud character varying(20) DEFAULT 'Recibida'::character varying NOT NULL,
    fecha_preferida date,
    hora_preferida character varying(5),
    modalidad character varying(12),
    id_profesional_preferido uuid,
    fecha_respuesta timestamp without time zone,
    motivo_rechazo text,
    id_facultad integer,
    jornada character varying(10),
    franja_horaria character varying(10),
    es_emergencia boolean DEFAULT false NOT NULL,
    CONSTRAINT ck_solicitudes_estado CHECK (((estado_solicitud)::text = ANY ((ARRAY['Recibida'::character varying, 'Procesada'::character varying])::text[]))),
    CONSTRAINT ck_solicitudes_franja CHECK (((franja_horaria)::text = ANY ((ARRAY['Mañana'::character varying, 'Tarde'::character varying, 'Noche'::character varying, 'Todas'::character varying])::text[]))),
    CONSTRAINT ck_solicitudes_jornada CHECK (((jornada)::text = ANY ((ARRAY['Diurna'::character varying, 'Nocturna'::character varying])::text[]))),
    CONSTRAINT ck_solicitudes_modalidad CHECK (((modalidad)::text = ANY ((ARRAY['Presencial'::character varying, 'Virtual'::character varying])::text[]))),
    CONSTRAINT ck_solicitudes_tipo_servicio CHECK (((tipo_servicio)::text = ANY ((ARRAY['Salud Mental'::character varying, 'Inclusión'::character varying])::text[])))
);

CREATE TABLE public.tareas_terapeuticas (
    id_tarea uuid DEFAULT gen_random_uuid() NOT NULL,
    id_caso uuid NOT NULL,
    id_profesional uuid,
    titulo character varying(150) NOT NULL,
    descripcion text NOT NULL,
    fecha_limite date,
    fecha_asignacion timestamp without time zone DEFAULT now() NOT NULL,
    estado character varying(30) DEFAULT 'Pendiente de evaluación'::character varying NOT NULL,
    id_remision uuid,
    CONSTRAINT ck_tareas_estado CHECK (((estado)::text = ANY ((ARRAY['Pendiente de evaluación'::character varying, 'Cumplida'::character varying, 'No cumplida'::character varying, 'Cancelada'::character varying])::text[])))
);

CREATE TABLE public.usuarios (
    id_usuario uuid DEFAULT gen_random_uuid() NOT NULL,
    codigo_unico character varying(30) NOT NULL,
    contrasena_hash character varying(255) NOT NULL,
    id_rol integer NOT NULL,
    estado_activo boolean DEFAULT true NOT NULL,
    ve_indicadores boolean DEFAULT false NOT NULL
);

CREATE TABLE public.vbg_acciones (
    id_accion uuid DEFAULT gen_random_uuid() NOT NULL,
    id_reporte uuid NOT NULL,
    id_usuario uuid NOT NULL,
    tipo_accion character varying(30) NOT NULL,
    descripcion text NOT NULL,
    fecha_hora timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT ck_vbg_accion CHECK (((tipo_accion)::text = ANY ((ARRAY['Remisión jurídica'::character varying, 'Denuncia ante Fiscalía'::character varying, 'Seguimiento terapéutico'::character varying, 'Remisión a Salud Mental'::character varying, 'Medida de protección'::character varying, 'Nota de seguimiento'::character varying, 'Caso tomado'::character varying, 'Caso cerrado'::character varying, 'Caso reabierto'::character varying])::text[])))
);

CREATE TABLE public.vbg_reportes (
    id_reporte uuid DEFAULT gen_random_uuid() NOT NULL,
    codigo character varying(20) NOT NULL,
    tipo_reportante character varying(10) NOT NULL,
    id_persona_reporta uuid,
    afectado_nombre character varying(150),
    afectado_vinculo character varying(100),
    relato text NOT NULL,
    tipo_violencia character varying(20),
    lugar character varying(150),
    fecha_hechos date,
    desea_contacto boolean DEFAULT false NOT NULL,
    medio_contacto character varying(150),
    acepta_datos boolean NOT NULL,
    fecha_reporte timestamp without time zone DEFAULT now() NOT NULL,
    estado character varying(15) DEFAULT 'Recibido'::character varying NOT NULL,
    nivel_riesgo character varying(10),
    id_profesional uuid,
    fecha_cierre timestamp without time zone,
    CONSTRAINT ck_vbg_anonimo CHECK ((((tipo_reportante)::text <> 'Anónimo'::text) OR ((id_persona_reporta IS NULL) AND (desea_contacto = false) AND (medio_contacto IS NULL)))),
    CONSTRAINT ck_vbg_datos CHECK (acepta_datos),
    CONSTRAINT ck_vbg_estado CHECK (((estado)::text = ANY ((ARRAY['Recibido'::character varying, 'En atención'::character varying, 'Cerrado'::character varying])::text[]))),
    CONSTRAINT ck_vbg_identificado CHECK ((((tipo_reportante)::text = 'Anónimo'::text) OR (id_persona_reporta IS NOT NULL))),
    CONSTRAINT ck_vbg_reportante CHECK (((tipo_reportante)::text = ANY ((ARRAY['Afectada'::character varying, 'Tercero'::character varying, 'Anónimo'::character varying])::text[]))),
    CONSTRAINT ck_vbg_riesgo CHECK (((nivel_riesgo)::text = ANY ((ARRAY['Bajo'::character varying, 'Medio'::character varying, 'Alto'::character varying])::text[]))),
    CONSTRAINT ck_vbg_tercero CHECK ((((tipo_reportante)::text <> 'Tercero'::text) OR (afectado_nombre IS NOT NULL))),
    CONSTRAINT ck_vbg_tipo CHECK (((tipo_violencia)::text = ANY ((ARRAY['Física'::character varying, 'Psicológica'::character varying, 'Sexual'::character varying, 'Económica'::character varying, 'Digital'::character varying, 'Otra'::character varying])::text[])))
);

CREATE TABLE public.versiones_consentimiento (
    id_version integer NOT NULL,
    id_modelo integer NOT NULL,
    numero_version integer NOT NULL,
    texto text NOT NULL,
    estado character varying(20) DEFAULT 'borrador'::character varying NOT NULL,
    fecha_publicacion timestamp without time zone,
    creado_por uuid,
    fecha_creacion timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT versiones_consentimiento_estado_check CHECK (((estado)::text = ANY ((ARRAY['borrador'::character varying, 'publicada'::character varying, 'retirada'::character varying])::text[]))),
    CONSTRAINT versiones_consentimiento_numero_version_check CHECK ((numero_version > 0))
);

ALTER TABLE public.versiones_consentimiento ALTER COLUMN id_version ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.versiones_consentimiento_id_version_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

CREATE TABLE public.vinculacion (
    id_vinculacion integer NOT NULL,
    id_persona uuid NOT NULL,
    tipo_vinculacion character varying(30) NOT NULL,
    facultad character varying(100),
    programa_academico character varying(150),
    id_facultad integer,
    jornada character varying(10),
    semestre smallint,
    CONSTRAINT ck_vinculacion_jornada CHECK (((jornada)::text = ANY ((ARRAY['Diurna'::character varying, 'Nocturna'::character varying])::text[]))),
    CONSTRAINT ck_vinculacion_semestre CHECK (((semestre >= 1) AND (semestre <= 12))),
    CONSTRAINT ck_vinculacion_tipo CHECK (((tipo_vinculacion)::text = ANY ((ARRAY['Estudiante'::character varying, 'Docente'::character varying, 'Administrativo'::character varying])::text[])))
);

ALTER TABLE public.vinculacion ALTER COLUMN id_vinculacion ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.vinculacion_id_vinculacion_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);

ALTER TABLE ONLY public.actuaciones
    ADD CONSTRAINT actuaciones_pkey PRIMARY KEY (id_actuacion);

ALTER TABLE ONLY public.ajustes_razonables
    ADD CONSTRAINT ajustes_razonables_pkey PRIMARY KEY (id_ajuste);

ALTER TABLE ONLY public.alim_donantes
    ADD CONSTRAINT alim_donantes_pkey PRIMARY KEY (id_donante);

ALTER TABLE ONLY public.alim_entregas
    ADD CONSTRAINT alim_entregas_id_solicitud_key UNIQUE (id_solicitud);

ALTER TABLE ONLY public.alim_entregas
    ADD CONSTRAINT alim_entregas_pkey PRIMARY KEY (id_entrega);

ALTER TABLE ONLY public.alim_lotes
    ADD CONSTRAINT alim_lotes_codigo_lote_key UNIQUE (codigo_lote);

ALTER TABLE ONLY public.alim_lotes
    ADD CONSTRAINT alim_lotes_pkey PRIMARY KEY (id_lote);

ALTER TABLE ONLY public.alim_solicitudes
    ADD CONSTRAINT alim_solicitudes_pkey PRIMARY KEY (id_solicitud);

ALTER TABLE ONLY public.asignaciones_profesional
    ADD CONSTRAINT asignaciones_profesional_pkey PRIMARY KEY (id_asignacion);

ALTER TABLE ONLY public.auditoria_expedientes
    ADD CONSTRAINT auditoria_expedientes_pkey PRIMARY KEY (id_auditoria);

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_codigo_expediente_key UNIQUE (codigo_expediente);

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_id_solicitud_origen_key UNIQUE (id_solicitud_origen);

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_pkey PRIMARY KEY (id_caso);

ALTER TABLE public.consentimiento_aceptaciones
    ADD CONSTRAINT ck_aceptacion_encabezado CHECK ((((decision)::text = 'Rechazado'::text) OR ((vinculacion IS NOT NULL) AND (dependencia_facultad IS NOT NULL) AND (((vinculacion)::text <> 'Estudiante'::text) OR ((programa_academico IS NOT NULL) AND (semestre IS NOT NULL)))))) NOT VALID;

ALTER TABLE public.actuaciones
    ADD CONSTRAINT ck_actuaciones_remision CHECK (((((tipo_actuacion)::text = 'Remisión'::text) AND ((((destino_remision)::text = 'Interna'::text) AND (id_profesional_destino IS NOT NULL) AND (entidad_destino IS NULL)) OR (((destino_remision)::text = 'Externa'::text) AND (entidad_destino IS NOT NULL) AND (id_profesional_destino IS NULL)))) OR (((tipo_actuacion)::text <> 'Remisión'::text) AND (destino_remision IS NULL) AND (id_profesional_destino IS NULL) AND (entidad_destino IS NULL)))) NOT VALID;

ALTER TABLE ONLY public.consentimiento_aceptaciones
    ADD CONSTRAINT consentimiento_aceptaciones_pkey PRIMARY KEY (id_aceptacion);

ALTER TABLE ONLY public.consentimiento_firmas_profesional
    ADD CONSTRAINT consentimiento_firmas_profesional_pkey PRIMARY KEY (id_aceptacion);

ALTER TABLE ONLY public.consentimientos
    ADD CONSTRAINT consentimientos_pkey PRIMARY KEY (id_consentimiento);

ALTER TABLE ONLY public.desistimientos
    ADD CONSTRAINT desistimientos_pkey PRIMARY KEY (id_desistimiento);

ALTER TABLE ONLY public.facultades
    ADD CONSTRAINT facultades_nombre_key UNIQUE (nombre);

ALTER TABLE ONLY public.facultades
    ADD CONSTRAINT facultades_pkey PRIMARY KEY (id_facultad);

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);

ALTER TABLE ONLY public.formatos_institucionales
    ADD CONSTRAINT formatos_institucionales_clave_key UNIQUE (clave);

ALTER TABLE ONLY public.formatos_institucionales
    ADD CONSTRAINT formatos_institucionales_pkey PRIMARY KEY (id_formato);

ALTER TABLE ONLY public.historia_clinica_anotaciones
    ADD CONSTRAINT historia_clinica_anotaciones_pkey PRIMARY KEY (id_anotacion);

ALTER TABLE ONLY public.historias_clinicas
    ADD CONSTRAINT historias_clinicas_id_caso_key UNIQUE (id_caso);

ALTER TABLE ONLY public.historias_clinicas
    ADD CONSTRAINT historias_clinicas_pkey PRIMARY KEY (id_historia);

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.modelos_consentimiento
    ADD CONSTRAINT modelos_consentimiento_codigo_key UNIQUE (codigo);

ALTER TABLE ONLY public.modelos_consentimiento
    ADD CONSTRAINT modelos_consentimiento_pkey PRIMARY KEY (id_modelo);

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_correo_inst_key UNIQUE (correo_inst);

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_id_usuario_key UNIQUE (id_usuario);

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_numero_documento_key UNIQUE (numero_documento);

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_pkey PRIMARY KEY (id_persona);

ALTER TABLE ONLY public.propuestas_cita
    ADD CONSTRAINT propuestas_cita_pkey PRIMARY KEY (id_propuesta);

ALTER TABLE ONLY public.remisiones_externas
    ADD CONSTRAINT remisiones_externas_pkey PRIMARY KEY (id_actuacion);

ALTER TABLE ONLY public.repositorio_documentos
    ADD CONSTRAINT repositorio_documentos_pkey PRIMARY KEY (id_documento);

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_nombre_rol_key UNIQUE (nombre_rol);

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id_rol);

ALTER TABLE ONLY public.seguimientos_ajuste
    ADD CONSTRAINT seguimientos_ajuste_pkey PRIMARY KEY (id_seguimiento);

ALTER TABLE ONLY public.seguimientos_evolucion
    ADD CONSTRAINT seguimientos_evolucion_id_actuacion_key UNIQUE (id_actuacion);

ALTER TABLE ONLY public.seguimientos_evolucion
    ADD CONSTRAINT seguimientos_evolucion_pkey PRIMARY KEY (id_seguimiento);

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.solicitudes_atencion
    ADD CONSTRAINT solicitudes_atencion_pkey PRIMARY KEY (id_solicitud);

ALTER TABLE ONLY public.tareas_terapeuticas
    ADD CONSTRAINT tareas_terapeuticas_pkey PRIMARY KEY (id_tarea);

ALTER TABLE ONLY public.asignaciones_profesional
    ADD CONSTRAINT uq_asignacion UNIQUE (id_profesional, tipo_servicio, id_facultad, jornada);

ALTER TABLE ONLY public.versiones_consentimiento
    ADD CONSTRAINT uq_version_por_modelo UNIQUE (id_modelo, numero_version);

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_codigo_unico_key UNIQUE (codigo_unico);

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id_usuario);

ALTER TABLE ONLY public.vbg_acciones
    ADD CONSTRAINT vbg_acciones_pkey PRIMARY KEY (id_accion);

ALTER TABLE ONLY public.vbg_reportes
    ADD CONSTRAINT vbg_reportes_codigo_key UNIQUE (codigo);

ALTER TABLE ONLY public.vbg_reportes
    ADD CONSTRAINT vbg_reportes_pkey PRIMARY KEY (id_reporte);

ALTER TABLE ONLY public.versiones_consentimiento
    ADD CONSTRAINT versiones_consentimiento_pkey PRIMARY KEY (id_version);

ALTER TABLE ONLY public.vinculacion
    ADD CONSTRAINT vinculacion_pkey PRIMARY KEY (id_vinculacion);

CREATE INDEX idx_aceptaciones_caso_fecha ON public.consentimiento_aceptaciones USING btree (id_caso, fecha_hora DESC);

CREATE INDEX idx_actuaciones_caso_fecha ON public.actuaciones USING btree (id_caso, fecha_hora);

CREATE INDEX idx_actuaciones_profesional_fecha ON public.actuaciones USING btree (id_profesional, fecha_hora);

CREATE INDEX idx_ajustes_caso ON public.ajustes_razonables USING btree (id_caso, fecha_registro);

CREATE INDEX idx_alim_solicitudes_estado ON public.alim_solicitudes USING btree (estado, prioridad, fecha_solicitud);

CREATE INDEX idx_anotaciones_historia_fecha ON public.historia_clinica_anotaciones USING btree (id_historia, fecha_hora);

CREATE INDEX idx_asignaciones_busqueda ON public.asignaciones_profesional USING btree (tipo_servicio, id_facultad, jornada) WHERE activa;

CREATE INDEX idx_auditoria_expedientes_caso ON public.auditoria_expedientes USING btree (id_caso, fecha_hora DESC);

CREATE INDEX idx_casos_profesional ON public.casos USING btree (id_profesional);

CREATE INDEX idx_consentimientos_id_version ON public.consentimientos USING btree (id_version);

CREATE INDEX idx_desistimientos_caso ON public.desistimientos USING btree (id_caso, fecha_hora);

CREATE INDEX idx_propuestas_caso ON public.propuestas_cita USING btree (id_caso, estado);

CREATE INDEX idx_seguimientos_ajuste ON public.seguimientos_ajuste USING btree (id_ajuste, fecha);

CREATE INDEX idx_seguimientos_caso_fecha ON public.seguimientos_evolucion USING btree (id_caso, fecha_hora DESC);

CREATE INDEX idx_tareas_caso_fecha ON public.tareas_terapeuticas USING btree (id_caso, fecha_asignacion);

CREATE INDEX idx_vbg_acciones_reporte ON public.vbg_acciones USING btree (id_reporte, fecha_hora);

CREATE INDEX idx_vbg_reportes_estado ON public.vbg_reportes USING btree (estado, fecha_reporte);

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);

CREATE UNIQUE INDEX uq_una_publicada_por_modelo ON public.versiones_consentimiento USING btree (id_modelo) WHERE ((estado)::text = 'publicada'::text);

CREATE TRIGGER trg_aceptaciones_inmutables BEFORE DELETE OR UPDATE ON public.consentimiento_aceptaciones FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_alim_entregas_inmutables BEFORE DELETE OR UPDATE ON public.alim_entregas FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_auditoria_expedientes_inmutable BEFORE DELETE OR UPDATE ON public.auditoria_expedientes FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_completar_version_formato BEFORE INSERT ON public.consentimientos FOR EACH ROW EXECUTE FUNCTION public.completar_version_formato();

CREATE TRIGGER trg_crear_caso_desde_solicitud AFTER INSERT ON public.solicitudes_atencion FOR EACH ROW EXECUTE FUNCTION public.fn_crear_caso_desde_solicitud();

CREATE TRIGGER trg_desistimientos_inmutables BEFORE DELETE OR UPDATE ON public.desistimientos FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_firmas_profesional_inmutables BEFORE DELETE OR UPDATE ON public.consentimiento_firmas_profesional FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_historia_anotaciones_inmutables BEFORE DELETE OR UPDATE ON public.historia_clinica_anotaciones FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_historias_clinicas_inmutables BEFORE DELETE OR UPDATE ON public.historias_clinicas FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_proteger_version_publicada BEFORE UPDATE ON public.versiones_consentimiento FOR EACH ROW EXECUTE FUNCTION public.proteger_version_publicada();

CREATE TRIGGER trg_remisiones_externas_inmutables BEFORE DELETE OR UPDATE ON public.remisiones_externas FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_seguimientos_ajuste_inmutables BEFORE DELETE OR UPDATE ON public.seguimientos_ajuste FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_seguimientos_inmutables BEFORE DELETE OR UPDATE ON public.seguimientos_evolucion FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

CREATE TRIGGER trg_validar_firma_profesional BEFORE INSERT ON public.consentimiento_firmas_profesional FOR EACH ROW EXECUTE FUNCTION public.validar_firma_profesional();

CREATE TRIGGER trg_validar_remision_externa BEFORE INSERT ON public.remisiones_externas FOR EACH ROW EXECUTE FUNCTION public.validar_remision_externa();

CREATE TRIGGER trg_validar_seguimiento_evolucion BEFORE INSERT ON public.seguimientos_evolucion FOR EACH ROW EXECUTE FUNCTION public.validar_seguimiento_evolucion();

CREATE TRIGGER trg_vbg_acciones_inmutables BEFORE DELETE OR UPDATE ON public.vbg_acciones FOR EACH ROW EXECUTE FUNCTION public.impedir_cambios();

ALTER TABLE ONLY public.actuaciones
    ADD CONSTRAINT actuaciones_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE RESTRICT;

ALTER TABLE ONLY public.actuaciones
    ADD CONSTRAINT actuaciones_id_profesional_destino_fkey FOREIGN KEY (id_profesional_destino) REFERENCES public.usuarios(id_usuario);

ALTER TABLE ONLY public.actuaciones
    ADD CONSTRAINT actuaciones_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.actuaciones
    ADD CONSTRAINT actuaciones_id_remision_fkey FOREIGN KEY (id_remision) REFERENCES public.actuaciones(id_actuacion);

ALTER TABLE ONLY public.ajustes_razonables
    ADD CONSTRAINT ajustes_razonables_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE RESTRICT;

ALTER TABLE ONLY public.ajustes_razonables
    ADD CONSTRAINT ajustes_razonables_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.alim_entregas
    ADD CONSTRAINT alim_entregas_entregado_por_fkey FOREIGN KEY (entregado_por) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.alim_entregas
    ADD CONSTRAINT alim_entregas_id_lote_fkey FOREIGN KEY (id_lote) REFERENCES public.alim_lotes(id_lote) ON DELETE RESTRICT;

ALTER TABLE ONLY public.alim_entregas
    ADD CONSTRAINT alim_entregas_id_solicitud_fkey FOREIGN KEY (id_solicitud) REFERENCES public.alim_solicitudes(id_solicitud) ON DELETE RESTRICT;

ALTER TABLE ONLY public.alim_lotes
    ADD CONSTRAINT alim_lotes_id_donante_fkey FOREIGN KEY (id_donante) REFERENCES public.alim_donantes(id_donante) ON DELETE RESTRICT;

ALTER TABLE ONLY public.alim_lotes
    ADD CONSTRAINT alim_lotes_registrado_por_fkey FOREIGN KEY (registrado_por) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.alim_solicitudes
    ADD CONSTRAINT alim_solicitudes_id_persona_fkey FOREIGN KEY (id_persona) REFERENCES public.personas(id_persona) ON DELETE RESTRICT;

ALTER TABLE ONLY public.alim_solicitudes
    ADD CONSTRAINT alim_solicitudes_revisada_por_fkey FOREIGN KEY (revisada_por) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.asignaciones_profesional
    ADD CONSTRAINT asignaciones_profesional_id_facultad_fkey FOREIGN KEY (id_facultad) REFERENCES public.facultades(id_facultad) ON DELETE RESTRICT;

ALTER TABLE ONLY public.asignaciones_profesional
    ADD CONSTRAINT asignaciones_profesional_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.auditoria_expedientes
    ADD CONSTRAINT auditoria_expedientes_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE SET NULL;

ALTER TABLE ONLY public.auditoria_expedientes
    ADD CONSTRAINT auditoria_expedientes_id_usuario_fkey FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario) ON DELETE SET NULL;

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.casos
    ADD CONSTRAINT casos_id_solicitud_origen_fkey FOREIGN KEY (id_solicitud_origen) REFERENCES public.solicitudes_atencion(id_solicitud) ON DELETE RESTRICT;

ALTER TABLE ONLY public.consentimiento_aceptaciones
    ADD CONSTRAINT consentimiento_aceptaciones_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE RESTRICT;

ALTER TABLE ONLY public.consentimiento_aceptaciones
    ADD CONSTRAINT consentimiento_aceptaciones_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.consentimiento_aceptaciones
    ADD CONSTRAINT consentimiento_aceptaciones_id_version_fkey FOREIGN KEY (id_version) REFERENCES public.versiones_consentimiento(id_version) ON DELETE RESTRICT;

ALTER TABLE ONLY public.consentimiento_firmas_profesional
    ADD CONSTRAINT consentimiento_firmas_profesional_id_aceptacion_fkey FOREIGN KEY (id_aceptacion) REFERENCES public.consentimiento_aceptaciones(id_aceptacion) ON DELETE RESTRICT;

ALTER TABLE ONLY public.consentimiento_firmas_profesional
    ADD CONSTRAINT consentimiento_firmas_profesional_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.consentimientos
    ADD CONSTRAINT consentimientos_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE RESTRICT;

ALTER TABLE ONLY public.consentimientos
    ADD CONSTRAINT consentimientos_id_version_fkey FOREIGN KEY (id_version) REFERENCES public.versiones_consentimiento(id_version) ON DELETE RESTRICT;

ALTER TABLE ONLY public.desistimientos
    ADD CONSTRAINT desistimientos_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE RESTRICT;

ALTER TABLE ONLY public.desistimientos
    ADD CONSTRAINT desistimientos_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.formatos_institucionales
    ADD CONSTRAINT formatos_institucionales_actualizado_por_fkey FOREIGN KEY (actualizado_por) REFERENCES public.usuarios(id_usuario) ON DELETE SET NULL;

ALTER TABLE ONLY public.historia_clinica_anotaciones
    ADD CONSTRAINT historia_clinica_anotaciones_id_historia_fkey FOREIGN KEY (id_historia) REFERENCES public.historias_clinicas(id_historia) ON DELETE RESTRICT;

ALTER TABLE ONLY public.historia_clinica_anotaciones
    ADD CONSTRAINT historia_clinica_anotaciones_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.historias_clinicas
    ADD CONSTRAINT historias_clinicas_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE RESTRICT;

ALTER TABLE ONLY public.historias_clinicas
    ADD CONSTRAINT historias_clinicas_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.personas
    ADD CONSTRAINT personas_id_usuario_fkey FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.propuestas_cita
    ADD CONSTRAINT propuestas_cita_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE RESTRICT;

ALTER TABLE ONLY public.propuestas_cita
    ADD CONSTRAINT propuestas_cita_id_cita_fkey FOREIGN KEY (id_cita) REFERENCES public.actuaciones(id_actuacion) ON DELETE RESTRICT;

ALTER TABLE ONLY public.propuestas_cita
    ADD CONSTRAINT propuestas_cita_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.remisiones_externas
    ADD CONSTRAINT remisiones_externas_id_actuacion_fkey FOREIGN KEY (id_actuacion) REFERENCES public.actuaciones(id_actuacion) ON DELETE RESTRICT;

ALTER TABLE ONLY public.remisiones_externas
    ADD CONSTRAINT remisiones_externas_id_cita_fkey FOREIGN KEY (id_cita) REFERENCES public.actuaciones(id_actuacion) ON DELETE RESTRICT;

ALTER TABLE ONLY public.repositorio_documentos
    ADD CONSTRAINT repositorio_documentos_publicado_por_fkey FOREIGN KEY (publicado_por) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.seguimientos_ajuste
    ADD CONSTRAINT seguimientos_ajuste_id_ajuste_fkey FOREIGN KEY (id_ajuste) REFERENCES public.ajustes_razonables(id_ajuste) ON DELETE RESTRICT;

ALTER TABLE ONLY public.seguimientos_ajuste
    ADD CONSTRAINT seguimientos_ajuste_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.seguimientos_evolucion
    ADD CONSTRAINT seguimientos_evolucion_id_actuacion_fkey FOREIGN KEY (id_actuacion) REFERENCES public.actuaciones(id_actuacion) ON DELETE RESTRICT;

ALTER TABLE ONLY public.seguimientos_evolucion
    ADD CONSTRAINT seguimientos_evolucion_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso) ON DELETE RESTRICT;

ALTER TABLE ONLY public.seguimientos_evolucion
    ADD CONSTRAINT seguimientos_evolucion_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.solicitudes_atencion
    ADD CONSTRAINT solicitudes_atencion_id_facultad_fkey FOREIGN KEY (id_facultad) REFERENCES public.facultades(id_facultad) ON DELETE RESTRICT;

ALTER TABLE ONLY public.solicitudes_atencion
    ADD CONSTRAINT solicitudes_atencion_id_persona_fkey FOREIGN KEY (id_persona) REFERENCES public.personas(id_persona) ON DELETE RESTRICT;

ALTER TABLE ONLY public.solicitudes_atencion
    ADD CONSTRAINT solicitudes_atencion_id_profesional_preferido_fkey FOREIGN KEY (id_profesional_preferido) REFERENCES public.usuarios(id_usuario);

ALTER TABLE ONLY public.tareas_terapeuticas
    ADD CONSTRAINT tareas_terapeuticas_id_caso_fkey FOREIGN KEY (id_caso) REFERENCES public.casos(id_caso);

ALTER TABLE ONLY public.tareas_terapeuticas
    ADD CONSTRAINT tareas_terapeuticas_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario);

ALTER TABLE ONLY public.tareas_terapeuticas
    ADD CONSTRAINT tareas_terapeuticas_id_remision_fkey FOREIGN KEY (id_remision) REFERENCES public.actuaciones(id_actuacion);

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_id_rol_fkey FOREIGN KEY (id_rol) REFERENCES public.roles(id_rol) ON DELETE RESTRICT;

ALTER TABLE ONLY public.vbg_acciones
    ADD CONSTRAINT vbg_acciones_id_reporte_fkey FOREIGN KEY (id_reporte) REFERENCES public.vbg_reportes(id_reporte) ON DELETE RESTRICT;

ALTER TABLE ONLY public.vbg_acciones
    ADD CONSTRAINT vbg_acciones_id_usuario_fkey FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.vbg_reportes
    ADD CONSTRAINT vbg_reportes_id_persona_reporta_fkey FOREIGN KEY (id_persona_reporta) REFERENCES public.personas(id_persona) ON DELETE RESTRICT;

ALTER TABLE ONLY public.vbg_reportes
    ADD CONSTRAINT vbg_reportes_id_profesional_fkey FOREIGN KEY (id_profesional) REFERENCES public.usuarios(id_usuario) ON DELETE RESTRICT;

ALTER TABLE ONLY public.versiones_consentimiento
    ADD CONSTRAINT versiones_consentimiento_creado_por_fkey FOREIGN KEY (creado_por) REFERENCES public.usuarios(id_usuario) ON DELETE SET NULL;

ALTER TABLE ONLY public.versiones_consentimiento
    ADD CONSTRAINT versiones_consentimiento_id_modelo_fkey FOREIGN KEY (id_modelo) REFERENCES public.modelos_consentimiento(id_modelo) ON DELETE RESTRICT;

ALTER TABLE ONLY public.vinculacion
    ADD CONSTRAINT vinculacion_id_facultad_fkey FOREIGN KEY (id_facultad) REFERENCES public.facultades(id_facultad) ON DELETE RESTRICT;

ALTER TABLE ONLY public.vinculacion
    ADD CONSTRAINT vinculacion_id_persona_fkey FOREIGN KEY (id_persona) REFERENCES public.personas(id_persona) ON DELETE CASCADE;

RESET ROLE;
COMMIT;

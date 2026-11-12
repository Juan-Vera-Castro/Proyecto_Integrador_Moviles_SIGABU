-- SIGABU en PostgreSQL propio · Paso 4 (solo instalación nueva, SIN datos de Supabase).
-- Catálogos que cargan las migraciones: roles, facultades, modelos y versiones de
-- consentimiento, formatos institucionales y el registro de las 29 migraciones ya aplicadas
-- (para que php artisan migrate solo ejecute las que se agreguen después).
--
--   psql -U postgres -d sigabu -f 04_catalogos_instalacion_nueva.sql
--
-- NO ejecutar si se van a traer los datos de Supabase: esos datos ya traen estos catálogos.

\set ON_ERROR_STOP on
SET client_encoding = 'UTF8';
SELECT pg_catalog.set_config('search_path', '', false);

BEGIN;
SET ROLE sigabu_owner;

INSERT INTO public.facultades VALUES
	(1, 'Facultad de Ciencias Administrativas, Contables, Económicas y Financieras', true),
	(2, 'Facultad de Ciencias Ambientales y Desarrollo Sostenible', true),
	(3, 'Facultad de Ciencias de la Educación', true),
	(4, 'Facultad de Ciencias de la Salud', true),
	(5, 'Facultad de Derecho, Ciencias Políticas y Sociales', true),
	(6, 'Facultad de Ingeniería', true);

INSERT INTO public.formatos_institucionales VALUES
	(1, 'REMISION_PSICOLOGICA', 'GESTIÓN APOYO AL ESTUDIANTE', 'REMISIÓN PSICOLÓGICA', 'GBU-GPGR-F-016', '3', '2024-04-18', NULL, '2026-10-27 22:04:28.087925'),
	(2, 'DESISTIMIENTO', 'GESTIÓN APOYO AL ESTUDIANTE', 'DESISTIMIENTO DE LA ATENCIÓN', 'POR-ASIGNAR', '1', '2026-10-21', NULL, '2026-10-27 22:04:28.531427');

INSERT INTO public.migrations VALUES
	(1, '0001_01_01_000000_create_users_table', 1),
	(2, '0001_01_01_000001_create_cache_table', 1),
	(3, '0001_01_01_000002_create_jobs_table', 1),
	(4, '2026_09_23_000000_create_esquema_base_sigabu', 1),
	(5, '2026_09_24_000000_add_abierto_to_casos_estado_check', 1),
	(6, '2026_09_30_000000_add_asistencia_to_actuaciones', 1),
	(7, '2026_09_30_000001_create_tareas_terapeuticas_table', 1),
	(8, '2026_09_30_000002_create_versiones_consentimiento', 1),
	(9, '2026_10_01_000000_add_remision_to_actuaciones', 1),
	(10, '2026_10_01_000001_add_preferencias_to_solicitudes_atencion', 1),
	(11, '2026_10_04_000000_add_respuesta_to_solicitudes_atencion', 1),
	(12, '2026_10_09_000000_remisiones_consentimiento', 1),
	(13, '2026_10_14_000000_cargar_consentimientos_institucionales', 1),
	(14, '2026_10_19_000000_create_historias_clinicas_table', 1),
	(15, '2026_10_20_000000_formato_consentimiento_firma_profesional', 1),
	(16, '2026_10_21_000000_asignacion_por_facultad_y_jornada', 1),
	(17, '2026_10_22_000000_sessions_user_id_uuid', 1),
	(18, '2026_10_23_000000_modulos_vbg_y_donacion_alimentaria', 1),
	(19, '2026_10_24_000000_create_repositorio_documentos', 1),
	(20, '2026_10_25_000000_create_desistimientos_table', 1),
	(21, '2026_10_26_000000_archivos_en_repositorio', 1),
	(22, '2026_10_27_000000_datos_academicos_en_vinculacion', 1),
	(23, '2026_10_28_000000_create_propuestas_cita_table', 1),
	(24, '2026_10_29_000000_create_ajustes_razonables', 1),
	(25, '2026_10_29_000000_create_seguimientos_evolucion_table', 1),
	(26, '2026_10_30_000000_permiso_indicadores_en_usuarios', 1),
	(27, '2026_10_31_000000_propuesta_cita_rechazada', 1),
	(28, '2026_11_01_000000_emergencia_en_solicitudes', 1),
	(29, '2026_11_02_000000_auditoria_expediente_y_reporte_inclusion', 1);

INSERT INTO public.modelos_consentimiento VALUES
	(1, 'LEGADO', 'Consentimientos anteriores a HU-009', NULL, 'Versiones migradas desde la columna version_formato', false, 'General'),
	(2, 'SM-ORIENTACION', 'Consentimiento informado orientación psicológica', 'Salud Mental', 'Formato GBU-GPGR-F-032, versión 1 (13/SEP/2023). Lo firma el estudiante.', true, 'General'),
	(3, 'SM-MENORES', 'Consentimiento informado menores de edad', 'Salud Mental', 'Formato GBU-GPGR-F-019, versión 1 (07/SEP/2021). Lo firma la madre, el padre o el acudiente.', true, 'Menores'),
	(4, 'IN-ORIENTACION', 'Consentimiento informado orientación psicológica', 'Inclusión', 'Formato GBU-GPGR-F-032, versión 1 (13/SEP/2023). Lo firma el estudiante.', true, 'General'),
	(5, 'IN-MENORES', 'Consentimiento informado menores de edad', 'Inclusión', 'Formato GBU-GPGR-F-019, versión 1 (07/SEP/2021). Lo firma la madre, el padre o el acudiente.', true, 'Menores');

INSERT INTO public.roles VALUES
	(1, 'Persona', 'PORTAL_USUARIO'),
	(2, 'Profesional', 'SIGABU_CORE'),
	(3, 'Admin', 'SIGABU_CORE'),
	(4, 'Alimentos', 'ALIMENTOS'),
	(5, 'VBG', 'VBG');

INSERT INTO public.versiones_consentimiento VALUES
	(1, 2, 1, 'Ciudad: {ciudad}. Fecha: {fecha}.

Yo, {nombre_estudiante}, con documento {tipo_documento_estudiante} número {documento_estudiante} de {lugar_expedicion}, declaro que he sido informado(a) sobre los procedimientos de orientación psicológica que se realizarán y de su importancia.

Comprendo que los resultados se utilizarán como parte del proceso que se realice y que, en caso de ser una urgencia, se me remitirá a la EPS o IPS correspondiente, {eps_ips}, y se notificará al acudiente o representante legal: {acudiente_notificacion}. Se guardará la confidencialidad de los datos obtenidos, salvo en el caso de existir un riesgo para mi salud física y/o mental.

Igualmente manifiesto que otorgo de forma libre mi consentimiento para la orientación psicológica por parte del profesional {profesional}, con documento de identificación {documento_profesional}.

En conformidad con lo anteriormente expresado y con pleno conocimiento de sus consecuencias legales, consiento la realización de la orientación psicológica y, como aceptación, presento mi firma.', 'publicada', '2026-10-27 22:04:28', NULL, '2026-10-27 22:04:28.149102'),
	(2, 3, 1, 'Ciudad: {ciudad}. Fecha: {fecha}.

Yo, {nombre_acudiente}, con documento {tipo_documento_acudiente} número {documento_acudiente} de {lugar_expedicion_acudiente}, en calidad de {parentesco_acudiente} (madre, padre y/o acudiente) del menor {nombre_estudiante}, identificado con {tipo_documento_estudiante} número {documento_estudiante}, otorgo de forma libre mi consentimiento para la orientación psicológica por parte del profesional {profesional}, con documento de identificación {documento_profesional}.

Comprendo que los resultados se utilizarán como parte del proceso que se realice y que, en caso de ser una urgencia, se remitirá al menor a la EPS o IPS correspondiente, {eps_ips}.

En conformidad con lo anteriormente expresado y con pleno conocimiento de sus consecuencias legales, consiento la realización de la orientación psicológica y, como aceptación, presento mi firma.', 'publicada', '2026-10-27 22:04:28', NULL, '2026-10-27 22:04:28.149102'),
	(3, 4, 1, 'Ciudad: {ciudad}. Fecha: {fecha}.

Yo, {nombre_estudiante}, con documento {tipo_documento_estudiante} número {documento_estudiante} de {lugar_expedicion}, declaro que he sido informado(a) sobre los procedimientos de orientación psicológica que se realizarán y de su importancia.

Comprendo que los resultados se utilizarán como parte del proceso que se realice y que, en caso de ser una urgencia, se me remitirá a la EPS o IPS correspondiente, {eps_ips}, y se notificará al acudiente o representante legal: {acudiente_notificacion}. Se guardará la confidencialidad de los datos obtenidos, salvo en el caso de existir un riesgo para mi salud física y/o mental.

Igualmente manifiesto que otorgo de forma libre mi consentimiento para la orientación psicológica por parte del profesional {profesional}, con documento de identificación {documento_profesional}.

En conformidad con lo anteriormente expresado y con pleno conocimiento de sus consecuencias legales, consiento la realización de la orientación psicológica y, como aceptación, presento mi firma.', 'publicada', '2026-10-27 22:04:28', NULL, '2026-10-27 22:04:28.149102'),
	(4, 5, 1, 'Ciudad: {ciudad}. Fecha: {fecha}.

Yo, {nombre_acudiente}, con documento {tipo_documento_acudiente} número {documento_acudiente} de {lugar_expedicion_acudiente}, en calidad de {parentesco_acudiente} (madre, padre y/o acudiente) del menor {nombre_estudiante}, identificado con {tipo_documento_estudiante} número {documento_estudiante}, otorgo de forma libre mi consentimiento para la orientación psicológica por parte del profesional {profesional}, con documento de identificación {documento_profesional}.

Comprendo que los resultados se utilizarán como parte del proceso que se realice y que, en caso de ser una urgencia, se remitirá al menor a la EPS o IPS correspondiente, {eps_ips}.

En conformidad con lo anteriormente expresado y con pleno conocimiento de sus consecuencias legales, consiento la realización de la orientación psicológica y, como aceptación, presento mi firma.', 'publicada', '2026-10-27 22:04:28', NULL, '2026-10-27 22:04:28.149102');

SELECT pg_catalog.setval('public.facultades_id_facultad_seq', 6, true);

SELECT pg_catalog.setval('public.formatos_institucionales_id_formato_seq', 2, true);

SELECT pg_catalog.setval('public.migrations_id_seq', 29, true);

SELECT pg_catalog.setval('public.modelos_consentimiento_id_modelo_seq', 5, true);

SELECT pg_catalog.setval('public.roles_id_rol_seq', 5, true);

SELECT pg_catalog.setval('public.versiones_consentimiento_id_version_seq', 4, true);

RESET ROLE;
COMMIT;

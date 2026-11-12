-- SIGABU · Base propia del módulo de Violencias Basadas en Género (sigabu_vbg).
-- Integra lo que la rama VIOLENCIA_GENERO guardaba en su propia base: citas, consentimiento
-- por cita, audios, transcripciones e informes. Está separada de la base principal (sigabu):
-- cada una tiene sus usuarios y no se mezclan; se relacionan solo por identificadores.
--
-- Lo ejecuta el superusuario (postgres) una sola vez:
--
--   psql -U postgres -v clave_owner='...' -v clave_app='...' -f 06_vbg_roles_y_base.sql
--
-- Las tablas las crea la migración 2026_11_10_000000_esquema_vbg_citas_e_informes con el dueño:
--
--   DB_USERNAME=sigabu_owner DB_PASSWORD=<clave_owner> \
--   DB_VBG_USERNAME=sigabu_vbg_owner DB_VBG_PASSWORD=<clave_owner_vbg> php artisan migrate --force
--
-- Roles:
--   sigabu_vbg_owner  dueño del esquema; solo lo usan las migraciones.
--   sigabu_vbg_app    el que usa la aplicación: lee, inserta y actualiza; no borra ni cambia la estructura.

\set ON_ERROR_STOP on

CREATE ROLE sigabu_vbg_owner LOGIN PASSWORD :'clave_owner';
CREATE ROLE sigabu_vbg_app LOGIN PASSWORD :'clave_app' CONNECTION LIMIT 50;

-- Mismas reglas que la base principal: UTF-8, orden en español y hora del servidor en UTC.
CREATE DATABASE sigabu_vbg
    OWNER sigabu_vbg_owner
    TEMPLATE template0
    ENCODING 'UTF8'
    LOCALE_PROVIDER icu
    ICU_LOCALE 'es-CO'
    LOCALE 'C.UTF-8';

ALTER DATABASE sigabu_vbg SET timezone TO 'UTC';
REVOKE ALL ON DATABASE sigabu_vbg FROM PUBLIC;
GRANT CONNECT, TEMPORARY ON DATABASE sigabu_vbg TO sigabu_vbg_app;

\connect sigabu_vbg

ALTER SCHEMA public OWNER TO sigabu_vbg_owner;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO sigabu_vbg_app;
ALTER ROLE sigabu_vbg_owner IN DATABASE sigabu_vbg SET search_path TO public;
ALTER ROLE sigabu_vbg_app IN DATABASE sigabu_vbg SET search_path TO public;

-- Las tablas que cree la migración quedan para la aplicación sin DELETE: la evidencia no se borra.
ALTER DEFAULT PRIVILEGES FOR ROLE sigabu_vbg_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE ON TABLES TO sigabu_vbg_app;
ALTER DEFAULT PRIVILEGES FOR ROLE sigabu_vbg_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO sigabu_vbg_app;

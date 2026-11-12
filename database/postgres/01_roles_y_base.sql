-- SIGABU en PostgreSQL propio · Paso 1: roles y base de datos.
-- Lo ejecuta el superusuario (postgres) una sola vez en el servidor nuevo:
--
--   psql -U postgres -v clave_owner='...' -v clave_app='...' -f 01_roles_y_base.sql
--
-- Dos roles con funciones separadas:
--   sigabu_owner  dueño del esquema; solo lo usan las migraciones (php artisan migrate).
--   sigabu_app    el que usa la aplicación: lee, inserta y actualiza, pero no borra
--                 datos de dominio, no vacía tablas y no cambia la estructura.

\set ON_ERROR_STOP on

CREATE ROLE sigabu_owner LOGIN PASSWORD :'clave_owner';
CREATE ROLE sigabu_app LOGIN PASSWORD :'clave_app' CONNECTION LIMIT 100;

-- UTF-8 con orden alfabético en español (tildes y ñ). La hora del servidor queda en UTC,
-- igual que en Supabase, para que los valores por defecto (now()) no cambien al mudarse.
CREATE DATABASE sigabu
    OWNER sigabu_owner
    TEMPLATE template0
    ENCODING 'UTF8'
    LOCALE_PROVIDER icu
    ICU_LOCALE 'es-CO'
    LOCALE 'C.UTF-8';

ALTER DATABASE sigabu SET timezone TO 'UTC';
REVOKE ALL ON DATABASE sigabu FROM PUBLIC;
GRANT CONNECT, TEMPORARY ON DATABASE sigabu TO sigabu_app;

\connect sigabu

-- Nadie más crea objetos en public; la aplicación solo lo usa.
ALTER SCHEMA public OWNER TO sigabu_owner;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO sigabu_app;
ALTER ROLE sigabu_owner IN DATABASE sigabu SET search_path TO public;
ALTER ROLE sigabu_app IN DATABASE sigabu SET search_path TO public;

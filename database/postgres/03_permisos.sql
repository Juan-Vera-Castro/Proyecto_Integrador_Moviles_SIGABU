-- SIGABU en PostgreSQL propio · Paso 3: permisos de la aplicación.
--
--   psql -U postgres -d sigabu -f 03_permisos.sql
--
-- La aplicación (sigabu_app) lee, inserta y actualiza, pero NO borra filas de las tablas
-- de dominio ni las vacía: aunque alguien ejecute un DELETE o TRUNCATE con las credenciales
-- de la aplicación, la base lo rechaza. Solo puede borrar en las tablas técnicas de Laravel
-- (sesiones, caché, colas y tokens de contraseña), que se limpian solas.
-- La evidencia clínica además tiene triggers que impiden modificarla o borrarla.

\set ON_ERROR_STOP on
BEGIN;

GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO sigabu_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO sigabu_app;

-- Tablas técnicas de Laravel que se depuran solas.
GRANT DELETE ON public.sessions, public.cache, public.cache_locks, public.jobs,
                public.job_batches, public.failed_jobs, public.password_reset_tokens
    TO sigabu_app;

-- El registro de migraciones solo lo escribe sigabu_owner.
REVOKE INSERT, UPDATE ON public.migrations FROM sigabu_app;

-- Las tablas que creen migraciones futuras quedan con los mismos permisos (sin DELETE).
ALTER DEFAULT PRIVILEGES FOR ROLE sigabu_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE ON TABLES TO sigabu_app;
ALTER DEFAULT PRIVILEGES FOR ROLE sigabu_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO sigabu_app;

COMMIT;

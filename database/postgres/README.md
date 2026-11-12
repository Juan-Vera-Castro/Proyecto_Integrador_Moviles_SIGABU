# SIGABU en PostgreSQL propio

Scripts para llevar la base de SIGABU de Supabase a un servidor PostgreSQL de la UCEVA.
Se probaron en PostgreSQL 17 limpio: instalación nueva y traslado de datos.

| Archivo | Qué hace | Quién lo ejecuta |
|---|---|---|
| `01_roles_y_base.sql` | Crea los roles `sigabu_owner` y `sigabu_app` y la base `sigabu` (UTF-8, orden en español, hora UTC). | `postgres` |
| `02_estructura.sql` | Crea las 40 tablas, 8 funciones y 17 triggers (estado final de las 29 migraciones), sin lo exclusivo de Supabase. | `postgres` (los objetos quedan de `sigabu_owner`) |
| `03_permisos.sql` | Da a `sigabu_app` lectura, inserción y actualización, **sin borrar ni vaciar** tablas de dominio. | `postgres` |
| `04_catalogos_instalacion_nueva.sql` | Solo instalación nueva: roles, facultades, consentimientos, formatos y registro de migraciones. | `postgres` |
| `05_verificar.sql` | Conteos para comparar origen y destino después del traslado. | cualquiera |
| `06_vbg_roles_y_base.sql` | Crea la base **separada** `sigabu_vbg` de Violencias de Género con sus roles `sigabu_vbg_owner` y `sigabu_vbg_app` (la aplicación no borra). Sus tablas las crea la migración `2026_11_10_000000`. Ver la sección de VBG en el README principal. | `postgres` |

## Por qué no basta con `php artisan migrate`

Las migraciones crean políticas RLS `TO anon, authenticated` y buckets en `storage.buckets`,
que solo existen en Supabase. En un PostgreSQL normal la primera migración propia falla con
`role "anon" does not exist`. Estos scripts dejan la estructura final sin esas piezas.

## Requisitos del servidor

- PostgreSQL 17 (o 18) con ICU, en Linux (Ubuntu Server 24.04 LTS recomendado).
- Acceso solo desde el servidor de la aplicación (firewall) y conexiones con TLS.
- Copias: `pg_dump` diario cifrado fuera del servidor y archivado de WAL para recuperar
  a un punto en el tiempo. Probar una restauración cada mes.

## Opción A · Traslado desde Supabase (la real)

1. Avisar a los usuarios y poner la aplicación en mantenimiento para que nadie escriba:
   `php artisan down`
2. Servidor nuevo, como `postgres`:
   ```bash
   psql -U postgres -v clave_owner='...' -v clave_app='...' -f 01_roles_y_base.sql
   psql -U postgres -d sigabu -f 02_estructura.sql
   psql -U postgres -d sigabu -f 03_permisos.sql
   ```
   (No ejecutar `04`: los catálogos llegan con los datos.)
3. Desde un equipo con acceso a Supabase por el puerto 5432 (conexión de sesión, no el pooler 6543):
   ```bash
   pg_dump "postgresql://postgres.<ref>:<clave>@aws-0-us-east-1.pooler.supabase.com:5432/postgres?sslmode=require" \
     --data-only --schema=public --no-owner --no-privileges -Fc -f sigabu_datos.dump
   ```
   La advertencia de llaves circulares en `actuaciones` es esperada.
4. En el servidor nuevo, como `postgres`:
   ```bash
   pg_restore -U postgres -d sigabu --data-only --disable-triggers --single-transaction --no-owner sigabu_datos.dump
   ```
   `--disable-triggers` es obligatorio: evita que el trigger de solicitudes abra casos
   duplicados y resuelve la llave circular de `actuaciones`.
5. Verificar en los dos lados y comparar (deben ser idénticos):
   ```bash
   psql "<supabase>" -At -f 05_verificar.sql > conteos_supabase.txt
   psql -U postgres -d sigabu -At -f 05_verificar.sql > conteos_nuevo.txt
   diff conteos_supabase.txt conteos_nuevo.txt
   ```
6. Copiar los archivos de los buckets `consentimientos-firmas` y `repositorio-documentos`
   (ver "Archivos") y cambiar el `.env`. Luego `php artisan config:clear` y `php artisan up`.
7. Mantener Supabase en solo lectura al menos 30 días antes de darla de baja.

## Opción B · Instalación nueva sin datos

Ejecutar `01`, `02`, `03` y `04`. En Windows, `01` falla porque Windows no tiene el locale
`C.UTF-8`: en una copia del archivo cambia `LOCALE 'C.UTF-8'` por `LOCALE 'C'` (el orden en
español lo sigue dando ICU `es-CO`). Si en ese PostgreSQL ya existe una base `sigabu` o los
usuarios `sigabu_owner` / `sigabu_app`, el script se detiene: no los borres; usa otros nombres
en la copia. Para programar en tu computador basta la opción 1 del README principal, sin PostgreSQL.

Después crear el primer administrador (rol 3) con
`php artisan tinker`:

```php
$u = App\Models\User::create(['codigo_unico' => 'ADMIN-1', 'contrasena_hash' => Hash::make('<clave>'), 'id_rol' => 3, 'estado_activo' => true]);
App\Models\Persona::create(['id_usuario' => $u->id_usuario, 'numero_documento' => '<cédula>', 'nombre_completo' => '<nombre>', 'correo_inst' => '<correo>@uceva.edu.co']);
```

Los demás usuarios y roles se gestionan desde Administración.

## Cambios en el `.env` de la aplicación

```dotenv
DB_CONNECTION=pgsql
DB_HOST=<ip-o-nombre-del-servidor>
DB_PORT=5432
DB_DATABASE=sigabu
DB_USERNAME=sigabu_app
DB_PASSWORD=<clave_app>
```

Las migraciones nuevas se ejecutan con el dueño, no con la aplicación:
`DB_USERNAME=sigabu_owner DB_PASSWORD=<clave_owner> php artisan migrate --force`

`config/database.php` fija `sslmode = prefer`; en producción conviene exigir TLS
(`require` o `verify-full`).

## Archivos (firmas y repositorio)

Hoy están en Supabase Storage y Laravel los usa por su API S3. En el servidor propio se
recomienda MinIO (compatible con S3): solo cambian `SIGABU_S3_ENDPOINT`, las claves y los
nombres de bucket del `.env`. Los objetos se copian con `rclone sync` de un bucket a otro.
Sin claves S3, el repositorio usa la carpeta privada `storage/app/private/repositorio`;
las firmas sí necesitan S3.

## Migraciones futuras

- No usar `TO anon, authenticated` ni `storage.buckets` sin comprobar antes que existen, o
  la migración fallará en el servidor propio. Ejemplo:
  ```sql
  DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
      ALTER TABLE public.mi_tabla ENABLE ROW LEVEL SECURITY;
      CREATE POLICY sin_acceso_api ON public.mi_tabla AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false);
    END IF;
  END $$;
  ```
- Las tablas nuevas reciben solo SELECT, INSERT y UPDATE para `sigabu_app` (privilegios por
  defecto de `03`). Si una tabla técnica necesita DELETE, darlo explícitamente.
- Para regenerar `02_estructura.sql`: ejecutar las migraciones en un PostgreSQL desechable donde
  existan los roles `anon` y `authenticated` y una tabla `storage.buckets`; volcar con
  `pg_dump --schema-only --schema=public --no-owner --no-privileges` y quitar los bloques
  `POLICY` y `ROW SECURITY`.

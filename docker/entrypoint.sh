#!/bin/sh
set -e

# La configuración se cachea al arrancar (no al construir), porque depende del .env del contenedor.
php artisan optimize

# Enlace público para archivos de storage/app/public, si se usa
[ -e public/storage ] || php artisan storage:link --quiet || true

exec "$@"

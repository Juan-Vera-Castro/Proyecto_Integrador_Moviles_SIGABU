#!/bin/sh
# Publica en este servidor lo último de main. Conserva el .env, los archivos y la base.
set -e
cd "$(dirname "$0")/../.."

git pull --ff-only origin main
sudo docker compose -f docker-compose.servidor.yml up -d --build

# sigabu_app no puede cambiar la estructura: las migraciones se ejecutan aparte con sigabu_owner
if sudo docker exec sigabu php artisan migrate:status 2>/dev/null | grep -q Pending; then
    echo
    echo "ATENCION: hay migraciones pendientes. Ejecútalas con sigabu_owner (database/postgres/README.md)."
fi

echo
echo "Versión publicada: $(git log -1 --format='%h %s')"

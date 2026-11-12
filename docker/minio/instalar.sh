#!/bin/sh
# Instala MinIO, crea los buckets y muestra las líneas que van en el .env de SIGABU.
# Si ya está instalado, conserva las claves y los archivos.
set -e
cd "$(dirname "$0")"

if [ ! -f .env ]; then
    umask 077
    cat > .env <<CLAVES
MINIO_ROOT_USER=sigabu-admin
MINIO_ROOT_PASSWORD=$(openssl rand -hex 24)
SIGABU_S3_KEY=sigabu-app
SIGABU_S3_SECRET=$(openssl rand -hex 24)
CLAVES
fi

# Usa la imagen si ya se cargó con docker load; si no está, la compila (tarda y pide memoria)
docker compose up -d --wait minio
docker compose run --rm buckets

echo
echo "Listo. Copia estas líneas en el .env de SIGABU:"
grep -E '^SIGABU_S3_(KEY|SECRET)=' .env
echo "SIGABU_S3_ENDPOINT=http://127.0.0.1:9000"

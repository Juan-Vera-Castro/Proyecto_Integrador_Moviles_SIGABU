#!/bin/sh
# Publica SIGABU en este servidor. Se ejecuta con sudo desde la carpeta del proyecto;
# se puede repetir para actualizar: conserva el certificado y los archivos.
set -e
cd "$(dirname "$0")/../.."
IP=192.168.10.67

[ -f .env ] || { echo "Falta el archivo .env en $(pwd)"; exit 1; }

docker compose -f docker-compose.servidor.yml up -d --build

# Certificado interno: el navegador advierte hasta que TI instale uno propio
if [ ! -f /etc/ssl/sigabu/sigabu.crt ]; then
    mkdir -p /etc/ssl/sigabu
    openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
        -keyout /etc/ssl/sigabu/sigabu.key -out /etc/ssl/sigabu/sigabu.crt \
        -subj "/CN=$IP" -addext "subjectAltName=IP:$IP"
    chmod 600 /etc/ssl/sigabu/sigabu.key
fi

a2enmod ssl proxy proxy_http headers
cp docker/servidor/apache-sigabu.conf /etc/apache2/sites-available/sigabu.conf
a2ensite sigabu
apache2ctl configtest
systemctl restart apache2

ufw allow 443/tcp

echo
echo "Respuesta de SIGABU en el servidor:"
curl -sk -o /dev/null -w '%{http_code}\n' "https://$IP/login"

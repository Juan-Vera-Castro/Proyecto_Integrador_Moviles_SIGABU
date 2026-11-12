# SIGABU en un contenedor: PHP 8.4 + Apache, con los assets de Vite ya compilados.
# La base de datos es PostgreSQL propio; su conexión se lee del .env al arrancar.

# 1. Assets del frontend (Tailwind + Alpine con Vite)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

# 2. Dependencias de PHP (sin las de desarrollo)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# 3. Imagen final
FROM php:8.4-apache

# Extensiones que usa el proyecto: pgsql, gd (firmas y PDF), zip (Word), intl, bcmath y opcache.
# Los repositorios de Debian se usan por HTTPS: en algunas redes el HTTP plano (puerto 80) está bloqueado.
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd zip intl bcmath opcache \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/sigabu.ini

# Apache sirve la carpeta public/ y deja que Laravel resuelva las rutas
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && a2enmod rewrite headers \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf && a2enconf servername

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/sigabu-entrypoint
# sed: quita los finales de línea CRLF si el archivo se editó en Windows
RUN sed -i 's/\r$//' /usr/local/bin/sigabu-entrypoint && chmod +x /usr/local/bin/sigabu-entrypoint

EXPOSE 80
ENTRYPOINT ["sigabu-entrypoint"]
CMD ["apache2-foreground"]

# syntax=docker/dockerfile:1.7
# TriTransportes · imagen multi-etapa con bases ligeras (Alpine) y versiones fijas.
#
#   deps-prod  → dependencias de producción (sin herramientas de desarrollo)
#   deps-dev   → + PHPUnit, solo para la etapa de pruebas
#   php-base   → PHP-FPM 8.3 con las extensiones que usa el sistema
#   test       → corre las pruebas unitarias: si fallan, la construcción se detiene
#   api        → imagen final de PHP-FPM (API y sistema clásico), usuario sin privilegios
#   web        → nginx: sirve el front end y los recursos estáticos, y deriva PHP a "api"

ARG PHP_IMAGE=php:8.3.14-fpm-alpine3.20
ARG COMPOSER_IMAGE=composer:2.8.4
ARG NGINX_IMAGE=nginx:1.27.3-alpine

# ── Dependencias ─────────────────────────────────────────────────────────────
FROM ${COMPOSER_IMAGE} AS deps-prod
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY src/ src/
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

FROM deps-prod AS deps-dev
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts --ignore-platform-reqs \
 && composer dump-autoload --optimize

# ── PHP base ─────────────────────────────────────────────────────────────────
FROM ${PHP_IMAGE} AS php-base
RUN apk add --no-cache libzip \
 && apk add --no-cache --virtual .build-deps libzip-dev $PHPIZE_DEPS \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql zip opcache \
 && apk del .build-deps
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-tritransportes.ini
WORKDIR /var/www/html/TriTransportes

# ── Pruebas unitarias dentro de la construcción ──────────────────────────────
FROM php-base AS test
COPY --from=deps-dev /app/vendor ./vendor
COPY . .
RUN vendor/bin/phpunit --testsuite Unit --no-progress

# ── Imagen final de la API ───────────────────────────────────────────────────
FROM php-base AS api
COPY --chown=www-data:www-data . .
COPY --from=deps-prod --chown=www-data:www-data /app/vendor ./vendor
RUN rm -rf tests docker .github phpunit.xml.dist \
 && mkdir -p logs uploads exports \
 && chown -R www-data:www-data logs uploads exports
USER www-data
EXPOSE 9000
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=5 \
  CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

# ── Servidor web (front end) ─────────────────────────────────────────────────
FROM ${NGINX_IMAGE} AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=api /var/www/html/TriTransportes /var/www/html/TriTransportes
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=5 \
  CMD wget -qO- http://127.0.0.1:8080/healthz >/dev/null || exit 1

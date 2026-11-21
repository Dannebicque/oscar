# Image de production d'Oscar : le front compilé et l'API Symfony servis par FrankenPHP (Caddy : HTTPS automatique, PHP et fichiers statiques dans un seul binaire).
# Construire : docker compose -f compose.prod.yaml build        Voir docs/INSTALL.md

# --- 1. Front (Vue 3). VITE_API_URL vide = même origine : l'API répond sur /api du même site, pas de CORS.
FROM node:22-alpine AS web
WORKDIR /web
COPY web/package.json web/package-lock.json ./
RUN npm ci
COPY web/ ./
ARG VITE_API_URL=""
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build

# --- 2. API (Symfony 8, PHP 8.4) + front
FROM dunglas/frankenphp:1-php8.4 AS app
RUN install-php-extensions pdo_pgsql intl sodium opcache zip ldap
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1 PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"
COPY docker/prod/php.ini $PHP_INI_DIR/app.conf.d/zz-oscar.ini

WORKDIR /app
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY api/ ./
# .env.example fournit des valeurs par défaut à chaque variable ; l'environnement réel (compose) prime toujours.
RUN cp .env.example .env \
 && composer dump-autoload --no-dev --classmap-authoritative \
 && mkdir -p var/cache var/log var/share \
 && APP_SECRET=build APP_VAULT_KEY="base64:$(php -r 'echo base64_encode(str_repeat("k",32));')" php bin/console cache:warmup --env=prod \
 && chown -R www-data:www-data var

COPY --from=web /web/dist /srv/web
COPY docker/prod/Caddyfile /etc/caddy/Caddyfile
COPY docker/prod/entrypoint.sh /usr/local/bin/oscar-entrypoint
RUN chmod +x /usr/local/bin/oscar-entrypoint

EXPOSE 80 443
ENTRYPOINT ["oscar-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

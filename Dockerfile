# Imagem de produção do SisREAd (docs/deploy.md).
# Um único container roda o servidor web (FrankenPHP), o worker de fila e o agendador,
# todos sob o supervisord. O SQLite fica no volume montado em /data (nunca na imagem).

FROM composer:2 AS composer

FROM dunglas/frankenphp:1-php8.3-bookworm

RUN install-php-extensions intl zip pcntl opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends supervisor sqlite3 curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-sisread.ini

WORKDIR /app

# Dependências primeiro, para aproveitar o cache de camadas.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && rm -f database/*.sqlite* .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

COPY docker/supervisord.conf /etc/supervisor/sisread.conf
COPY docker/Caddyfile /etc/caddy/Caddyfile
RUN chmod +x docker/entrypoint.sh docker/backup-sqlite.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    QUEUE_CONNECTION=database \
    QUEUE_WORKER_SUPERVISIONADO=true \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    SERVER_NAME=:8080 \
    XDG_DATA_HOME=/data/caddy-data \
    XDG_CONFIG_HOME=/data/caddy-config

EXPOSE 8080
VOLUME ["/data"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/app/docker/entrypoint.sh"]

# syntax=docker/dockerfile:1.7
#
# BookFlow — production image
# PHP 8.3-fpm + nginx + supervisord (single container)
#
# Build:
#   docker build -t bookflow:latest .
#   docker build --target=dev -t bookflow:dev .
#
# Run:
#   docker run -p 8080:80 --env-file .env bookflow:latest

# ──────────────────────────────────────────────────────────
# STAGE 1: composer dependencies
# ──────────────────────────────────────────────────────────
FROM composer:2.9 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ──────────────────────────────────────────────────────────
# STAGE 2: base PHP image with extensions
# ──────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

ENV TZ=Asia/Seoul \
    PHP_INI_DIR=/usr/local/etc/php \
    COMPOSER_ALLOW_SUPERUSER=1

# 시스템 패키지 + PHP 확장
RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        tzdata \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        icu-dev \
        mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        intl \
        opcache \
        pdo_mysql \
        pcntl \
        zip \
        exif \
    && cp /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && rm -rf /var/cache/apk/* /tmp/*

# PHP-FPM, php.ini, opcache, nginx, supervisord 설정
COPY docker/php/php.ini       $PHP_INI_DIR/conf.d/00-bookflow.ini
COPY docker/php/opcache.ini   $PHP_INI_DIR/conf.d/10-opcache.ini
COPY docker/php/www.conf      /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx/nginx.conf  /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf  /etc/supervisord.conf

# Composer (마이그레이션 등에서 필요 시 사용)
COPY --from=composer:2.9 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# ──────────────────────────────────────────────────────────
# STAGE 3: production
# ──────────────────────────────────────────────────────────
FROM base AS production

# 애플리케이션 코드 복사
COPY --chown=www-data:www-data . .
# vendor 디렉토리는 stage 1의 결과 사용
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

# storage, bootstrap/cache 쓰기 권한 + 심볼릭링크용 디렉토리
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && php artisan storage:link || true

# Laravel optimize (route/config/view cache는 K8s에서 entrypoint에서 실행 권장)

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -sf http://127.0.0.1/up || exit 1

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# ──────────────────────────────────────────────────────────
# STAGE 4: dev (선택, docker-compose 로컬 개발용)
# ──────────────────────────────────────────────────────────
FROM base AS dev

# dev에서는 source mount하니까 코드 복사 안 함
# 단, composer dev dependencies 설치
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

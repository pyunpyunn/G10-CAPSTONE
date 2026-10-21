FROM node:22-alpine AS web-build
WORKDIR /src
COPY frontend-web/package.json frontend-web/package-lock.json ./
RUN npm ci
COPY frontend-web/ ./
RUN npm run build

FROM composer:2 AS composer-bin

FROM php:8.3-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl mbstring opcache pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY backend-laravel/ ./
COPY --from=web-build /src/dist/ ./public/frontend-web/
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
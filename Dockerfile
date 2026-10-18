# syntax=docker/dockerfile:1

# ---- Front-end assets (used by the prod image) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json ./
RUN npm install
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- Shared PHP base ----
FROM php:8.3-fpm-alpine AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ---- Local development (source is bind-mounted by docker-compose) ----
FROM base AS dev
COPY docker/php/dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

# ---- Production ----
FROM base AS prod
COPY docker/php/prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data

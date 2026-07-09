FROM php:8.3-cli AS php-base

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libonig-dev \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install mbstring pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS php-deps

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM node:22-bookworm AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY --from=php-deps /var/www/html/vendor ./vendor
RUN npm run build

FROM php-base

WORKDIR /var/www/html

COPY . .
COPY --from=php-deps /var/www/html/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD php artisan config:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}

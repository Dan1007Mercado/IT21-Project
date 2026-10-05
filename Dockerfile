FROM node:20-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_APP_NAME=INTSEC

ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME} \
    VITE_APP_NAME=${VITE_APP_NAME}

RUN npm run build \
    && test -f public/build/manifest.json \
    && test -d public/build/assets


FROM php:8.3-apache-bookworm AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=10000

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && a2enmod expires headers rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .

RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
    && rm -rf public/build public/hot

COPY --from=frontend /app/public/build ./public/build
COPY docker/apache/ports.conf /etc/apache2/ports.conf
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/servername.conf /etc/apache2/conf-available/servername.conf
COPY docker/start.sh /usr/local/bin/intsec-start

RUN a2enconf servername \
    && mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && ln -sfn ../storage/app/public public/storage \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R 775 bootstrap/cache storage \
    && chmod 755 /usr/local/bin/intsec-start \
    && test -f public/build/manifest.json \
    && test -d public/build/assets

RUN printf '%s\n' \
    'memory_limit=256M' \
    'upload_max_filesize=20M' \
    'post_max_size=20M' \
    'max_execution_time=60' \
    'expose_php=Off' \
    'opcache.enable=1' \
    'opcache.memory_consumption=128' \
    'opcache.interned_strings_buffer=16' \
    'opcache.max_accelerated_files=20000' \
    'opcache.validate_timestamps=0' \
    > /usr/local/etc/php/conf.d/intsec-production.ini

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/intsec-start"]

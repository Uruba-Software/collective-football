FROM php:8.5-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    postgresql-dev \
    linux-headers \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    autoconf \
    g++ \
    make \
    rabbitmq-c-dev \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        mbstring \
        xml \
        bcmath \
        pcntl \
        intl \
        sockets \
        opcache

# Install PECL extensions
RUN pecl install redis && docker-php-ext-enable redis
RUN pecl install amqp && docker-php-ext-enable amqp

# Install Swoole for Laravel Octane (high-traffic mode)
RUN pecl install swoole --enable-swoole-curl --enable-openssl && \
    docker-php-ext-enable swoole

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/backend

# Non-root user for security
RUN addgroup -g 1000 -S collective && \
    adduser -u 1000 -S collective -G collective && \
    mkdir -p /var/log/php && \
    chown -R collective:collective /var/www /var/log/php

USER collective

EXPOSE 9000

CMD ["php-fpm"]

FROM php:8.5-fpm-alpine

# PHP 8.5-fpm-alpine already includes: mbstring, xml, opcache, libxml,
# json, curl, openssl, sodium, dom, fileinfo, filter, hash, iconv, zlib
# We only need to install what's NOT bundled.

# System dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    # For zip extension
    libzip-dev \
    # For GD extension
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    # For pgsql extensions
    postgresql-dev \
    # For amqp (RabbitMQ)
    rabbitmq-c-dev \
    # For intl extension
    icu-dev \
    # pkg-config (needed by some extensions)
    pkgconf \
    # Build tools
    linux-headers \
    autoconf \
    g++ \
    make \
    # Supervisor for process management
    supervisor

# Configure GD (freetype + jpeg support)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# PostgreSQL extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Archive + math + process
RUN docker-php-ext-install zip bcmath pcntl

# Image processing
RUN docker-php-ext-install gd

# Internationalization
RUN docker-php-ext-install intl

# Sockets (for WebSocket / RabbitMQ communication)
RUN docker-php-ext-install sockets

# Redis — caching, sessions, queues
RUN pecl install redis && docker-php-ext-enable redis

# AMQP — RabbitMQ driver
RUN pecl install amqp && docker-php-ext-enable amqp

# Swoole — Laravel Octane high-traffic server (10K+ concurrent connections)
RUN pecl install swoole && docker-php-ext-enable swoole

# Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Non-root user for security
RUN addgroup -g 1000 -S collective && \
    adduser -u 1000 -S collective -G collective && \
    mkdir -p /var/log/php && \
    chown -R collective:collective /var/www /var/log/php

USER collective

EXPOSE 9000

CMD ["php-fpm"]

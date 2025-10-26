# Start from the official PHP 8.2 FPM image
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libonig-dev \
    libxml2-dev

# Install PHP extensions required by Laravel
## Install PHP extensions required by Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml dom

## Install build deps for PECL extensions (xdebug) and phpdbg
USER root
RUN apt-get update && apt-get install -y --no-install-recommends \
    autoconf \
    pkg-config \
    gcc \
    make \
    ca-certificates \
    wget \
    --no-install-recommends \
    && pecl channel-update pecl.php.net \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

## Configure Xdebug for coverage by default
ENV XDEBUG_MODE=coverage
ENV XDEBUG_CONFIG="client_host=host.docker.internal"

## phpdbg is useful for coverage without Xdebug; try to ensure it's available
RUN apt-get update && apt-get install -y phpdbg || true

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Run as a non-root user
RUN groupadd -g 1000 www || true
RUN useradd -u 1000 -ms /bin/bash -g www www || true

# Change current user to www
USER www

# Expose port 9000
EXPOSE 9000
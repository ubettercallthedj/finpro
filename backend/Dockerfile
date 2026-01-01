# Backend development Dockerfile for Laravel (php + composer)
# Uses Debian-based official PHP image for easier extension installation.

FROM php:8.2-fpm

ARG DEBIAN_FRONTEND=noninteractive

# System dependencies and PHP extensions commonly required by Laravel
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    libonig-dev \
    libicu-dev \
    libxml2-dev \
    zip \
    curl \
    gnupg2 \
    ca-certificates \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath intl xml gd \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && rm -rf /var/lib/apt/lists/*

# Install Composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# copy composer files first to leverage layer cache
COPY composer.json composer.lock* ./

# Copy the full app before running composer so post-install scripts can access project files
COPY . .

# Ensure bootstrap cache directory exists and is writable for composer scripts
RUN mkdir -p bootstrap/cache \
  && chown -R www-data:www-data bootstrap \
  && chmod -R 775 bootstrap/cache

# Install PHP dependencies (no dev in this container by default)
RUN composer install --prefer-dist --no-interaction --no-progress --no-suggest

# Ensure proper permissions for typical Laravel usage
RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www

# Default command for development: use Artisan serve (keeps compatibility with docker-compose command)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

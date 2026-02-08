FROM dunglas/frankenphp:php8.4

# Install system dependencies and PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Allow Composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER=1
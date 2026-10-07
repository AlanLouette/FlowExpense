FROM php:8.2-apache

# Install required packages
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    unzip \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        zip \
        gd \
        mbstring

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy custom Apache config
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working dir
WORKDIR /var/www/html

# Install the locked PDF and spreadsheet dependencies.
COPY public/composer.json public/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

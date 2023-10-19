# Use the official PHP 8.2-fpm base image
FROM php:8.2-fpm

# Set the working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy the application files to the container
COPY ../support-api .

# Install application dependencies
RUN composer install --no-interaction --no-scripts --no-progress --prefer-dist

# Generate the application key
RUN php artisan key:generate

# Set the correct permissions for the storage and bootstrap/cache directories
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
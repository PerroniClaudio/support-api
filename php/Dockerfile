FROM php:8.2-fpm

# Installa le estensioni necessarie per Laravel
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo pdo_mysql \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

# Composer: Installa Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Laravel Installer: Installa il Laravel Installer globalmente
RUN composer global require laravel/installer

# Aggiungi il binario Composer all'ambiente
ENV PATH="${PATH}:/root/.composer/vendor/bin"

# Altre personalizzazioni, se necessario



WORKDIR /var/www/html

CMD ["php-fpm"]

EXPOSE 9000

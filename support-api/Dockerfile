# Usa un'immagine PHP ufficiale.
# https://hub.docker.com/_/php
FROM php:8.2-fpm

# Imposta la directory di lavoro in /app
WORKDIR /app

# Installa le dipendenze di sistema e le estensioni PHP necessarie per Laravel.
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
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia i file dell'applicazione
COPY . .

# Installa le dipendenze di Composer come root
RUN composer install --no-dev --optimize-autoloader

# Esegui le ottimizzazioni di Laravel
# RUN php artisan config:cache
# RUN php artisan route:cache
# RUN php artisan view:cache

# Imposta le autorizzazioni corrette per la directory dell'app
RUN chown -R www-data:www-data /app

# Passa all'utente non-root per una maggiore sicurezza
USER www-data

# Esponi la porta 8080 per consentire l'accesso all'applicazione
EXPOSE 8080

# Comando per avviare il server di sviluppo di Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]

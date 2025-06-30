FROM php:8.4-fpm-alpine 

# Installa le dipendenze necessarie
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor

# Configura e installa le estensioni PHP necessarie
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd pcntl bcmath zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia la configurazione PHP per produzione
COPY ./php/php.prod.ini /usr/local/etc/php/php.ini

# Installa nginx e supervisor
RUN apk add --no-cache nginx supervisor

# Configura nginx
COPY ./nginx/default.cloudrun.conf /etc/nginx/nginx.conf

# Crea le directory necessarie per nginx
RUN mkdir -p /run/nginx /var/log/nginx

# Configura supervisord
COPY ./php/supervisord.cloudrun.conf /etc/supervisor/conf.d/supervisord.conf

# Imposta la directory di lavoro
WORKDIR /app

# Copia i file dell'applicazione
COPY ./support-api/ .

# Imposta i permessi corretti per Laravel
RUN mkdir -p /app/storage/logs /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views /app/bootstrap/cache \
    && chown -R www-data:www-data /app \
    && chmod -R 755 /app \
    && chmod -R 775 /app/storage \
    && chmod -R 775 /app/bootstrap/cache

# Installa le dipendenze del progetto
RUN composer install --no-dev --optimize-autoloader

# Esponi la porta 8080
EXPOSE 8080

# Avvia supervisord che gestirà nginx e php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
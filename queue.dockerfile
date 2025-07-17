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

# Installa nginx per il health check endpoint
RUN apk add --no-cache nginx

# Copia la configurazione Nginx
COPY ./nginx/default.queue.conf /etc/nginx/http.d/default.conf

# Crea le directory necessarie per nginx
RUN mkdir -p /run/nginx /var/log/nginx

# Copia la configurazione Supervisord
COPY ./php/supervisord.queue.conf /etc/supervisor/conf.d/supervisord.conf

# Imposta la directory di lavoro
WORKDIR /app

# Copia i file dell'applicazione
COPY ./support-api/ .

# Crea un semplice health check endpoint
RUN mkdir -p /app/public
RUN echo '<?php echo json_encode(["status" => "healthy", "queue_worker" => "running", "timestamp" => time()]);' > /app/public/queue-health.php

# Imposta i permessi corretti per Laravel
RUN mkdir -p /app/storage/logs /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views /app/bootstrap/cache \
    && chown -R www-data:www-data /app \
    && chmod -R 755 /app \
    && chmod -R 775 /app/storage \
    && chmod -R 775 /app/bootstrap/cache

# Installa le dipendenze del progetto
RUN composer install --no-dev --optimize-autoloader

# Esponi la porta 8080 per health checks
EXPOSE 8080

# Avvia supervisord che gestirà nginx e il queue worker
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
# 🐳 Spreetzitt Backend - Production Dockerfile
# Multi-stage build per ottimizzare dimensioni immagine

# =============================================================================
# BUILDER STAGE - Installa dipendenze PHP
# =============================================================================
FROM php:8.2-fpm-alpine as builder

# Installa dipendenze di sistema per build
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    mysql-client

# Installa estensioni PHP necessarie
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        exif \
        pcntl \
        bcmath

# Installa Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Imposta directory di lavoro
WORKDIR /app

# Copia composer files
COPY composer.json composer.lock ./

# Installa dipendenze PHP (senza dev dependencies)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --optimize-autoloader \
    --prefer-dist

# =============================================================================
# RUNTIME STAGE - Immagine finale ottimizzata
# =============================================================================
FROM php:8.2-fpm-alpine as runtime

# Installa solo dipendenze runtime necessarie
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    libpng \
    libjpeg \
    freetype \
    libzip \
    oniguruma

# Installa estensioni PHP (stesso set del builder)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        exif \
        pcntl \
        bcmath

# Crea utente per applicazione
RUN addgroup -g 1000 app && adduser -D -s /bin/sh -u 1000 -G app app

# Imposta directory di lavoro
WORKDIR /app

# Copia vendor da builder stage
COPY --from=builder /app/vendor ./vendor

# Copia applicazione
COPY . .

# Copia file di configurazione
COPY ../cloud-run/docker/nginx-backend.conf /etc/nginx/nginx.conf
COPY ../cloud-run/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Genera autoloader ottimizzato
RUN composer dump-autoload --optimize --classmap-authoritative

# Crea directory per logs e cache
RUN mkdir -p \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    bootstrap/cache

# Imposta permessi
RUN chown -R app:app \
    storage \
    bootstrap/cache \
    && chmod -R 775 \
    storage \
    bootstrap/cache

# Configurazione PHP-FPM per Cloud Run
RUN echo "php_admin_value[memory_limit] = 512M" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "php_admin_value[max_execution_time] = 300" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "php_admin_value[upload_max_filesize] = 50M" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "php_admin_value[post_max_size] = 50M" >> /usr/local/etc/php-fpm.d/www.conf

# Esponi porta 8080 (richiesto da Cloud Run)
EXPOSE 8080

# Usa supervisor per gestire nginx + php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Health check per Cloud Run
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/health || exit 1

# Labels per metadata
LABEL maintainer="spreetzitt-team"
LABEL version="1.0"
LABEL description="Spreetzitt Laravel Backend for Google Cloud Run"

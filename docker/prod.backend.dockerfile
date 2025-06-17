# ==================================================
# Spreetzitt Backend - Production Dockerfile
# Multi-stage build for optimized Laravel production image
# ==================================================

# Stage 1: Build stage
FROM php:8.3-fpm-alpine AS builder

# Install build dependencies
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    linux-headers \
    && apk add --no-cache \
    git \
    curl \
    zip \
    unzip

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        gd \
        pcntl \
        bcmath \
        zip \
        opcache

# Install Redis extension
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY ./support-api/composer.json ./support-api/composer.lock ./

# Install PHP dependencies with production optimizations
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    && composer clear-cache

# Copy application code
COPY ./support-api/ .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Clean up build dependencies
RUN apk del .build-deps

# ==================================================
# Stage 2: Production stage
FROM php:8.3-fpm-alpine AS production

# Set labels for better maintainability
LABEL maintainer="Spreetzitt Team"
LABEL version="1.0"
LABEL description="Spreetzitt Backend - Production Image"

# Install runtime dependencies
RUN apk add --no-cache \
    supervisor \
    nginx \
    freetype \
    libjpeg-turbo \
    libpng \
    libzip \
    && rm -rf /var/cache/apk/*

# Copy PHP extensions from builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Create application user
RUN addgroup -g 1001 -S appgroup \
    && adduser -u 1001 -S appuser -G appgroup

# Set working directory
WORKDIR /var/www/html

# Copy application from builder stage
COPY --from=builder --chown=appuser:appgroup /var/www/html/ .

# Copy production configurations
COPY ./php/supervisord.prod.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./php/php.prod.ini /usr/local/etc/php/conf.d/99-production.ini
COPY ./php/php-fpm.prod.conf /usr/local/etc/php-fpm.d/zzz-production.conf

# Create necessary directories with proper permissions
RUN mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /run/nginx \
    /var/log/supervisor \
    && chown -R appuser:appgroup \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
        /run/nginx \
        /var/log/supervisor \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Optimize Laravel for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache \
    && php artisan storage:link

# Set proper ownership
RUN chown -R appuser:appgroup /var/www/html

# Switch to non-root user
USER appuser

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD php artisan inspire || exit 1

# Expose port
EXPOSE 9000

# Start supervisord
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

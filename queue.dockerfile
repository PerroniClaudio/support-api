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

# Configura nginx per il health check
RUN echo 'server { \
    listen 8080; \
    root /app/public; \
    index index.php; \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        include fastcgi_params; \
    } \
}' > /etc/nginx/http.d/default.conf

# Crea le directory necessarie per nginx
RUN mkdir -p /run/nginx /var/log/nginx

# Configura supervisord per gestire sia nginx che il queue worker
RUN echo '[supervisord]\n\
nodaemon=true\n\
user=root\n\
logfile=/var/log/supervisord.log\n\
pidfile=/var/run/supervisord.pid\n\
\n\
[program:php-fpm]\n\
command=php-fpm -F\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
autorestart=true\n\
startretries=0\n\
\n\
[program:nginx]\n\
command=nginx -g "daemon off;"\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
autorestart=true\n\
startretries=0\n\
\n\
[program:laravel-queue]\n\
process_name=%(program_name)s_%(process_num)02d\n\
command=php /app/artisan queue:work --tries=3 --backoff=3 --sleep=3 --max-time=3600 --timeout=300\n\
autostart=true\n\
autorestart=true\n\
stopasgroup=true\n\
killasgroup=true\n\
user=www-data\n\
numprocs=2\n\
redirect_stderr=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stopwaitsecs=3600\n\
' > /etc/supervisor/conf.d/supervisord.conf

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
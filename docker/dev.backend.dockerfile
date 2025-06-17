# Usa l'immagine ufficiale di PHP 8.3 con FPM
FROM php:8.3-fpm

# Installa le dipendenze necessarie
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor \
    nodejs \
    npm

# Configura e installa le estensioni PHP necessarie
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd pcntl bcmath zip

# Installa l'estensione Redis
RUN pecl install redis && docker-php-ext-enable redis

# Imposta la max filesize a 20MB su php.ini
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/php.ini
RUN echo "post_max_size = 20M" >> /usr/local/etc/php/php.ini



# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Copia i file dell'applicazione
COPY ./support-api/ .

# Esegui npm build
# RUN npm install -g pnpm
# RUN pnpm i
# RUN pnpm build
# Installa le dipendenze del progetto
# Nota: questo passo potrebbe fallire se non hai un composer.json valido nella directory del progetto
RUN composer install

# Se il comando precedente fallisce, puoi decommentare la seguente riga per ignorare l'errore
# RUN echo "Composer install failed, but continuing anyway"

# Ottimizza la configurazione per la produzione
# RUN php artisan config:cache \
#     && php artisan route:cache \
#     && php artisan view:cache

# Copia la configurazione di supervisord
COPY /php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# Imposta i permessi corretti
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Esponi la porta 9000 per FPM
EXPOSE 9000

# Avvia supervisord
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
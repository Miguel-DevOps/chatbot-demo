# api/Dockerfile

# Etapa 1: CONSTRUCTOR (Instala TODO, incluyendo dependencias de desarrollo)
FROM php:8.2-cli-alpine AS dependencies

# Instala dependencias del sistema + extensiones de PHP
RUN apk add --no-cache git curl libpng-dev oniguruma-dev libxml2-dev freetype-dev libjpeg-turbo-dev libzip-dev zip unzip
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia archivos de Composer e instala TODAS las dependencias (incluyendo dev)
COPY composer.json composer.lock* ./
RUN composer install --prefer-dist --no-scripts --no-autoloader

# Copia el resto del código fuente
COPY . .

# Genera el autoloader optimizado
RUN composer dump-autoload --optimize

# Create health check script
RUN echo '<?php' > /usr/local/bin/healthcheck.php && \
    echo 'try {' >> /usr/local/bin/healthcheck.php && \
    echo '  require_once "/var/www/html/vendor/autoload.php";' >> /usr/local/bin/healthcheck.php && \
    echo '  $config = \ChatbotDemo\Config\AppConfig::getInstance();' >> /usr/local/bin/healthcheck.php && \
    echo '  $redis = new Redis();' >> /usr/local/bin/healthcheck.php && \
    echo '  $redis->connect($config->get("redis.host", "redis"), $config->get("redis.port", 6379));' >> /usr/local/bin/healthcheck.php && \
    echo '  $redis->ping();' >> /usr/local/bin/healthcheck.php && \
    echo '  echo "OK";' >> /usr/local/bin/healthcheck.php && \
    echo '} catch (Exception $e) {' >> /usr/local/bin/healthcheck.php && \
    echo '  exit(1);' >> /usr/local/bin/healthcheck.php && \
    echo '}' >> /usr/local/bin/healthcheck.php && \
    chmod +x /usr/local/bin/healthcheck.php


# Etapa 2: PRODUCCIÓN (Imagen final, limpia y segura)
FROM php:8.2-fpm-alpine AS production

# Instala solo las extensiones de PHP necesarias en runtime
RUN apk add --no-cache libpng oniguruma libxml2 freetype libjpeg-turbo libzip curl
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Crea el usuario no-root
RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www

WORKDIR /var/www/html

# Instala Composer para poder limpiar dependencias
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia solo los artefactos necesarios desde la etapa de CONSTRUCTOR
# Copiamos código fuente y composer files, pero NO el vendor con dev dependencies
COPY --from=dependencies --chown=www:www /var/www/html/composer.json ./composer.json
COPY --from=dependencies --chown=www:www /var/www/html/composer.lock ./composer.lock
COPY --from=dependencies --chown=www:www /var/www/html/public ./public
COPY --from=dependencies --chown=www:www /var/www/html/src ./src
COPY --from=dependencies --chown=www:www /var/www/html/knowledge ./knowledge
COPY --from=dependencies --chown=www:www /var/www/html/tests ./tests
COPY --from=dependencies --chown=www:www /var/www/html/phpunit.xml ./phpunit.xml

# Instala SOLO dependencias de producción (sin dev) desde cero
RUN composer install --no-dev --no-scripts --classmap-authoritative --prefer-dist

# Crea directorios y ajusta permisos
RUN mkdir -p storage/logs logs && \
    chown -R www:www storage logs && \
    chmod -R 775 storage logs

# Configure PHP-FPM for security and performance
RUN echo "pm.max_children = 10" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm.start_servers = 3" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm.min_spare_servers = 2" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm.max_spare_servers = 4" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm.max_requests = 500" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "security.limit_extensions = .php" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "php_admin_flag[expose_php] = off" >> /usr/local/etc/php-fpm.d/www.conf

# Configure PHP security settings
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "allow_url_fopen = Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "allow_url_include = Off" >> /usr/local/etc/php/conf.d/security.ini

# Copy health check script from dependencies stage
COPY --from=dependencies /usr/local/bin/healthcheck.php /usr/local/bin/healthcheck.php

# Aplica hardening a PHP y PHP-FPM (configuraciones ya aplicadas desde dependencies)
USER www
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 CMD php /usr/local/bin/healthcheck.php || exit 1
CMD ["php-fpm"]
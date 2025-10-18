# Dockerfile para Chatbot Demo API
# Multi-stage build optimizado para producción con mejores prácticas de seguridad

# =============================================================================
# ETAPA BASE: Configuración común de PHP con extensiones necesarias
# =============================================================================
FROM php:8.2-fpm-alpine AS base

# Instalar dependencias del sistema necesarias para runtime y build
RUN apk add --no-cache \
    # Runtime dependencies
    libpng \
    oniguruma \
    libxml2 \
    freetype \
    libjpeg-turbo \
    libzip \
    curl \
    # Build dependencies (temporal)
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        oniguruma-dev \
        libxml2-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libzip-dev \
    # Configurar e instalar extensiones PHP
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    # Instalar Redis extension
    && pecl install redis \
    && docker-php-ext-enable redis \
    # Limpiar build dependencies
    && apk del .build-deps \
    # Limpiar cache
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# Configurar PHP para producción
RUN { \
    echo 'expose_php = Off'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/www/html/logs/php_errors.log'; \
    echo 'allow_url_fopen = Off'; \
    echo 'allow_url_include = Off'; \
    echo 'session.cookie_httponly = On'; \
    echo 'session.cookie_secure = On'; \
    echo 'session.use_strict_mode = On'; \
    echo 'max_execution_time = 30'; \
    echo 'memory_limit = 256M'; \
    echo 'post_max_size = 50M'; \
    echo 'upload_max_filesize = 50M'; \
} > /usr/local/etc/php/conf.d/99-production.ini

# Crear usuario no-root para seguridad
RUN addgroup -g 1000 www && \
    adduser -u 1000 -G www -s /bin/sh -D www

WORKDIR /var/www/html

# =============================================================================
# ETAPA DEPENDENCIES: Gestión de dependencias PHP
# =============================================================================
FROM base AS dependencies

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar archivos de dependencias
COPY composer.json composer.lock ./

# Instalar dependencias de producción (sin dev)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader \
    && composer clear-cache

# En etapa separada, instalar todas las dependencias (para tests)
FROM dependencies AS dependencies-dev
RUN composer install \
    --prefer-dist \
    --no-scripts \
    --no-autoloader \
    && composer clear-cache

# =============================================================================
# ETAPA PRODUCTION: Imagen final optimizada
# =============================================================================
FROM base AS production

# Copiar Composer para generar autoloader optimizado
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar dependencias de producción
COPY --from=dependencies --chown=www:www /var/www/html/vendor ./vendor
COPY --from=dependencies --chown=www:www /var/www/html/composer.json ./composer.json
COPY --from=dependencies --chown=www:www /var/www/html/composer.lock ./composer.lock

# Copiar código fuente de la aplicación
COPY --chown=www:www public ./public
COPY --chown=www:www src ./src
COPY --chown=www:www knowledge ./knowledge

# Copiar scripts y configuraciones
COPY --chown=www:www scripts ./scripts

# Generar autoloader optimizado para producción
RUN composer dump-autoload --classmap-authoritative --no-dev

# Crear directorios necesarios con permisos correctos
RUN mkdir -p storage/logs logs data && \
    chown -R www:www storage logs data && \
    chmod -R 755 storage logs data

# Configurar PHP-FPM para producción
RUN { \
    echo '[www]'; \
    echo 'user = www'; \
    echo 'group = www'; \
    echo 'listen = 9000'; \
    echo 'listen.owner = www'; \
    echo 'listen.group = www'; \
    echo 'listen.mode = 0660'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 10'; \
    echo 'pm.start_servers = 3'; \
    echo 'pm.min_spare_servers = 2'; \
    echo 'pm.max_spare_servers = 4'; \
    echo 'pm.max_requests = 500'; \
    echo 'pm.status_path = /status'; \
    echo 'ping.path = /ping'; \
    echo 'ping.response = pong'; \
    echo 'security.limit_extensions = .php'; \
    echo 'php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source'; \
    echo 'php_admin_flag[expose_php] = off'; \
    echo 'access.log = /var/www/html/logs/fpm-access.log'; \
    echo 'slowlog = /var/www/html/logs/fpm-slow.log'; \
    echo 'request_slowlog_timeout = 10s'; \
    echo 'catch_workers_output = yes'; \
    echo 'decorate_workers_output = no'; \
} > /usr/local/etc/php-fpm.d/www.conf

# Copiar y configurar healthcheck
COPY --chown=www:www scripts/healthcheck.php /usr/local/bin/healthcheck.php
RUN chmod +x /usr/local/bin/healthcheck.php

# Cambiar a usuario no-root
USER www

# Configurar healthcheck robusto
HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD php /usr/local/bin/healthcheck.php || exit 1

# Exponer puerto
EXPOSE 9000

# Comando por defecto
CMD ["php-fpm"]

# =============================================================================
# ETAPA DEV: Para desarrollo y testing (opcional)
# =============================================================================
FROM production AS development

USER root

# Instalar herramientas de desarrollo
RUN apk add --no-cache git

# Copiar dependencias de desarrollo
COPY --from=dependencies-dev --chown=www:www /var/www/html/vendor ./vendor

# Copiar archivos de testing
COPY --chown=www:www tests ./tests
COPY --chown=www:www phpunit.xml ./phpunit.xml

# Generar autoloader con dependencias de dev
RUN composer dump-autoload --optimize

# Configurar PHP para desarrollo
RUN { \
    echo 'display_errors = On'; \
    echo 'error_reporting = E_ALL'; \
    echo 'xdebug.mode = debug'; \
    echo 'xdebug.start_with_request = yes'; \
} > /usr/local/etc/php/conf.d/99-development.ini

USER www
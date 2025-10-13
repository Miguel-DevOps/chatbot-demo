# Multi-stage Dockerfile for Production
# Stage 1: Dependencies installation
FROM php:8.2-cli-alpine AS dependencies

# Install system dependencies needed for composer install
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    zip \
    unzip

# Install PHP extensions required for dependencies
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install dependencies (no-dev for production, no scripts yet, no autoloader)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy source code
COPY . .

# Generate optimized autoloader (this creates the complete vendor directory)
RUN composer dump-autoload --optimize

# Stage 2: Production runtime
FROM php:8.2-fpm-alpine AS production

# Install only runtime dependencies (no build tools)
RUN apk add --no-cache \
    libpng \
    oniguruma \
    libxml2 \
    freetype \
    libjpeg-turbo \
    libzip \
    curl

# Install PHP extensions (runtime build)
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

# Create non-root user
RUN addgroup -g 1000 www && \
    adduser -u 1000 -G www -s /bin/sh -D www

# Set working directory
WORKDIR /var/www/html

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

# Copy ONLY the built artifacts from dependencies stage (NO composer install here)
COPY --from=dependencies --chown=www:www /var/www/html/vendor ./vendor
COPY --from=dependencies --chown=www:www /var/www/html/public ./public
COPY --from=dependencies --chown=www:www /var/www/html/src ./src
COPY --from=dependencies --chown=www:www /var/www/html/knowledge ./knowledge
COPY --from=dependencies --chown=www:www /var/www/html/composer.json ./composer.json
COPY --from=dependencies --chown=www:www /var/www/html/composer.lock ./composer.lock

# Create required directories and set permissions
RUN mkdir -p /var/www/html/storage/logs && \
    mkdir -p /var/www/html/logs && \
    chown -R www:www /var/www/html/storage /var/www/html/logs && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/logs

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

# Switch to non-root user (CRITICAL SECURITY REQUIREMENT)
USER www

# Expose PHP-FPM port
EXPOSE 9000

# Functional health check that tests PHP-FPM is responding
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php /usr/local/bin/healthcheck.php || exit 1

# Start PHP-FPM
CMD ["php-fpm"]
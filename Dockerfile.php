# Smart Hive Solution - Production Docker Image
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        mysqli \
        zip \
        intl \
        opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application files
COPY . .

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Copy configuration files
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create production environment file
RUN echo "APP_ENV=production" > /var/www/html/.env \
    && echo "DB_HOST=\${DB_HOST:-localhost}" >> /var/www/html/.env \
    && echo "DB_NAME=\${DB_NAME:-smart_hive}" >> /var/www/html/.env \
    && echo "DB_USER=\${DB_USER:-root}" >> /var/www/html/.env \
    && echo "DB_PASS=\${DB_PASS:-password}" >> /var/www/html/.env

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

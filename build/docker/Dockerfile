# Multi-stage build for Smart Hive Solution
FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    mysql-client \
    curl \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY ../../ ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create storage directories
RUN mkdir -p storage/logs storage/cache storage/sessions \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# Production stage
FROM base AS production

# Copy nginx configuration
COPY build/nginx/nginx.conf /etc/nginx/nginx.conf
COPY build/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy PHP configuration
COPY build/php/php.ini /usr/local/etc/php/php.ini
COPY build/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Create startup script
COPY build/docker/start.sh /start.sh
RUN chmod +x /start.sh

# Expose ports
EXPOSE 80 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start services
CMD ["/start.sh"]

#!/bin/sh

# Start PHP-FPM in background
php-fpm -D

# Wait for PHP-FPM to start
sleep 2

# Start Nginx in foreground
nginx -g "daemon off;"

# --- Build Stage: Composer & Node ---
FROM php:8.4-fpm-alpine AS build

# Install system dependencies
RUN apk add --no-cache git unzip curl nodejs npm

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set workdir
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY ./src/composer.json ./src/composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy node files and build assets
COPY ./src/package.json ./src/package-lock.json ./
RUN npm install && npm run build

# Copy application code
COPY ./src .

# --- Production Stage ---
FROM php:8.3-fpm-alpine

# Install system dependencies (only what's needed for runtime)
RUN apk add --no-cache libpng libjpeg-turbo freetype

# Set workdir
WORKDIR /var/www/html

# Copy built vendor and assets from build stage
COPY --from=build /var/www/html/vendor ./vendor
COPY --from=build /var/www/html/public ./public
COPY --from=build /var/www/html/resources ./resources
COPY --from=build /var/www/html/bootstrap ./bootstrap
COPY --from=build /var/www/html/config ./config
COPY --from=build /var/www/html/database ./database
COPY --from=build /var/www/html/routes ./routes
COPY --from=build /var/www/html/app ./app
COPY --from=build /var/www/html/artisan ./artisan

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]

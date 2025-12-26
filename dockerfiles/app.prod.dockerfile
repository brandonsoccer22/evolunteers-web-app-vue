# Build stage: install PHP extensions, Composer deps, and front-end assets
FROM php:8.4-fpm-alpine AS build

RUN apk add --no-cache \
    git unzip curl nodejs npm \
    icu-dev zip libzip-dev libintl oniguruma-dev postgresql-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev libxml2-dev \
    imagemagick imagemagick-dev libmemcached-dev openldap-dev \
    gcc make autoconf g++ libc-dev linux-headers acl \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath gd intl ldap mbstring opcache pcntl pdo_mysql pdo_pgsql simplexml xml zip \
    && pecl install xdebug igbinary memcached imagick \
    && docker-php-ext-enable memcached xdebug igbinary imagick \
    && echo "y" | pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies
COPY src/composer.json src/composer.lock ./
RUN composer install --optimize-autoloader --no-interaction --no-progress --no-scripts

# Install JS deps and build assets
COPY src/package.json src/package-lock.json ./
RUN npm ci --no-audit --no-fund

# Copy the rest of the application and build assets
COPY src ./
RUN rm -f bootstrap/cache/*.php \
    && cp .env.example .env \
    && php artisan key:generate --ansi \
    && composer dump-autoload --optimize --no-interaction --no-scripts \
    && APP_ENV=production APP_DEBUG=false php artisan package:discover --ansi \
    && APP_ENV=production APP_DEBUG=false npm run build \
    && rm -rf node_modules \
    && rm -f .env

# Runtime stage: minimal image with compiled code + assets
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
    icu-libs libzip libintl oniguruma postgresql-libs \
    libpng libjpeg-turbo freetype libxml2 \
    imagemagick libmemcached openldap acl \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev postgresql-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev libxml2-dev \
    imagemagick-dev libmemcached-dev openldap-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath gd intl ldap mbstring opcache pcntl pdo_mysql pdo_pgsql simplexml xml zip \
    && pecl install igbinary memcached imagick \
    && docker-php-ext-enable memcached igbinary imagick \
    && echo "y" | pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY src /var/www/html
COPY --from=build /var/www/html/vendor /var/www/html/vendor
COPY --from=build /var/www/html/public/build /var/www/html/public/build

RUN rm -rf /var/www/html/bootstrap/cache/* \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown root:www-data /usr/local/bin \
    && chmod 775 /usr/local/bin

EXPOSE 9000

USER www-data

CMD ["php-fpm"]

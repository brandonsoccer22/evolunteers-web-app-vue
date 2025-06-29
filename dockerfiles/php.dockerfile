FROM php:8.4-fpm-alpine

#ENV PHPGROUP=laravel
#ENV PHPUSER=laravel

RUN adduser -g ${PHPGROUP:-laravel} -s /bin/sh -D ${PHPUSER:-laravel}

RUN sed -i "s/user = www-data/user = ${PHPUSER:-laravel}/g" /usr/local/etc/php-fpm.d/www.conf
RUN sed -i "s/group = www-data/group = ${PHPGROUP:-laravel}/g" /usr/local/etc/php-fpm.d/www.conf

RUN mkdir -p /var/www/html/public

# Install necessary packages and PHP extensions
RUN apk add --no-cache \
    icu-dev \
    zip \
    libzip-dev \
    libintl \
    oniguruma-dev \
    curl \
    gcc \
    make \
    autoconf \
    g++ \
    libc-dev \
    linux-headers \
    libpng-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libxml2-dev \
    imagemagick \
    imagemagick-dev \
    libmemcached-dev \
    openldap-dev \
    postgresql-dev \
    libmcrypt-dev \
    acl

RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    intl \
    zip \
    pcntl \
    mbstring \
    gd \
    xml \
    simplexml \
    ldap \
    bcmath \
    pcntl

RUN pecl install xdebug igbinary memcached imagick

RUN docker-php-ext-enable memcached xdebug igbinary imagick xml simplexml pdo_pgsql

RUN echo "y" | pecl install redis && \
    docker-php-ext-enable redis

# Give ownership of the /var/www/html directory to the laravel user
RUN find /var/www/html/ -type f -exec chmod 664 {} \; && \
    find /var/www/html/ -type d -exec chmod 775 {} \;  && \
    chown -R ${PHPUSER:-laravel}:${PHPUSER:-laravel} /var/www/html/  && \
    setfacl -R -m default:u:${PHPUSER:-laravel}:rwx /var/www/html/ && \
    setfacl -R -m u:${PHPUSER:-laravel}:rwx /var/www/html/

CMD ["php-fpm", "-y", "/usr/local/etc/php-fpm.conf", "-R"]

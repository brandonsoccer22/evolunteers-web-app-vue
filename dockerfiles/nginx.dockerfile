FROM nginx:stable-alpine

#ENV NGINXUSER=laravel
#ENV NGINXGROUP=laravel

RUN mkdir -p /var/www/html/public

ADD nginx/default.conf /etc/nginx/conf.d/default.conf

RUN sed -i "s/user www-data/user ${NGINXUSER:-laravel}/g" /etc/nginx/nginx.conf

RUN adduser -g ${NGINXGROUP-laravel} -s /bin/sh -D ${NGINXUSER-laravel}

FROM composer:2

ENV COMPOSERUSER=laravel
ENV COMPOSERGROUP=laravel

RUN adduser -g ${COMPOSERGROUP:-laravel} -s /bin/sh -D ${COMPOSERUSER:-laravel}

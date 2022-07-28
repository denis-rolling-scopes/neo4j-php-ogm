FROM composer:2.3.10 as composerDocker

FROM php:8.1.8-cli-buster

WORKDIR /application

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install sockets \
    && docker-php-ext-install bcmath \
    && docker-php-ext-configure zip --with-zip \
    && docker-php-ext-install zip

# Install XDebug
USER root
RUN pecl install xdebug-3.1.4 \
  && docker-php-ext-enable xdebug

ADD ./docker/xdebug/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=composerDocker /usr/bin/composer /usr/bin/composer

COPY . /application

RUN composer install --prefer-dist

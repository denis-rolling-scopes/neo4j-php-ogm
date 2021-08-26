FROM composer:2.0 as composerDocker

FROM php:8.0.9-cli-buster

WORKDIR /application

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install sockets \
    && pecl install xdebug-3.0.1 \
    && docker-php-ext-install bcmath \
    && docker-php-ext-configure zip --with-zip \
    && docker-php-ext-install zip \
    && docker-php-ext-enable xdebug

COPY --from=composerDocker /usr/bin/composer /usr/bin/composer

COPY . /application

ARG COMPOSER_AUTH

RUN composer install --prefer-dist

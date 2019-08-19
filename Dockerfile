FROM php:7.2-fpm

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
        git \
        libzip-dev \
        zip \
    && docker-php-ext-configure zip --with-libzip \
    && docker-php-ext-install \
        zip \
        mysqli \
        pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && composer global require hirak/prestissimo --no-progress --no-suggest --no-interaction
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

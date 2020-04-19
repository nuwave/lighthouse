FROM php:7.4-cli

WORKDIR /workdir

RUN apt-get update && apt-get install -y \
        git \
        libzip-dev \
        zip \
    && docker-php-ext-install \
        zip \
        mysqli \
        pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && composer global require hirak/prestissimo --no-progress --no-suggest --no-interaction

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN echo 'memory_limit=-1' > /usr/local/etc/php/conf.d/lighthouse.ini

RUN echo "alias phpunit='vendor/bin/phpunit'" >> ~/.bashrc

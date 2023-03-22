FROM php:8.1-cli

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && \
    apt-get install --yes \
        git \
        rsync \
        libzip-dev \
        zip \
    && docker-php-ext-install \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN composer create-project --no-progress laravel/laravel /app
RUN composer require --no-progress nuwave/lighthouse:dev-master
COPY . /app/vendor/nuwave/lighthouse
RUN php artisan vendor:publish --tag=lighthouse-schema

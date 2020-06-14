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

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && composer global require hirak/prestissimo --no-progress --no-suggest --no-interaction

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN echo 'memory_limit=-1' > /usr/local/etc/php/conf.d/lighthouse.ini

RUN echo "alias phpunit='vendor/bin/phpunit'" >> ~/.bashrc

ARG USER
ARG USER_ID
ARG GROUP_ID

RUN if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    groupadd -g ${GROUP_ID} ${USER} &&\
    useradd -l -u ${USER_ID} -g ${USER} ${USER} &&\
    install -d -m 0755 -o ${USER} -g ${USER} /home/${USER} &&\
    chown --changes --silent --no-dereference --recursive ${USER_ID}:${GROUP_ID} /home/${USER} \
;fi

USER ${USER}

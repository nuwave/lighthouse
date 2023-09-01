FROM php:8.2-cli

WORKDIR /workdir

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && \
    apt-get install --yes \
        git \
        libzip-dev \
        zip \
        libicu-dev \
    && docker-php-ext-install \
        zip \
        mysqli \
        pdo_mysql \
        intl \
    && rm -rf /var/lib/apt/lists/* \
    && pecl install \
        xdebug \
        redis \
    && docker-php-ext-enable \
        xdebug \
        redis \
    && echo 'memory_limit=-1' > /usr/local/etc/php/conf.d/lighthouse.ini

ARG USER
ARG USER_ID
ARG GROUP_ID

RUN if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    groupadd --force --gid ${GROUP_ID} ${USER} &&\
    useradd --no-log-init  --create-home --uid ${USER_ID} --gid ${GROUP_ID} ${USER} \
;fi

USER ${USER}

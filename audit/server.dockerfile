FROM php:8.1-cli

WORKDIR /workdir

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

ARG USER_ID
ARG GROUP_ID
RUN if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    groupadd --force --gid ${GROUP_ID} lighthouse &&\
    useradd --no-log-init  --create-home --uid ${USER_ID} --gid ${GROUP_ID} lighthouse \
;fi
USER lighthouse

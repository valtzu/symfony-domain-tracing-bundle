FROM dunglas/frankenphp:php8.5

RUN install-php-extensions \
        apcu \
        intl \
        opcache \
        zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:8080 \
    APP_ENV=dev

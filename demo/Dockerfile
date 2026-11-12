FROM dunglas/frankenphp:php8.5 AS frankenphp_base

RUN install-php-extensions \
        apcu \
        intl \
        opcache \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY frankenphp/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
COPY frankenphp/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev

RUN install-php-extensions xdebug \
    && apt-get update \
    && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/* \
    && rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo 'zend_extension=xdebug.so' > /usr/local/etc/php/conf.d/xdebug.ini

COPY frankenphp/conf.d/xdebug.dev.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY frankenphp/xdebug-prepend.php /usr/local/etc/php/xdebug-prepend.php

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch"]

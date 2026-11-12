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

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch"]

FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader

COPY . /app

RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader \
    && php bin/console cache:warmup --env=prod

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

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

RUN arch="$(uname -m | sed 's/aarch64/arm64/')" \
    && php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" \
    && curl -fsSL -o "$(php-config --extension-dir)/php_debugger.so" \
        "https://github.com/php-debugger/php-debugger/releases/latest/download/php-debugger-php${php_version}-ts-linux-${arch}.so" \
    && echo 'zend_extension=php_debugger.so' > /usr/local/etc/php/conf.d/php-debugger.ini

COPY frankenphp/conf.d/php-debugger.dev.ini /usr/local/etc/php/conf.d/zz-php-debugger.ini

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch"]

FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader

COPY . /app

RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader \
    && php bin/console cache:warmup --env=prod

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

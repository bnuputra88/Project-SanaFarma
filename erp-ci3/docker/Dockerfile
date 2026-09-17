FROM php:7.4-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev $PHPIZE_DEPS \
 && docker-php-ext-install pdo_mysql mysqli mbstring intl zip opcache \
 && rm -rf /var/cache/apk/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/erp.ini
RUN mkdir -p application/logs application/cache storage/uploads && chown -R www-data:www-data application/logs application/cache storage
USER www-data


# composer
FROM composer:2 AS vendor

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader



# shared runtime base
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        nginx \
        supervisor \
        libpng-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        opcache \
        fileinfo \
        curl \
        gd \
        bcmath \
    && rm -rf /var/cache/apk/*

COPY docker/nginx/default.conf  /etc/nginx/http.d/default.conf
COPY docker/php/app.ini         $PHP_INI_DIR/conf.d/10-app.ini
COPY docker/supervisord.conf    /etc/supervisord.conf

WORKDIR /var/www/html



# dev
FROM base AS dev

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/* /tmp/pear

COPY docker/php/xdebug.ini $PHP_INI_DIR/conf.d/20-xdebug.ini
COPY --from=vendor /usr/bin/composer /usr/bin/composer

RUN mkdir -p public var && chown -R www-data:www-data .

EXPOSE 80
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]



# 4: prod
FROM base AS prod

COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/20-opcache.ini
COPY --from=vendor /build/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data var \
    && chmod -R 755 Public

EXPOSE 80
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]

# composer - production vendor install
# use php 8.4 (not the composer image, which ships php 8.5) so that platform requirement checks run against the same version as the runtime stage.
FROM php:8.4-alpine AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install bcmath gd zip

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
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    curl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        opcache \
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

RUN mkdir -p Public var && chown -R www-data:www-data .

EXPOSE 80
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]



# prod
FROM base AS prod

COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/20-opcache.ini
COPY --from=vendor /build/vendor ./vendor
COPY . .

RUN mkdir -p var \
    && chown -R www-data:www-data var \
    && chmod -R 755 Public

EXPOSE 80
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]

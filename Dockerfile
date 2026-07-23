
# ==================================================
# composer dependencies
# ==================================================
FROM php:8.4-alpine AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
    && docker-php-ext-configure \
        gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        gd \
        zip

WORKDIR /build

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader


# ==================================================
# shared runtime base
# ==================================================
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        curl-dev \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        nginx \
        oniguruma-dev \
        supervisor \
    && docker-php-ext-configure \
        gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        curl \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        zip

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

COPY docker/php/app.ini ${PHP_INI_DIR}/conf.d/10-app.ini

COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html


# ==================================================
# development
# ==================================================
FROM base AS dev

RUN apk add --no-cache \
        ${PHPIZE_DEPS} \
        linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del \
        ${PHPIZE_DEPS} \
        linux-headers \
    && rm -rf \
        /tmp/pear

COPY docker/php/xdebug.ini \
    ${PHP_INI_DIR}/conf.d/20-xdebug.ini

COPY --from=vendor /usr/bin/composer \
    /usr/bin/composer

RUN mkdir -p \
        Public \
        var \
        /var/auxilium/formdata \
        /var/auxilium/auxlfs \
    && chown -R www-data:www-data \
        /var/www/html \
        /var/auxilium

EXPOSE 80

CMD [
    "/usr/bin/supervisord",
    "-n",
    "-c",
    "/etc/supervisord.conf"
]


# ==================================================
# production
# ==================================================
FROM base AS prod

COPY docker/php/opcache.ini \
    ${PHP_INI_DIR}/conf.d/20-opcache.ini

COPY --from=vendor /build/vendor ./vendor

COPY . .

RUN mkdir -p \
        var \
        /var/auxilium/formdata \
        /var/auxilium/auxlfs \
    && chown -R www-data:www-data \
        /var/www/html/var \
        /var/auxilium \
    && find Public -type d -exec chmod 755 {} \; \
    && find Public -type f -exec chmod 644 {} \;

EXPOSE 80

CMD [
    "/usr/bin/supervisord",
    "-n",
    "-c",
    "/etc/supervisord.conf"
]

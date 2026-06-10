FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    postgresql-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pcntl sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
ENV COMPOSER_POLICY_ADVISORIES_BLOCK=false
RUN composer install --no-interaction --no-dev --optimize-autoloader --ignore-platform-req=php --no-scripts

COPY . .

RUN composer run-script post-autoload-dump && \
    mkdir -p storage/app storage/framework/cache/data \
    storage/framework/sessions storage/framework/testing \
    storage/framework/views storage/logs \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 8000

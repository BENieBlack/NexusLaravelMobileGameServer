FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip pdo pdo_mysql

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

RUN groupadd -g 1000 sail && useradd -m -u 1000 -g sail sail

USER sail

WORKDIR /var/www

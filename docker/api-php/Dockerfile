FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip pdo pdo_mysql

RUN pecl install redis && docker-php-ext-enable redis

# カバレッジ計測用（xdebugと違い常時有効でも実行速度への影響が小さい）
# pcov.directory はカレントディレクトリ配下を自動検出するため、
# api と packages の両方を含む /var/www を明示する
# カバレッジ生成時に128Mでは足りないためCLIのmemory_limitも上げる
RUN pecl install pcov && docker-php-ext-enable pcov \
    && printf 'pcov.directory=/var/www\nmemory_limit=512M\n' > /usr/local/etc/php/conf.d/pcov.ini

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN groupadd -g 1000 sail && useradd -m -u 1000 -g sail sail

USER sail

WORKDIR /var/www

FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev tesseract-ocr tesseract-ocr-por \
    && docker-php-ext-install pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader

CMD ["php-fpm"]

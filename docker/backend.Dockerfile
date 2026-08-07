FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-install pdo_mysql bcmath pcntl posix
RUN pecl install redis && docker-php-ext-enable redis
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EXPOSE 8000

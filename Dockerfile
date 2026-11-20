FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli && \
    a2enmod rewrite

# Set UTF-8 and Thailand Timezone
RUN echo "date.timezone = Asia/Bangkok" > /usr/local/etc/php/conf.d/timezone.ini && \
    echo "default_charset = UTF-8" >> /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /var/www/html

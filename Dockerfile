FROM php:8.4-fpm
RUN apt-get update && apt-get install -y git unzip libzip-dev libicu-dev default-mysql-client nginx supervisor && docker-php-ext-install pdo_mysql intl zip opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction || true && chown -R www-data:www-data storage bootstrap/cache
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord","-n"]

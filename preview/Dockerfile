FROM php:8.2-apache
RUN docker-php-ext-install mysqli && a2enmod rewrite headers
COPY apache.conf /etc/apache2/conf-available/woodex-preview.conf
RUN a2enconf woodex-preview
COPY php.ini /usr/local/etc/php/conf.d/woodex-preview.ini

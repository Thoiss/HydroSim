FROM php:8.3-apache
RUN docker-php-ext-install mysqli
# php.ini copied from the school server (Debian apache2 php.ini): display_errors=Off, output_buffering=4096
COPY website-deploy/php.ini /usr/local/etc/php/php.ini
COPY website/ /var/www/html/

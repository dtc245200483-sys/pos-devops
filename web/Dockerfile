FROM php:8.2-apache

# Install PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Security Hardening Configurations
COPY ./security.ini /usr/local/etc/php/conf.d/security.ini
COPY ./security-apache.conf /etc/apache2/conf-available/security.conf

COPY ./src /var/www/html/

WORKDIR /var/www/html

# Prepare permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

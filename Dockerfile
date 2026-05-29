FROM php:8.2-apache

# Habilita mod_rewrite para .htaccess
RUN a2enmod rewrite deflate expires

# Permite .htaccess sobrescrever configs
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/

# Garante que a pasta data existe e é gravável
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod 755 /var/www/html/data

EXPOSE 80

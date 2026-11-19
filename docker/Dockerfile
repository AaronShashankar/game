# Local development image: PHP 8.3 + Apache, web root = public/.
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' \
      /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf

COPY docker/entrypoint.sh /usr/local/bin/mazequest-entrypoint
RUN chmod +x /usr/local/bin/mazequest-entrypoint

ENTRYPOINT ["mazequest-entrypoint"]
CMD ["apache2-foreground"]

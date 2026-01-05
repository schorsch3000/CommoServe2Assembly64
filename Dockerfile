FROM php:8.4-apache

run a2enmod rewrite
ADD index.php /var/www/html/
ADD .htaccess /var/www/html/
EXPOSE 80
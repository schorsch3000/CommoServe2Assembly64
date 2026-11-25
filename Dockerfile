FROM php:8.4-apache

run a2enmod rewrite
ADD index.php /var/www/html/
ADD .htaccess /var/www/html/

ENV COMMO2ASSEMBLY_LOG_PATH=/var/log/commo2assembly/
RUN mkdir -p $COMMO2ASSEMBLY_LOG_PATH \
    && chown www-data:www-data $COMMO2ASSEMBLY_LOG_PATH
VOLUME /var/log/commo2assembly

EXPOSE 80
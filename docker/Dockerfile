# Image locale de test — NON utilisée en production (OVH = PHP mutualisé)
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install pdo_mysql mysqli gd \
 && a2enmod rewrite headers expires \
 && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && rm -rf /var/lib/apt/lists/*

RUN { echo 'display_errors=On'; echo 'error_reporting=E_ALL'; echo 'date.timezone=Europe/Brussels'; \
      echo 'upload_max_filesize=20M'; echo 'post_max_size=25M'; } > /usr/local/etc/php/conf.d/local.ini

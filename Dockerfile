FROM php:8.2-apache

# 1. Instalar librerías del sistema y los drivers PDO para MySQL
RUN apt-get update && apt-get install -y git unzip libzip-dev && docker-php-ext-install zip pdo pdo_mysql

# 2. Habilitar el módulo de reescritura de Apache
RUN a2enmod rewrite

# 3. Configurar Apache para que apunte a la carpeta pública de ejecución
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Instalar Composer de forma oficial e instalar dependencias (Twig) limpiamente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/html/
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 5. Dar permisos correctos para que Apache lea y Twig escriba su caché
RUN chown -R www-data:www-data /var/www/html/
EXPOSE 80

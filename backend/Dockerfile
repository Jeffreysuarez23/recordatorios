FROM php:8.2-apache

# Instalar dependencias del sistema y drivers de PostgreSQL
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones de PHP requeridas por Laravel y Supabase
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Activar mod_rewrite de Apache para las rutas de Laravel
RUN a2enmod rewrite

# Apuntar Apache a la carpeta public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar el código del proyecto (desde backend)
COPY . .

# Instalar las dependencias de Laravel
RUN composer install --no-dev --optimize-autoloader

# Dar permisos a Apache para escribir en carpetas necesarias
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

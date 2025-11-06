FROM php:8.3-fpm

# Cài các extension cần thiết (thêm libzip-dev và zip)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Copy code
COPY . /var/www/html
WORKDIR /var/www/html

# Cài Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Cài dependencies
RUN composer install --optimize-autoloader --no-dev

# Quyền
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

CMD ["php-fpm"]
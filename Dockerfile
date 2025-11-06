FROM php:8.3-fpm

# Cài các extension cần thiết
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

# Không copy code nữa (Render mount tự động)
# COPY . /var/www/html
WORKDIR /var/www/html

# Cài Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Quyền
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Chạy PHP built-in server thay vì FPM
EXPOSE 10000
CMD ["sh", "-c", "composer install --no-dev --optimize-autoloader --no-interaction || true && php -S 0.0.0.0:10000 -t public"]
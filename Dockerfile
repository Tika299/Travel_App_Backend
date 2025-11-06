FROM php:8.3-fpm

# Cài extension
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

# Cài Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Thư mục làm việc
WORKDIR /var/www/html

# Không COPY code (Render mount tự động)

# Quyền (nếu cần)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# Expose port cho Render
EXPOSE 10000

# Chạy composer + PHP server khi start
CMD ["sh", "-c", "composer install --no-dev --optimize-autoloader --no-interaction && php -S 0.0.0.0:10000 -t public"]
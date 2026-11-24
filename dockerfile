# Stage 1: Build dependencies & Vendor
FROM php:8.4-cli AS vendor
WORKDIR /app

# Install system dependencies & extensions required for Composer & GD
RUN apt-get update && apt-get install -y \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Install Composer manually in PHP image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
# Tambahkan --no-scripts di sini supaya tidak mencari file artisan saat vendor di-build
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Stage 2: Development / Test & Lint Stage (Digunakan untuk Jenkins Test & Lint)
FROM php:8.4-cli AS test
WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN composer install --no-interaction --prefer-dist

# Stage 3: Production Image (Apache + PHP)
FROM php:8.4-apache AS production
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
    && printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
    && printf "<Directory /var/www/html/public>\n\tOptions -Indexes +FollowSymLinks\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n" > /etc/apache2/conf-available/laravel-public.conf \
    && a2enmod rewrite \
    && a2enconf servername laravel-public

COPY --from=vendor /app/vendor /var/www/html/vendor
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Entrypoint: migrasi + seeder otomatis sebelum Apache berjalan
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]

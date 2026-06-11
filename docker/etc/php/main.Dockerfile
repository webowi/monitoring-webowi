FROM php:8.4-fpm

# libxml2-dev is required for soap extension
# soap is for gusApi

# Install dependencies
RUN apt-get -q update && apt-get -qy install \
    zip \
    cron \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libjpeg-dev \
    libtiff-dev \
    libwebp-dev \
    libgif-dev \
    git \
    libxml2-dev \
    libexif-dev \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && docker-php-ext-install intl opcache pdo pdo_mysql exif soap \
    && docker-php-ext-configure gd --with-webp --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash

# Copy application files
COPY . /var/www/html

# Copy configuration files
COPY .php-cs-fixer.php /var/www/html/.php-cs-fixer.php
COPY phpstan.neon /var/www/html/phpstan.neon

# Initialize grumphp
RUN ./vendor/bin/grumphp git:init || true

RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory_limit.ini \
    && echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/upload_max_filesize.ini \
    && echo "post_max_size = 20M" > /usr/local/etc/php/conf.d/post_max_size.ini

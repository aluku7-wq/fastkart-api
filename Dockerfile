# Use an official PHP runtime as a parent image
FROM php:8.1-fpm

# Set the working directory to /var/www
WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libexif-dev \
    libgd-dev   # Added libgd-dev for gd extension

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring xml zip exif gd   # Added gd extension

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy the .env file
COPY .env /var/www/

# Copy composer files
COPY composer.json /var/www/


# Install dependencies
RUN composer install --no-scripts --no-autoloader

# Copy the rest of the application code
COPY . /var/www/

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]

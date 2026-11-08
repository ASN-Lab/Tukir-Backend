FROM php:8.3-cli-alpine

# Install dependensi sistem
RUN apk add --no-cache \
    git \
    unzip \
    libpng-dev \
    libzip-dev \
    zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy seluruh source code
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 8080

# Jalankan server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]

FROM php:8.3-cli
WORKDIR /app
RUN apt-get update && apt-get install -y unzip git libzip-dev
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-scripts
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]

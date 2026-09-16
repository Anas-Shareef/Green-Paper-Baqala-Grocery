FROM php:8.3-cli-alpine

# Install dependencies and extensions
RUN apk add --no-cache \
    sqlite-dev \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    nodejs \
    npm

RUN docker-php-ext-install pdo pdo_sqlite zip intl opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
RUN npm ci && npm run build

# Prepare SQLite database
RUN mkdir -p database && touch database/database.sqlite
RUN chown -R www-data:www-data storage database

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=8000"]

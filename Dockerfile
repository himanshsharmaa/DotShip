FROM php:8.2-cli

WORKDIR /var/www/html

# Install system dependencies and PHP extensions needed by the app
RUN apt-get update \
  && apt-get install -y --no-install-recommends git unzip zip libsqlite3-dev \
  && docker-php-ext-install pdo_sqlite sqlite3 \
  && rm -rf /var/lib/apt/lists/*

# Install composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer file first to leverage Docker cache, then install dependencies
COPY composer.json /var/www/html/
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Copy application
COPY . /var/www/html/

# Ensure writable storage for SQLite and logs
RUN mkdir -p /var/www/html/storage \
  && chown -R www-data:www-data /var/www/html/storage || true

EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]

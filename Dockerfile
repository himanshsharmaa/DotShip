FROM php:8.2-apache

# Install system dependencies
RUN apt-get update \
  && apt-get install -y --no-install-recommends git unzip zip libzip-dev libcurl4-openssl-dev libssl-dev \
  && docker-php-ext-install zip \
  && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite
RUN a2enmod rewrite

# Ensure Apache uses the prefork MPM (required by mod_php) and disable other MPMs.
# Remove any mpm_*.load/.conf links from mods-enabled then enable prefork.
RUN set -eux; \
  for f in /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf; do \
    if [ -e "$f" ]; then rm -f "$f"; fi; \
  done; \
  a2enmod mpm_prefork || true

# Diagnostic step: show enabled/available modules and apache test output (temporary)
RUN echo "--- DIAGNOSTIC: /etc/apache2/mods-enabled ---" \
  && ls -la /etc/apache2/mods-enabled || true \
  && echo "--- DIAGNOSTIC: /etc/apache2/mods-available ---" \
  && ls -la /etc/apache2/mods-available || true \
  && echo "--- DIAGNOSTIC: apachectl -M ---" \
  && apachectl -M 2>&1 || true \
  && echo "--- DIAGNOSTIC: apachectl -t ---" \
  && apachectl -t 2>&1 || true

# Install composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first to leverage Docker cache, then install dependencies
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --no-interaction --optimize-autoloader --ignore-platform-req=ext-mongodb

# Copy application
COPY . /var/www/html/

# Ensure storage is writable
RUN chown -R www-data:www-data /var/www/html/storage || true

EXPOSE 80
CMD ["apache2-foreground"]

# ============================================================================
# INSOPHINIA POS v7 — Dockerfile
# ----------------------------------------------------------------------------
# PHP 8.2 + Apache + all required extensions + Composer.
# Works with both `docker` and `podman`. Multi-stage not needed — image is
# ~250 MB which is fine for a self-contained POS deployment.
# ============================================================================

FROM php:8.2-apache

# ---- 1. System dependencies + PHP extensions ----
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libicu-dev \
        libxml2-dev \
        unzip \
        git \
        mariadb-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        mbstring \
        zip \
        gd \
        intl \
        opcache \
        bcmath \
        sockets \
    && pecl install redis && docker-php-ext-enable redis || true

# ---- 2. Enable Apache mod_rewrite + set document root to /public ----
RUN a2enmod rewrite headers expires deflate

# Point Apache docroot at /var/www/html/public (where index.php + .htaccess live)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
    && echo '<Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes +FollowSymLinks\n\
    </Directory>' > /etc/apache2/conf-available/insophinia.conf \
    && a2enconf insophinia

# ---- 3. Install Composer ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_NO_INTERACTION=1

# ---- 4. Copy app source ----
WORKDIR /var/www/html
COPY . .

# ---- 5. Install PHP dependencies (production) ----
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    || echo "Composer install failed — will retry at runtime if needed"

# ---- 6. Custom php.ini (production-tuned) ----
RUN echo "[PHP]" > /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "upload_max_filesize = 16M" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "post_max_size = 32M" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "max_execution_time = 60" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "max_input_time = 60" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "error_log = /var/www/html/storage/logs/php-error.log" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "date.timezone = Asia/Colombo" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "session.cookie_samesite = Strict" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "session.use_strict_mode = 1" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "opcache.memory_consumption = 128" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "opcache.max_accelerated_files = 10000" >> /usr/local/etc/php/conf.d/insophinia.ini \
    && echo "opcache.validate_timestamps = 0" >> /usr/local/etc/php/conf.d/insophinia.ini

# ---- 7. Ensure storage directories exist & are writable by www-data ----
RUN mkdir -p storage/temp_pdfs storage/logs storage/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage

# ---- 8. Copy docker entrypoint ----
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# ---- 9. Expose port 80 ----
EXPOSE 80

# ---- 10. Healthcheck ----
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://localhost/ || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

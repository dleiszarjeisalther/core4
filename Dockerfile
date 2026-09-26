# syntax=docker/dockerfile:1

# Reusable PHP/Apache layer. Keep this before the application COPY so normal
# source changes do not rebuild native PHP extensions on every deployment.
FROM php:8.2-apache AS php-base

# Install only the native extensions used by Core Transaction 4.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libcurl4-openssl-dev \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        curl \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV PORT=8080

# Let the hosting platform choose the runtime port while retaining .htaccess
# routing and the existing protection for server-only application paths.
RUN sed -ri 's!Listen 80!Listen ${PORT}!' /etc/apache2/ports.conf \
    && { \
        echo '<VirtualHost *:${PORT}>'; \
        echo '    ServerName core4.test'; \
        echo '    DocumentRoot /var/www/html'; \
        echo '    <Directory /var/www/html>'; \
        echo '        AllowOverride All'; \
        echo '        Require all granted'; \
        echo '        Options -Indexes +FollowSymLinks'; \
        echo '    </Directory>'; \
        echo '    <LocationMatch "^/(\.env|\.git|database|includes)">'; \
        echo '        Require all denied'; \
        echo '    </LocationMatch>'; \
        echo '    ErrorLog /dev/stderr'; \
        echo '    CustomLog /dev/stdout combined'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini \
    && { \
        echo 'expose_php=Off'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/var/log/php_errors.log'; \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=60'; \
        echo 'session.cookie_httponly=1'; \
        echo 'session.cookie_samesite=Lax'; \
        echo 'session.use_strict_mode=1'; \
    } > /usr/local/etc/php/conf.d/app-production.ini

FROM php-base AS runtime

WORKDIR /var/www/html

COPY . ./

RUN mkdir -p storage/logs storage/exports storage/reports \
    && chown -R www-data:www-data storage \
    && chmod -R ug+rwX storage

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent "http://127.0.0.1:${PORT}/" > /dev/null || exit 1

CMD ["apache2-foreground"]

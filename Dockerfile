# =============================================================
# GREAT SOLOMON MANPOWER SERVICES INC. — CORE TRANSACTION 4
# Dockerfile — Production image for HostForge deployment.
#
# Domain : core4.test (custom domain configured in HostForge)
# Stack  : PHP 8.3 + Apache (mod_rewrite enabled)
# Auth   : Authoritative Dockerfile route — wizard commands blank.
# =============================================================

FROM php:8.3-apache

# ---------------------------------------------------------------------------
# System packages & PHP extensions
# ---------------------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        gd \
        zip \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# PHP runtime configuration — production hardened
# ---------------------------------------------------------------------------
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.fast_shutdown=1'; \
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

# ---------------------------------------------------------------------------
# Apache: enable mod_rewrite and configure virtual host
# HostForge edge proxy passes traffic on $PORT (default 80 inside container).
# ---------------------------------------------------------------------------
RUN a2enmod rewrite headers

# Virtual host: serve from /var/www/html (project root), .htaccess controls routing.
RUN { \
        echo '<VirtualHost *:80>'; \
        echo '    ServerName core4.test'; \
        echo '    DocumentRoot /var/www/html'; \
        echo '    <Directory /var/www/html>'; \
        echo '        AllowOverride All'; \
        echo '        Require all granted'; \
        echo '        Options -Indexes +FollowSymLinks'; \
        echo '    </Directory>'; \
        echo '    # Security: block direct access to sensitive paths'; \
        echo '    <LocationMatch "^/(\.env|\.git|database|includes)">'; \
        echo '        Require all denied'; \
        echo '    </LocationMatch>'; \
        echo '    ErrorLog /dev/stderr'; \
        echo '    CustomLog /dev/stdout combined'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

# ---------------------------------------------------------------------------
# Copy application source
# ---------------------------------------------------------------------------
WORKDIR /var/www/html

COPY . .

# Ensure storage directories exist and are writable (ephemeral container safety)
RUN mkdir -p storage/logs storage/exports storage/reports \
    && chown -R www-data:www-data storage \
    && chmod -R 755 storage

# Remove local .env so the container uses real environment variables only
# (secrets come from HostForge environment variable injection)
RUN rm -f .env

# ---------------------------------------------------------------------------
# Expose & start
# HostForge routes external HTTPS traffic → container port 80.
# The $PORT env var is honoured for platform flexibility.
# ---------------------------------------------------------------------------
EXPOSE 80

CMD ["apache2-foreground"]

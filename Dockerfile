FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create Laravel project if it doesn't exist
RUN if [ ! -d /var/www/html/app ]; then \
    composer create-project --prefer-dist laravel/laravel . ; \
    fi

COPY . /var/www/html/
# COPY ./storage/cert/DigiCertGlobalRootCA.crt.pem /var/www/html/storage/cert/DigiCertGlobalRootCA.crt.pem

# Create storage directory if it doesn't exist
RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views /var/www/html/storage/framework/cache \
    /var/www/html/storage/app/public

# Update .env with remote database settings
RUN sed -i 's/DB_HOST=127.0.0.1/DB_HOST=${DB_HOST:-your_remote_db_host}/g' .env \
    && sed -i 's/DB_DATABASE=laravel/DB_DATABASE=${DB_DATABASE:-your_database_name}/g' .env \
    && sed -i 's/DB_USERNAME=root/DB_USERNAME=${DB_USERNAME:-your_database_user}/g' .env \
    && sed -i 's/DB_PASSWORD=/DB_PASSWORD=${DB_PASSWORD:-your_database_password}/g' .env

# Install dependencies
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Generate key if needed
RUN php artisan key:generate --force

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html/storage

# Configure Apache
RUN a2enmod rewrite
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

ARG DB_REMOTE_HOST
ARG DB_REMOTE_PORT
ARG DB_REMOTE_DATABASE
ARG DB_REMOTE_USERNAME
ARG DB_REMOTE_PASSWORD
ARG APP_NAME
ARG APP_ENV
ARG APP_KEY
ARG APP_DEBUG
ARG APP_TIMEZONE
ARG APP_URL
ARG APP_LOCALE
ARG APP_FALLBACK_LOCALE
ARG APP_FAKER_LOCALE
ARG APP_MAINTENANCE_DRIVER
ARG PHP_CLI_SERVER_WORKERS
ARG BCRYPT_ROUNDS
ARG LOG_CHANNEL
ARG LOG_STACK
ARG LOG_DEPRECATIONS_CHANNEL
ARG LOG_LEVEL
ARG DB_CONNECTION
ARG DB_OPTIONS
ARG SESSION_DRIVER
ARG SESSION_LIFETIME
ARG SESSION_ENCRYPT
ARG SESSION_PATH
ARG SESSION_DOMAIN
ARG BROADCAST_CONNECTION
ARG FILESYSTEM_DISK
ARG QUEUE_CONNECTION
ARG CACHE_STORE
ARG CACHE_PREFIX
ARG VITE_APP_NAME
ARG DB_CONNECTION
ARG MYSQL_ATTR_SSL_CA

ENV MYSQL_ATTR_SSL_CA=$MYSQL_ATTR_SSL_CA
ENV DB_CONNECTION=$DB_CONNECTION
ENV DB_REMOTE_HOST=$DB_REMOTE_HOST
ENV DB_REMOTE_PORT=$DB_REMOTE_PORT
ENV DB_REMOTE_DATABASE=$DB_REMOTE_DATABASE
ENV DB_REMOTE_USERNAME=$DB_REMOTE_USERNAME
ENV DB_REMOTE_PASSWORD=$DB_REMOTE_PASSWORD
ENV APP_NAME=$APP_NAME
ENV APP_ENV=$APP_ENV
ENV APP_KEY=$APP_KEY
ENV APP_DEBUG=$APP_DEBUG
ENV APP_TIMEZONE=$APP_TIMEZONE
ENV APP_TIMEZONE=$APP_TIMEZONE
ENV APP_URL=$APP_URL
ENV APP_LOCALE=$APP_LOCALE
ENV APP_FALLBACK_LOCALE=$APP_FALLBACK_LOCALE
ENV APP_FAKER_LOCALE=$APP_FAKER_LOCALE
ENV APP_MAINTENANCE_DRIVER=$APP_MAINTENANCE_DRIVER
ENV PHP_CLI_SERVER_WORKERS=$PHP_CLI_SERVER_WORKERS
ENV BCRYPT_ROUNDS=$BCRYPT_ROUNDS
ENV LOG_CHANNEL=$LOG_CHANNEL
ENV LOG_STACK=$LOG_STACK
ENV LOG_DEPRECATIONS_CHANNEL=$LOG_DEPRECATIONS_CHANNEL
ENV LOG_LEVEL=$LOG_LEVEL
ENV DB_CONNECTION=$DB_CONNECTION
ENV DB_OPTIONS=$DB_OPTIONS
ENV SESSION_DRIVER=$SESSION_DRIVER
ENV SESSION_LIFETIME=$SESSION_LIFETIME
ENV SESSION_ENCRYPT=$SESSION_ENCRYPT
ENV SESSION_PATH=$SESSION_PATH
ENV SESSION_DOMAIN=$SESSION_DOMAIN
ENV FILESYSTEM_DISK=$FILESYSTEM_DISK
ENV QUEUE_CONNECTION=$QUEUE_CONNECTION
ENV CACHE_STORE=$CACHE_STORE
ENV CACHE_PREFIX=$CACHE_PREFIX
ENV VITE_APP_NAME=$VITE_APP_NAME


# Expose port 80
EXPOSE 80

# Start Apache service
CMD ["apache2-foreground"]

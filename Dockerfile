FROM php:8.2-apache

# Install SQLite and Zip extensions
RUN apt-get update && apt-get install -y libsqlite3-dev libzip-dev && \
    docker-php-ext-install pdo pdo_sqlite zip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite headers

# Configure Apache to allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copy application files
COPY . .

# Create data directory for SQLite database and photos directory
RUN mkdir -p /var/www/html/data /var/www/html/photos

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/html/data /var/www/html/photos

EXPOSE 80

CMD ["apache2-foreground"]
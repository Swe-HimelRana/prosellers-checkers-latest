FROM php:8.2-apache

# Install PDO extensions
RUN docker-php-ext-install pdo pdo_mysql

# SQLite is usually built-in, but ensure it's ready if needed
RUN apt-get update && apt-get install -y libsqlite3-dev && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Disable PHP OPcache and Apache EnableSendfile for macOS volume compatibility
RUN echo "opcache.enable=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini || \
    echo "opcache.enable=0" > /usr/local/etc/php/conf.d/no-opcache.ini
RUN echo "EnableSendfile Off" >> /etc/apache2/apache2.conf

# Copy application files
COPY . /var/www/html

# Set permissions for Apache to write to the directory
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80

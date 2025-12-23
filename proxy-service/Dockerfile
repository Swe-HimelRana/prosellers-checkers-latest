FROM ubuntu:22.04

# Avoid prompts from apt
ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies
RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    libapache2-mod-php \
    php-sqlite3 \
    php-curl \
    python3 \
    python3-pip \
    sshpass \
    openssh-client \
    curl \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install python dependencies for tunnel manager
RUN pip3 install requests 

# Configure Apache
RUN a2enmod rewrite
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/

# Copy supervisor config
COPY supervisor.conf /etc/supervisord.conf

# Setup working directory for tunnel manager
WORKDIR /var/www/html

# Expose ports
# 80 for PHP API/UI
# 9900-9950 for SSH Tunnels
EXPOSE 80
EXPOSE 9900-9950

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]

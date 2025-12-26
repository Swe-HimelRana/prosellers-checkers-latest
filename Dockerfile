FROM ubuntu:22.04

# Avoid interactive prompts
ENV DEBIAN_FRONTEND=noninteractive

# Update apt and install dependencies for ALL services
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    freerdp2-x11 \
    xvfb \
    iputils-ping \
    sshpass \
    openssh-client \
    proxychains-ng \
    redis-server \
    apache2 \
    php \
    libapache2-mod-php \
    php-curl \
    php-mysql \
    supervisor \
    curl \
    && rm -rf /var/lib/apt/lists/*

# --- Checker & Worker Setup ---
WORKDIR /app
COPY requirements.txt .
# Create venv and install dependencies
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"
RUN pip install --no-cache-dir -r requirements.txt

# --- Proxy Service Setup ---
# Copy proxy service to /var/www/html/proxy-service
COPY proxy-service /var/www/html/proxy-service

# --- Logs Service Setup ---
COPY log /var/www/html/log
# Patch log/config.php to use env var or default to /data path
# Note: config.php already checks DB_FILE_PATH env var, so we don't need to patch it.

# --- Seoinfo Service Setup ---
COPY seoinfo /var/www/html/seoinfo

# --- Common Setup ---
# Copy Checker App Code
COPY . /app
# Remove copied subdirs from /app to avoid confusion (they are already in /var/www/html)
# But we need to keep requirements.txt, and app code files.
# We can mistakenly copy 'proxy-service' folder into /app/proxy-service again.
# It's better to be explicit or clean up.
RUN rm -rf /app/proxy-service /app/log /app/seoinfo /app/docker_data

# Apache Config
RUN a2enmod rewrite
COPY apache-unified.conf /etc/apache2/sites-available/000-default.conf

# Permissions for Apache
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Supervisor Config
COPY supervisord.conf /etc/supervisord.conf

# Data Directory for Persistence & GeoIP
RUN mkdir -p /data && chmod 777 /data
COPY docker_data/GeoLite2-City.mmdb /data/
COPY docker_data/GeoLite2-ASN.mmdb /data/

# Cleanup SQLite files if any found their way in
RUN find /var/www/html -name "*.sqlite" -delete && \
    find /app -name "*.sqlite" -delete

# Expose all ports
# 8888: Checker API
# 8801: Proxy Service
# 8803: Logs
# 8804: Seoinfo
# 9900-9950: SSH Tunnels
EXPOSE 8888 8801 8803 8804 9900-9950

# Environment variables
ENV REDIS_URL=redis://127.0.0.1:6379
ENV PROXY_API_URL=http://localhost:8801/api.php
ENV LOG_SERVER_URL=http://localhost:8803/api.php
# Default Secrets (Override these in production!)
ENV API_KEY=b009302d-deda-4e0d-a93a-cc1d342ea563
ENV PROXY_API_KEY=83853b46-5d66-45f2-9de6-f4c563003147
ENV LOG_ADMIN_PASSWORD=@Pass123@Pass
ENV LOG_ENCRYPTION_KEY=r8F#2Z!qA9mT@xK7D\$S5LwP^cN4YB&UeHfGJ0RkC6VQ1
ENV SEOINFO_API_KEY=my_secret_api_key_123
ENV SEOINFO_ADMIN_PASSWORD=admin123

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

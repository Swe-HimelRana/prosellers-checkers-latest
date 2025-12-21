FROM ubuntu:22.04

# Avoid interactive prompts during package installation
ENV DEBIAN_FRONTEND=noninteractive

# Update apt and install dependencies
# freerdp2-x11 provides the 'xfreerdp' tool needed for RDP checks
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    freerdp2-x11 \
    xvfb \
    iputils-ping \
    supervisor \
    php-cli \
    php-sqlite3 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Create a virtual environment to manage python packages cleanly
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY . .

# Expose the API ports (8802 for Python, 8801 for PHP Log)
EXPOSE 8802 8801

# Run supervisor to manage both processes
CMD ["/usr/bin/supervisord", "-c", "/app/supervisord.conf"]

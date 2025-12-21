# SEO Info API - Docker Deployment

## Quick Start

### Using Docker Compose (Recommended)

```bash
# Build and start the container
docker-compose up -d

# View logs
docker-compose logs -f

# Stop the container
docker-compose down
```

The application will be available at `http://localhost:8080`

### Using Docker directly

```bash
# Build the image
docker build -t seoinfo-api .

# Run the container
docker run -d -p 8080:80 --name seoinfo-api seoinfo-api

# View logs
docker logs -f seoinfo-api

# Stop the container
docker stop seoinfo-api
```

## Configuration

- Edit `config.php` to change API keys and database settings
- SQLite database will be stored in the `data/` directory
- To use MySQL, update `config.php` with your MySQL credentials

## Volumes

The docker-compose setup mounts:
- `./data` - SQLite database storage
- `./config.php` - Configuration file

## Ports

- Container exposes port 80
- Mapped to host port 8080 (configurable in docker-compose.yml)

## Notes

- Database files persist in the `data/` directory
- Change default API key and admin password in `config.php` before deployment

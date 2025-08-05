#!/bin/bash
#
# Docker-based deployment for AZE_Gemini
# Creates containerized deployment with automatic updates
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
DOCKER_IMAGE="aze-gemini"
DOCKER_TAG="latest"
CONTAINER_NAME="aze-gemini-app"
NETWORK_NAME="aze-network"
VOLUME_NAME="aze-data"

echo -e "${GREEN}🐳 Docker Deployment for AZE_Gemini${NC}"
echo "===================================="
echo

# Function to log with timestamp
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}"
}

# Function to handle errors
error_exit() {
    echo -e "${RED}❌ Error: $1${NC}" >&2
    exit 1
}

# Function to check Docker installation
check_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        error_exit "Docker is not installed. Please install Docker first."
    fi
    
    if ! docker info >/dev/null 2>&1; then
        error_exit "Docker daemon is not running or accessible."
    fi
    
    log "✓ Docker is available and running"
}

# Function to create Dockerfile
create_dockerfile() {
    log "Creating Dockerfile..."
    
    cat > Dockerfile << 'EOF'
# Multi-stage build for AZE_Gemini
FROM node:18-alpine AS builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy source code
COPY . .

# Build the application
RUN npm run build

# Production stage
FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install additional tools
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Copy built application
COPY --from=builder /app/dist/ /var/www/html/
COPY --from=builder /app/api/ /var/www/html/api/
COPY --from=builder /app/config.php /var/www/html/
COPY --from=builder /app/schema.sql /var/www/html/

# Copy Apache configuration
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 644 /var/www/html/*.php \
    && mkdir -p /var/www/html/logs /var/www/html/data \
    && chmod 755 /var/www/html/logs /var/www/html/data

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/api/health.php || exit 1

EXPOSE 80 443

CMD ["apache2-foreground"]
EOF
    
    log "✓ Dockerfile created"
}

# Function to create Docker Compose file
create_docker_compose() {
    log "Creating Docker Compose configuration..."
    
    cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  app:
    build: .
    image: aze-gemini:latest
    container_name: aze-gemini-app
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    environment:
      - PHP_ERROR_REPORTING=E_ALL & ~E_DEPRECATED & ~E_STRICT
      - PHP_DISPLAY_ERRORS=Off
      - PHP_LOG_ERRORS=On
    volumes:
      - aze-data:/var/www/html/data
      - aze-logs:/var/www/html/logs
      - ./docker/ssl:/etc/ssl/private
    networks:
      - aze-network
    depends_on:
      - db
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/api/health.php"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

  db:
    image: mysql:8.0
    container_name: aze-gemini-db
    restart: unless-stopped
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-secure_root_password}
      - MYSQL_DATABASE=${MYSQL_DATABASE:-aze_gemini}
      - MYSQL_USER=${MYSQL_USER:-aze_user}
      - MYSQL_PASSWORD=${MYSQL_PASSWORD:-secure_password}
    volumes:
      - aze-db-data:/var/lib/mysql
      - ./schema.sql:/docker-entrypoint-initdb.d/schema.sql
    networks:
      - aze-network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  nginx:
    image: nginx:alpine
    container_name: aze-gemini-nginx
    restart: unless-stopped
    ports:
      - "8080:80"
      - "4433:443"
    volumes:
      - ./docker/nginx.conf:/etc/nginx/nginx.conf
      - ./docker/ssl:/etc/ssl/private
    networks:
      - aze-network
    depends_on:
      - app

  watchtower:
    image: containrrr/watchtower
    container_name: aze-gemini-watchtower
    restart: unless-stopped
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    environment:
      - WATCHTOWER_CLEANUP=true
      - WATCHTOWER_POLL_INTERVAL=300
      - WATCHTOWER_INCLUDE_STOPPED=true
    command: aze-gemini-app

volumes:
  aze-data:
  aze-logs:
  aze-db-data:

networks:
  aze-network:
    driver: bridge
EOF
    
    log "✓ Docker Compose configuration created"
}

# Function to create Apache configuration
create_apache_config() {
    log "Creating Apache configuration..."
    
    mkdir -p docker
    
    cat > docker/apache.conf << 'EOF'
<VirtualHost *:80>
    ServerName aze.mikropartner.de
    DocumentRoot /var/www/html
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' login.microsoftonline.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self' login.microsoftonline.com graph.microsoft.com; font-src 'self'"
    
    # Directory configuration
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # PHP configuration
        php_value error_reporting "E_ALL & ~E_DEPRECATED & ~E_STRICT"
        php_value display_errors Off
        php_value log_errors On
        php_value error_log /var/www/html/logs/php-errors.log
    </Directory>
    
    # API directory
    <Directory /var/www/html/api>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
    
    # Logs
    ErrorLog /var/log/apache2/aze_error.log
    CustomLog /var/log/apache2/aze_access.log combined
    
    # Rewrite rules for SPA
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} !^/api/
    RewriteRule . /index.html [L]
</VirtualHost>

<VirtualHost *:443>
    ServerName aze.mikropartner.de
    DocumentRoot /var/www/html
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/private/cert.pem
    SSLCertificateKeyFile /etc/ssl/private/key.pem
    
    # Include same configuration as HTTP
    Include /etc/apache2/sites-available/000-default.conf
</VirtualHost>
EOF
    
    log "✓ Apache configuration created"
}

# Function to create Nginx configuration
create_nginx_config() {
    log "Creating Nginx configuration..."
    
    cat > docker/nginx.conf << 'EOF'
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';
    
    access_log /var/log/nginx/access.log main;
    
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    
    # Security headers
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;
    
    upstream aze-app {
        server app:80;
    }
    
    server {
        listen 80;
        server_name aze.mikropartner.de;
        
        # Redirect to HTTPS
        return 301 https://$server_name$request_uri;
    }
    
    server {
        listen 443 ssl http2;
        server_name aze.mikropartner.de;
        
        ssl_certificate /etc/ssl/private/cert.pem;
        ssl_certificate_key /etc/ssl/private/key.pem;
        
        # SSL configuration
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384;
        ssl_prefer_server_ciphers off;
        
        location / {
            proxy_pass http://aze-app;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            
            # Timeouts
            proxy_connect_timeout 60s;
            proxy_send_timeout 60s;
            proxy_read_timeout 60s;
        }
        
        # Health check endpoint
        location /health {
            access_log off;
            proxy_pass http://aze-app/api/health.php;
        }
    }
}
EOF
    
    log "✓ Nginx configuration created"
}

# Function to create environment file
create_env_file() {
    log "Creating environment configuration..."
    
    cat > .env << 'EOF'
# Database Configuration
MYSQL_ROOT_PASSWORD=secure_root_password_change_me
MYSQL_DATABASE=aze_gemini
MYSQL_USER=aze_user
MYSQL_PASSWORD=secure_password_change_me

# Application Configuration
APP_ENV=production
APP_DEBUG=false

# SSL Configuration (optional)
SSL_ENABLED=false
SSL_CERT_PATH=./docker/ssl/cert.pem
SSL_KEY_PATH=./docker/ssl/key.pem
EOF
    
    log "✓ Environment file created"
    echo -e "${YELLOW}⚠️ Please edit .env file and set secure passwords${NC}"
}

# Function to build Docker image
build_image() {
    log "Building Docker image..."
    
    docker build -t "$DOCKER_IMAGE:$DOCKER_TAG" . || error_exit "Docker build failed"
    
    log "✓ Docker image built successfully"
}

# Function to deploy with Docker Compose
deploy() {
    log "Deploying with Docker Compose..."
    
    # Pull latest images
    docker-compose pull
    
    # Build and start services
    docker-compose up -d --build
    
    # Wait for services to be ready
    log "Waiting for services to start..."
    sleep 30
    
    # Check health
    if docker-compose ps | grep -q "Up (healthy)"; then
        log "✅ Deployment successful and healthy"
    else
        log "⚠️ Some services may not be healthy. Check with: docker-compose ps"
    fi
}

# Function to show status
show_status() {
    log "Current deployment status:"
    docker-compose ps
    echo
    docker-compose logs --tail=20
}

# Function to backup data
backup_data() {
    local backup_dir="backups/$(date +%Y%m%d_%H%M%S)"
    
    log "Creating backup..."
    mkdir -p "$backup_dir"
    
    # Backup database
    docker-compose exec -T db mysqldump -u root -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE} > "$backup_dir/database.sql"
    
    # Backup volumes
    docker run --rm -v aze-data:/data -v "$(pwd)/$backup_dir":/backup alpine tar czf /backup/data.tar.gz -C /data .
    docker run --rm -v aze-logs:/logs -v "$(pwd)/$backup_dir":/backup alpine tar czf /backup/logs.tar.gz -C /logs .
    
    log "✓ Backup created in $backup_dir"
}

# Function to cleanup
cleanup() {
    log "Cleaning up Docker resources..."
    
    docker-compose down -v
    docker system prune -f
    
    log "✓ Cleanup completed"
}

# Main function
main() {
    local action="${1:-deploy}"
    
    check_docker
    
    case "$action" in
        "init")
            log "Initializing Docker deployment setup..."
            create_dockerfile
            create_docker_compose
            create_apache_config
            create_nginx_config
            create_env_file
            log "✅ Docker deployment initialized. Edit .env file and run: $0 deploy"
            ;;
        "build")
            build_image
            ;;
        "deploy")
            deploy
            ;;
        "status")
            show_status
            ;;
        "logs")
            docker-compose logs -f
            ;;
        "backup")
            backup_data
            ;;
        "stop")
            docker-compose stop
            log "✅ Services stopped"
            ;;
        "start")
            docker-compose start
            log "✅ Services started"
            ;;
        "restart")
            docker-compose restart
            log "✅ Services restarted"
            ;;
        "cleanup")
            cleanup
            ;;
        *)
            echo "Usage: $0 {init|build|deploy|status|logs|backup|stop|start|restart|cleanup}"
            echo
            echo "Commands:"
            echo "  init    - Initialize Docker deployment files"
            echo "  build   - Build Docker image"
            echo "  deploy  - Deploy with Docker Compose"
            echo "  status  - Show deployment status"
            echo "  logs    - Show and follow logs"
            echo "  backup  - Create backup of data and database"
            echo "  stop    - Stop all services"
            echo "  start   - Start all services"
            echo "  restart - Restart all services"
            echo "  cleanup - Remove all containers and volumes"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
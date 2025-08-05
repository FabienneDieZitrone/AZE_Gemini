#!/bin/bash
#
# Git-based deployment with webhooks for AZE_Gemini
# This script sets up automatic deployment from Git repository
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
WEBHOOK_SECRET="${WEBHOOK_SECRET:-$(openssl rand -hex 32)}"
WEBHOOK_PORT="${WEBHOOK_PORT:-9000}"
REPO_DIR="/var/www/aze-repo"
DEPLOY_DIR="/htdocs/aze"
LOG_FILE="/var/log/aze-deployment.log"

echo -e "${GREEN}🔄 Git Webhook Deployment Setup${NC}"
echo "================================="
echo

# Function to log with timestamp
log() {
    local message="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo -e "${BLUE}$message${NC}"
    echo "$message" >> "$LOG_FILE"
}

# Function to handle errors
error_exit() {
    local message="Error: $1"
    echo -e "${RED}❌ $message${NC}" >&2
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $message" >> "$LOG_FILE"
    exit 1
}

# Function to install webhook listener
install_webhook_listener() {
    log "Installing webhook listener..."
    
    # Create webhook listener script
    cat > /usr/local/bin/aze-webhook-listener << EOF
#!/bin/bash
#
# AZE Gemini Webhook Listener
# Listens for GitHub webhooks and triggers deployment
#

set -euo pipefail

WEBHOOK_SECRET="$WEBHOOK_SECRET"
REPO_URL="${GIT_REPO_URL:-https://github.com/FabienneDieZitrone/AZE_Gemini.git}"
REPO_DIR="$REPO_DIR"
DEPLOY_DIR="$DEPLOY_DIR"
LOG_FILE="$LOG_FILE"

# Function to log messages
log() {
    echo "[\\$(date '+%Y-%m-%d %H:%M:%S')] \\$1" | tee -a "\\$LOG_FILE"
}

# Function to validate webhook signature
validate_signature() {
    local payload="\\$1"
    local signature="\\$2"
    local expected="sha256=\\$(echo -n "\\$payload" | openssl dgst -sha256 -hmac "\\$WEBHOOK_SECRET" | cut -d' ' -f2)"
    
    if [ "\\$signature" = "\\$expected" ]; then
        return 0
    else
        return 1
    fi
}

# Function to deploy from git
deploy_from_git() {
    log "Starting Git deployment..."
    
    # Navigate to repo directory
    cd "\\$REPO_DIR"
    
    # Pull latest changes
    git fetch origin
    git reset --hard origin/main
    
    # Navigate to build directory
    cd build
    
    # Install dependencies and build
    npm ci
    npm run build
    
    # Create deployment package
    rm -rf temp_deploy
    mkdir -p temp_deploy
    
    # Copy files
    cp -r dist/* temp_deploy/
    mkdir -p temp_deploy/api
    cp -r api/* temp_deploy/api/
    [ -f config.php ] && cp config.php temp_deploy/
    [ -f schema.sql ] && cp schema.sql temp_deploy/
    
    # Deploy to target directory
    rsync -av --delete temp_deploy/ "\\$DEPLOY_DIR/"
    
    # Set permissions
    find "\\$DEPLOY_DIR" -type f -exec chmod 644 {} \\;
    find "\\$DEPLOY_DIR" -type d -exec chmod 755 {} \\;
    [ -f "\\$DEPLOY_DIR/.env" ] && chmod 600 "\\$DEPLOY_DIR/.env"
    
    # Cleanup
    rm -rf temp_deploy
    
    log "✅ Deployment completed successfully"
}

# Simple HTTP server to handle webhooks
while true; do
    # Listen for incoming connections
    response="HTTP/1.1 200 OK\\r\\nContent-Length: 2\\r\\n\\r\\nOK"
    
    # Read HTTP request
    request=\\$(timeout 30 nc -l -p $WEBHOOK_PORT || echo "")
    
    if [[ "\\$request" =~ "POST /webhook" ]]; then
        # Extract payload and signature (simplified)
        payload=\\$(echo "\\$request" | tail -1)
        signature=\\$(echo "\\$request" | grep -i "x-hub-signature-256" | cut -d' ' -f2 | tr -d '\\r')
        
        if validate_signature "\\$payload" "\\$signature"; then
            log "Valid webhook received, starting deployment..."
            deploy_from_git &
            echo -e "\\$response" | nc -l -p $WEBHOOK_PORT &
        else
            log "Invalid webhook signature"
            echo -e "HTTP/1.1 401 Unauthorized\\r\\n\\r\\n" | nc -l -p $WEBHOOK_PORT &
        fi
    fi
    
    sleep 1
done
EOF
    
    chmod +x /usr/local/bin/aze-webhook-listener
    log "✓ Webhook listener installed"
}

# Function to create systemd service
create_systemd_service() {
    log "Creating systemd service..."
    
    cat > /etc/systemd/system/aze-webhook.service << EOF
[Unit]
Description=AZE Gemini Webhook Deployment Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$REPO_DIR
ExecStart=/usr/local/bin/aze-webhook-listener
Restart=always
RestartSec=10
StandardOutput=append:$LOG_FILE
StandardError=append:$LOG_FILE

[Install]
WantedBy=multi-user.target
EOF
    
    systemctl daemon-reload
    systemctl enable aze-webhook.service
    log "✓ Systemd service created and enabled"
}

# Function to setup git repository
setup_git_repository() {
    log "Setting up Git repository..."
    
    if [ -d "$REPO_DIR" ]; then
        log "Repository directory already exists, updating..."
        cd "$REPO_DIR"
        git pull origin main
    else
        log "Cloning repository..."
        git clone "${GIT_REPO_URL:-https://github.com/FabienneDieZitrone/AZE_Gemini.git}" "$REPO_DIR"
    fi
    
    # Set proper ownership
    chown -R www-data:www-data "$REPO_DIR"
    
    log "✓ Git repository setup completed"
}

# Function to configure nginx (if available)
configure_nginx() {
    if command -v nginx >/dev/null 2>&1; then
        log "Configuring nginx for webhook endpoint..."
        
        cat > /etc/nginx/sites-available/aze-webhook << EOF
server {
    listen 80;
    server_name webhook.aze.mikropartner.de;
    
    location /webhook {
        proxy_pass http://localhost:$WEBHOOK_PORT/webhook;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF
        
        ln -sf /etc/nginx/sites-available/aze-webhook /etc/nginx/sites-enabled/
        nginx -t && systemctl reload nginx
        
        log "✓ Nginx configured for webhooks"
    else
        log "⚠️ Nginx not found, webhook will run on port $WEBHOOK_PORT"
    fi
}

# Function to test deployment
test_deployment() {
    log "Testing deployment..."
    
    cd "$REPO_DIR/build"
    
    # Test build process
    npm ci || error_exit "npm install failed"
    npm run build || error_exit "Build failed"
    
    # Test file structure
    if [ ! -d "dist" ]; then
        error_exit "Build output 'dist' directory not found"
    fi
    
    if [ ! -d "api" ]; then
        error_exit "API directory not found"
    fi
    
    log "✓ Deployment test successful"
}

# Function to show setup summary
show_setup_summary() {
    log "Setup Summary"
    echo
    echo -e "${GREEN}✅ Git Webhook Deployment Setup Complete${NC}"
    echo
    echo "Configuration:"
    echo "- Repository: ${GIT_REPO_URL:-https://github.com/FabienneDieZitrone/AZE_Gemini.git}"
    echo "- Webhook Port: $WEBHOOK_PORT"
    echo "- Webhook Secret: $WEBHOOK_SECRET"
    echo "- Deploy Directory: $DEPLOY_DIR"
    echo "- Log File: $LOG_FILE"
    echo
    echo "GitHub Webhook URL: http://your-server.com:$WEBHOOK_PORT/webhook"
    echo
    echo "To configure GitHub webhook:"
    echo "1. Go to your repository settings"
    echo "2. Navigate to Webhooks"
    echo "3. Add webhook with URL above"
    echo "4. Set Content type to application/json"
    echo "5. Set Secret to: $WEBHOOK_SECRET"
    echo "6. Select 'Just the push event'"
    echo
    echo "Service Management:"
    echo "- Start: systemctl start aze-webhook"
    echo "- Stop: systemctl stop aze-webhook"
    echo "- Status: systemctl status aze-webhook"
    echo "- Logs: tail -f $LOG_FILE"
    echo
}

# Main setup process
main() {
    local action="${1:-setup}"
    
    # Check if running as root
    if [ "$EUID" -ne 0 ]; then
        error_exit "This script must be run as root"
    fi
    
    case "$action" in
        "setup")
            log "Starting Git webhook deployment setup..."
            
            # Create necessary directories
            mkdir -p "$(dirname "$REPO_DIR")"
            mkdir -p "$(dirname "$DEPLOY_DIR")"
            mkdir -p "$(dirname "$LOG_FILE")"
            
            # Setup components
            setup_git_repository
            install_webhook_listener
            create_systemd_service
            configure_nginx
            test_deployment
            
            # Start service
            systemctl start aze-webhook.service
            
            show_setup_summary
            ;;
        "start")
            systemctl start aze-webhook.service
            log "✅ Webhook service started"
            ;;
        "stop")
            systemctl stop aze-webhook.service
            log "✅ Webhook service stopped"
            ;;
        "status")
            systemctl status aze-webhook.service
            ;;
        "logs")
            tail -f "$LOG_FILE"
            ;;
        "test")
            test_deployment
            ;;
        *)
            echo "Usage: $0 {setup|start|stop|status|logs|test}"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
#!/bin/bash
#
# Secure SSH-Based Deployment Script for AZE_Gemini
# Uses SSH key authentication for maximum security
#

set -euo pipefail

# Change to script directory
cd "$(dirname "$0")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
DEPLOYMENT_CONFIG_FILE=".env.deployment"
SSH_KEY_PATH="${HOME}/.ssh/aze_deployment_key"
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"
HEALTH_CHECK_TIMEOUT=30

echo -e "${GREEN}🚀 Secure SSH Deployment for AZE_Gemini${NC}"
echo "============================================="
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

# Load deployment configuration
if [ -f "$DEPLOYMENT_CONFIG_FILE" ]; then
    log "Loading deployment configuration..."
    export $(grep -v '^#' "$DEPLOYMENT_CONFIG_FILE" | xargs)
else
    error_exit "Deployment configuration file '$DEPLOYMENT_CONFIG_FILE' not found"
fi

# Validate required environment variables
REQUIRED_VARS=("SSH_HOST" "SSH_USER" "SSH_PORT" "REMOTE_PATH" "HEALTH_CHECK_URL")
MISSING_VARS=()

for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var:-}" ]; then
        MISSING_VARS+=("$var")
    fi
done

if [ ${#MISSING_VARS[@]} -ne 0 ]; then
    error_exit "Missing required environment variables: ${MISSING_VARS[*]}"
fi

# Check if SSH key exists
if [ ! -f "$SSH_KEY_PATH" ]; then
    error_exit "SSH key not found at $SSH_KEY_PATH. Please generate one first."
fi

# Verify SSH key permissions
chmod 600 "$SSH_KEY_PATH"

log "✓ Configuration validated"
echo "Host: $SSH_HOST:$SSH_PORT"
echo "User: $SSH_USER"
echo "Remote Path: $REMOTE_PATH"
echo

# Function to execute remote commands via SSH
ssh_exec() {
    ssh -i "$SSH_KEY_PATH" \
        -p "$SSH_PORT" \
        -o StrictHostKeyChecking=no \
        -o ConnectTimeout=10 \
        "$SSH_USER@$SSH_HOST" "$@"
}

# Function to upload files via SFTP
sftp_upload() {
    local local_path="$1"
    local remote_path="$2"
    
    sftp -i "$SSH_KEY_PATH" \
         -P "$SSH_PORT" \
         -o StrictHostKeyChecking=no \
         "$SSH_USER@$SSH_HOST" << EOF
cd $remote_path
put -r $local_path/*
quit
EOF
}

# Function to create backup
create_backup() {
    log "Creating backup of current deployment..."
    
    ssh_exec "
        if [ -d '$REMOTE_PATH' ]; then
            mkdir -p '$REMOTE_PATH/../$BACKUP_DIR'
            cp -r '$REMOTE_PATH'/* '$REMOTE_PATH/../$BACKUP_DIR/' 2>/dev/null || true
            echo 'Backup created at $BACKUP_DIR'
        else
            echo 'No existing deployment to backup'
        fi
    "
}

# Function to build project
build_project() {
    log "Building project..."
    
    if [ ! -f "package.json" ]; then
        error_exit "package.json not found. Are you in the build directory?"
    fi
    
    # Install dependencies if node_modules doesn't exist
    if [ ! -d "node_modules" ]; then
        log "Installing dependencies..."
        npm ci
    fi
    
    # Build the project
    log "Running build..."
    npm run build || error_exit "Build failed"
    
    # Verify build output
    if [ ! -d "dist" ]; then
        error_exit "Build output directory 'dist' not found"
    fi
    
    log "✓ Build completed successfully"
}

# Function to prepare deployment package
prepare_deployment() {
    log "Preparing deployment package..."
    
    # Create temporary deployment directory
    local deploy_dir="temp_deploy_$(date +%s)"
    mkdir -p "$deploy_dir"
    
    # Copy built frontend
    cp -r dist/* "$deploy_dir/"
    
    # Copy API files
    if [ -d "api" ]; then
        mkdir -p "$deploy_dir/api"
        cp -r api/* "$deploy_dir/api/"
    fi
    
    # Copy configuration files
    [ -f "config.php" ] && cp config.php "$deploy_dir/"
    [ -f "schema.sql" ] && cp schema.sql "$deploy_dir/"
    [ -f ".htaccess" ] && cp .htaccess "$deploy_dir/"
    
    # Create production .env file (without secrets)
    if [ -f ".env.production.template" ]; then
        cp .env.production.template "$deploy_dir/.env"
    fi
    
    echo "$deploy_dir"
}

# Function to deploy files
deploy_files() {
    local deploy_dir="$1"
    
    log "Deploying files to remote server..."
    
    # Ensure remote directory exists
    ssh_exec "mkdir -p '$REMOTE_PATH'"
    
    # Upload files
    sftp_upload "$deploy_dir" "$REMOTE_PATH" || error_exit "File upload failed"
    
    log "✓ Files uploaded successfully"
}

# Function to set permissions
set_permissions() {
    log "Setting file permissions..."
    
    ssh_exec "
        cd '$REMOTE_PATH'
        find . -type f -name '*.php' -exec chmod 644 {} \;
        find . -type f -name '*.html' -exec chmod 644 {} \;
        find . -type f -name '*.css' -exec chmod 644 {} \;
        find . -type f -name '*.js' -exec chmod 644 {} \;
        find . -type d -exec chmod 755 {} \;
        [ -f .env ] && chmod 600 .env
        [ -d logs ] && chmod 755 logs
        [ -d data ] && chmod 755 data
    " || log "⚠️ Some permission changes may have failed"
    
    log "✓ Permissions set"
}

# Function to run health check
health_check() {
    log "Running health check..."
    
    local start_time=$(date +%s)
    local max_time=$((start_time + HEALTH_CHECK_TIMEOUT))
    
    while [ $(date +%s) -lt $max_time ]; do
        if curl -f -s "$HEALTH_CHECK_URL" > /dev/null; then
            log "✓ Health check passed"
            return 0
        fi
        sleep 5
    done
    
    log "⚠️ Health check failed or timed out"
    return 1
}

# Function to rollback deployment
rollback() {
    log "Rolling back deployment..."
    
    local latest_backup=$(ssh_exec "ls -t '$REMOTE_PATH/../backups' | head -1" 2>/dev/null || echo "")
    
    if [ -n "$latest_backup" ]; then
        ssh_exec "
            rm -rf '$REMOTE_PATH'/*
            cp -r '$REMOTE_PATH/../backups/$latest_backup'/* '$REMOTE_PATH/'
        "
        log "✓ Rollback completed"
    else
        log "⚠️ No backup found for rollback"
    fi
}

# Main deployment process
main() {
    local deployment_method="${1:-full}"
    
    case "$deployment_method" in
        "frontend")
            log "Deploying frontend only..."
            build_project
            deploy_dir=$(prepare_deployment)
            deploy_files "$deploy_dir"
            set_permissions
            rm -rf "$deploy_dir"
            health_check
            ;;
        "backend")
            log "Deploying backend only..."
            # For backend-only deployment, we skip the build step
            deploy_dir="temp_deploy_$(date +%s)"
            mkdir -p "$deploy_dir/api"
            cp -r api/* "$deploy_dir/api/"
            [ -f "config.php" ] && cp config.php "$deploy_dir/"
            [ -f "schema.sql" ] && cp schema.sql "$deploy_dir/"
            deploy_files "$deploy_dir"
            set_permissions
            rm -rf "$deploy_dir"
            health_check
            ;;
        "full"|*)
            log "Performing full deployment..."
            
            # Test SSH connection first
            log "Testing SSH connection..."
            ssh_exec "echo 'SSH connection successful'" || error_exit "SSH connection failed"
            
            # Create backup
            create_backup
            
            # Build and deploy
            build_project
            deploy_dir=$(prepare_deployment)
            deploy_files "$deploy_dir"
            set_permissions
            
            # Cleanup
            rm -rf "$deploy_dir"
            
            # Verify deployment
            if health_check; then
                log "🎉 Deployment completed successfully!"
            else
                log "❌ Health check failed. Consider rollback."
                read -p "Do you want to rollback? (y/N): " -n 1 -r
                echo
                if [[ $REPLY =~ ^[Yy]$ ]]; then
                    rollback
                fi
            fi
            ;;
    esac
}

# Trap to cleanup on exit
cleanup() {
    # Remove any temporary directories
    rm -rf temp_deploy_*
}
trap cleanup EXIT

# Run main function
main "$@"
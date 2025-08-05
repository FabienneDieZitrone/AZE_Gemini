#!/bin/bash
#
# Setup SSH-based deployment for AZE_Gemini
# This script helps configure SSH key authentication and deployment environment
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SSH_KEY_PATH="${HOME}/.ssh/aze_deployment_key"
SSH_CONFIG_FILE="${HOME}/.ssh/config"

echo -e "${GREEN}🔧 SSH Deployment Setup for AZE_Gemini${NC}"
echo "==========================================="
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

# Function to generate SSH key
generate_ssh_key() {
    log "Generating SSH key for deployment..."
    
    if [ -f "$SSH_KEY_PATH" ]; then
        echo -e "${YELLOW}SSH key already exists at $SSH_KEY_PATH${NC}"
        read -p "Do you want to generate a new key? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            return 0
        fi
    fi
    
    # Generate SSH key
    ssh-keygen -t ed25519 -f "$SSH_KEY_PATH" -C "aze-deployment-$(date +%Y%m%d)" -N ""
    
    log "✓ SSH key generated successfully"
    echo "Public key location: ${SSH_KEY_PATH}.pub"
    echo
}

# Function to display public key
display_public_key() {
    log "Your public key (add this to your server's authorized_keys):"
    echo
    echo -e "${GREEN}$(cat ${SSH_KEY_PATH}.pub)${NC}"
    echo
    echo "Add this key to your HostEurope server by:"
    echo "1. Logging into your HostEurope control panel"
    echo "2. Finding SSH key management section"
    echo "3. Adding the above public key"
    echo "OR"
    echo "1. SSH into your server: ssh wp10454681@wp10454681.server-he.de"
    echo "2. Create ~/.ssh directory: mkdir -p ~/.ssh"
    echo "3. Add key to authorized_keys: echo 'PUBLIC_KEY_ABOVE' >> ~/.ssh/authorized_keys"
    echo "4. Set permissions: chmod 600 ~/.ssh/authorized_keys"
    echo
}

# Function to test SSH connection
test_ssh_connection() {
    log "Testing SSH connection..."
    
    if [ ! -f ".env.deployment" ]; then
        error_exit "Please create .env.deployment file first (copy from .env.deployment.example)"
    fi
    
    # Load configuration
    export $(grep -v '^#' .env.deployment | xargs)
    
    if ssh -i "$SSH_KEY_PATH" -p "${SSH_PORT:-22}" -o ConnectTimeout=10 -o StrictHostKeyChecking=no "$SSH_USER@$SSH_HOST" "echo 'SSH connection successful'"; then
        log "✅ SSH connection successful!"
        return 0
    else
        log "❌ SSH connection failed"
        return 1
    fi
}

# Function to setup SSH config
setup_ssh_config() {
    log "Setting up SSH config..."
    
    if [ ! -f ".env.deployment" ]; then
        error_exit "Please create .env.deployment file first"
    fi
    
    export $(grep -v '^#' .env.deployment | xargs)
    
    # Create SSH config entry
    cat >> "$SSH_CONFIG_FILE" << EOF

# AZE Gemini Deployment
Host aze-deployment
    HostName $SSH_HOST
    Port ${SSH_PORT:-22}
    User $SSH_USER
    IdentityFile $SSH_KEY_PATH
    StrictHostKeyChecking no
    ServerAliveInterval 60
    ServerAliveCountMax 3

EOF
    
    log "✓ SSH config updated"
    echo "You can now connect using: ssh aze-deployment"
}

# Function to create deployment configuration
create_deployment_config() {
    log "Creating deployment configuration..."
    
    if [ ! -f ".env.deployment.example" ]; then
        error_exit ".env.deployment.example not found"
    fi
    
    if [ -f ".env.deployment" ]; then
        echo -e "${YELLOW}Deployment configuration already exists${NC}"
        read -p "Do you want to recreate it? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            return 0
        fi
    fi
    
    cp .env.deployment.example .env.deployment
    
    echo -e "${YELLOW}Please edit .env.deployment and fill in your specific values${NC}"
    echo "Required fields:"
    echo "- SSH_HOST (your server hostname)"
    echo "- SSH_USER (your SSH username)"
    echo "- SSH_PORT (usually 22)"
    echo "- REMOTE_PATH (path on server, usually /htdocs/aze)"
    echo "- HEALTH_CHECK_URL (URL to verify deployment)"
    echo
}

# Function to verify server requirements
verify_server_requirements() {
    log "Verifying server requirements..."
    
    if [ ! -f ".env.deployment" ]; then
        error_exit "Please create .env.deployment file first"
    fi
    
    export $(grep -v '^#' .env.deployment | xargs)
    
    # Test connection and verify requirements
    ssh -i "$SSH_KEY_PATH" -p "${SSH_PORT:-22}" -o StrictHostKeyChecking=no "$SSH_USER@$SSH_HOST" bash << 'EOF'
echo "Testing server environment..."

# Check PHP version
if command -v php >/dev/null 2>&1; then
    echo "✓ PHP available: $(php -v | head -1)"
else
    echo "❌ PHP not found"
fi

# Check if we can write to htdocs
if [ -w "/htdocs" ]; then
    echo "✓ /htdocs is writable"
else
    echo "❌ /htdocs is not writable"
fi

# Check available disk space
echo "Disk space:"
df -h /htdocs | tail -1

# Check if required PHP extensions are available
php -m | grep -E "(mysqli|curl|json|mbstring)" | sed 's/^/✓ PHP extension: /'

echo "Server verification complete."
EOF
    
    log "✓ Server requirements check completed"
}

# Function to setup GitHub secrets (information only)
setup_github_secrets() {
    log "GitHub Secrets Setup Instructions"
    echo
    echo "To use the GitHub Actions deployment workflow, add these secrets to your repository:"
    echo
    echo "1. Go to your GitHub repository"
    echo "2. Navigate to Settings > Secrets and variables > Actions"
    echo "3. Add the following secrets:"
    echo
    echo -e "${GREEN}SSH_PRIVATE_KEY${NC}: $(cat $SSH_KEY_PATH | base64 -w 0)"
    echo -e "${GREEN}SSH_HOST${NC}: Your server hostname (e.g., wp10454681.server-he.de)"
    echo -e "${GREEN}SSH_USER${NC}: Your SSH username (e.g., wp10454681)"
    echo -e "${GREEN}HEALTH_CHECK_URL${NC}: Your health check URL"
    echo
    echo "Optional secrets for FTP fallback:"
    echo -e "${GREEN}FTP_HOST${NC}: Your FTP server"
    echo -e "${GREEN}FTP_USER${NC}: Your FTP username" 
    echo -e "${GREEN}FTP_PASS${NC}: Your FTP password"
    echo
}

# Main setup process
main() {
    local step="${1:-all}"
    
    case "$step" in
        "ssh-key")
            generate_ssh_key
            display_public_key
            ;;
        "config")
            create_deployment_config
            ;;
        "ssh-config")
            setup_ssh_config
            ;;
        "test")
            test_ssh_connection
            ;;
        "verify")
            verify_server_requirements
            ;;
        "github")
            setup_github_secrets
            ;;
        "all"|*)
            log "Starting complete SSH deployment setup..."
            
            # Step 1: Create deployment configuration
            create_deployment_config
            
            # Step 2: Generate SSH key
            generate_ssh_key
            
            # Step 3: Display public key for manual addition
            display_public_key
            
            # Step 4: Setup SSH config
            setup_ssh_config
            
            echo -e "${YELLOW}Manual Steps Required:${NC}"
            echo "1. Add the public key shown above to your server's authorized_keys"
            echo "2. Edit .env.deployment with your specific server details"
            echo "3. Run: ./setup-ssh-deployment.sh test"
            echo "4. If test passes, run: ./setup-ssh-deployment.sh verify"
            echo "5. For GitHub Actions: ./setup-ssh-deployment.sh github"
            echo
            log "Setup preparation completed!"
            ;;
    esac
}

# Ensure .ssh directory exists
mkdir -p "${HOME}/.ssh"
chmod 700 "${HOME}/.ssh"

# Run main function
main "$@"
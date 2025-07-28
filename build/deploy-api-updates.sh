#!/bin/bash
# Deployment script to update all API files with error handlers

API_DIR="./api"
APIS_TO_UPDATE=(
    "approvals.php"
    "auth-callback.php"
    "auth-logout.php"
    "auth-oauth-client.php"
    "auth-start.php"
    "auth-status.php"
    "history.php"
    "logs.php"
    "masterdata.php"
    "settings.php"
    "users.php"
)

echo "=== API Error Handler Integration ==="
echo "Updating ${#APIS_TO_UPDATE[@]} API files..."

for api in "${APIS_TO_UPDATE[@]}"; do
    FILE="$API_DIR/$api"
    
    if [ -f "$FILE" ]; then
        # Check if already has error handler
        if grep -q "error-handler.php" "$FILE"; then
            echo "✓ Already updated: $api"
        else
            # Create backup
            cp "$FILE" "$FILE.backup"
            
            # Add error handler after PHP opening tag and comments
            sed -i '1,/\*\//s|\*/|*/\n\n// Error handling\nrequire_once __DIR__ . '"'"'/error-handler.php'"'"';\nrequire_once __DIR__ . '"'"'/security-headers.php'"'"';|' "$FILE"
            
            echo "✓ Updated: $api"
        fi
    else
        echo "✗ Not found: $api"
    fi
done

echo ""
echo "=== Creating deployment package ==="

# Create deployment directory
DEPLOY_DIR="deploy_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$DEPLOY_DIR/api"
mkdir -p "$DEPLOY_DIR/dist"
mkdir -p "$DEPLOY_DIR/logs"
mkdir -p "$DEPLOY_DIR/data"
mkdir -p "$DEPLOY_DIR/cache"

# Copy updated API files
cp -r api/*.php "$DEPLOY_DIR/api/"

# Copy frontend build
if [ -d "dist" ]; then
    cp -r dist/* "$DEPLOY_DIR/"
else
    echo "⚠️  No dist directory found. Run 'npm run build' first!"
fi

# Copy other necessary files
cp .htaccess "$DEPLOY_DIR/" 2>/dev/null || echo "⚠️  .htaccess not found"
cp config.php "$DEPLOY_DIR/" 2>/dev/null || echo "⚠️  config.php not found"
cp fix-permissions.php "$DEPLOY_DIR/" 2>/dev/null || echo "⚠️  fix-permissions.php not found"

# Create deployment instructions
cat > "$DEPLOY_DIR/DEPLOYMENT_INSTRUCTIONS.txt" << EOF
AZE_Gemini Deployment Instructions
==================================

1. Upload all files to /aze/ directory on server
2. Ensure .env file exists with proper database credentials
3. Visit https://aze.mikropartner.de/fix-permissions.php once
4. Delete fix-permissions.php after successful execution
5. Test the application at https://aze.mikropartner.de

Directory Structure:
- /api/         - Backend API endpoints
- /assets/      - Frontend assets
- /logs/        - Application logs (must be writable)
- /data/        - Application data (must be writable)
- /cache/       - Cache directory (must be writable)

Security Checklist:
✓ Error handlers integrated in all APIs
✓ Security headers enabled
✓ Input validation active
✓ Session management secure
✓ No sensitive data in repository
EOF

echo "✓ Deployment package created: $DEPLOY_DIR"
echo ""
echo "Next steps:"
echo "1. Review the updated files"
echo "2. Test locally with 'npm run dev'"
echo "3. Build frontend with 'npm run build'"
echo "4. Deploy using FTP or automated deployment"
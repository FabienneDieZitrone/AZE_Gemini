#!/bin/bash
# AZE Gemini Deployment Script

echo "=== AZE Gemini Deployment Script ==="
echo "Deploying to production server..."

# Load deployment configuration
if [ -f ".env.deployment" ]; then
    export $(cat .env.deployment | grep -v '^#' | xargs)
else
    echo "Error: .env.deployment file not found"
    exit 1
fi

# Create deployment archive
echo "Creating deployment archive..."
cd build
tar -czf ../aze-deployment.tar.gz .
cd ..

echo "Archive created: aze-deployment.tar.gz"
echo "Size: $(ls -lh aze-deployment.tar.gz | awk '{print $5}')"

# Note: Manual deployment steps required
echo ""
echo "=== Manual Deployment Steps Required ==="
echo "1. Upload the archive to the server:"
echo "   scp -P $SSH_PORT aze-deployment.tar.gz $SSH_USER@$SSH_HOST:/tmp/"
echo ""
echo "2. SSH into the server:"
echo "   ssh -p $SSH_PORT $SSH_USER@$SSH_HOST"
echo ""
echo "3. On the server, run:"
echo "   cd $REMOTE_PATH"
echo "   tar -xzf /tmp/aze-deployment.tar.gz"
echo "   rm /tmp/aze-deployment.tar.gz"
echo ""
echo "4. Verify deployment:"
echo "   curl -s $HEALTH_CHECK_URL | jq ."
echo ""
echo "=== Deployment Archive Ready ==="
#!/bin/bash
# Test Environment Deployment Script for AZE Gemini Security Fixes

echo "=== AZE Gemini TEST ENVIRONMENT Deployment Script ==="
echo "Deploying security fixes to test environment..."

# Load FTP credentials
source /app/credentials/ftp.env

# Test environment path
TEST_PATH="/www/aze-test/"
LOCAL_API_PATH="build/api"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Files with security fixes
SECURITY_FIXED_FILES=(
    "time-entries.php"
    "users.php"
)

echo ""
echo "Deploying security fixes to test environment..."
echo "Test URL will be: https://aze.mikropartner.de/aze-test/"
echo ""

# Create deployment package with only the fixed files
echo "Creating security patch package..."
mkdir -p temp_deploy/api
for file in "${SECURITY_FIXED_FILES[@]}"; do
    cp "$LOCAL_API_PATH/$file" temp_deploy/api/
    echo "Added: $file"
done

# Copy other necessary API files for testing
cp "$LOCAL_API_PATH/db.php" temp_deploy/api/
cp "$LOCAL_API_PATH/constants.php" temp_deploy/api/
cp "$LOCAL_API_PATH/health.php" temp_deploy/api/
cp "$LOCAL_API_PATH/validation.php" temp_deploy/api/
cp "$LOCAL_API_PATH/security-middleware.php" temp_deploy/api/
cp "$LOCAL_API_PATH/login.php" temp_deploy/api/
cp "$LOCAL_API_PATH/auth_helpers.php" temp_deploy/api/
cp "$LOCAL_API_PATH/approvals.php" temp_deploy/api/
cp "$LOCAL_API_PATH/history.php" temp_deploy/api/

# Create test environment marker
echo "<?php echo json_encode(['environment' => 'test', 'deployment' => '$TIMESTAMP', 'security_patch' => 'issue_74']); ?>" > temp_deploy/api/test-info.php

# Create deployment archive
tar -czf security-patch-test.tar.gz -C temp_deploy .
rm -rf temp_deploy

echo ""
echo "=== FTP Upload Commands ==="
echo ""
echo "1. Create test directory and upload patch:"
echo "lftp -u $FTP_USER,$FTP_PASS ftps://$FTP_HOST:$FTP_PORT -e \"set ftp:ssl-force true; set ssl:verify-certificate false; mkdir -p $TEST_PATH; mkdir -p ${TEST_PATH}api; bye\""
echo ""
echo "2. Upload security patch:"
echo "curl --ftp-ssl-reuse --ftp-create-dirs -T security-patch-test.tar.gz -u $FTP_USER:$FTP_PASS ftp://$FTP_HOST${TEST_PATH}"
echo ""
echo "3. Extract on server (via SSH if available):"
echo "cd /www/aze-test && tar -xzf security-patch-test.tar.gz && rm security-patch-test.tar.gz"
echo ""
echo "=== Verification Steps ==="
echo "1. Test environment info: curl https://aze.mikropartner.de/aze-test/api/test-info.php"
echo "2. Test time-entries authorization: Test with different user roles"
echo "3. Test users PATCH authorization: Try changing roles as non-admin"
echo ""
echo "Security patch package created: security-patch-test.tar.gz"
#!/usr/bin/env python3
"""
Complete Test Environment Deployment for AZE Gemini
Deploys the entire application with security fixes to a test subdirectory
"""

import os
import ftplib
import ssl
import tarfile
import shutil
from datetime import datetime

# FTP Configuration from environment variables
FTP_HOST = os.getenv('FTP_HOST', 'wp10454681.server-he.de')
FTP_USER = os.getenv('FTP_USER', 'ftp10454681-aze')
FTP_PASS = os.getenv('FTP_PASS')
TEST_PATH = os.getenv('FTP_TEST_PATH', '/www/aze-test/')

# Security check: Ensure password is not hardcoded
if not FTP_PASS:
    print("ERROR: FTP_PASS environment variable is not set!")
    print("Please set the FTP_PASS environment variable before running this script.")
    print("Example: export FTP_PASS='your-password-here'")
    import sys
    sys.exit(1)

def create_test_package():
    """Create a complete deployment package for testing"""
    print("Creating complete test deployment package...")
    
    # Create temporary directory structure
    temp_dir = "temp_test_deploy"
    if os.path.exists(temp_dir):
        shutil.rmtree(temp_dir)
    
    # Copy build directory structure
    shutil.copytree("build", temp_dir, ignore=shutil.ignore_patterns(
        '*.log', '*.bak', 'node_modules', '.git', '*.sql'
    ))
    
    # Create test environment marker
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    with open(f"{temp_dir}/TEST_ENVIRONMENT.txt", "w") as f:
        f.write(f"Test Environment Deployment\n")
        f.write(f"Timestamp: {timestamp}\n")
        f.write(f"Security Patch: Issue #74 - Authorization Fixes\n")
        f.write(f"Fixed Files:\n")
        f.write(f"- api/time-entries.php: Role-based filtering implemented\n")
        f.write(f"- api/users.php: Admin-only role changes\n")
    
    # Create .htaccess for test environment
    with open(f"{temp_dir}/.htaccess", "w") as f:
        f.write("# Test Environment Configuration\n")
        f.write("Options -Indexes\n")
        f.write("DirectoryIndex index.html\n\n")
        f.write("# Rewrite rules for React app\n")
        f.write("RewriteEngine On\n")
        f.write("RewriteBase /aze-test/\n")
        f.write("RewriteCond %{REQUEST_FILENAME} !-f\n")
        f.write("RewriteCond %{REQUEST_FILENAME} !-d\n")
        f.write("RewriteCond %{REQUEST_URI} !^/aze-test/api\n")
        f.write("RewriteRule . /aze-test/index.html [L]\n\n")
        f.write("# Security headers\n")
        f.write("Header set X-Environment \"test\"\n")
    
    # Update API endpoints for test environment
    config_file = f"{temp_dir}/src/config/api.js"
    if os.path.exists(config_file):
        with open(config_file, 'r') as f:
            content = f.read()
        # Update API base URL for test environment
        content = content.replace('/api/', '/aze-test/api/')
        with open(config_file, 'w') as f:
            f.write(content)
    
    # Create tarball
    tar_filename = "aze-test-complete.tar.gz"
    with tarfile.open(tar_filename, "w:gz") as tar:
        tar.add(temp_dir, arcname=".")
    
    # Cleanup
    shutil.rmtree(temp_dir)
    
    print(f"Test package created: {tar_filename}")
    return tar_filename

def upload_via_ftps(filename):
    """Upload file via FTPS"""
    print(f"Uploading {filename} to test environment...")
    
    # Create SSL context
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        # Connect to FTP server
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()  # Enable protection for data channel
        
        # Create test directory if needed
        try:
            ftp.mkd(TEST_PATH)
        except:
            pass  # Directory might already exist
        
        # Change to test directory
        ftp.cwd(TEST_PATH)
        
        # Upload file
        with open(filename, 'rb') as f:
            ftp.storbinary(f'STOR {filename}', f)
        
        print(f"Successfully uploaded {filename}")
        
        # List files to verify
        print("\nFiles in test directory:")
        ftp.retrlines('LIST')
        
        ftp.quit()
        return True
        
    except Exception as e:
        print(f"FTP Error: {e}")
        return False

def create_test_verification_script():
    """Create a script to verify the security fixes"""
    script_content = """#!/bin/bash
# Security Fix Verification Script

echo "=== AZE Gemini Security Fix Verification ==="
echo ""

BASE_URL="https://aze.mikropartner.de/aze-test/api"

echo "1. Testing time-entries.php authorization..."
echo "   - Should only return entries for the logged-in user (Honorarkraft/Mitarbeiter)"
echo "   - Or filtered by location (Standortleiter)"
echo ""

echo "2. Testing users.php PATCH authorization..."
echo "   - Non-admin users should get 403 Forbidden"
echo ""

echo "3. Checking test environment marker..."
curl -s "$BASE_URL/../TEST_ENVIRONMENT.txt"
echo ""

echo "=== Manual Testing Required ==="
echo "1. Login as Honorarkraft - verify you only see your own time entries"
echo "2. Login as Mitarbeiter - verify you only see your own time entries"
echo "3. Login as Standortleiter - verify you only see entries from your location"
echo "4. Login as Admin - verify you see all entries"
echo "5. Try changing user roles as non-Admin - should be forbidden"
echo ""
echo "Test URL: https://aze.mikropartner.de/aze-test/"
"""
    
    with open("verify-security-fixes.sh", "w") as f:
        f.write(script_content)
    
    os.chmod("verify-security-fixes.sh", 0o755)
    print("Created verification script: verify-security-fixes.sh")

if __name__ == "__main__":
    print("=== Complete Test Environment Deployment ===")
    
    # Create deployment package
    package = create_test_package()
    
    # Upload via FTPS
    if upload_via_ftps(package):
        print("\n✅ Test environment deployed successfully!")
        print(f"Test URL: https://aze.mikropartner.de/aze-test/")
        
        # Create verification script
        create_test_verification_script()
        
        print("\n=== Next Steps ===")
        print("1. Extract the package on the server")
        print("2. Run ./verify-security-fixes.sh for initial checks")
        print("3. Perform manual testing with different user roles")
        print("4. Document test results")
    else:
        print("\n❌ Deployment failed. Please check the error messages above.")
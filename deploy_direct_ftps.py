#!/usr/bin/env python3
"""
Direct FTPS Deployment for AZE Gemini
Uploads files directly without creating archives
"""

import os
import sys
import ftplib
import ssl
from datetime import datetime
from pathlib import Path

# FTP Configuration from environment variables
FTP_HOST = os.getenv('FTP_HOST', 'wp10454681.server-he.de')
FTP_USER = os.getenv('FTP_USER', 'ftp10454681-aze')
FTP_PASS = os.getenv('FTP_PASS')
FTP_BASE_DIR = os.getenv('FTP_BASE_DIR', '/www/it/aze')

# Security check: Ensure password is not hardcoded
if not FTP_PASS:
    print("ERROR: FTP_PASS environment variable is not set!")
    print("Please set the FTP_PASS environment variable before running this script.")
    print("Example: export FTP_PASS='your-password-here'")
    sys.exit(1)

class DirectFTPSDeployer:
    def __init__(self, target_path=None):
        if target_path is None:
            # default test path next to base dir
            base = FTP_BASE_DIR.rstrip('/')
            target_path = f"{base}-test/"
        self.target_path = target_path
        self.ftp = None
        self.uploaded_files = []
        self.failed_files = []
        
    def connect(self):
        """Establish FTPS connection"""
        print("Connecting to FTPS server...")
        context = ssl.create_default_context()
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE
        
        self.ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        self.ftp.prot_p()  # Enable protection for data channel
        print("✅ Connected successfully")
        
    def create_remote_dir(self, remote_dir):
        """Create directory if it doesn't exist"""
        try:
            self.ftp.mkd(remote_dir)
            print(f"Created directory: {remote_dir}")
        except ftplib.error_perm as e:
            if "550" in str(e):  # Directory already exists
                pass
            else:
                raise
    
    def upload_file(self, local_path, remote_path):
        """Upload a single file"""
        try:
            with open(local_path, 'rb') as f:
                self.ftp.storbinary(f'STOR {remote_path}', f)
            self.uploaded_files.append(remote_path)
            print(f"✅ Uploaded: {local_path} -> {remote_path}")
            return True
        except Exception as e:
            self.failed_files.append((local_path, str(e)))
            print(f"❌ Failed: {local_path} - {e}")
            return False
    
    def upload_directory(self, local_dir, remote_base):
        """Recursively upload a directory"""
        for root, dirs, files in os.walk(local_dir):
            # Calculate relative path
            rel_path = os.path.relpath(root, local_dir)
            if rel_path == ".":
                remote_dir = remote_base
            else:
                remote_dir = os.path.join(remote_base, rel_path).replace("\\", "/")
            
            # Create remote directory
            if rel_path != ".":
                self.create_remote_dir(remote_dir)
            
            # Upload files in this directory
            for file in files:
                # Skip certain files
                if file.endswith(('.log', '.bak', '.sql', '.pyc', '__pycache__')):
                    continue
                if file.startswith('.'):
                    continue
                    
                local_file = os.path.join(root, file)
                remote_file = os.path.join(remote_dir, file).replace("\\", "/")
                self.upload_file(local_file, remote_file)
    
    def deploy_test_environment(self):
        """Deploy complete test environment"""
        print("\n=== Deploying Test Environment ===")
        print(f"Target: {self.target_path}")
        
        # Connect to FTP
        self.connect()
        
        # Create base directory
        self.create_remote_dir(self.target_path)
        
        # Deploy specific directories
        deployments = [
            ("build/api", f"{self.target_path}api"),
            ("build/src", f"{self.target_path}src"),
            ("build/public", f"{self.target_path}public"),
        ]
        
        # Upload individual important files
        important_files = [
            ("build/index.html", f"{self.target_path}index.html"),
            ("build/package.json", f"{self.target_path}package.json"),
            ("build/vite.config.js", f"{self.target_path}vite.config.js"),
        ]
        
        # Upload directories
        for local_dir, remote_dir in deployments:
            if os.path.exists(local_dir):
                print(f"\nUploading {local_dir}...")
                self.upload_directory(local_dir, remote_dir)
        
        # Upload individual files
        for local_file, remote_file in important_files:
            if os.path.exists(local_file):
                self.upload_file(local_file, remote_file)
        
        # Create test environment marker
        marker_content = f"""Test Environment Deployment
Timestamp: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}
Security Patch: Issue #74 - Authorization Fixes
Fixed Files:
- api/time-entries.php: Role-based filtering implemented
- api/users.php: Admin-only role changes
"""
        marker_file = "TEST_ENVIRONMENT.txt"
        with open(marker_file, "w") as f:
            f.write(marker_content)
        self.upload_file(marker_file, f"{self.target_path}TEST_ENVIRONMENT.txt")
        os.remove(marker_file)
        
        # Create .htaccess for test environment
        htaccess_content = """# Test Environment Configuration
Options -Indexes
DirectoryIndex index.html

# Security headers
Header set X-Environment "test"
"""
        htaccess_file = ".htaccess"
        with open(htaccess_file, "w") as f:
            f.write(htaccess_content)
        self.upload_file(htaccess_file, f"{self.target_path}.htaccess")
        os.remove(htaccess_file)
        
        # Close connection
        self.ftp.quit()
        
        # Summary
        print("\n=== Deployment Summary ===")
        print(f"✅ Uploaded: {len(self.uploaded_files)} files")
        print(f"❌ Failed: {len(self.failed_files)} files")
        
        if self.failed_files:
            print("\nFailed files:")
            for file, error in self.failed_files:
                print(f"  - {file}: {error}")
        
        return len(self.failed_files) == 0
    
    def deploy_security_fixes_only(self):
        """Deploy only the security fixes to production"""
        print("\n=== Deploying Security Fixes Only ===")
        
        self.connect()
        
        # Security fixed files
        base = FTP_BASE_DIR.rstrip('/')
        fixes = [
            ("build/api/time-entries.php", f"{base}/api/time-entries.php"),
            ("build/api/time-entries.impl.php", f"{base}/api/time-entries.impl.php"),
            ("build/api/users.php", f"{base}/api/users.php"),
        ]
        
        print("\nBacking up and deploying fixes...")
        for local_file, remote_file in fixes:
            if os.path.exists(local_file):
                # Create backup name
                backup_file = remote_file + ".backup_" + datetime.now().strftime("%Y%m%d_%H%M%S")
                
                # Try to rename existing file as backup
                try:
                    self.ftp.rename(remote_file, backup_file)
                    print(f"Backed up: {remote_file} -> {backup_file}")
                except:
                    print(f"Could not backup {remote_file} (might not exist)")
                
                # Upload new file
                self.upload_file(local_file, remote_file)
        
        self.ftp.quit()
        return len(self.failed_files) == 0

def main():
    import sys
    
    print("=== AZE Gemini Direct FTPS Deployment ===")
    
    if len(sys.argv) > 1 and sys.argv[1] == "--production-fixes":
        # Deploy only security fixes to production
        deployer = DirectFTPSDeployer(target_path="/www/aze/")
        success = deployer.deploy_security_fixes_only()
        
        if success:
            print("\n✅ Security fixes deployed to PRODUCTION!")
            print("⚠️  Please test immediately at https://aze.mikropartner.de/")
        else:
            print("\n❌ Deployment failed! Check errors above.")
    else:
        # Deploy complete test environment
        deployer = DirectFTPSDeployer()
        success = deployer.deploy_test_environment()
        
        if success:
            print("\n✅ Test environment deployed successfully!")
            print(f"Test URL: https://aze.mikropartner.de/aze-test/")
            print("\nNext steps:")
            print("1. Run ./test_security_fixes.sh")
            print("2. Perform manual testing")
            print("3. Deploy to production: python3 deploy_direct_ftps.py --production-fixes")
        else:
            print("\n❌ Deployment failed! Check errors above.")

if __name__ == "__main__":
    main()

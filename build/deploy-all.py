#!/usr/bin/env python3
"""
Complete FTP Deployment Script for AZE_Gemini
"""

import ftplib
import os
import sys
import json
from pathlib import Path
from datetime import datetime

FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "10454681-ftpaze"
FTP_PASS = "Start321"

class AZEDeployer:
    def __init__(self):
        self.ftp = None
        self.deployed_files = []
        self.errors = []
        
    def connect(self):
        """Connect to FTP server with TLS"""
        try:
            print(f"🔌 Connecting to {FTP_HOST}...")
            self.ftp = ftplib.FTP_TLS(FTP_HOST)
            self.ftp.login(FTP_USER, FTP_PASS)
            self.ftp.prot_p()  # Enable data connection encryption
            print("✅ Connected successfully!")
            return True
        except Exception as e:
            print(f"❌ Connection failed: {str(e)}")
            self.errors.append(f"Connection: {str(e)}")
            return False
    
    def upload_file(self, local_path, remote_path):
        """Upload a single file"""
        try:
            # Ensure remote directory exists
            remote_dir = os.path.dirname(remote_path)
            if remote_dir and remote_dir != '/':
                self.ensure_directory(remote_dir)
            
            # Upload file
            with open(local_path, 'rb') as f:
                self.ftp.storbinary(f'STOR {remote_path}', f)
            
            print(f"✅ Uploaded: {remote_path}")
            self.deployed_files.append(remote_path)
            return True
        except Exception as e:
            print(f"❌ Failed to upload {remote_path}: {str(e)}")
            self.errors.append(f"{remote_path}: {str(e)}")
            return False
    
    def ensure_directory(self, path):
        """Create directory if it doesn't exist"""
        parts = path.strip('/').split('/')
        current = '/'
        
        for part in parts:
            current = os.path.join(current, part).replace('\\', '/')
            try:
                self.ftp.cwd(current)
            except:
                try:
                    self.ftp.mkd(current)
                    print(f"📁 Created directory: {current}")
                except:
                    pass  # Directory might already exist
    
    def deploy_all(self):
        """Deploy all components"""
        if not self.connect():
            return False
        
        print("\n📦 Starting deployment...")
        
        # 1. Deploy fix-permissions.php
        print("\n1️⃣ Deploying fix-permissions.php...")
        self.upload_file('fix-permissions.php', '/aze/fix-permissions.php')
        
        # 2. Deploy all API updates
        print("\n2️⃣ Deploying API updates...")
        api_files = [
            'api/error-handler.php',
            'api/structured-logger.php',
            'api/security-headers.php',
            'api/health.php',
            'api/monitoring.php'
        ]
        
        # Find latest deployment directory
        deploy_dirs = list(Path('.').glob('deploy_*'))
        if deploy_dirs:
            latest_deploy = sorted(deploy_dirs)[-1]
            print(f"Using deployment directory: {latest_deploy}")
            
            for api_file in Path(latest_deploy / 'api').glob('*.php'):
                local = str(api_file)
                remote = f"/aze/api/{api_file.name}"
                self.upload_file(local, remote)
        
        # Also deploy newly created files
        for api_file in api_files:
            if os.path.exists(api_file):
                remote = f"/aze/{api_file}"
                self.upload_file(api_file, remote)
        
        # 3. Deploy monitoring dashboard
        print("\n3️⃣ Deploying monitoring dashboard...")
        if os.path.exists('monitoring-dashboard.html'):
            self.upload_file('monitoring-dashboard.html', '/aze/monitoring-dashboard.html')
        
        # 4. Deploy .htaccess for security
        print("\n4️⃣ Deploying security files...")
        htaccess_content = """# Security settings for AZE directories
Options -Indexes
Options -FollowSymLinks

# Protect sensitive directories
<FilesMatch "\\.(env|log|sql|ini|conf|cfg)$">
    Require all denied
</FilesMatch>

# Protect logs directory
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^logs/ - [F,L]
    RewriteRule ^data/ - [F,L]
    RewriteRule ^cache/ - [F,L]
</IfModule>"""
        
        with open('.htaccess', 'w') as f:
            f.write(htaccess_content)
        
        self.upload_file('.htaccess', '/aze/.htaccess')
        
        # 5. Create required directories
        print("\n5️⃣ Creating required directories...")
        for directory in ['/aze/logs', '/aze/data', '/aze/cache', '/aze/cache/rate-limit']:
            self.ensure_directory(directory)
        
        # Close connection
        self.ftp.quit()
        
        # Summary
        print("\n" + "="*50)
        print(f"✅ Deployed {len(self.deployed_files)} files successfully")
        if self.errors:
            print(f"❌ {len(self.errors)} errors occurred:")
            for error in self.errors:
                print(f"   - {error}")
        
        return len(self.errors) == 0

if __name__ == "__main__":
    deployer = AZEDeployer()
    success = deployer.deploy_all()
    
    if success:
        print("\n🎉 Deployment completed successfully!")
        print("\n⚠️  IMPORTANT NEXT STEPS:")
        print("1. Run: curl -k https://aze.mikropartner.de/fix-permissions.php")
        print("2. Then DELETE fix-permissions.php from server!")
        print("3. Test: curl -k https://aze.mikropartner.de/api/health.php")
    else:
        print("\n❌ Deployment failed! Check errors above.")
        sys.exit(1)
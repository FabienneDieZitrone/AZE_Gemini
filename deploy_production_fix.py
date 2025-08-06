#!/usr/bin/env python3
"""
CRITICAL PRODUCTION FIX - Deploy correct built files
Uploads the built JavaScript bundle and corrected index.html to production
"""

import ftplib
import os
import sys
from pathlib import Path

# FTP Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"

def deploy_fix():
    """Deploy the corrected index.html and assets to production"""
    try:
        print("🚨 CRITICAL PRODUCTION FIX - Starting deployment...")
        
        # Connect to FTP server with TLS
        ftp = ftplib.FTP_TLS(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()  # Switch to secure data connection
        
        print(f"✅ Connected to {FTP_HOST}")
        
        # Check current directory structure
        print("\n📁 Current production directory structure:")
        ftp.retrlines('LIST')
        
        # Upload corrected index.html
        local_index = "/app/projects/aze-gemini/build/dist/index.html"
        print(f"\n📤 Uploading corrected index.html...")
        with open(local_index, 'rb') as f:
            ftp.storbinary('STOR index.html', f)
        print("✅ index.html uploaded successfully")
        
        # Create/navigate to assets directory
        try:
            ftp.cwd('assets')
            print("📁 Navigated to existing assets directory")
        except ftplib.error_perm:
            print("📁 Creating assets directory...")
            ftp.mkd('assets')
            ftp.cwd('assets')
            print("✅ Assets directory created")
        
        # Upload all assets from dist/assets
        assets_dir = "/app/projects/aze-gemini/build/dist/assets"
        asset_files = os.listdir(assets_dir)
        
        print(f"\n📤 Uploading {len(asset_files)} asset files...")
        for asset_file in asset_files:
            local_path = os.path.join(assets_dir, asset_file)
            if os.path.isfile(local_path):
                print(f"  📤 Uploading {asset_file}...")
                with open(local_path, 'rb') as f:
                    ftp.storbinary(f'STOR {asset_file}', f)
                print(f"  ✅ {asset_file} uploaded")
        
        # Go back to root and verify deployment
        ftp.cwd('/')
        print("\n🔍 Verifying deployment - Current production files:")
        ftp.retrlines('LIST')
        
        print("\n🔍 Assets directory contents:")
        ftp.cwd('assets')
        ftp.retrlines('LIST')
        
        ftp.quit()
        print("\n🎉 PRODUCTION FIX DEPLOYED SUCCESSFULLY!")
        print("✅ index.html now correctly references built JavaScript bundle")
        print("✅ All asset files uploaded to /assets/ directory")
        print("🌐 Production site should now be working correctly")
        
        return True
        
    except Exception as e:
        print(f"❌ Deployment failed: {e}")
        return False

if __name__ == "__main__":
    success = deploy_fix()
    sys.exit(0 if success else 1)
#!/usr/bin/env python3
"""
Direct FTPS Deployment - Upload files directly without archive
"""

import ftplib
import os
import sys
from pathlib import Path

# Configuration
FTPS_HOST = "wp10454681.server-he.de"
FTPS_USER = "ftp10454681-aze"
FTPS_PASS = "321Start321"
FTPS_PORT = 21
LOCAL_BUILD_DIR = "build"
REMOTE_BASE = "/www/aze"

def upload_directory_ftps(ftps, local_dir, remote_dir):
    """Recursively upload directory via FTPS"""
    local_path = Path(local_dir)
    
    # Create remote directory if it doesn't exist
    try:
        ftps.cwd(remote_dir)
    except:
        try:
            ftps.mkd(remote_dir)
            ftps.cwd(remote_dir)
        except:
            pass
    
    # Upload all files and subdirectories
    for item in local_path.iterdir():
        if item.is_file():
            # Skip certain files
            if item.name in ['.env', '.env.production', '.gitignore', '.DS_Store']:
                continue
            if item.suffix in ['.log', '.tmp']:
                continue
                
            # Upload file
            remote_file = item.name
            print(f"Uploading: {item} -> {remote_dir}/{remote_file}")
            
            try:
                with open(item, 'rb') as f:
                    ftps.storbinary(f'STOR {remote_file}', f)
            except Exception as e:
                print(f"Error uploading {item}: {e}")
                
        elif item.is_dir():
            # Skip certain directories
            if item.name in ['node_modules', '.git', '__pycache__', '.pytest_cache']:
                continue
                
            # Recursively upload subdirectory
            remote_subdir = f"{remote_dir}/{item.name}"
            upload_directory_ftps(ftps, item, remote_subdir)

def deploy_direct():
    """Deploy files directly via FTPS"""
    try:
        print(f"Connecting to {FTPS_HOST}:{FTPS_PORT} as {FTPS_USER}...")
        
        # Create FTPS connection with explicit TLS
        ftps = ftplib.FTP_TLS()
        ftps.connect(FTPS_HOST, FTPS_PORT)
        ftps.login(FTPS_USER, FTPS_PASS)
        
        # Switch to secure data connection
        ftps.prot_p()
        
        print("Connected! Starting direct file upload...")
        
        # Change to root directory
        ftps.cwd('/')
        
        # Upload the build directory
        upload_directory_ftps(ftps, LOCAL_BUILD_DIR, REMOTE_BASE)
        
        print("\nDeployment completed!")
        
        # Close connection
        ftps.quit()
        
        return True
        
    except Exception as e:
        print(f"Error: {e}")
        return False

def main():
    """Main function"""
    # Check if build directory exists
    if not os.path.exists(LOCAL_BUILD_DIR):
        print(f"Error: {LOCAL_BUILD_DIR} directory not found!")
        sys.exit(1)
    
    # Count files to upload
    total_files = sum(1 for _ in Path(LOCAL_BUILD_DIR).rglob('*') if _.is_file())
    print(f"Found {total_files} files to upload")
    
    # Deploy directly
    if deploy_direct():
        print("\nDirect deployment completed successfully!")
        print("\nVerifying deployment...")
        
        # Verify with curl
        import subprocess
        result = subprocess.run(
            ["curl", "-k", "-s", "https://aze.mikropartner.de/api/health.php"],
            capture_output=True,
            text=True
        )
        
        print("Health check response:")
        print(result.stdout)
    else:
        print("\nDeployment failed!")
        sys.exit(1)

if __name__ == "__main__":
    main()
#!/usr/bin/env python3
"""
FTPS Deployment Script for AZE Gemini
Uploads deployment archive via FTPS with SSL/TLS
"""

import ftplib
import os
import sys
from pathlib import Path

# Configuration
FTPS_HOST = "wp10454681.server-he.de"
FTPS_USER = "ftp10454681-aze"
FTPS_PORT = 21
ARCHIVE_NAME = "aze-deployment.tar.gz"
REMOTE_TMP = "/tmp/"

def upload_via_ftps(password):
    """Upload deployment archive via FTPS"""
    try:
        print(f"Connecting to {FTPS_HOST}:{FTPS_PORT} as {FTPS_USER}...")
        
        # Create FTPS connection with explicit TLS
        ftps = ftplib.FTP_TLS()
        ftps.connect(FTPS_HOST, FTPS_PORT)
        
        # Login
        ftps.login(FTPS_USER, password)
        
        # Switch to secure data connection
        ftps.prot_p()
        
        # Check current directory
        print(f"Current directory: {ftps.pwd()}")
        
        # Navigate to tmp directory
        try:
            ftps.cwd(REMOTE_TMP)
        except:
            print(f"Creating {REMOTE_TMP} directory...")
            ftps.mkd(REMOTE_TMP)
            ftps.cwd(REMOTE_TMP)
        
        # Upload file
        file_size = os.path.getsize(ARCHIVE_NAME)
        print(f"Uploading {ARCHIVE_NAME} ({file_size:,} bytes)...")
        
        with open(ARCHIVE_NAME, 'rb') as file:
            ftps.storbinary(f'STOR {ARCHIVE_NAME}', file)
        
        print("Upload completed successfully!")
        
        # List uploaded file to verify
        ftps.retrlines('LIST ' + ARCHIVE_NAME)
        
        # Close connection
        ftps.quit()
        
        print("\n=== Next Steps ===")
        print(f"1. SSH into the server: ssh -p 22 {FTPS_USER}@{FTPS_HOST}")
        print(f"2. Extract the archive: cd /www/aze/ && tar -xzf {REMOTE_TMP}{ARCHIVE_NAME}")
        print(f"3. Remove temp file: rm {REMOTE_TMP}{ARCHIVE_NAME}")
        print("4. Verify deployment: curl -s https://aze.mikropartner.de/api/health.php")
        
        return True
        
    except Exception as e:
        print(f"Error: {e}")
        return False

def main():
    """Main function"""
    # Check if archive exists
    if not os.path.exists(ARCHIVE_NAME):
        print(f"Error: {ARCHIVE_NAME} not found!")
        print("Please run: cd build && tar -czf ../aze-deployment.tar.gz .")
        sys.exit(1)
    
    # Get password from environment or prompt
    import getpass
    password = os.environ.get('FTPS_PASSWORD')
    
    if not password:
        print("FTPS password not found in environment.")
        print("Please enter the password for FTPS deployment:")
        password = getpass.getpass(f"Password for {FTPS_USER}@{FTPS_HOST}: ")
    
    # Upload via FTPS
    if upload_via_ftps(password):
        print("\nDeployment archive uploaded successfully!")
    else:
        print("\nDeployment failed!")
        sys.exit(1)

if __name__ == "__main__":
    main()
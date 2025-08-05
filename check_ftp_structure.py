#!/usr/bin/env python3
"""
Check FTP directory structure
"""

import os
import sys
import ftplib
import ssl

# FTP Configuration from environment variables
FTP_HOST = os.getenv('FTP_HOST', 'wp10454681.server-he.de')
FTP_USER = os.getenv('FTP_USER', 'ftp10454681-aze')
FTP_PASS = os.getenv('FTP_PASS')

# Security check: Ensure password is not hardcoded
if not FTP_PASS:
    print("ERROR: FTP_PASS environment variable is not set!")
    print("Please set the FTP_PASS environment variable before running this script.")
    print("Example: export FTP_PASS='your-password-here'")
    sys.exit(1)

def check_structure():
    # Connect via FTPS
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
    ftp.prot_p()
    print("✅ Connected to FTPS")
    
    # Get root directory
    print("\nRoot directory contents:")
    files = []
    ftp.retrlines('LIST', files.append)
    for f in files:
        print(f"  {f}")
    
    # Check for www directory
    print("\nChecking for www directory...")
    try:
        ftp.cwd('/www')
        print("✅ Found /www")
        www_files = []
        ftp.retrlines('LIST', www_files.append)
        print("\n/www contents:")
        for f in www_files[:10]:
            print(f"  {f}")
    except:
        print("❌ /www not found")
    
    # Check for direct access
    try:
        ftp.cwd('/')
        # Check if files are in root
        if any('api' in f for f in files):
            print("\n✅ Found api directory in root")
        
        # Try to find index.html or similar
        if any('index' in f.lower() for f in files):
            print("✅ Found index file in root")
            
    except:
        pass
    
    ftp.quit()

if __name__ == "__main__":
    check_structure()
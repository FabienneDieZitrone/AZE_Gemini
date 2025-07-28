#!/usr/bin/env python3
"""
Delete fix-permissions.php from FTP server
"""

import ftplib
import sys

FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze3"
FTP_PASS = "Start321"

try:
    print(f"Connecting to {FTP_HOST}...")
    ftp = ftplib.FTP_TLS(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.prot_p()
    
    print("Connected successfully!")
    
    # Try to delete fix-permissions.php
    try:
        ftp.delete('/aze/fix-permissions.php')
        print("✅ fix-permissions.php deleted successfully!")
    except ftplib.error_perm as e:
        if "550" in str(e):
            print(f"❌ File not found or no permission: {e}")
        else:
            print(f"❌ Error deleting file: {e}")
    
    # Also try in root directory
    try:
        ftp.delete('/fix-permissions.php')
        print("✅ /fix-permissions.php deleted successfully!")
    except:
        pass  # Might not exist there
    
    ftp.quit()
    
except Exception as e:
    print(f"❌ Error: {e}")
    sys.exit(1)
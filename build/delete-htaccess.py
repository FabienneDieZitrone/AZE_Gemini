#!/usr/bin/env python3
"""
Delete .htaccess from server
"""

import ftplib

FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze3"
FTP_PASS = "Start321"

try:
    print(f"Connecting to {FTP_HOST}...")
    ftp = ftplib.FTP_TLS(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.prot_p()
    
    print("Connected successfully!")
    
    # Delete .htaccess
    try:
        ftp.delete('/.htaccess')
        print("✅ .htaccess deleted successfully!")
    except Exception as e:
        print(f"❌ Error: {e}")
    
    # Also try in aze directory
    try:
        ftp.delete('/aze/.htaccess')
        print("✅ /aze/.htaccess deleted successfully!")
    except:
        pass
    
    ftp.quit()
    
except Exception as e:
    print(f"❌ Error: {e}")
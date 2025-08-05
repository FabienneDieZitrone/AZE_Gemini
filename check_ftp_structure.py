#!/usr/bin/env python3
"""
Check FTP directory structure
"""

import ftplib

FTPS_HOST = "wp10454681.server-he.de"
FTPS_USER = "ftp10454681-aze"
FTPS_PASS = "321Start321"
FTPS_PORT = 21

def check_structure():
    """Check FTP directory structure"""
    try:
        print("Connecting to FTPS...")
        ftps = ftplib.FTP_TLS()
        ftps.connect(FTPS_HOST, FTPS_PORT)
        ftps.login(FTPS_USER, FTPS_PASS)
        ftps.prot_p()
        
        print(f"Current directory: {ftps.pwd()}")
        
        # List root directory
        print("\nRoot directory contents:")
        ftps.retrlines('LIST')
        
        # Try common web directories
        web_dirs = ['www', 'public_html', 'httpdocs', 'html', 'web', '.']
        
        for dir_name in web_dirs:
            try:
                print(f"\nTrying to access: {dir_name}")
                ftps.cwd(f'/{dir_name}')
                print(f"Success! Current: {ftps.pwd()}")
                ftps.retrlines('LIST')
                ftps.cwd('/')
            except Exception as e:
                print(f"Failed: {e}")
        
        ftps.quit()
        
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    check_structure()
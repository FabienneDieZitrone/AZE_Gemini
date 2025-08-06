#!/usr/bin/env python3
"""
Fix permissions for the setup-backup.sh script
"""

import ftplib
import ssl

# FTP Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"

def fix_setup_permissions():
    """Fix permissions for setup-backup.sh"""
    print("🔧 Fixing setup-backup.sh permissions...")
    
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()
        
        ftp.cwd("/")
        
        # Try to set executable permissions
        try:
            ftp.voidcmd('SITE CHMOD 755 setup-backup.sh')
            print("✅ Successfully set executable permissions on setup-backup.sh")
        except Exception as e:
            print(f"⚠️  Could not set permissions via FTP: {e}")
            print("   You may need to set permissions manually via SSH:")
            print("   chmod +x /setup-backup.sh")
        
        ftp.quit()
        return True
        
    except Exception as e:
        print(f"❌ Error fixing permissions: {e}")
        return False

if __name__ == "__main__":
    fix_setup_permissions()
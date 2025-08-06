#!/usr/bin/env python3
"""
Check existing backup scripts on FTP server
"""

import ftplib
import ssl
import tempfile
import os

# FTP Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"

def check_existing_scripts():
    """Check what backup scripts already exist on the server"""
    print("🔍 Checking existing backup scripts on production server...")
    
    # Create SSL context
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        # Connect to FTPS
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()
        print(f"✅ Connected to {FTP_HOST}")
        
        # Check /scripts/backup directory
        ftp.cwd("/scripts/backup")
        files = []
        ftp.retrlines('LIST', files.append)
        
        print("\n📋 Files in /scripts/backup/:")
        backup_scripts = []
        for file_info in files:
            parts = file_info.split()
            if len(parts) >= 9:
                filename = ' '.join(parts[8:])
                size = parts[4]
                date = ' '.join(parts[5:8])
                print(f"  📄 {filename} ({size} bytes, {date})")
                backup_scripts.append(filename)
        
        # Download and examine key scripts
        key_scripts = ['mysql-backup.sh', 'mysql-restore.sh', 'backup-monitor.sh', 'setup-backups.sh']
        
        for script_name in key_scripts:
            if script_name in backup_scripts:
                print(f"\n📜 Content of {script_name}:")
                print("-" * 50)
                
                # Download to temporary file
                with tempfile.NamedTemporaryFile(mode='w+t', delete=False) as temp_file:
                    try:
                        ftp.retrbinary(f'RETR {script_name}', 
                                     lambda data: temp_file.write(data.decode('utf-8')))
                        temp_file.flush()
                        temp_file.seek(0)
                        
                        # Read and display first 20 lines
                        lines = temp_file.read().split('\n')
                        for i, line in enumerate(lines[:20]):
                            print(f"  {i+1:2d}: {line}")
                        
                        if len(lines) > 20:
                            print(f"  ... ({len(lines) - 20} more lines)")
                            
                    except Exception as e:
                        print(f"  ❌ Error reading {script_name}: {e}")
                    finally:
                        os.unlink(temp_file.name)
            else:
                print(f"\n❌ {script_name} not found on server")
        
        ftp.quit()
        return backup_scripts
        
    except Exception as e:
        print(f"❌ Connection error: {e}")
        return []

if __name__ == "__main__":
    scripts = check_existing_scripts()
    if scripts:
        print(f"\n✅ Found {len(scripts)} backup scripts on server")
    else:
        print("\n❌ No backup scripts found or connection failed")
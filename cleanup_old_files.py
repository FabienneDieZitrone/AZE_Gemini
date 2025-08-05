#!/usr/bin/env python3
"""
Clean up old timer endpoint files
"""

import ftplib

FTPS_HOST = "wp10454681.server-he.de"
FTPS_USER = "ftp10454681-aze"
FTPS_PASS = "321Start321"
FTPS_PORT = 21

# Files to delete
OLD_FILES = [
    'timer-start.php',
    'timer-stop.php',
    'api/timer-start.php',
    'api/timer-stop.php'
]

def cleanup_old_files():
    """Delete old timer endpoint files"""
    try:
        print("Connecting to FTPS...")
        ftps = ftplib.FTP_TLS()
        ftps.connect(FTPS_HOST, FTPS_PORT)
        ftps.login(FTPS_USER, FTPS_PASS)
        ftps.prot_p()
        
        deleted = []
        not_found = []
        
        for file_path in OLD_FILES:
            try:
                print(f"Attempting to delete: {file_path}")
                ftps.delete(file_path)
                deleted.append(file_path)
                print(f"✅ Deleted: {file_path}")
            except ftplib.error_perm as e:
                if "550" in str(e):  # File not found
                    not_found.append(file_path)
                    print(f"❌ Not found: {file_path}")
                else:
                    print(f"❌ Error deleting {file_path}: {e}")
        
        ftps.quit()
        
        print(f"\nSummary:")
        print(f"Deleted: {len(deleted)} files")
        print(f"Not found: {len(not_found)} files")
        
        if deleted:
            print("\nDeleted files:")
            for f in deleted:
                print(f"  - {f}")
        
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    cleanup_old_files()
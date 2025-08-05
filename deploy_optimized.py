#!/usr/bin/env python3
"""
Optimized FTPS Deployment - Upload only changed files
"""

import ftplib
import os
from pathlib import Path

# Configuration
FTPS_HOST = "wp10454681.server-he.de"
FTPS_USER = "ftp10454681-aze"
FTPS_PASS = "321Start321"
FTPS_PORT = 21
LOCAL_BUILD_DIR = "build"
REMOTE_BASE = "/www/aze"

# Priority files to upload first
PRIORITY_FILES = [
    'api/login.php',
    'api/auth_helpers.php',
    'api/timer-control.php',
    'api/constants.php',
    'src/App.tsx',
    'src/hooks/useTimer.ts',
    'src/components/TimerService.tsx',
    'src/views/MainAppView.tsx',
    'src/views/DashboardView.tsx',
    'src/views/TimeSheetView.tsx',
    'src/utils/export.ts',
    'src/constants.ts',
    'dist/index.html',
    'dist/assets/index-DsjfTLkB.css',
    'dist/assets/index-DsjfTLkB.js'
]

def upload_priority_files(ftps):
    """Upload priority files first"""
    uploaded = 0
    for file_path in PRIORITY_FILES:
        local_file = Path(LOCAL_BUILD_DIR) / file_path
        if local_file.exists():
            remote_path = file_path.replace('/', os.sep)
            remote_dir = os.path.dirname(remote_path)
            
            # Ensure remote directory exists
            if remote_dir:
                try:
                    ftps.cwd(f"{REMOTE_BASE}/{remote_dir}")
                except:
                    # Create directory structure
                    parts = remote_dir.split(os.sep)
                    current = REMOTE_BASE
                    for part in parts:
                        current = f"{current}/{part}"
                        try:
                            ftps.mkd(current)
                        except:
                            pass
                    ftps.cwd(f"{REMOTE_BASE}/{remote_dir}")
            
            # Upload file
            try:
                print(f"Uploading priority: {file_path}")
                with open(local_file, 'rb') as f:
                    ftps.storbinary(f'STOR {os.path.basename(file_path)}', f)
                uploaded += 1
            except Exception as e:
                print(f"Error uploading {file_path}: {e}")
    
    return uploaded

def main():
    """Deploy priority files"""
    try:
        print("Connecting to FTPS server...")
        ftps = ftplib.FTP_TLS()
        ftps.connect(FTPS_HOST, FTPS_PORT)
        ftps.login(FTPS_USER, FTPS_PASS)
        ftps.prot_p()
        
        print("Uploading priority files...")
        count = upload_priority_files(ftps)
        
        print(f"\nUploaded {count} priority files")
        ftps.quit()
        
        # Verify deployment
        import subprocess
        print("\nVerifying deployment...")
        result = subprocess.run(
            ["curl", "-k", "-s", "https://aze.mikropartner.de/api/health.php"],
            capture_output=True,
            text=True
        )
        print("Health check:", "OK" if "healthy" in result.stdout else "FAILED")
        
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    main()
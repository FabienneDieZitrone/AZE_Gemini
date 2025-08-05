#!/usr/bin/env python3
"""
Deploy essential updated files
"""

import ftplib
import os
from pathlib import Path

FTPS_HOST = "wp10454681.server-he.de"
FTPS_USER = "ftp10454681-aze"
FTPS_PASS = "321Start321"
FTPS_PORT = 21

def deploy_essential():
    """Deploy essential files"""
    try:
        print("Connecting to FTPS...")
        ftps = ftplib.FTP_TLS()
        ftps.connect(FTPS_HOST, FTPS_PORT)
        ftps.login(FTPS_USER, FTPS_PASS)
        ftps.prot_p()
        
        # Navigate to web root
        ftps.cwd('/www/aze')
        print(f"Current directory: {ftps.pwd()}")
        
        # Create api directory if needed
        try:
            ftps.mkd('api')
        except:
            pass
        
        # Upload critical API files
        api_files = [
            ('build/api/timer-control.php', 'api/timer-control.php'),
            ('build/api/constants.php', 'api/constants.php'),
            ('build/api/auth_helpers.php', 'api/auth_helpers.php'),
        ]
        
        for local, remote in api_files:
            if os.path.exists(local):
                print(f"Uploading {local} -> {remote}")
                with open(local, 'rb') as f:
                    ftps.storbinary(f'STOR {remote}', f)
        
        # Create src directory structure
        try:
            ftps.mkd('src')
            ftps.mkd('src/hooks')
            ftps.mkd('src/components')
            ftps.mkd('src/views')
            ftps.mkd('src/utils')
        except:
            pass
        
        # Upload source files
        src_files = [
            ('build/src/constants.ts', 'src/constants.ts'),
            ('build/src/hooks/useTimer.ts', 'src/hooks/useTimer.ts'),
            ('build/src/components/TimerService.tsx', 'src/components/TimerService.tsx'),
            ('build/src/views/MainAppView.tsx', 'src/views/MainAppView.tsx'),
            ('build/src/views/DashboardView.tsx', 'src/views/DashboardView.tsx'),
        ]
        
        for local, remote in src_files:
            if os.path.exists(local):
                print(f"Uploading {local} -> {remote}")
                with open(local, 'rb') as f:
                    ftps.storbinary(f'STOR {remote}', f)
        
        # Upload dist files if they exist
        try:
            ftps.cwd('/www/aze')
            ftps.mkd('dist')
            ftps.mkd('dist/assets')
        except:
            pass
        
        dist_files = [
            ('build/dist/index.html', 'dist/index.html'),
        ]
        
        for local, remote in dist_files:
            if os.path.exists(local):
                print(f"Uploading {local} -> {remote}")
                with open(local, 'rb') as f:
                    ftps.storbinary(f'STOR {remote}', f)
        
        # Check for dist assets
        dist_assets = Path('build/dist/assets')
        if dist_assets.exists():
            for asset in dist_assets.glob('*'):
                if asset.is_file():
                    remote_path = f'dist/assets/{asset.name}'
                    print(f"Uploading {asset} -> {remote_path}")
                    with open(asset, 'rb') as f:
                        ftps.storbinary(f'STOR {remote_path}', f)
        
        ftps.quit()
        print("\nEssential files deployed!")
        
        # Test
        import subprocess
        result = subprocess.run(
            ["curl", "-k", "-s", "https://aze.mikropartner.de/api/health.php"],
            capture_output=True,
            text=True
        )
        if "healthy" in result.stdout:
            print("✅ Health check: OK")
        else:
            print("❌ Health check: FAILED")
            print(result.stdout)
        
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    deploy_essential()
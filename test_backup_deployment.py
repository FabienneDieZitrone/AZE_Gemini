#!/usr/bin/env python3
"""
Test backup deployment - check current status and plan deployment
"""

import ftplib
import ssl
import tempfile
import os
from datetime import datetime

# FTP Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"
PROD_PATH = "/"

def check_backup_status():
    """Check current backup system status on production server"""
    print("🔍 Checking current backup system status...")
    
    # Create SSL context
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        # Connect to FTPS
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()
        print(f"✅ Connected to {FTP_HOST}")
        
        status = {
            'scripts_exist': False,
            'backup_dir_exists': False,
            'scripts_count': 0,
            'scripts_list': [],
            'setup_script_exists': False
        }
        
        # Check if /scripts/backup directory exists
        try:
            ftp.cwd("/scripts/backup")
            status['backup_dir_exists'] = True
            
            # List backup scripts
            files = []
            ftp.retrlines('LIST', files.append)
            
            for file_info in files:
                parts = file_info.split()
                if len(parts) >= 9:
                    filename = ' '.join(parts[8:])
                    if filename.endswith('.sh'):
                        status['scripts_list'].append(filename)
                        status['scripts_count'] += 1
            
            status['scripts_exist'] = status['scripts_count'] > 0
            
        except Exception as e:
            print(f"❌ /scripts/backup directory not accessible: {e}")
        
        # Check if setup script exists in root
        try:
            ftp.cwd("/")
            files = []
            ftp.retrlines('LIST', files.append)
            
            for file_info in files:
                filename = ' '.join(file_info.split()[8:])
                if 'setup-backup.sh' in filename:
                    status['setup_script_exists'] = True
                    break
                    
        except Exception as e:
            print(f"❌ Cannot check root directory: {e}")
        
        ftp.quit()
        return status
        
    except Exception as e:
        print(f"❌ Connection error: {e}")
        return None

def compare_local_remote_scripts():
    """Compare local backup scripts with remote ones"""
    print("\n📋 Comparing local and remote backup scripts...")
    
    local_scripts_dir = "scripts/backup"
    if not os.path.exists(local_scripts_dir):
        print(f"❌ Local scripts directory '{local_scripts_dir}' not found")
        return
    
    # List local scripts
    local_scripts = [f for f in os.listdir(local_scripts_dir) if f.endswith('.sh')]
    print(f"📁 Local scripts ({len(local_scripts)}): {', '.join(local_scripts)}")
    
    # Get remote scripts
    status = check_backup_status()
    if status and status['scripts_exist']:
        print(f"🌐 Remote scripts ({status['scripts_count']}): {', '.join(status['scripts_list'])}")
        
        # Compare
        local_set = set(local_scripts)
        remote_set = set(status['scripts_list'])
        
        if local_set == remote_set:
            print("✅ Local and remote scripts match perfectly")
        else:
            missing_remote = local_set - remote_set
            extra_remote = remote_set - local_set
            
            if missing_remote:
                print(f"📤 Scripts to upload: {', '.join(missing_remote)}")
            if extra_remote:
                print(f"📥 Extra scripts on server: {', '.join(extra_remote)}")
    else:
        print("❌ No remote scripts found or connection failed")

def create_deployment_plan():
    """Create a deployment plan based on current status"""
    print("\n📋 Creating deployment plan...")
    
    status = check_backup_status()
    if not status:
        print("❌ Cannot create deployment plan - connection failed")
        return
    
    plan = []
    
    if not status['backup_dir_exists']:
        plan.append("1. Create /scripts/backup directory")
    
    if not status['scripts_exist'] or status['scripts_count'] < 3:
        plan.append("2. Upload backup scripts (mysql-backup.sh, mysql-restore.sh, backup-monitor.sh)")
    
    if not status['setup_script_exists']:
        plan.append("3. Create and upload setup-backup.sh script")
    
    if status['scripts_exist']:
        plan.append("4. Compare and update existing scripts if needed")
    
    plan.append("5. Set executable permissions on all scripts")
    plan.append("6. Create deployment documentation")
    
    print("📋 Deployment Plan:")
    for item in plan:
        print(f"   {item}")
    
    return plan

def main():
    print("=== Backup Deployment Test & Analysis ===")
    print(f"Target: {FTP_HOST}{PROD_PATH}")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("")
    
    # Check current status
    status = check_backup_status()
    
    if status:
        print("\n📊 Current Status:")
        print(f"   Scripts directory exists: {'✅' if status['backup_dir_exists'] else '❌'}")
        print(f"   Backup scripts exist: {'✅' if status['scripts_exist'] else '❌'}")
        print(f"   Number of scripts: {status['scripts_count']}")
        print(f"   Setup script exists: {'✅' if status['setup_script_exists'] else '❌'}")
        
        if status['scripts_list']:
            print(f"   Scripts found: {', '.join(status['scripts_list'])}")
    
    # Compare local vs remote
    compare_local_remote_scripts()
    
    # Create deployment plan
    create_deployment_plan()
    
    print("\n" + "="*50)
    print("✅ ANALYSIS COMPLETE")
    print("="*50)
    print("\nRecommendation:")
    if status and status['scripts_exist']:
        print("• Backup scripts already exist on server")
        print("• Consider updating only if local scripts are newer")
        print("• Focus on configuration and testing")
    else:
        print("• Deploy backup scripts to production server")
        print("• Set up cron jobs and configuration")
        print("• Test backup functionality")

if __name__ == "__main__":
    main()
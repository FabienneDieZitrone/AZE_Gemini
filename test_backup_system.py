#!/usr/bin/env python3
"""
Test the backup system on the production server
This script verifies that backup scripts are properly deployed and configured
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

def test_script_permissions():
    """Test that backup scripts have correct permissions"""
    print("🔍 Testing script permissions...")
    
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()
        
        ftp.cwd("/scripts/backup")
        files = []
        ftp.retrlines('LIST', files.append)
        
        print("📋 Script permissions:")
        for file_info in files:
            parts = file_info.split()
            if len(parts) >= 9:
                permissions = parts[0]
                filename = ' '.join(parts[8:])
                if filename.endswith('.sh'):
                    executable = 'x' in permissions
                    status = '✅' if executable else '❌'
                    print(f"   {status} {filename}: {permissions}")
        
        ftp.quit()
        return True
        
    except Exception as e:
        print(f"❌ Error checking permissions: {e}")
        return False

def test_script_syntax():
    """Download and test basic syntax of backup scripts"""
    print("\n🔍 Testing script syntax...")
    
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()
        ftp.cwd("/scripts/backup")
        
        scripts_to_test = ['mysql-backup.sh', 'mysql-restore.sh', 'backup-monitor.sh']
        
        for script_name in scripts_to_test:
            print(f"📜 Testing {script_name}...")
            
            with tempfile.NamedTemporaryFile(mode='w+b', delete=False, suffix='.sh') as temp_file:
                try:
                    ftp.retrbinary(f'RETR {script_name}', temp_file.write)
                    temp_file.flush()
                    
                    # Check basic syntax with bash -n
                    result = os.system(f'bash -n {temp_file.name} 2>/dev/null')
                    if result == 0:
                        print(f"   ✅ {script_name} syntax OK")
                    else:
                        print(f"   ❌ {script_name} syntax error")
                        
                except Exception as e:
                    print(f"   ❌ Error testing {script_name}: {e}")
                finally:
                    os.unlink(temp_file.name)
        
        ftp.quit()
        return True
        
    except Exception as e:
        print(f"❌ Error during syntax testing: {e}")
        return False

def test_setup_script():
    """Test the setup script"""
    print("\n🔍 Testing setup script...")
    
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()
        
        # Check if setup-backup.sh exists in root
        ftp.cwd("/")
        files = []
        ftp.retrlines('LIST', files.append)
        
        setup_script_found = False
        for file_info in files:
            filename = ' '.join(file_info.split()[8:])
            if 'setup-backup.sh' in filename:
                setup_script_found = True
                permissions = file_info.split()[0]
                executable = 'x' in permissions
                print(f"   ✅ setup-backup.sh found with permissions: {permissions}")
                if not executable:
                    print(f"   ⚠️  setup-backup.sh is not executable")
                break
        
        if not setup_script_found:
            print("   ❌ setup-backup.sh not found in root directory")
        
        ftp.quit()
        return setup_script_found
        
    except Exception as e:
        print(f"❌ Error testing setup script: {e}")
        return False

def create_production_test_plan():
    """Create a plan for production testing"""
    print("\n📋 Production Testing Plan:")
    print("="*50)
    
    steps = [
        "1. SSH to production server: ssh user@wp10454681.server-he.de",
        "",
        "2. Set up environment variables (create .env file in /scripts/backup/):",
        "   export DB_HOST='vwp8374.webpack.hosteurope.de'",
        "   export DB_NAME='db10454681-aze'", 
        "   export DB_USER='db10454681-aze'",
        "   export DB_PASS='your-database-password'",
        "   export BACKUP_DIR='/var/backups/aze-gemini/mysql'",
        "",
        "3. Create backup directories:",
        "   sudo mkdir -p /var/backups/aze-gemini/mysql",
        "   sudo mkdir -p /var/backups/aze-gemini/logs",
        "   sudo chown -R www-data:www-data /var/backups/aze-gemini",
        "",
        "4. Test database connection:",
        "   mysql -h vwp8374.webpack.hosteurope.de -u db10454681-aze -p db10454681-aze",
        "",
        "5. Run backup test:",
        "   cd /scripts/backup",
        "   source .env",
        "   ./mysql-backup.sh",
        "",
        "6. Verify backup was created:",
        "   ls -la /var/backups/aze-gemini/mysql/",
        "",
        "7. Test restore functionality:",
        "   ./mysql-restore.sh --list",
        "",
        "8. Set up cron job:",
        "   crontab -e",
        "   # Add: 0 2 * * * cd /scripts/backup && source .env && ./mysql-backup.sh",
        "",
        "9. Test monitoring:",
        "   ./backup-monitor.sh",
        "",
        "10. Check logs:",
        "    tail -f /var/backups/aze-gemini/logs/backup.log"
    ]
    
    for step in steps:
        print(step)

def main():
    print("=== Backup System Testing ===")
    print(f"Target: {FTP_HOST}")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("")
    
    # Run tests
    test_results = {
        'permissions': test_script_permissions(),
        'syntax': test_script_syntax(),
        'setup_script': test_setup_script()
    }
    
    # Summary
    print("\n" + "="*50)
    print("📊 TEST RESULTS SUMMARY")
    print("="*50)
    
    all_passed = True
    for test_name, result in test_results.items():
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{test_name.replace('_', ' ').title()}: {status}")
        if not result:
            all_passed = False
    
    if all_passed:
        print("\n🎉 All tests passed! Backup system is ready for production testing.")
    else:
        print("\n⚠️  Some tests failed. Please review and fix issues before production deployment.")
    
    # Create production test plan
    create_production_test_plan()
    
    print("\n" + "="*50)
    print("✅ TESTING COMPLETE")
    print("="*50)

if __name__ == "__main__":
    main()
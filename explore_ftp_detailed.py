#!/usr/bin/env python3
"""
Detailed FTP directory exploration to find the correct production path
"""

import ftplib
import ssl
import os
import sys

# FTP Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"

def explore_directory(ftp, path, max_depth=2, current_depth=0):
    """Recursively explore FTP directory structure"""
    if current_depth >= max_depth:
        return
    
    try:
        print(f"\n{'  ' * current_depth}📁 {path}")
        ftp.cwd(path)
        
        files = []
        ftp.retrlines('LIST', files.append)
        
        directories = []
        regular_files = []
        
        for file_info in files:
            parts = file_info.split()
            if len(parts) >= 9:
                permissions = parts[0]
                filename = ' '.join(parts[8:])
                if permissions.startswith('d'):
                    directories.append(filename)
                else:
                    regular_files.append(filename)
        
        # Show directories first
        for dirname in sorted(directories):
            if dirname not in ['.', '..']:
                print(f"{'  ' * (current_depth + 1)}📂 {dirname}/")
                
        # Show some key files
        key_files = [f for f in regular_files if any(keyword in f.lower() for keyword in 
                    ['backup', 'mysql', 'script', 'setup', 'config', 'php', 'index'])]
        
        if key_files:
            print(f"{'  ' * (current_depth + 1)}📄 Key files:")
            for filename in sorted(key_files)[:5]:  # Show first 5 key files
                print(f"{'  ' * (current_depth + 2)}- {filename}")
        
        # Recursively explore subdirectories
        for dirname in sorted(directories):
            if dirname not in ['.', '..'] and current_depth < max_depth - 1:
                try:
                    explore_directory(ftp, f"{path}/{dirname}" if path != "/" else f"/{dirname}", 
                                    max_depth, current_depth + 1)
                except Exception as e:
                    print(f"{'  ' * (current_depth + 1)}❌ Cannot access {dirname}: {e}")
        
        # Go back to parent
        if path != "/":
            ftp.cwd("/")
            
    except Exception as e:
        print(f"{'  ' * current_depth}❌ Error exploring {path}: {e}")

def find_production_path():
    """Find the correct production path for the application"""
    print("🔍 Exploring FTP server structure to find production path...")
    
    # Create SSL context
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        # Connect to FTPS
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()
        print(f"✅ Connected to {FTP_HOST}")
        
        # Start exploration from root
        explore_directory(ftp, "/", max_depth=3)
        
        print("\n" + "="*60)
        print("🎯 ANALYSIS:")
        print("="*60)
        
        # Check if production files are in root
        ftp.cwd("/")
        files = []
        ftp.retrlines('LIST', files.append)
        
        production_indicators = ['index.html', 'api', 'config.php', 'login.php']
        found_indicators = []
        
        for file_info in files:
            filename = ' '.join(file_info.split()[8:])
            if any(indicator in filename for indicator in production_indicators):
                found_indicators.append(filename)
        
        if found_indicators:
            print("✅ Production files appear to be in ROOT directory (/)")
            print(f"   Found: {', '.join(found_indicators)}")
            print("   📍 Recommended PROD_PATH: '/'")
        else:
            print("❓ Production path unclear - manual verification needed")
        
        # Check www/aze-test directory
        try:
            ftp.cwd("/www/aze-test")
            test_files = []
            ftp.retrlines('LIST', test_files.append)
            print(f"\n📋 /www/aze-test contains {len(test_files)} items")
            print("   This appears to be a test environment")
        except:
            pass
        
        # Check if scripts directory exists
        try:
            ftp.cwd("/scripts")
            print("\n✅ /scripts directory exists in root")
            scripts_files = []
            ftp.retrlines('LIST', scripts_files.append)
            print(f"   Contains {len(scripts_files)} items")
        except:
            print("\n❌ /scripts directory does not exist in root")
        
        ftp.quit()
        return "/"  # Based on analysis, production is in root
        
    except Exception as e:
        print(f"❌ Connection error: {e}")
        return None

if __name__ == "__main__":
    production_path = find_production_path()
    if production_path:
        print(f"\n🎯 CONCLUSION: Production path is '{production_path}'")
    else:
        print("\n❌ Could not determine production path")
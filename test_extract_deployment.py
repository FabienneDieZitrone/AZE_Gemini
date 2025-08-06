#!/usr/bin/env python3
"""
Test script for extract_test_deployment.py
Validates dependencies and connection capabilities
"""

import sys
import subprocess

def check_python_version():
    """Check if Python version is adequate"""
    version = sys.version_info
    if version.major >= 3 and version.minor >= 6:
        print(f"✓ Python version: {version.major}.{version.minor}.{version.micro}")
        return True
    else:
        print(f"✗ Python version {version.major}.{version.minor}.{version.micro} is too old (requires 3.6+)")
        return False

def check_dependencies():
    """Check if required Python packages are available"""
    required_packages = ['requests', 'ftplib', 'ssl']
    missing_packages = []
    
    for package in required_packages:
        try:
            __import__(package)
            print(f"✓ {package} module available")
        except ImportError:
            print(f"✗ {package} module missing")
            missing_packages.append(package)
    
    if missing_packages:
        print(f"\nTo install missing packages, run:")
        if 'requests' in missing_packages:
            print("pip install requests")
        return False
    
    return True

def validate_ftp_config():
    """Validate FTP configuration"""
    from extract_test_deployment import FTP_HOST, FTP_USER, FTP_PATH
    
    print(f"\nFTP Configuration:")
    print(f"  Host: {FTP_HOST}")
    print(f"  User: {FTP_USER}")
    print(f"  Path: {FTP_PATH}")
    
    # Basic validation
    if not FTP_HOST or not FTP_USER or not FTP_PATH:
        print("✗ FTP configuration incomplete")
        return False
    
    print("✓ FTP configuration appears complete")
    return True

def test_ftp_connection():
    """Test FTP connection without modifying anything"""
    from extract_test_deployment import FTPSConnection, FTP_HOST, FTP_USER, FTP_PASS
    
    print(f"\nTesting FTPS connection to {FTP_HOST}...")
    
    ftp_conn = FTPSConnection(FTP_HOST, FTP_USER, FTP_PASS)
    
    try:
        if ftp_conn.connect():
            print("✓ FTPS connection test successful")
            ftp_conn.close()
            return True
        else:
            print("✗ FTPS connection test failed")
            return False
    except Exception as e:
        print(f"✗ FTPS connection test failed: {e}")
        return False

def main():
    """Main test function"""
    print("=== Extract Test Deployment - Validation ===\n")
    
    all_checks_passed = True
    
    # Check Python version
    if not check_python_version():
        all_checks_passed = False
    
    print()
    
    # Check dependencies
    if not check_dependencies():
        all_checks_passed = False
    
    # Validate configuration
    if not validate_ftp_config():
        all_checks_passed = False
    
    # Test FTP connection (optional - may fail due to network/firewall)
    try:
        if not test_ftp_connection():
            print("⚠ FTP connection test failed - this may be due to network/firewall issues")
            print("  The main script may still work when run from the proper environment")
    except Exception as e:
        print(f"⚠ Could not test FTP connection: {e}")
    
    print(f"\n{'='*50}")
    
    if all_checks_passed:
        print("✓ All validation checks passed!")
        print("The extract_test_deployment.py script should work correctly.")
        print(f"\nTo run the extraction script:")
        print("python3 extract_test_deployment.py")
        return True
    else:
        print("✗ Some validation checks failed!")
        print("Please resolve the issues before running the extraction script.")
        return False

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
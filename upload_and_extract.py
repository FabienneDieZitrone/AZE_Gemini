#!/usr/bin/env python3
"""
Upload and Extract Script

This script:
1. Uploads simple_extract.php to the FTP server at /www/aze-test/
2. Makes an HTTP request to execute it
3. Verifies the extraction was successful
"""

import ftplib
import requests
import ssl
import os
from urllib.parse import urljoin

# FTPS Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"
FTP_TARGET_DIR = "/www/aze-test/"

# HTTP Configuration
HTTP_BASE_URL = "https://aze.mikropartner.de/aze-test/"

def upload_file_ftps():
    """Upload simple_extract.php to the FTP server using FTPS."""
    print("Connecting to FTPS server...")
    
    # Create SSL context
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        # Connect to FTPS server
        ftps = ftplib.FTP_TLS(context=context)
        ftps.connect(FTP_HOST, 21)
        ftps.login(FTP_USER, FTP_PASS)
        ftps.prot_p()  # Enable data encryption
        
        print(f"Connected to {FTP_HOST}")
        
        # Change to target directory
        ftps.cwd(FTP_TARGET_DIR)
        print(f"Changed to directory: {FTP_TARGET_DIR}")
        
        # Upload the PHP file
        local_file = "simple_extract.php"
        if not os.path.exists(local_file):
            raise FileNotFoundError(f"Local file {local_file} not found")
        
        with open(local_file, 'rb') as file:
            ftps.storbinary(f'STOR {local_file}', file)
        
        print(f"Successfully uploaded {local_file}")
        
        # List files to verify upload
        print("\nFiles in target directory:")
        ftps.retrlines('LIST')
        
        ftps.quit()
        return True
        
    except Exception as e:
        print(f"FTPS upload failed: {e}")
        return False

def execute_extraction():
    """Execute the PHP script via HTTP request."""
    print("\nExecuting extraction script...")
    
    script_url = urljoin(HTTP_BASE_URL, "simple_extract.php")
    
    try:
        response = requests.get(script_url, timeout=30, verify=False)
        response.raise_for_status()
        
        print(f"HTTP Status: {response.status_code}")
        print("Script output:")
        print(response.text)
        
        # Check if extraction was successful
        if "Extraction successful" in response.text and "Done!" in response.text:
            return True
        else:
            print("Extraction may have failed - check output above")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"HTTP request failed: {e}")
        return False

def verify_extraction():
    """Verify that the extraction was successful by checking for expected files."""
    print("\nVerifying extraction...")
    
    # Try to access a common file that should exist after extraction
    test_urls = [
        urljoin(HTTP_BASE_URL, "index.php"),
        urljoin(HTTP_BASE_URL, "config.php"),
        urljoin(HTTP_BASE_URL, "src/"),
    ]
    
    for url in test_urls:
        try:
            response = requests.head(url, timeout=10)
            if response.status_code in [200, 403]:  # 403 is OK for directories
                print(f"✓ Found: {url} (Status: {response.status_code})")
                return True
        except requests.exceptions.RequestException:
            continue
    
    print("Could not verify extracted files - they may not be accessible via HTTP")
    return False

def main():
    """Main execution flow."""
    print("=== Upload and Extract Script ===\n")
    
    # Step 1: Upload the PHP file
    if not upload_file_ftps():
        print("Upload failed. Exiting.")
        return False
    
    # Step 2: Execute the extraction
    if not execute_extraction():
        print("Extraction execution failed.")
        return False
    
    # Step 3: Verify extraction
    verify_extraction()
    
    print("\n=== Process completed ===")
    return True

if __name__ == "__main__":
    main()
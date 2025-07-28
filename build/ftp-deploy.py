#!/usr/bin/env python3
"""
FTP Deployment Script for AZE_Gemini
"""

import ftplib
import os
import sys
from pathlib import Path

FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "10454681-ftpaze"
FTP_PASS = "Start321"
FTP_PATH = "/aze/api"

def deploy_files():
    try:
        # Connect to FTP server
        print(f"Connecting to {FTP_HOST}...")
        ftp = ftplib.FTP_TLS(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()  # Enable encryption
        
        print("Connected successfully!")
        
        # Change to target directory
        try:
            ftp.cwd(FTP_PATH)
        except:
            print(f"Creating directory {FTP_PATH}...")
            ftp.mkd(FTP_PATH)
            ftp.cwd(FTP_PATH)
        
        # Upload files
        deploy_dir = Path("./deploy_20250728_171752/api")
        for file_path in deploy_dir.glob("*.php"):
            print(f"Uploading {file_path.name}...")
            with open(file_path, 'rb') as f:
                ftp.storbinary(f'STOR {file_path.name}', f)
            print(f"✓ {file_path.name} uploaded")
        
        # Create logs directory
        try:
            ftp.cwd("/aze")
            ftp.mkd("logs")
            print("✓ logs directory created")
        except:
            print("logs directory already exists")
        
        ftp.quit()
        print("\n✅ Deployment successful!")
        return True
        
    except Exception as e:
        print(f"\n❌ Error: {str(e)}")
        return False

if __name__ == "__main__":
    deploy_files()
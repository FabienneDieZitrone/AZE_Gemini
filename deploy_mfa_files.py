#!/usr/bin/env python3
"""
Direct MFA file deployment to test environment
"""

import ftplib
import ssl
from datetime import datetime

# FTP Configuration
FTP_HOST = 'wp10454681.server-he.de'
FTP_USER = 'ftp10454681-aze'
FTP_PASS = '321Start321'
TEST_PATH = '/www/aze-test/'

# Connect to FTP
print("🚀 Deploying MFA files to test environment...")
ftp = ftplib.FTP_TLS(FTP_HOST)
ftp.login(FTP_USER, FTP_PASS)
ftp.prot_p()

# Create required directories
directories = ['/www/aze-test/api/', '/www/aze-test/database/', '/www/aze-test/src/components/auth/']

for dir_path in directories:
    try:
        ftp.mkd(dir_path)
        print(f"📁 Created: {dir_path}")
    except:
        pass  # Directory may already exist

# Upload MFA files
files_deployed = 0

# Upload API files
try:
    with open('api/mfa-setup.php', 'rb') as f:
        ftp.storbinary('STOR /www/aze-test/api/mfa-setup.php', f)
    print("✅ Uploaded: api/mfa-setup.php")
    files_deployed += 1
except Exception as e:
    print(f"❌ Failed: api/mfa-setup.php - {e}")

try:
    with open('api/mfa-verify.php', 'rb') as f:
        ftp.storbinary('STOR /www/aze-test/api/mfa-verify.php', f)
    print("✅ Uploaded: api/mfa-verify.php")
    files_deployed += 1
except Exception as e:
    print(f"❌ Failed: api/mfa-verify.php - {e}")

# Upload config
try:
    with open('mfa-config.php', 'rb') as f:
        ftp.storbinary('STOR /www/aze-test/config/mfa.php', f)
    print("✅ Uploaded: config/mfa.php")
    files_deployed += 1
except Exception as e:
    print(f"❌ Failed: config/mfa.php - {e}")

# Upload database schema
try:
    with open('database/mfa_schema.sql', 'rb') as f:
        ftp.storbinary('STOR /www/aze-test/database/mfa_schema.sql', f)
    print("✅ Uploaded: database/mfa_schema.sql")
    files_deployed += 1
except Exception as e:
    print(f"❌ Failed: database/mfa_schema.sql - {e}")

# Upload React component
try:
    with open('src/components/auth/MFASetup.tsx', 'rb') as f:
        ftp.storbinary('STOR /www/aze-test/src/components/auth/MFASetup.tsx', f)
    print("✅ Uploaded: src/components/auth/MFASetup.tsx")
    files_deployed += 1
except Exception as e:
    print(f"❌ Failed: src/components/auth/MFASetup.tsx - {e}")

# Create deployment marker
timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
marker_content = f"""MFA Deployment Status
====================
Timestamp: {timestamp}
Issue: #115 - Multi-Factor Authentication
Files Deployed: {files_deployed}/5

Next Steps:
1. Run database migration: mfa_schema.sql
2. Update login.php to include MFA check
3. Build React components
4. Test with admin account
"""

with open('MFA_TEST_STATUS.txt', 'w') as f:
    f.write(marker_content)

with open('MFA_TEST_STATUS.txt', 'rb') as f:
    ftp.storbinary('STOR /www/aze-test/MFA_TEST_STATUS.txt', f)

ftp.quit()

print(f"\n✅ MFA deployment complete!")
print(f"📊 Deployed {files_deployed} files")
print(f"🔗 Test URL: https://aze.mikropartner.de/aze-test/")
print(f"\n📝 Database migration required!")
print(f"   Execute: database/mfa_schema.sql")
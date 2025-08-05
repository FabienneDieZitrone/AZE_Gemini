# Deployment Environment Setup Guide

## Overview
This guide explains how to securely configure environment variables for deploying the AZE Gemini application. All deployment scripts have been updated to use environment variables instead of hardcoded credentials.

## Security Notice
⚠️ **NEVER commit credentials to version control!**
- The `.env` file is already in `.gitignore`
- Always use environment variables for sensitive data
- Rotate credentials regularly

## Required Environment Variables

### 1. FTP Deployment Credentials
```bash
# Required for all deployment scripts
export FTP_HOST="wp10454681.server-he.de"
export FTP_USER="ftp10454681-aze"
export FTP_PASS="your-actual-password-here"

# Optional path configurations (defaults shown)
export FTP_PROD_PATH="/www/aze/"
export FTP_TEST_PATH="/www/aze-test/"
```

### 2. Setup Methods

#### Method A: Using .env file (Recommended for Development)
1. Copy the example file:
   ```bash
   cp .env.example .env
   ```

2. Edit `.env` and fill in your actual credentials:
   ```bash
   nano .env
   ```

3. Load the environment variables:
   ```bash
   source .env
   ```

#### Method B: Direct Export (Recommended for CI/CD)
```bash
# Set variables in your shell or CI/CD pipeline
export FTP_PASS="your-actual-password"
export DB_PASS="your-database-password"
# ... other variables
```

#### Method C: Using a secure script
Create a file `load-env.sh` (DO NOT commit this!):
```bash
#!/bin/bash
export FTP_HOST="wp10454681.server-he.de"
export FTP_USER="ftp10454681-aze"
export FTP_PASS="your-actual-password"
```

Then load it when needed:
```bash
source load-env.sh
```

## Updated Deployment Scripts

All deployment scripts now require environment variables:

1. **deploy_production_final.py** - Production deployment
2. **deploy_production_fixes.py** - Security fixes deployment  
3. **deploy_test_complete.py** - Test environment deployment
4. **deploy_direct_ftps.py** - Direct file transfer
5. **deploy_essential.py** - Essential files only
6. **check_ftp_structure.py** - FTP structure verification
7. **test-ftp-connection.sh** - Connection testing

### Running Deployment Scripts

```bash
# First, ensure environment variables are set
source .env  # or use your preferred method

# Then run the deployment script
python deploy_production_final.py

# For shell scripts
./test-ftp-connection.sh
```

## Troubleshooting

### Error: "FTP_PASS environment variable is not set!"
This means the required environment variable is missing. Solution:
```bash
export FTP_PASS="your-password-here"
```

### Verifying Variables are Set
```bash
# Check if a variable is set
echo $FTP_PASS

# List all FTP-related variables
env | grep FTP_
```

## Best Practices

1. **Never hardcode credentials** in scripts
2. **Use different credentials** for test and production
3. **Rotate passwords regularly**
4. **Use a password manager** for team credential sharing
5. **Document which variables are required** for each environment

## CI/CD Integration

For GitHub Actions or other CI/CD systems:

1. Add secrets to your repository settings
2. Reference them in your workflow:
   ```yaml
   env:
     FTP_PASS: ${{ secrets.FTP_PASS }}
     FTP_USER: ${{ secrets.FTP_USER }}
   ```

## Security Checklist

- [ ] All scripts use environment variables
- [ ] No credentials in version control
- [ ] `.env` file is in `.gitignore`
- [ ] Production credentials are different from test
- [ ] Team knows how to set environment variables
- [ ] CI/CD uses secure secret storage

---

**Last Updated**: 05.08.2025
**Related Issue**: #31 - Secure hardcoded credentials
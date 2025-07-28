# 🔒 Secure Deployment Guide - AZE_Gemini

## Overview

This guide explains how to deploy AZE_Gemini securely without exposing credentials.

## ⚠️ Security First

**NEVER** commit credentials to Git. This includes:
- FTP passwords
- Database passwords
- API keys
- OAuth secrets

## 🚀 Deployment Setup

### 1. Initial Setup (Once per environment)

```bash
# Copy the example environment file
cp .env.example .env.local

# Edit .env.local with your credentials
nano .env.local
```

Add your actual credentials to `.env.local`:
```
FTP_HOST=wp10454681.server-he.de
FTP_USER=ftp10454681-aze
FTP_PASSWORD=your-actual-password
```

### 2. Deploy

```bash
# Deploy everything
./deploy-secure.sh

# Deploy only frontend
./deploy-secure.sh frontend

# Deploy only backend
./deploy-secure.sh backend
```

## 🔐 For Future Sessions

### Option 1: Environment File (Recommended)
1. Create `.env.local` with credentials
2. The deploy script will automatically load it
3. Delete `.env.local` after deployment if in shared environment

### Option 2: Environment Variables
```bash
export FTP_HOST="wp10454681.server-he.de"
export FTP_USER="ftp10454681-aze"
export FTP_PASSWORD="your-password"
./deploy-secure.sh
```

### Option 3: Interactive Input
```bash
# The script will prompt for missing credentials
./deploy-secure.sh
```

## 🛡️ Security Checklist

- [ ] `.env.local` is in `.gitignore`
- [ ] No passwords in any committed files
- [ ] Pre-commit hook is active
- [ ] Credentials are obtained from secure channel
- [ ] Deployment logs don't contain passwords

## 📋 Credential Storage Options

### For CI/CD (GitHub Actions)
Use repository secrets:
1. Go to Settings → Secrets → Actions
2. Add: `FTP_USER`, `FTP_PASS`
3. Use in workflow: `${{ secrets.FTP_USER }}`

### For Local Development
Use `.env.local` file (never commit!)

### For Production Deployment
Use environment variables or secret management service

## 🚨 If Credentials Are Exposed

1. **Immediately** change the FTP password
2. Check server logs for unauthorized access
3. Run Git history cleaning if needed
4. Notify the team

## 🔧 Troubleshooting

### "Missing required environment variables"
- Ensure `.env.local` exists and contains all variables
- Check file permissions: `chmod 600 .env.local`

### "Permission denied"
- Make script executable: `chmod +x deploy-secure.sh`

### "Failed to upload"
- Verify credentials are correct
- Check FTP server is accessible
- Ensure SSL/TLS is enabled

## 📝 Best Practices

1. **Rotate credentials regularly**
2. **Use strong, unique passwords**
3. **Monitor access logs**
4. **Keep `.env.local` permissions restrictive**
5. **Never share credentials via insecure channels**

## 🔍 Security Validation

Run this to check for exposed credentials:
```bash
# Check for passwords in Git history
git log -p | grep -E "(password|secret|token|321Start)"

# Check current files
grep -r "FTP_PASS" . --exclude-dir=node_modules --exclude-dir=.git
```

---

**Remember**: Security is everyone's responsibility. When in doubt, ask for help rather than risk exposure.
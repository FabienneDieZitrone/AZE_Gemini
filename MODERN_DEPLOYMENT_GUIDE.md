# 🚀 Modern Deployment Guide for AZE_Gemini

This comprehensive guide provides multiple secure deployment options to replace the failing FTP deployment system.

## 📋 Table of Contents

1. [Current Issue Analysis](#current-issue-analysis)
2. [Deployment Methods Overview](#deployment-methods-overview)
3. [SSH/SFTP Deployment (Recommended)](#sshsftp-deployment-recommended)
4. [GitHub Actions CI/CD](#github-actions-cicd)
5. [Git Webhook Deployment](#git-webhook-deployment)
6. [Docker Deployment](#docker-deployment)
7. [Manual Deployment Fallback](#manual-deployment-fallback)
8. [Security Best Practices](#security-best-practices)
9. [Troubleshooting](#troubleshooting)

## 🔍 Current Issue Analysis

### Problem Summary
- **FTP Authentication Failure**: Error 530 "Login incorrect" 
- **Server**: `ftp10454681-aze3@wp10454681.server-he.de`
- **Previous Success**: FTP worked successfully on 2025-07-29
- **SSL/TLS**: Connection and encryption working correctly
- **Root Cause**: Likely password change or account lockout

### Impact
- Automated deployments are blocked
- Manual deployment required
- Security concerns with FTP protocol

## 🎯 Deployment Methods Overview

| Method | Security | Automation | Complexity | Recommended Use |
|--------|----------|------------|------------|-----------------|
| SSH/SFTP | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | **Primary choice** |
| GitHub Actions | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | **CI/CD pipeline** |
| Git Webhooks | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | Server-side automation |
| Docker | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | Container deployment |
| Manual Upload | ⭐⭐ | ⭐ | ⭐ | Emergency fallback |

## 🔐 SSH/SFTP Deployment (Recommended)

### Overview
Secure deployment using SSH key authentication, eliminating password-based security risks.

### Setup Process

#### 1. Initial Setup
```bash
cd /app/projects/aze-gemini/build
chmod +x setup-ssh-deployment.sh
./setup-ssh-deployment.sh
```

#### 2. Configure SSH Keys
```bash
# Generate SSH key pair
./setup-ssh-deployment.sh ssh-key

# Add public key to server
# Copy the displayed public key to your server's ~/.ssh/authorized_keys
```

#### 3. Configure Deployment Settings
```bash
# Copy configuration template
cp .env.deployment.example .env.deployment

# Edit configuration
nano .env.deployment
```

**Required Settings:**
```bash
SSH_HOST=wp10454681.server-he.de
SSH_USER=wp10454681
SSH_PORT=22
REMOTE_PATH=/htdocs/aze
HEALTH_CHECK_URL=https://aze.mikropartner.de/api/health.php
```

#### 4. Test Connection
```bash
./setup-ssh-deployment.sh test
```

#### 5. Deploy
```bash
chmod +x deploy-secure-ssh.sh
./deploy-secure-ssh.sh        # Full deployment
./deploy-secure-ssh.sh frontend  # Frontend only
./deploy-secure-ssh.sh backend   # Backend only
```

### Benefits
- ✅ **Strong Security**: SSH key authentication
- ✅ **Encrypted Transfer**: All data encrypted in transit
- ✅ **Automated Backups**: Creates backups before deployment
- ✅ **Health Checks**: Verifies deployment success
- ✅ **Rollback Support**: Can rollback on failure

## 🔄 GitHub Actions CI/CD

### Overview
Fully automated deployment pipeline triggered by Git pushes.

### Features
- **Multi-stage deployment**: Build → Deploy → Verify
- **Multiple deployment methods**: SFTP, FTP fallback, manual packages
- **Artifact preservation**: Downloads for manual deployment
- **Health verification**: Automatic deployment validation

### Setup

#### 1. Repository Secrets
Add these secrets to your GitHub repository (Settings → Secrets and variables → Actions):

```
SSH_PRIVATE_KEY: [Your SSH private key content]
SSH_HOST: wp10454681.server-he.de
SSH_USER: wp10454681
HEALTH_CHECK_URL: https://aze.mikropartner.de/api/health.php

# FTP Fallback (optional)
FTP_HOST: wp10454681.server-he.de
FTP_USER: ftp10454681-aze3
FTP_PASS: [Your FTP password]
```

#### 2. Trigger Deployment
```bash
# Automatic on push to main branch
git push origin main

# Manual trigger via GitHub Actions UI
# Or commit with [deploy] tag
git commit -m "Update feature [deploy]"
git push
```

### Workflow Jobs

1. **build**: Compiles application and creates deployment package
2. **deploy-sftp**: Primary deployment via SSH/SFTP
3. **deploy-ftp-fallback**: Fallback to FTPS if SSH fails
4. **deploy-manual**: Creates manual deployment package

## 🎣 Git Webhook Deployment

### Overview
Server-side automation that deploys automatically when code is pushed to GitHub.

### Setup

#### 1. Server Installation
```bash
cd /app/projects/aze-gemini/build
chmod +x deploy-git-webhooks.sh
sudo ./deploy -git-webhooks.sh setup
```

#### 2. GitHub Webhook Configuration
1. Go to repository Settings → Webhooks
2. Add webhook:
   - **URL**: `http://your-server.com:9000/webhook`
   - **Content Type**: `application/json`
   - **Secret**: [Generated during setup]
   - **Events**: Just the push event

#### 3. Service Management
```bash
# Start service
sudo systemctl start aze-webhook

# Check status
sudo systemctl status aze-webhook

# View logs
sudo ./deploy-git-webhooks.sh logs
```

### Benefits
- ✅ **Zero-touch deployment**: Automatic on push
- ✅ **Server-side processing**: No external dependencies
- ✅ **Secure webhooks**: HMAC signature verification
- ✅ **Service monitoring**: Systemd service management

## 🐳 Docker Deployment

### Overview
Containerized deployment with full stack orchestration.

### Setup

#### 1. Initialize Docker Environment
```bash
cd /app/projects/aze-gemini/build
chmod +x deploy-docker.sh
./deploy-docker.sh init
```

#### 2. Configure Environment
```bash
# Edit configuration
nano .env
```

Set secure passwords:
```bash
MYSQL_ROOT_PASSWORD=secure_root_password
MYSQL_PASSWORD=secure_user_password
```

#### 3. Deploy Stack
```bash
./deploy-docker.sh deploy
```

### Services Included
- **Application**: PHP/Apache container with your app
- **Database**: MySQL 8.0 with automatic schema import
- **Reverse Proxy**: Nginx with SSL termination
- **Auto-updates**: Watchtower for automatic container updates

### Management Commands
```bash
./deploy-docker.sh status    # Check status
./deploy-docker.sh logs      # View logs
./deploy-docker.sh backup    # Create backup
./deploy-docker.sh restart   # Restart services
```

## 📋 Manual Deployment Fallback

### When to Use
- SSH/SFTP unavailable
- Emergency deployments
- First-time setup

### Process

#### 1. Build Locally
```bash
cd /app/projects/aze-gemini/build
npm ci
npm run build
```

#### 2. Create Deployment Package
```bash
# Create package directory
mkdir -p deployment-package

# Copy built files
cp -r dist/* deployment-package/
mkdir -p deployment-package/api
cp -r api/* deployment-package/api/
cp config.php deployment-package/ 2>/dev/null || true
cp schema.sql deployment-package/ 2>/dev/null || true

# Create archive
tar -czf deployment-package.tar.gz -C deployment-package .
```

#### 3. Upload Options

**Option A: Web File Manager**
1. Login to HostEurope control panel
2. Open file manager
3. Navigate to `/htdocs/aze/`
4. Upload and extract deployment package

**Option B: FTP Client**
```bash
# Using FileZilla, WinSCP, or command line
sftp wp10454681@wp10454681.server-he.de
cd /htdocs/aze
put -r deployment-package/*
```

**Option C: rsync (if SSH available)**
```bash
rsync -avz --delete deployment-package/ wp10454681@wp10454681.server-he.de:/htdocs/aze/
```

## 🔒 Security Best Practices

### 1. Authentication
- ✅ **SSH Keys**: Use Ed25519 keys for SSH authentication
- ✅ **Key Rotation**: Rotate keys regularly
- ❌ **Password Auth**: Disable password authentication where possible

### 2. Secrets Management
- ✅ **Environment Variables**: Use .env files for secrets
- ✅ **GitHub Secrets**: Store sensitive data in repository secrets
- ✅ **File Permissions**: Set restrictive permissions (600) on sensitive files
- ❌ **Hardcoded Secrets**: Never commit secrets to version control

### 3. Network Security
- ✅ **HTTPS Only**: All traffic over encrypted connections
- ✅ **Security Headers**: Implement CSP, HSTS, etc.
- ✅ **Firewall Rules**: Restrict access to deployment endpoints

### 4. Deployment Security
- ✅ **Signature Verification**: Verify webhook signatures
- ✅ **Backup Before Deploy**: Always create backups
- ✅ **Health Checks**: Verify deployment success
- ✅ **Rollback Capability**: Quick rollback on failures

## 🔧 Troubleshooting

### SSH Connection Issues

**Problem**: SSH connection refused
```bash
# Test SSH connectivity
ssh -v wp10454681@wp10454681.server-he.de

# Check if SSH is available on different port
nmap -p 22,2222,22000-22999 wp10454681.server-he.de
```

**Solution**: 
- Contact HostEurope support to confirm SSH availability
- Use alternative port if SSH runs on non-standard port
- Fallback to FTP/SFTP with password authentication

### FTP Authentication Issues

**Problem**: 530 Login incorrect
```bash
# Test current credentials
curl -v --user "ftp10454681-aze3:password" "ftp://wp10454681.server-he.de/"
```

**Solutions**:
1. **Reset Password**: Use HostEurope control panel
2. **Check Account Status**: Verify account isn't locked
3. **Try Different User**: Create new FTP user if needed

### Build Failures

**Problem**: npm build fails
```bash
# Clear cache and reinstall
rm -rf node_modules package-lock.json
npm cache clean --force
npm install
npm run build
```

### Health Check Failures

**Problem**: Deployment succeeds but health check fails
```bash
# Debug health endpoint
curl -v https://aze.mikropartner.de/api/health.php

# Check server logs
ssh wp10454681@wp10454681.server-he.de "tail -f /var/log/apache2/error.log"
```

### Permission Issues

**Problem**: Files uploaded but not accessible
```bash
# Fix permissions via SSH
ssh wp10454681@wp10454681.server-he.de << 'EOF'
cd /htdocs/aze
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 600 .env
EOF
```

## 📞 Support Contacts

### HostEurope Support
- **Control Panel**: Login to manage SSH keys and FTP accounts
- **Support Ticket**: For SSH availability and configuration
- **Documentation**: Check hosting package features

### Repository Maintainer
- **GitHub Issues**: Report deployment problems
- **Pull Requests**: Submit deployment improvements
- **Discussions**: Ask questions about deployment setup

## 🎉 Quick Start Checklist

### For SSH Deployment (Recommended)
- [ ] Run `./setup-ssh-deployment.sh`
- [ ] Add public key to server
- [ ] Configure `.env.deployment`
- [ ] Test with `./setup-ssh-deployment.sh test`
- [ ] Deploy with `./deploy-secure-ssh.sh`

### For GitHub Actions
- [ ] Add repository secrets
- [ ] Push to main branch or trigger manually
- [ ] Monitor workflow in Actions tab
- [ ] Verify deployment with health check

### For Emergency Manual Deployment
- [ ] Build locally with `npm run build`
- [ ] Create deployment package
- [ ] Upload via web interface or FTP client
- [ ] Set file permissions
- [ ] Test application

---

**Last Updated**: 2025-08-03  
**Version**: 1.0  
**Status**: Production Ready
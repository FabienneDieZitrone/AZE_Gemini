# HostEurope FTP Login Issue Analysis

## Executive Summary

**Critical Finding**: The production environment is configured with the wrong FTP username.
- **Current (WRONG)**: `ftp10454681-aze3` in `/app/build/.env.production`
- **Correct (WORKING)**: `ftp10454681-aze` (confirmed via KIS login)

## 1. What is HostEurope KIS?

**KIS (Kunden-Informations-System)** is HostEurope's Customer Information System - a web portal where customers:
- Manage hosting accounts and services
- Configure FTP users and passwords
- View/change contact data and billing
- Set up additional FTP accounts
- Monitor maintenance and security notices

Access: https://kis.hosteurope.de/

## 2. FTP Username Pattern at HostEurope

### Username Structure
```
ftp[package-number]-[custom-suffix]
```

- **Fixed Prefix**: `ftp10454681-` (based on hosting package number)
- **Custom Suffix**: User-defined 1-20 characters (e.g., `aze`, `aze2`, `aze3`)

### Main vs Additional FTP Users

**Main FTP User**:
- Created with hosting package
- Full webspace access
- Cannot be removed
- Usually has simpler suffix (e.g., `aze`)

**Additional FTP Users**:
- Created via KIS portal
- Can have restricted directory access
- Can be time-limited or IP-restricted
- Often numbered (e.g., `aze2`, `aze3`)

## 3. Timeline of FTP Username Evolution

Based on deployment documentation analysis:

1. **Initial Attempts**: 
   - `wp10454681` / `ftp10454681` (base username, failed)

2. **DEPLOYMENT_GUIDE.md**: 
   - `ftp10454681-aze2` (documented but possibly outdated)

3. **SUCCESSFUL_FTP_DEPLOYMENT.md**: 
   - `ftp10454681-aze3` (marked as successful on 25.07.2025)

4. **Current Reality** (confirmed by user):
   - `ftp10454681-aze` (working in KIS)

## 4. Why Different Username Variants Exist

### Possible Explanations:

1. **Multiple FTP Accounts Created**:
   - `aze` - Main FTP account
   - `aze2`, `aze3` - Additional accounts (possibly for different developers/purposes)

2. **Account Rotation/Security**:
   - HostEurope might have disabled `aze3` due to security issues
   - User reverted to main account `aze`

3. **Documentation Lag**:
   - Different deployment attempts used different accounts
   - Documentation wasn't updated consistently

## 5. Common HostEurope FTP Login Issues

### Error: "530 Login incorrect"
- Wrong username or password
- Account disabled/expired
- Special characters in password causing issues

### Connection Issues:
- Requires FTPS (explicit) or SFTP (standard FTP may be blocked)
- Passive mode often required
- Changes in KIS can take up to 15 minutes to propagate

### Security Updates:
- HostEurope now requires encrypted connections (FTPS/SFTP)
- Standard FTP on port 21 may be restricted

## 6. Recommended Actions

### Immediate Fix:
```bash
# Update /app/build/.env.production
FTP_USER=ftp10454681-aze  # Remove the "3"
```

### Best Practices:
1. **Verify in KIS**: Always check current FTP users in KIS portal
2. **Use Main Account**: Prefer the main FTP account for production deployments
3. **Document Changes**: Update deployment docs when credentials change
4. **Test Connection**: Verify FTP access before deployment scripts

### Security Recommendations:
1. Use FTPS or SFTP instead of plain FTP
2. Rotate passwords regularly via KIS
3. Consider IP restrictions for production FTP access
4. Use deployment-specific FTP accounts with limited permissions

## 7. Connection Details (Corrected)

```yaml
Server: wp10454681.server-he.de
Port: 21 (FTP) or 22 (SFTP)
Username: ftp10454681-aze  # WITHOUT "3"
Password: 321Start321
Protocol: FTPS (explicit) recommended
Mode: Passive (PASV)
```

## Conclusion

The FTP login failure is due to using an outdated or additional FTP username (`aze3`) instead of the main/active account (`aze`). This is a common issue when multiple FTP accounts exist for the same hosting package, and documentation becomes out of sync with the actual KIS configuration.
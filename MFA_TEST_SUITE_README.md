# MFA Test Suite Documentation

## Overview

This comprehensive test suite verifies the Multi-Factor Authentication (MFA) implementation for the AZE Gemini application. The tests are designed to validate all aspects of MFA functionality including setup, verification, security features, and user experience.

**Test Environment URL:** https://aze.mikropartner.de/aze-test/

## Test Suite Components

### 1. Core Test Scripts

| Script | Purpose | Dependencies |
|--------|---------|--------------|
| `tests/mfa_comprehensive_test_suite.py` | Complete MFA functionality testing | requests |
| `tests/mfa_database_test.py` | Database integration and schema testing | requests |
| `tests/mfa_user_flow_test.py` | User experience and workflow testing | requests |
| `tests/mfa_simple_test.py` | Basic functionality test (no dependencies) | Built-in only |
| `tests/run_mfa_tests.py` | Master test runner and report generator | - |

### 2. Convenience Scripts

| Script | Purpose |
|--------|---------|
| `run_mfa_tests.sh` | Shell script for easy test execution |

## Features Tested

### 🔐 Security Features
- **TOTP Code Generation and Verification**
  - Time-based one-time password algorithm
  - 6-digit code format validation
  - Time window tolerance testing
  - Secret key encryption/decryption

- **Backup Code Functionality**
  - 8-digit backup code generation
  - One-time use validation
  - Secure storage and removal after use

- **Rate Limiting and Lockout**
  - Failed attempt tracking
  - Account lockout after max attempts (default: 5)
  - Lockout duration enforcement (default: 30 minutes)
  - Lockout bypass prevention

### 👤 User Experience
- **Role-based MFA Requirements**
  - Admin and Bereichsleiter role enforcement
  - Optional MFA for other roles
  - Grace period for new users

- **Setup Workflow**
  - QR code generation for authenticator apps
  - Manual entry key formatting
  - Setup verification process
  - Backup code presentation

- **Authentication Flow**
  - Login with MFA integration
  - Session management
  - Trusted device handling

### 🗄️ Database Integration
- **Schema Verification**
  - MFA columns in users table
  - Audit logging table (mfa_audit_log)
  - Lockout tracking table (mfa_lockouts)
  - Trusted devices table (mfa_trusted_devices)

- **Data Operations**
  - Encrypted secret storage
  - Audit trail logging
  - Cleanup procedures

### 🌐 API Endpoint Testing
- **Endpoint Availability**
  - `/api/mfa-setup.php` - MFA setup and configuration
  - `/api/mfa-verify.php` - Code verification
  - `/api/login-with-mfa.php` - Enhanced login flow

- **Input Validation**
  - Parameter validation
  - SQL injection protection
  - XSS prevention
  - CSRF protection

## Running the Tests

### Quick Start

```bash
# Make script executable
chmod +x run_mfa_tests.sh

# Run complete test suite
./run_mfa_tests.sh
```

### Individual Test Scripts

```bash
# Run comprehensive functionality tests
python3 tests/mfa_comprehensive_test_suite.py

# Run database integration tests
python3 tests/mfa_database_test.py

# Run user flow tests
python3 tests/mfa_user_flow_test.py

# Run simple test (no dependencies)
python3 tests/mfa_simple_test.py

# Run master test runner
python3 tests/run_mfa_tests.py
```

### Prerequisites

1. **Python 3.6+** installed on the system
2. **Internet connection** to test environment
3. **Optional:** `requests` library for advanced tests
   ```bash
   pip3 install requests
   ```
   (Simple test works without external dependencies)

## Test Reports Generated

### Master Reports
- `MFA_MASTER_TEST_REPORT.md` - Comprehensive overview of all test results
- `MFA_COMPREHENSIVE_TEST_REPORT.md` - Detailed functionality test results
- `MFA_DATABASE_TEST_REPORT.md` - Database integration test results
- `MFA_USER_FLOW_TEST_REPORT.md` - User experience test results

### Simple Reports
- `SIMPLE_MFA_TEST_REPORT.md` - Basic functionality test results

## Understanding Test Results

### Success Rates

| Pass Rate | Status | Interpretation |
|-----------|--------|----------------|
| 100% | ✅ ALL TESTS PASSED | MFA implementation is ready for production |
| 80-99% | 🟡 MOSTLY PASSING | Minor issues to address before deployment |
| 50-79% | 🟠 MIXED RESULTS | Significant issues requiring attention |
| <50% | 🔴 CRITICAL ISSUES | Major problems, do not deploy |

### Common Issues and Solutions

#### 500 Server Errors
- **Cause:** Missing database schema or API files not deployed
- **Solution:** Execute `database/mfa_schema.sql` and deploy MFA PHP files

#### Authentication Errors (401/403)
- **Cause:** Expected behavior for protected endpoints
- **Solution:** Normal - indicates proper security implementation

#### Connection Timeouts
- **Cause:** Network issues or server unavailable
- **Solution:** Check test environment availability

## Test Scenarios Covered

### Positive Test Cases
- ✅ Valid TOTP code verification
- ✅ Successful MFA setup flow
- ✅ Backup code usage
- ✅ Proper role-based access
- ✅ Grace period functionality
- ✅ Session management

### Negative Test Cases
- ❌ Invalid TOTP codes
- ❌ Expired setup sessions
- ❌ Rate limiting enforcement
- ❌ SQL injection attempts
- ❌ Malformed JSON requests
- ❌ Missing required parameters

### Security Test Cases
- 🔒 Input validation
- 🔒 Authentication requirements
- 🔒 Session security
- 🔒 Rate limiting
- 🔒 Audit logging
- 🔒 Encryption verification

## Customization

### Modifying Test Configuration

Edit the test scripts to customize:

```python
# Change test environment
TEST_URL = "https://your-test-environment.com"

# Modify test parameters
TEST_TIMEOUT = 10
TOTP_SECRET = "YOUR_TEST_SECRET"
```

### Adding New Tests

1. Create new test method in appropriate test class
2. Add to test execution list
3. Update documentation

### Custom Test Environment

The test suite can be adapted for different environments by:
1. Changing the `TEST_URL` in each script
2. Modifying endpoint paths if different
3. Adjusting expected response codes for your implementation

## Troubleshooting

### Common Issues

#### Tests Fail with Connection Errors
```bash
# Check if test environment is accessible
curl -I https://aze.mikropartner.de/aze-test/api/mfa-setup.php
```

#### Python Module Not Found
```bash
# Install required modules
pip3 install requests
# Or use simple test that doesn't require external modules
python3 tests/mfa_simple_test.py
```

#### Permission Denied on Shell Script
```bash
# Make script executable
chmod +x run_mfa_tests.sh
```

### Getting Help

1. **Review generated test reports** for specific failure details
2. **Check server logs** for backend errors
3. **Verify database schema** has been applied
4. **Test individual components** using simple test script
5. **Contact development team** with specific error messages

## Production Deployment Checklist

Before deploying MFA to production:

- [ ] All test suites pass with >95% success rate
- [ ] Database schema successfully applied
- [ ] User acceptance testing completed
- [ ] Frontend integration verified
- [ ] TOTP authenticator apps tested (Google Authenticator, Authy, etc.)
- [ ] Backup and recovery procedures tested
- [ ] Security review completed
- [ ] Performance testing under load
- [ ] Documentation updated for end users
- [ ] Admin training completed

## Security Considerations

### Test Data Security
- Test scripts use non-production secrets and user IDs
- No real user data is accessed during testing
- All test attempts use obviously fake credentials

### Test Environment Isolation
- Tests should only run against test/staging environments
- Never run tests against production systems
- Test data should not contain real user information

### Audit Trail
- All test activities are logged in the MFA audit system
- Test runs generate detailed reports for security review
- Failed tests may indicate security vulnerabilities

## Contributing

To contribute to the test suite:

1. **Follow existing patterns** for test structure
2. **Add comprehensive documentation** for new tests
3. **Include both positive and negative test cases**
4. **Update this README** with new functionality
5. **Test your changes** before submitting

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-08-06 | Initial comprehensive test suite release |

## License and Support

This test suite is part of the AZE Gemini project and follows the same licensing and support guidelines as the main application.

For technical support or questions about the MFA implementation, refer to the project documentation or contact the development team.
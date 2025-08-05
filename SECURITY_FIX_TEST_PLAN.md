# Security Fix Test Plan - Issue #74

## Overview
This test plan verifies the critical authorization vulnerabilities fixed in issue #74.

**Test Environment URL**: https://aze.mikropartner.de/aze-test/
**Deployment Timestamp**: 2025-08-05 18:31
**Fixed Vulnerabilities**:
1. **time-entries.php**: ALL users could see ALL time entries
2. **users.php**: ANY user could change roles (privilege escalation)

## Test Accounts Required
- Honorarkraft (Freelancer)
- Mitarbeiter (Employee)
- Standortleiter (Location Manager)
- Bereichsleiter (Area Manager)
- Admin

## Test Cases

### 1. Time Entries Authorization (time-entries.php)

#### Test 1.1: Honorarkraft Access
**Steps**:
1. Login as Honorarkraft user
2. Navigate to time entries page
3. Call GET /api/time-entries.php

**Expected Result**:
- ✅ Only see own time entries
- ❌ Cannot see entries from other users
- Response should contain WHERE user_id filter

#### Test 1.2: Mitarbeiter Access
**Steps**:
1. Login as Mitarbeiter user
2. Navigate to time entries page
3. Call GET /api/time-entries.php

**Expected Result**:
- ✅ Only see own time entries
- ❌ Cannot see entries from other users

#### Test 1.3: Standortleiter Access
**Steps**:
1. Login as Standortleiter user
2. Navigate to time entries page
3. Call GET /api/time-entries.php

**Expected Result**:
- ✅ See all entries from their location
- ❌ Cannot see entries from other locations

#### Test 1.4: Admin Access
**Steps**:
1. Login as Admin user
2. Navigate to time entries page
3. Call GET /api/time-entries.php

**Expected Result**:
- ✅ See ALL time entries from all users and locations

### 2. User Role Change Authorization (users.php)

#### Test 2.1: Non-Admin Role Change Attempt
**Steps**:
1. Login as Honorarkraft/Mitarbeiter/Standortleiter
2. Attempt to PATCH /api/users.php with role change

**Request**:
```json
{
  "userId": 123,
  "newRole": "Admin"
}
```

**Expected Result**:
- ❌ HTTP 403 Forbidden
- Response: "Forbidden: Only Admin users can change user roles"

#### Test 2.2: Admin Role Change
**Steps**:
1. Login as Admin user
2. PATCH /api/users.php with role change

**Expected Result**:
- ✅ HTTP 200 OK
- Role successfully updated

### 3. Additional Security Checks

#### Test 3.1: SQL Injection Prevention
**Steps**:
1. Try injecting SQL in API parameters
2. Example: `/api/time-entries.php?user_id=1' OR '1'='1`

**Expected Result**:
- ❌ No additional data exposed
- Prepared statements prevent injection

#### Test 3.2: Session Security
**Steps**:
1. Check session handling for each role
2. Verify session data contains user role and ID

**Expected Result**:
- ✅ Session properly identifies user
- ✅ Role-based filtering uses session data

## Verification Checklist

### Pre-Production Deployment Checklist
- [ ] All tests pass in test environment
- [ ] No regression in existing functionality
- [ ] Performance impact minimal
- [ ] Error handling works correctly
- [ ] Logging captures authorization attempts

### Security Verification
- [ ] Honorarkraft cannot see other users' data
- [ ] Mitarbeiter cannot see other users' data
- [ ] Standortleiter only see their location's data
- [ ] Only Admin can change roles
- [ ] No SQL injection vulnerabilities
- [ ] Session security maintained

## Test Execution Log

### Test Run 1 - Date: ___________
| Test Case | Result | Notes |
|-----------|--------|-------|
| 1.1 | [ ] Pass [ ] Fail | |
| 1.2 | [ ] Pass [ ] Fail | |
| 1.3 | [ ] Pass [ ] Fail | |
| 1.4 | [ ] Pass [ ] Fail | |
| 2.1 | [ ] Pass [ ] Fail | |
| 2.2 | [ ] Pass [ ] Fail | |
| 3.1 | [ ] Pass [ ] Fail | |
| 3.2 | [ ] Pass [ ] Fail | |

### Issues Found
1. _________________________________
2. _________________________________
3. _________________________________

## Rollback Plan
If issues are found:
1. Revert to previous version of affected files
2. Document the issue
3. Fix and re-test
4. Re-deploy to test environment

## Sign-off
- [ ] Security fixes verified
- [ ] No regressions found
- [ ] Ready for production deployment
- [ ] Tested by: _____________ Date: _____________
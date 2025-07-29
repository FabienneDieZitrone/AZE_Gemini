# AZE System Test Documentation

## Test User Credentials
- **Email**: azetestclaude@mikropartner.de  
- **Password**: a1b2c3d4
- **System**: https://aze.mikropartner.de

## Test Scripts Created

### 1. Comprehensive Test Suite
**File**: `/app/build/test-aze-system.cjs`  
**Run**: `node test-aze-system.cjs`

Tests the following:
- API endpoint availability
- Login functionality (Note: OAuth requires browser)
- Session management
- Timer operations
- Database verification

### 2. User Verification Script
**URL**: https://aze.mikropartner.de/api/verify-test-user.php

Checks:
- If test user exists in database
- Recent timer entries
- Session validity (if accessed with valid session)

### 3. Debug Session & Timer Script
**URL**: https://aze.mikropartner.de/api/debug-session-timer.php  
**Test Timer**: https://aze.mikropartner.de/api/debug-session-timer.php?test_timer=1

Provides detailed information about:
- Current session state
- User ID in session (CRITICAL!)
- User data in database
- Running timers
- Recent timer entries
- Diagnostic information

## Test Process

### Step 1: Initial System Check
```bash
cd /app/build
node test-aze-system.cjs
```

Expected results:
- ✅ All API endpoints reachable
- ⚠️ Login requires browser (OAuth flow)
- ✅ Verification script created

### Step 2: Manual Browser Login
1. Open https://aze.mikropartner.de
2. Click "Mit Microsoft anmelden"
3. Enter credentials:
   - Email: azetestclaude@mikropartner.de
   - Password: a1b2c3d4
4. Complete Microsoft login

### Step 3: Verify User Creation
After login, check: https://aze.mikropartner.de/api/verify-test-user.php

Expected response:
```json
{
    "user_exists": true,
    "user_data": {
        "id": [number],
        "azure_oid": "[guid]",
        "username": "azetestclaude@mikropartner.de",
        "display_name": "[user's display name]",
        "role": "Mitarbeiter"
    }
}
```

### Step 4: Check Session State
With active session, visit: https://aze.mikropartner.de/api/debug-session-timer.php

Critical checks:
- `session_data.user_id` MUST be a number (not "NOT SET - CRITICAL!")
- `session_status` should be "active_with_user"
- No critical issues in diagnostics

### Step 5: Test Timer Operations

#### Start Timer
In the AZE application:
1. Click "Timer starten" / "Start"
2. Timer should show as running
3. Elapsed time should update

#### Verify Running Timer
Check: https://aze.mikropartner.de/api/debug-session-timer.php
- `running_timers` array should have one entry
- Entry should have `stop_time: null`

#### Stop Timer
1. Click "Timer stoppen" / "Stop"
2. Timer should stop
3. Entry should appear in time list

#### Verify Stopped Timer
Check: https://aze.mikropartner.de/api/debug-session-timer.php
- `running_timers` should be empty
- `recent_timers` should show the stopped entry with stop_time

## Critical Success Criteria

### ✅ MUST PASS:
1. **User Creation**: Test user created in database on first login
2. **Session User ID**: `$_SESSION['user']['id']` must be set after login
3. **Timer Start**: Creates entry with correct user_id
4. **Timer Stop**: Updates entry with stop_time
5. **No Orphaned Timers**: Only one running timer per user

### ⚠️ Known Issues to Watch:
1. **OAuth State**: Must match between request and callback
2. **Session Cookie**: Must be httponly and secure
3. **CORS**: API must handle cross-origin requests
4. **User ID**: Must be integer from database, not OID

## Troubleshooting

### User Not Created
- Check OAuth callback logs
- Verify Azure AD returns correct user info
- Check database connection in auth-callback.php

### Session Missing User ID
- Check auth-callback.php lines 120-153
- Verify user lookup by azure_oid
- Ensure session regeneration doesn't lose data

### Timer Won't Start
- Verify session has user ID
- Check browser console for errors
- Test with debug endpoint

### Timer Won't Stop
- Check if timer ID is passed correctly
- Verify user owns the timer
- Check stop endpoint URL format

## Quick Test URLs

1. **Health Check**: https://aze.mikropartner.de/api/health.php
2. **User Verification**: https://aze.mikropartner.de/api/verify-test-user.php
3. **Session Debug**: https://aze.mikropartner.de/api/debug-session-timer.php
4. **Create Test Timer**: https://aze.mikropartner.de/api/debug-session-timer.php?test_timer=1

## Expected Database State

After successful test:

### users table:
```sql
username: azetestclaude@mikropartner.de
azure_oid: [from Microsoft]
display_name: [from Microsoft]
role: Mitarbeiter
```

### time_entries table:
```sql
user_id: [matches users.id]
username: [user's display name]
date: [today]
start_time: [when started]
stop_time: [when stopped or NULL if running]
location: [selected location]
```

## Test Report Template

```
Date: [DATE]
Tester: [NAME]

1. API Health: ✅/❌
2. User Login: ✅/❌
3. User Created in DB: ✅/❌
4. Session Has User ID: ✅/❌
5. Timer Start Works: ✅/❌
6. Timer Stop Works: ✅/❌
7. Data Persists: ✅/❌

Issues Found:
- [Issue 1]
- [Issue 2]

Notes:
[Additional observations]
```
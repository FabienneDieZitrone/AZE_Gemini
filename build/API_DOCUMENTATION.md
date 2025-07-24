# API Documentation - Time Tracking System

## Overview

This is a comprehensive API reference for a time tracking system built with PHP that uses Azure AD OAuth2 authentication. The system follows a Backend-for-Frontend (BFF) architecture with server-side session management and secure database operations.

## Base Configuration

- **Base URL**: `https://aze.mikropartner.de/api/`
- **Content-Type**: `application/json; charset=UTF-8`
- **Authentication**: Server-side PHP sessions with Azure AD OAuth2
- **Database**: MySQL with prepared statements for SQL injection prevention

## Authentication & Security

### Security Features

1. **OAuth2 Authentication**: Azure AD integration with PKCE flow
2. **CSRF Protection**: State parameter validation in OAuth flow
3. **Session Security**: 
   - HttpOnly cookies
   - Secure flag (HTTPS only)
   - SameSite=Lax
   - Session regeneration after authentication
4. **SQL Injection Prevention**: All queries use prepared statements
5. **Input Validation**: Comprehensive validation of all request data
6. **Error Handling**: Structured error responses with appropriate HTTP status codes

### Session Management

Sessions are managed server-side with secure cookie parameters:
- Lifetime: Session (expires when browser closes)
- Path: `/`
- Domain: Current domain
- Secure: `true` (HTTPS only)
- HttpOnly: `true` (JavaScript inaccessible)
- SameSite: `Lax`

## Authentication Endpoints

### 1. Start Authentication
**Endpoint**: `GET /api/auth-start.php`

Initiates the OAuth2 flow by redirecting to Azure AD.

**Response**: 
- HTTP 302 redirect to Azure AD login page
- Sets secure session with CSRF state parameter

**Example**:
```bash
curl -X GET https://aze.mikropartner.de/api/auth-start.php
```

### 2. OAuth Callback
**Endpoint**: `GET /api/auth-callback.php`

Handles the OAuth2 callback from Azure AD.

**Query Parameters**:
- `code` (string): Authorization code from Azure AD
- `state` (string): CSRF protection state parameter

**Response**:
- HTTP 302 redirect to `/` on success
- HTTP 400/500 with error message on failure

**Session Data Created**:
```json
{
  "user": {
    "oid": "azure-object-id",
    "name": "Display Name",
    "username": "user@domain.com"
  },
  "auth_tokens": {
    "access_token": "...",
    "refresh_token": "...",
    "id_token": "..."
  }
}
```

### 3. Check Authentication Status
**Endpoint**: `GET /api/auth-status.php`

Verifies if the user has a valid session.

**Response**:
- `204 No Content`: Valid session exists
- `401 Unauthorized`: No valid session

**Example**:
```bash
curl -X GET https://aze.mikropartner.de/api/auth-status.php \
  -H "Cookie: PHPSESSID=your-session-id"
```

### 4. Logout
**Endpoint**: `GET /api/auth-logout.php`

Destroys the user session and redirects to home page.

**Response**:
- HTTP 302 redirect to `/`
- Session cookie deleted

**Example**:
```bash
curl -X GET https://aze.mikropartner.de/api/auth-logout.php \
  -H "Cookie: PHPSESSID=your-session-id"
```

## User Management Endpoints

### 5. Initial Login/Data Sync
**Endpoint**: `POST /api/login.php`

Called after successful OAuth authentication to sync user data and retrieve initial application state.

**Authentication**: Required (valid session)

**Response**: `200 OK`
```json
{
  "currentUser": {
    "id": 1,
    "name": "Display Name",
    "role": "Honorarkraft|Admin",
    "azureOid": "azure-object-id"
  },
  "users": [
    {
      "id": 1,
      "name": "User Name",
      "role": "Honorarkraft",
      "azureOid": "azure-oid"
    }
  ],
  "masterData": {
    "1": {
      "weeklyHours": 40.0,
      "workdays": ["Mo", "Di", "Mi", "Do", "Fr"],
      "canWorkFromHome": false
    }
  },
  "timeEntries": [
    {
      "id": 1,
      "userId": 1,
      "username": "user@domain.com",
      "date": "2025-07-24",
      "startTime": "09:00",
      "stopTime": "17:00",
      "location": "Büro",
      "role": "Honorarkraft",
      "createdAt": "2025-07-24 09:00:00",
      "updatedBy": "User Name",
      "updatedAt": "2025-07-24 09:00:00"
    }
  ],
  "approvalRequests": [],
  "history": [],
  "globalSettings": {
    "overtimeThreshold": 8.0,
    "changeReasons": ["Fehler", "Vergessen"],
    "locations": ["Büro", "Homeoffice"]
  }
}
```

**Features**:
- GDPR compliant (deletes users older than 6 months)
- Auto-creates new users on first login
- Creates default master data for new users
- Returns complete application state

### 6. Get Users
**Endpoint**: `GET /api/users.php`

Retrieves all users in the system.

**Authentication**: Required

**Response**: `200 OK`
```json
[
  {
    "id": 1,
    "name": "Display Name",
    "role": "Honorarkraft",
    "azureOid": "azure-object-id"
  }
]
```

### 7. Update User Role
**Endpoint**: `PATCH /api/users.php`

Updates a user's role in the system.

**Authentication**: Required

**Request Body**:
```json
{
  "userId": 1,
  "newRole": "Admin"
}
```

**Response**: `200 OK`
```json
{
  "message": "User role updated successfully."
}
```

**Error Responses**:
- `400 Bad Request`: Missing required fields
- `500 Internal Server Error`: Database error

## Time Entry Management

### 8. Get Time Entries
**Endpoint**: `GET /api/time-entries.php`

Retrieves all time entries (TODO: implement role-based filtering).

**Authentication**: Required

**Response**: `200 OK`
```json
[
  {
    "id": 1,
    "userId": 1,
    "username": "user@domain.com",
    "date": "2025-07-24",
    "startTime": "09:00",
    "stopTime": "17:00",
    "location": "Büro",
    "role": "Honorarkraft",
    "createdAt": "2025-07-24 09:00:00",
    "updatedBy": "User Name",
    "updatedAt": "2025-07-24 09:00:00"
  }
]
```

### 9. Create Time Entry
**Endpoint**: `POST /api/time-entries.php`

Creates a new time entry.

**Authentication**: Required

**Request Body**:
```json
{
  "userId": 1,
  "username": "user@domain.com",
  "date": "2025-07-24",
  "startTime": "09:00",
  "stopTime": "17:00",
  "location": "Büro",
  "role": "Honorarkraft",
  "updatedBy": "User Name"
}
```

**Response**: `201 Created`
```json
{
  "id": 1,
  "userId": 1,
  "username": "user@domain.com",
  "date": "2025-07-24",
  "startTime": "09:00",
  "stopTime": "17:00",
  "location": "Büro",
  "role": "Honorarkraft",
  "updatedBy": "User Name"
}
```

**Validation**:
- All fields are required
- TODO: Permission check for creating entries for other users

## Approval Workflow

### 10. Get Approval Requests
**Endpoint**: `GET /api/approvals.php`

Retrieves all pending approval requests.

**Authentication**: Required

**Response**: `200 OK`
```json
[
  {
    "id": "uuid-string",
    "type": "edit|delete",
    "entry_id": 1,
    "entry": {
      "id": 1,
      "userId": 1,
      "username": "user@domain.com",
      "date": "2025-07-24",
      "startTime": "09:00",
      "stopTime": "17:00",
      "location": "Büro",
      "role": "Honorarkraft",
      "createdAt": "2025-07-24 09:00:00",
      "updatedBy": "User Name",
      "updatedAt": "2025-07-24 09:00:00"
    },
    "newData": {
      "startTime": "08:00",
      "stopTime": "16:00"
    },
    "reasonData": {
      "reason": "Fehler",
      "comment": "Falsche Zeit eingetragen"
    },
    "requested_by": "User Name",
    "requested_at": "2025-07-24 10:00:00",
    "status": "pending"
  }
]
```

### 11. Create Approval Request
**Endpoint**: `POST /api/approvals.php`

Creates a new approval request for editing or deleting a time entry.

**Authentication**: Required

**Request Body**:
```json
{
  "type": "edit",
  "entryId": 1,
  "newData": {
    "startTime": "08:00",
    "stopTime": "16:00"
  },
  "reasonData": {
    "reason": "Fehler",
    "comment": "Falsche Zeit eingetragen"
  }
}
```

**Response**: `201 Created`
```json
{
  "message": "Approval request created.",
  "id": "uuid-string"
}
```

**Request Types**:
- `edit`: Modify time entry fields
- `delete`: Delete time entry

### 12. Process Approval Request
**Endpoint**: `PATCH /api/approvals.php`

Approves or rejects an approval request.

**Authentication**: Required

**Request Body**:
```json
{
  "requestId": "uuid-string",
  "finalStatus": "genehmigt"
}
```

**Response**: `200 OK`
```json
{
  "message": "Request genehmigt."
}
```

**Final Status Options**:
- `genehmigt`: Approved (applies changes to time entry)
- `abgelehnt`: Rejected (no changes applied)

## Master Data Management

### 13. Get Master Data
**Endpoint**: `GET /api/masterdata.php`

Retrieves master data for all users.

**Authentication**: Required

**Response**: `200 OK`
```json
{
  "1": {
    "weeklyHours": 40.0,
    "workdays": ["Mo", "Di", "Mi", "Do", "Fr"],
    "canWorkFromHome": false
  }
}
```

### 14. Update Master Data
**Endpoint**: `PUT /api/masterdata.php`

Updates master data for a specific user.

**Authentication**: Required

**Request Body**:
```json
{
  "userId": 1,
  "weeklyHours": 35.0,
  "workdays": ["Mo", "Di", "Mi", "Do"],
  "canWorkFromHome": true
}
```

**Response**: `200 OK`
```json
{
  "message": "Master data updated successfully."
}
```

## Global Settings

### 15. Get Global Settings
**Endpoint**: `GET /api/settings.php`

Retrieves global application settings.

**Authentication**: Required

**Response**: `200 OK`
```json
{
  "overtimeThreshold": 8.0,
  "changeReasons": ["Fehler", "Vergessen", "Krankheit"],
  "locations": ["Büro", "Homeoffice", "Kunde"]
}
```

### 16. Update Global Settings
**Endpoint**: `PUT /api/settings.php`

Updates global application settings (Admin only).

**Authentication**: Required (Admin role)

**Request Body**:
```json
{
  "overtimeThreshold": 8.5,
  "changeReasons": ["Fehler", "Vergessen", "Krankheit"],
  "locations": ["Büro", "Homeoffice", "Kunde"]
}
```

**Response**: `200 OK`
```json
{
  "message": "Global settings updated successfully."
}
```

**Error Responses**:
- `403 Forbidden`: Non-admin user attempting to update settings

## History & Logging

### 17. Get Change History
**Endpoint**: `GET /api/history.php`

Retrieves the complete history of processed approval requests.

**Authentication**: Required

**Response**: `200 OK`
```json
[
  {
    "id": "uuid-string",
    "type": "edit",
    "entry": {
      "id": 1,
      "userId": 1,
      "username": "user@domain.com",
      "date": "2025-07-24",
      "startTime": "09:00",
      "stopTime": "17:00",
      "location": "Büro",
      "role": "Honorarkraft",
      "createdAt": "2025-07-24 09:00:00",
      "updatedBy": "User Name",
      "updatedAt": "2025-07-24 09:00:00"
    },
    "newData": {
      "startTime": "08:00",
      "stopTime": "16:00"
    },
    "reasonData": {
      "reason": "Fehler",
      "comment": "Falsche Zeit eingetragen"
    },
    "requestedBy": "User Name",
    "finalStatus": "genehmigt",
    "resolvedAt": "2025-07-24 11:00:00",
    "resolvedBy": "Admin User"
  }
]
```

### 18. Frontend Error Logging
**Endpoint**: `POST /api/logs.php`

Accepts error logs from the frontend application.

**Authentication**: Not required

**Request Body**:
```json
{
  "message": "JavaScript error occurred",
  "context": "UserDashboard.js:45",
  "stack": "Error stack trace..."
}
```

**Response**: `204 No Content`

**Features**:
- No authentication required
- Writes to `/api/error.log` file
- Includes timestamp and formatting

## Database Integration

### Database Connection (`/api/db.php`)

**Features**:
- Uses environment variables for credentials (loaded via `/config.php`)
- MySQLi extension with UTF-8 charset
- Comprehensive error handling and logging
- Connection validation

**Configuration**:
```php
$servername = Config::get('db.host');
$username = Config::get('db.username');
$password = Config::get('db.password');
$dbname = Config::get('db.name');
```

### Common Query Patterns

**All endpoints use prepared statements**:
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
```

**Transaction Usage** (in `/api/login.php`):
```php
$conn->begin_transaction();
try {
    // Multiple database operations
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Transaction failed: " . $e->getMessage());
}
```

## Error Handling

### Standard Error Response Format

```json
{
  "message": "Human-readable error message",
  "error_details": "Technical details (development only)"
}
```

### HTTP Status Codes

- `200 OK`: Successful GET/PATCH/PUT requests
- `201 Created`: Successful POST requests
- `204 No Content`: Successful requests with no body (auth-status, logout)
- `400 Bad Request`: Invalid request data
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `405 Method Not Allowed`: HTTP method not supported
- `500 Internal Server Error`: Server/database errors

### Error Logging

All endpoints include comprehensive error logging:
- Database errors logged to server error log
- Fatal PHP errors handled with shutdown functions
- Frontend errors accepted via `/api/logs.php`

## CORS Configuration

All API endpoints (except `/api/logs.php`) use centralized CORS handling:

```php
header("Access-Control-Allow-Origin: https://aze.mikropartner.de");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
```

## Development Notes

### TODO Items in Code

1. **Authorization**: Role-based access control needs implementation
2. **Permission Checks**: Cross-user operations need proper validation  
3. **Data Filtering**: Endpoints should filter data based on user roles
4. **OAuth Client Secret**: Must be configured as environment variable

### Security Considerations

1. **Client Secret**: Currently has placeholder, must be configured properly
2. **Environment Variables**: All sensitive data should use environment variables
3. **HTTPS Only**: All authentication features require HTTPS
4. **Input Validation**: All user inputs are validated and sanitized
5. **SQL Injection**: Prevented through prepared statements
6. **XSS Prevention**: JSON encoding with proper flags

This API follows modern security practices and provides a robust foundation for a time tracking application with Azure AD integration.
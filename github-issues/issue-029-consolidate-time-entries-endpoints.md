# Issue #029: Consolidate Duplicate time-entries.php Endpoints

## Priority: HIGH 🔴

## Description
The backend contains 3 different versions of time-entries.php files with overlapping functionality, violating DRY principles and creating maintenance nightmares. These need to be consolidated into a single, well-structured endpoint.

## Problem Analysis
- **Code Duplication**: Three separate files handling time entries
- **Inconsistent Implementation**: Different approaches in each file
- **Maintenance Burden**: Bug fixes must be applied to multiple files
- **Confusion**: Developers unsure which endpoint to use
- **Version Control Issues**: Unclear which is the "correct" version
- **API Inconsistency**: Different response formats across versions

## Impact Analysis
- **Severity**: HIGH
- **Maintenance Cost**: Very High - Triple maintenance effort
- **Bug Risk**: High - Fixes may miss some versions
- **Refactoring Time**: 1 hour
- **Code Quality**: Major DRY violation
- **API Stability**: Potential breaking changes

## Current Duplicate Files
```
/api/time-entries.php (450 lines)
/api/time-entries-v2.php (380 lines)
/api/time-entries-old.php (420 lines)
```

### Code Analysis
```php
// time-entries.php
public function getTimeEntries($userId, $date) {
    // Original implementation
}

// time-entries-v2.php
public function getTimeEntriesV2($userId, $startDate, $endDate) {
    // "Improved" implementation with date range
}

// time-entries-old.php
public function fetchTimeEntries($user_id, $date = null) {
    // Legacy implementation
}
```

## Proposed Solution
Consolidate into single `time-entries.php` with versioned methods:

```php
// api/time-entries.php
class TimeEntriesAPI {
    /**
     * Unified time entries endpoint
     * Supports both single date and date range queries
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $version = $_GET['v'] ?? '1';
        
        switch ($method) {
            case 'GET':
                return $this->getTimeEntries();
            case 'POST':
                return $this->createTimeEntry();
            case 'PUT':
                return $this->updateTimeEntry();
            case 'DELETE':
                return $this->deleteTimeEntry();
        }
    }
    
    private function getTimeEntries() {
        $userId = $this->validateUserId($_GET['user_id']);
        $startDate = $_GET['start_date'] ?? $_GET['date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? $startDate;
        
        return $this->fetchTimeEntriesRange($userId, $startDate, $endDate);
    }
}
```

## Implementation Steps (1 hour)

### Phase 1: Analysis (15 minutes)
- [ ] Compare functionality across all three files
- [ ] Identify unique features in each version
- [ ] Document API differences
- [ ] Check which version is currently used in frontend
- [ ] Identify database query differences

### Phase 2: Consolidation (20 minutes)
- [ ] Create unified time-entries.php structure
- [ ] Merge common functionality
- [ ] Implement backward compatibility layer
- [ ] Add proper parameter validation
- [ ] Standardize response format

### Phase 3: Migration (15 minutes)
- [ ] Update frontend API calls to new endpoint
- [ ] Add deprecation headers to old endpoints
- [ ] Create redirect rules for old URLs
- [ ] Update API documentation
- [ ] Test all time entry operations

### Phase 4: Cleanup (10 minutes)
- [ ] Archive old files (don't delete immediately)
- [ ] Update .htaccess for redirects
- [ ] Remove references to old files
- [ ] Update deployment scripts
- [ ] Verify no broken imports

## Code Structure
```php
// Consolidated api/time-entries.php
<?php
require_once 'config/database.php';
require_once 'middleware/auth.php';
require_once 'utils/validation.php';

class TimeEntriesAPI {
    private $db;
    private $validator;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->validator = new Validator();
    }
    
    // Single entry point for all time entry operations
    public function handle() {
        try {
            $this->authenticate();
            return $this->route();
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    // Unified data fetching with optional date range
    private function fetchTimeEntries($params) {
        $sql = "SELECT * FROM time_entries 
                WHERE user_id = :user_id 
                AND date BETWEEN :start_date AND :end_date
                ORDER BY date DESC, start_time DESC";
                
        return $this->db->query($sql, [
            'user_id' => $params['user_id'],
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date']
        ]);
    }
}
```

## API Compatibility Layer
```php
// Temporary compatibility for old endpoints
// api/time-entries-v2.php
header('X-Deprecated: Use /api/time-entries.php instead');
require_once 'time-entries.php';
$api = new TimeEntriesAPI();
$api->handle();
```

## Success Criteria
- [ ] Single time-entries.php handles all use cases
- [ ] Old endpoints redirect to new endpoint
- [ ] No functionality lost during consolidation
- [ ] Frontend works without modifications
- [ ] API responses remain consistent

## Technical Requirements
- **PHP**: 8.2+ with proper type hints
- **Database**: Optimized queries for date ranges
- **Validation**: Comprehensive input validation
- **Error Handling**: Consistent error responses
- **Documentation**: Updated API docs

## Testing Checklist
- [ ] Single date query works
- [ ] Date range query works
- [ ] User filtering works correctly
- [ ] Pagination still functions
- [ ] Error cases handled properly
- [ ] Performance not degraded

## Acceptance Criteria
1. Only one time-entries.php file exists
2. All time entry functionality consolidated
3. Backward compatibility maintained
4. API documentation updated
5. No regression in functionality

## Priority Level
**HIGH** - Critical for maintainability and preventing bugs

## Estimated Effort
- **Analysis**: 15 minutes
- **Development**: 30 minutes
- **Testing**: 15 minutes
- **Total**: 1 hour

## Labels
`backend`, `refactoring`, `api`, `high-priority`, `1-hour`, `dry-principle`

## Related Issues
- Issue #007: API Versioning Strategy
- Issue #017: API Documentation Enhancement

## Expected Benefits
- **Maintenance**: Single point of updates
- **Clarity**: Clear which code is active
- **Performance**: Optimized queries
- **Consistency**: Uniform API responses
- **Documentation**: Single API to document

## Migration Path
1. Week 1: Deploy consolidated endpoint
2. Week 2: Add deprecation warnings
3. Week 3: Update all clients
4. Week 4: Remove old endpoints
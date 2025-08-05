# Issue #030: Extract Magic Numbers to TIME_CONSTANTS Module

## Priority: MEDIUM 🔶

## Description
The codebase contains numerous magic numbers for time calculations (3600, 86400, 15000, etc.) scattered throughout multiple files. These should be extracted into a centralized TIME_CONSTANTS module for better maintainability and clarity.

## Problem Analysis
- **Magic Numbers**: Hard-coded time values without context
- **Unclear Intent**: `3600` doesn't immediately convey "seconds per hour"
- **Error Prone**: Easy to mistype time constants
- **Inconsistent**: Same values defined differently across files
- **Maintenance Burden**: Changing time logic requires finding all occurrences
- **Code Readability**: Reduces code self-documentation

## Impact Analysis
- **Severity**: MEDIUM
- **Code Quality**: Significant improvement potential
- **Refactoring Time**: 1 hour
- **Risk Level**: Very Low - Simple constant extraction
- **Maintainability**: High improvement
- **Developer Experience**: Better code clarity

## Current Magic Numbers Found
```typescript
// MainAppView.tsx
const hours = Math.floor(totalSeconds / 3600);  // Magic number!
const remainingSeconds = totalSeconds % 3600;   // Magic number!

// api/timer-control.php
$timeout = 15000;  // What unit? Milliseconds? Seconds?

// utils/calculations.js
const SECONDS_PER_DAY = 86400;  // Defined locally
const workHours = time / 3600;   // Magic number again!

// api/time-entries.php
if ($duration > 43200) {  // 12 hours in seconds - not obvious!
    throw new Exception("Duration too long");
}
```

## Proposed Solution
Create centralized constants modules:

### TypeScript Constants (Frontend)
```typescript
// constants/time.constants.ts
export const TIME_CONSTANTS = {
  // Time units in milliseconds
  MS_PER_SECOND: 1000,
  MS_PER_MINUTE: 60 * 1000,
  MS_PER_HOUR: 60 * 60 * 1000,
  MS_PER_DAY: 24 * 60 * 60 * 1000,
  
  // Time units in seconds
  SECONDS_PER_MINUTE: 60,
  SECONDS_PER_HOUR: 3600,
  SECONDS_PER_DAY: 86400,
  SECONDS_PER_WEEK: 604800,
  
  // Business logic constants
  WORK_HOURS_PER_DAY: 8,
  MAX_WORK_HOURS_PER_DAY: 12,
  BREAK_DURATION_MINUTES: 30,
  
  // API timeouts
  DEFAULT_API_TIMEOUT_MS: 15000,
  LONG_API_TIMEOUT_MS: 30000,
  
  // Timer intervals
  TIMER_UPDATE_INTERVAL_MS: 1000,
  AUTO_SAVE_INTERVAL_MS: 60000,
} as const;

// Helper functions
export const hoursToSeconds = (hours: number) => hours * TIME_CONSTANTS.SECONDS_PER_HOUR;
export const secondsToHours = (seconds: number) => seconds / TIME_CONSTANTS.SECONDS_PER_HOUR;
export const formatDuration = (seconds: number) => {
  const hours = Math.floor(seconds / TIME_CONSTANTS.SECONDS_PER_HOUR);
  const minutes = Math.floor((seconds % TIME_CONSTANTS.SECONDS_PER_HOUR) / TIME_CONSTANTS.SECONDS_PER_MINUTE);
  return `${hours}h ${minutes}m`;
};
```

### PHP Constants (Backend)
```php
// config/time-constants.php
<?php
class TimeConstants {
    // Time units in seconds
    const SECONDS_PER_MINUTE = 60;
    const SECONDS_PER_HOUR = 3600;
    const SECONDS_PER_DAY = 86400;
    const SECONDS_PER_WEEK = 604800;
    
    // Business logic
    const WORK_HOURS_PER_DAY = 8;
    const MAX_WORK_HOURS_PER_DAY = 12;
    const MAX_WORK_SECONDS_PER_DAY = self::MAX_WORK_HOURS_PER_DAY * self::SECONDS_PER_HOUR;
    
    // Timeouts (in milliseconds for consistency with frontend)
    const DEFAULT_TIMEOUT_MS = 15000;
    const SESSION_TIMEOUT_SECONDS = 3600; // 1 hour
    
    // Helper methods
    public static function hoursToSeconds($hours) {
        return $hours * self::SECONDS_PER_HOUR;
    }
    
    public static function secondsToHours($seconds) {
        return $seconds / self::SECONDS_PER_HOUR;
    }
}
```

## Implementation Steps (1 hour)

### Phase 1: Create Constants Modules (20 minutes)
- [ ] Create `/constants/time.constants.ts` for frontend
- [ ] Create `/api/config/time-constants.php` for backend
- [ ] Define all time-related constants
- [ ] Add helper functions for conversions
- [ ] Add JSDoc/PHPDoc documentation

### Phase 2: Find and Replace Magic Numbers (30 minutes)
- [ ] Search for common time magic numbers: 3600, 86400, 60, 1000
- [ ] Replace with appropriate constants
- [ ] Update calculations to use helper functions
- [ ] Ensure consistent time units (ms vs seconds)
- [ ] Update timeout values

### Phase 3: Testing and Verification (10 minutes)
- [ ] Run application and verify timer works
- [ ] Check API timeout behavior
- [ ] Verify time calculations are correct
- [ ] Ensure no regression in functionality
- [ ] Update unit tests if needed

## Refactoring Examples

### Before
```typescript
// Unclear magic numbers
const hours = Math.floor(totalSeconds / 3600);
const minutes = Math.floor((totalSeconds % 3600) / 60);
const seconds = totalSeconds % 60;

// Confusing timeout
axios.get('/api/data', { timeout: 15000 });
```

### After
```typescript
import { TIME_CONSTANTS, secondsToHours } from '@/constants/time.constants';

// Clear, self-documenting code
const hours = Math.floor(totalSeconds / TIME_CONSTANTS.SECONDS_PER_HOUR);
const minutes = Math.floor((totalSeconds % TIME_CONSTANTS.SECONDS_PER_HOUR) / TIME_CONSTANTS.SECONDS_PER_MINUTE);
const seconds = totalSeconds % TIME_CONSTANTS.SECONDS_PER_MINUTE;

// Obvious timeout duration
axios.get('/api/data', { timeout: TIME_CONSTANTS.DEFAULT_API_TIMEOUT_MS });
```

## Success Criteria
- [ ] All time magic numbers replaced with constants
- [ ] Constants module created and documented
- [ ] Code is more readable and self-documenting
- [ ] No functionality changes or regressions
- [ ] Consistent time units across codebase

## Technical Requirements
- **TypeScript**: Const assertions for type safety
- **PHP**: Class constants for immutability
- **Documentation**: Clear comments explaining each constant
- **Naming**: Descriptive constant names with units

## Testing Approach
```typescript
// Test helper functions
describe('Time Constants', () => {
  it('should convert hours to seconds correctly', () => {
    expect(hoursToSeconds(1)).toBe(3600);
    expect(hoursToSeconds(8)).toBe(28800);
  });
  
  it('should format duration correctly', () => {
    expect(formatDuration(3665)).toBe('1h 1m');
    expect(formatDuration(7200)).toBe('2h 0m');
  });
});
```

## Acceptance Criteria
1. No magic time numbers in codebase
2. Centralized time constants module
3. Helper functions for common conversions
4. Improved code readability
5. No functional regressions

## Priority Level
**MEDIUM** - Important for code quality and maintainability

## Estimated Effort
- **Development**: 45 minutes
- **Testing**: 15 minutes
- **Total**: 1 hour

## Labels
`refactoring`, `code-quality`, `frontend`, `backend`, `medium-priority`, `1-hour`

## Related Issues
- Issue #027: Extract Timer Service from MainAppView
- Issue #024: Refactoring als Standard etablieren

## Expected Benefits
- **Readability**: Self-documenting code
- **Maintainability**: Easy to update time logic
- **Consistency**: Same constants used everywhere
- **Debugging**: Clear what values represent
- **Onboarding**: New developers understand time logic
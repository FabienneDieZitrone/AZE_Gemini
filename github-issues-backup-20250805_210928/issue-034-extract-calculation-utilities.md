# Issue #034: Extract Time Calculation Utilities

## Priority: MEDIUM 🔶

## Description
Time calculation logic (daily soll time, overtime, break time) is embedded directly in components rather than being extracted into reusable utility functions. This violates DRY principles and makes calculations difficult to test and maintain.

## Problem Analysis
- **Inline Calculations**: Complex math scattered in components
- **Duplicate Logic**: Same calculations in multiple places
- **Hard to Test**: Cannot unit test calculations in isolation
- **Error Prone**: Easy to introduce calculation bugs
- **Business Logic in UI**: Calculations should be in utilities
- **No Single Source of Truth**: Different implementations possible

## Impact Analysis
- **Severity**: MEDIUM
- **Bug Risk**: High - Calculation errors affect payroll
- **Refactoring Time**: 1 hour
- **Risk Level**: Low - Pure functions
- **Business Impact**: Critical - Affects time tracking accuracy

## Current Calculation Examples
```typescript
// MainAppView.tsx - Inline calculations
const calculateDailySoll = () => {
  const hours = 8; // Magic number
  const breakMinutes = sollTime > 6 * 60 ? 30 : 0; // Inline break logic
  return hours * 60 + breakMinutes;
};

// Different calculation in another component
const overtime = Math.max(0, workedMinutes - (8 * 60 + 30));

// Duplicate logic in reports
const dailyTarget = user.contractHours / 5 * 60; // Assumes 5-day week
```

## Proposed Solution

### Create Centralized Calculation Utilities
```typescript
// utils/timeCalculations.ts
import { TIME_CONSTANTS } from '@/constants/time.constants';

export interface WorkTimeConfig {
  contractHoursPerWeek: number;
  workDaysPerWeek: number;
  breakRules: BreakRule[];
}

export interface BreakRule {
  minWorkHours: number;
  breakMinutes: number;
}

/**
 * Calculate daily soll (target) time including breaks
 */
export const calculateDailySollTime = (config: WorkTimeConfig): number => {
  const dailyHours = config.contractHoursPerWeek / config.workDaysPerWeek;
  const dailyMinutes = dailyHours * TIME_CONSTANTS.MINUTES_PER_HOUR;
  
  // Apply break rules
  const breakMinutes = calculateRequiredBreak(dailyHours, config.breakRules);
  
  return dailyMinutes + breakMinutes;
};

/**
 * Calculate required break time based on work hours
 */
export const calculateRequiredBreak = (
  workHours: number, 
  breakRules: BreakRule[]
): number => {
  // German law: 30 min break for >6 hours, 45 min for >9 hours
  const applicableRule = breakRules
    .filter(rule => workHours >= rule.minWorkHours)
    .sort((a, b) => b.minWorkHours - a.minWorkHours)[0];
    
  return applicableRule?.breakMinutes || 0;
};

/**
 * Calculate overtime based on worked time vs target
 */
export const calculateOvertime = (
  workedMinutes: number,
  targetMinutes: number
): number => {
  return Math.max(0, workedMinutes - targetMinutes);
};

/**
 * Calculate weekly summary statistics
 */
export const calculateWeeklySummary = (
  dailyEntries: TimeEntry[],
  config: WorkTimeConfig
) => {
  const totalWorked = dailyEntries.reduce(
    (sum, entry) => sum + entry.duration, 
    0
  );
  
  const targetMinutes = config.contractHoursPerWeek * TIME_CONSTANTS.MINUTES_PER_HOUR;
  const overtime = calculateOvertime(totalWorked, targetMinutes);
  
  return {
    totalWorked,
    targetMinutes,
    overtime,
    averagePerDay: totalWorked / config.workDaysPerWeek
  };
};

/**
 * Format duration for display
 */
export const formatDuration = (minutes: number): string => {
  const hours = Math.floor(minutes / TIME_CONSTANTS.MINUTES_PER_HOUR);
  const mins = minutes % TIME_CONSTANTS.MINUTES_PER_HOUR;
  return `${hours}:${mins.toString().padStart(2, '0')}`;
};

// Default German work time configuration
export const DEFAULT_WORK_CONFIG: WorkTimeConfig = {
  contractHoursPerWeek: 40,
  workDaysPerWeek: 5,
  breakRules: [
    { minWorkHours: 6, breakMinutes: 30 },
    { minWorkHours: 9, breakMinutes: 45 }
  ]
};
```

## Implementation Steps (1 hour)

### Phase 1: Create Utility Module (20 minutes)
- [ ] Create `utils/timeCalculations.ts`
- [ ] Implement core calculation functions
- [ ] Add TypeScript interfaces
- [ ] Include German labor law rules
- [ ] Add JSDoc documentation

### Phase 2: Find & Replace (25 minutes)
- [ ] Search for inline calculations
- [ ] Replace with utility function calls
- [ ] Update component imports
- [ ] Ensure consistent usage
- [ ] Remove duplicate logic

### Phase 3: Testing (10 minutes)
- [ ] Create unit tests for utilities
- [ ] Test edge cases
- [ ] Verify calculation accuracy
- [ ] Test with different configs
- [ ] Ensure no regressions

### Phase 4: Documentation (5 minutes)
- [ ] Document calculation rules
- [ ] Add usage examples
- [ ] Update component docs
- [ ] Note legal requirements

## Unit Tests
```typescript
// utils/timeCalculations.test.ts
describe('timeCalculations', () => {
  describe('calculateDailySollTime', () => {
    it('calculates 8-hour day with 30min break', () => {
      const result = calculateDailySollTime(DEFAULT_WORK_CONFIG);
      expect(result).toBe(510); // 480 + 30
    });
    
    it('handles part-time correctly', () => {
      const partTimeConfig = {
        ...DEFAULT_WORK_CONFIG,
        contractHoursPerWeek: 20
      };
      const result = calculateDailySollTime(partTimeConfig);
      expect(result).toBe(240); // 4 hours, no break
    });
  });
  
  describe('calculateOvertime', () => {
    it('returns 0 for no overtime', () => {
      expect(calculateOvertime(480, 480)).toBe(0);
    });
    
    it('calculates positive overtime', () => {
      expect(calculateOvertime(540, 480)).toBe(60);
    });
  });
});
```

## Success Criteria
- [ ] All calculations in centralized utilities
- [ ] No inline time calculations in components
- [ ] Full unit test coverage
- [ ] Consistent calculation results
- [ ] Clear documentation

## Configuration Integration
```typescript
// Usage in components
import { calculateDailySollTime, DEFAULT_WORK_CONFIG } from '@/utils/timeCalculations';

const MyComponent = () => {
  const userConfig = {
    ...DEFAULT_WORK_CONFIG,
    contractHoursPerWeek: user.contractHours
  };
  
  const dailyTarget = calculateDailySollTime(userConfig);
  // Use calculated value
};
```

## Acceptance Criteria
1. Utility module with all time calculations
2. No duplicate calculation logic
3. 100% unit test coverage
4. German labor law compliance
5. Easy configuration support

## Priority Level
**MEDIUM** - Important for accuracy and maintainability

## Estimated Effort
- **Development**: 45 minutes
- **Testing**: 15 minutes
- **Total**: 1 hour

## Labels
`refactoring`, `utilities`, `calculations`, `medium-priority`, `1-hour`

## Related Issues
- Issue #030: Extract Time Constants
- Issue #012: Database Query Performance

## Expected Benefits
- **Accuracy**: Consistent calculations everywhere
- **Testability**: Isolated unit testing
- **Maintainability**: Single place to update logic
- **Compliance**: Centralized labor law rules
- **Reusability**: Use in reports, exports, etc.
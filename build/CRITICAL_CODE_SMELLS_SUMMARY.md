# Critical Code Smells - Quick Reference

## 🔴 TOP 5 CRITICAL ISSUES

### 1. **God Object: MainAppView.tsx (525 lines)**
```
- 17+ useState hooks
- Handles: auth, timer, data, navigation, modals, calculations
- Cognitive Complexity: ~50+
- Direct API calls instead of using api module
```

### 2. **Duplicate Timer Logic**
```
MainAppView.tsx:
- Lines 103-122: checkForRunningTimer()
- Lines 253-272: Duplicate timer verification
- Lines 229-278 & 285-313: Direct fetch() calls
```

### 3. **Magic Numbers Everywhere**
```
3600 (seconds/hour): 11 occurrences
8 * 60 * 60 * 1000: Line 151 (8 hour reminder)
15000: API timeout in api.ts
100: Timer double-check delay
```

### 4. **Backend Chaos: 40+ PHP Files**
```
Production contains:
- 15+ debug files (debug-*.php)
- 8+ test files (test-*.php)  
- 3 versions of time-entries.php
- Multiple fix-*.php patches
```

### 5. **Inconsistent Error Handling**
```
- Some try/catch blocks log errors
- Some silently fail
- No error boundaries
- Generic error messages
```

## 🟡 QUICK WINS (< 1 hour each)

1. **Extract Constants**
   ```typescript
   // Before
   const dailySollTime = (weeklyHours / workdays.length) * 3600;
   
   // After
   const dailySollTime = (weeklyHours / workdays.length) * TIME.SECONDS_PER_HOUR;
   ```

2. **Remove Debug Files**
   ```bash
   rm api/debug-*.php api/test-*.php api/*-quickfix.php api/*.backup.php
   ```

3. **Consolidate Timer API Calls**
   ```typescript
   // Before: Direct fetch in MainAppView
   await fetch('/api/time-entries.php?action=check_running', {...})
   
   // After: Use api module
   await api.timer.checkRunning()
   ```

## 🟢 REFACTORING PRIORITIES

| Component | Current LOC | Target LOC | Complexity |
|-----------|------------|------------|------------|
| MainAppView.tsx | 525 | < 200 | High → Low |
| TimeSheetView.tsx | 218 | < 150 | Medium → Low |
| Backend APIs | 40+ files | < 15 files | High → Medium |

## ⚡ IMMEDIATE ACTIONS

1. **Backend Cleanup** (30 mins)
   - Run `cleanup-production.php` script
   - Merge duplicate APIs

2. **Extract Timer Component** (2 hours)
   - Move lines 86-325 to `TimerControl.tsx`
   - Create `useTimer` hook

3. **Constants Module** (1 hour)
   - Create `/constants/index.ts`
   - Replace all magic numbers

4. **Error Boundary** (1 hour)
   - Wrap app in ErrorBoundary
   - Centralize error handling

## 📊 EXPECTED IMPACT

- **Code Reduction**: -40% lines in MainAppView
- **Complexity**: From 50+ to <10 per component  
- **Testability**: From 0% to 80% coverage possible
- **Performance**: Reduced re-renders, better memoization
- **Maintainability**: Clear separation of concerns
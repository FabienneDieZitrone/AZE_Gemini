# Issue #027: Extract Timer Service from MainAppView God Object

## Priority: HIGH 🔴

## Description
The MainAppView.tsx component is a 525-line God Object that violates SOLID principles, specifically the Single Responsibility Principle. The timer-related logic (lines 86-325) should be extracted into a dedicated TimerService component to improve maintainability, testability, and code reusability.

## Problem Analysis
- **God Object Anti-Pattern**: MainAppView handles too many responsibilities
- **Mixed Concerns**: Timer logic intertwined with UI state management
- **Difficult Testing**: Cannot unit test timer logic in isolation
- **Code Duplication**: Timer state management repeated in multiple places
- **Poor Maintainability**: Changes to timer logic require modifying the entire component
- **Violation of SRP**: Component handles timer, notifications, data management, and UI

## Impact Analysis
- **Severity**: HIGH
- **Maintenance Cost**: High - Any timer changes affect entire component
- **Testing Difficulty**: Very High - Cannot test timer logic independently
- **Code Quality**: Critical - Major SOLID principle violation
- **Refactoring Time**: 1-2 hours
- **Risk Level**: Low - Straightforward extraction

## Current Code Issues
```typescript
// Current problematic code in MainAppView.tsx (lines 86-325)
const [isRunning, setIsRunning] = useState(false);
const [currentSeconds, setCurrentSeconds] = useState(0);
const [displayTime, setDisplayTime] = useState('00:00:00');
// ... 200+ lines of timer-related logic mixed with other concerns
```

## Proposed Solution
Extract timer functionality into a dedicated `TimerService` component and custom hook:

### 1. Create `useTimer` Hook
```typescript
// hooks/useTimer.ts
export const useTimer = () => {
  const [isRunning, setIsRunning] = useState(false);
  const [currentSeconds, setCurrentSeconds] = useState(0);
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  const start = useCallback(() => {
    setIsRunning(true);
    intervalRef.current = setInterval(() => {
      setCurrentSeconds(prev => prev + 1);
    }, 1000);
  }, []);

  const stop = useCallback(() => {
    setIsRunning(false);
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
    }
  }, []);

  const reset = useCallback(() => {
    setCurrentSeconds(0);
  }, []);

  return {
    isRunning,
    currentSeconds,
    displayTime: formatTime(currentSeconds),
    start,
    stop,
    reset
  };
};
```

### 2. Create `TimerService` Component
```typescript
// components/TimerService.tsx
interface TimerServiceProps {
  onTimerUpdate: (seconds: number) => void;
  onTimerStart: () => void;
  onTimerStop: () => void;
}

export const TimerService: React.FC<TimerServiceProps> = ({
  onTimerUpdate,
  onTimerStart,
  onTimerStop
}) => {
  const timer = useTimer();
  
  useEffect(() => {
    onTimerUpdate(timer.currentSeconds);
  }, [timer.currentSeconds, onTimerUpdate]);

  return (
    <TimerDisplay
      displayTime={timer.displayTime}
      isRunning={timer.isRunning}
      onStart={() => {
        timer.start();
        onTimerStart();
      }}
      onStop={() => {
        timer.stop();
        onTimerStop();
      }}
    />
  );
};
```

## Implementation Steps (1-2 hours)

### Phase 1: Extract Hook (30 minutes)
- [ ] Create `hooks/useTimer.ts` file
- [ ] Move timer state logic from MainAppView
- [ ] Extract timer methods (start, stop, reset)
- [ ] Add proper TypeScript types
- [ ] Export hook for reuse

### Phase 2: Create Component (30 minutes)
- [ ] Create `components/TimerService.tsx`
- [ ] Implement TimerService with props interface
- [ ] Move timer UI elements to new component
- [ ] Add proper event callbacks
- [ ] Handle timer lifecycle

### Phase 3: Refactor MainAppView (30 minutes)
- [ ] Remove timer state from MainAppView
- [ ] Import and use TimerService component
- [ ] Connect timer callbacks to existing logic
- [ ] Update timer-dependent methods
- [ ] Clean up unused imports

### Phase 4: Test & Verify (30 minutes)
- [ ] Test timer start/stop functionality
- [ ] Verify time tracking accuracy
- [ ] Ensure API calls still work
- [ ] Check supervisor notifications
- [ ] Validate no regressions

## Success Criteria
- [ ] MainAppView reduced by ~200 lines
- [ ] Timer logic fully isolated in dedicated component
- [ ] All timer functionality working as before
- [ ] Timer logic can be unit tested independently
- [ ] No regressions in existing functionality

## Technical Requirements
- **React**: 18.x with hooks
- **TypeScript**: Proper type definitions
- **Testing**: Timer hook should be testable in isolation
- **Performance**: No additional re-renders

## Code Quality Improvements
- ✅ Single Responsibility Principle respected
- ✅ Improved testability
- ✅ Better code organization
- ✅ Reusable timer logic
- ✅ Cleaner component structure

## Testing Strategy
```typescript
// hooks/useTimer.test.ts
describe('useTimer', () => {
  it('should start timer and increment seconds', () => {
    const { result } = renderHook(() => useTimer());
    
    act(() => {
      result.current.start();
    });
    
    expect(result.current.isRunning).toBe(true);
    
    // Fast-forward time
    act(() => {
      jest.advanceTimersByTime(3000);
    });
    
    expect(result.current.currentSeconds).toBe(3);
  });
});
```

## Acceptance Criteria
1. Timer functionality extracted to separate component
2. MainAppView no longer contains timer state management
3. All existing timer features work identically
4. Timer logic can be unit tested independently
5. No performance regressions

## Priority Level
**HIGH** - Critical for code maintainability and SOLID principles

## Estimated Effort
- **Development Time**: 1-2 hours
- **Testing Time**: 30 minutes
- **Risk**: Low - Straightforward refactoring

## Labels
`refactoring`, `frontend`, `solid-principles`, `high-priority`, `quick-win`, `1-2-hours`

## Related Issues
- Issue #016: Component Reusability Improvements
- Issue #024: Refactoring als Standard etablieren

## Expected Benefits
- **Immediate**: Cleaner, more maintainable code
- **Testing**: Can unit test timer logic independently
- **Reusability**: Timer can be used in other components
- **Maintainability**: Timer changes don't affect main component
- **Performance**: Potential for optimization in isolated component
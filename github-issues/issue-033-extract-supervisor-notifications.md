# Issue #033: Extract SupervisorNotifications from MainAppView

## Priority: HIGH 🔴

## Description
The MainAppView component contains mixed supervisor notification logic that should be extracted into a dedicated component. This violates Single Responsibility Principle and makes the code difficult to test and maintain.

## Problem Analysis
- **Mixed Concerns**: Notification logic scattered throughout MainAppView
- **Duplicate Logic**: Notification checks repeated in multiple places
- **Testing Difficulty**: Cannot test notifications in isolation
- **Poor Reusability**: Notification system tied to specific component
- **State Management**: Notification state mixed with other concerns

## Impact Analysis
- **Severity**: HIGH
- **Code Quality**: Major SRP violation
- **Refactoring Time**: 1 hour
- **Risk Level**: Low
- **Maintainability**: High improvement potential

## Current Code Structure
```typescript
// MainAppView.tsx - Mixed notification logic
const [notifications, setNotifications] = useState([]);
const [unreadCount, setUnreadCount] = useState(0);

// Notification logic scattered across component
useEffect(() => {
  if (user?.role === 'supervisor') {
    fetchPendingApprovals();
    checkOverdueTimesheets();
    // More notification logic...
  }
}, [user]);

// Mixed with other component logic
const handleNotificationClick = () => {
  // Notification handling mixed with UI state
};
```

## Proposed Solution

### 1. Create Dedicated Notification Hook
```typescript
// hooks/useSupervisorNotifications.ts
export const useSupervisorNotifications = (userId: string) => {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isLoading, setIsLoading] = useState(false);

  const fetchNotifications = useCallback(async () => {
    if (!userId) return;
    
    setIsLoading(true);
    try {
      const [approvals, overdues, alerts] = await Promise.all([
        fetchPendingApprovals(userId),
        fetchOverdueTimesheets(userId),
        fetchSystemAlerts(userId)
      ]);
      
      const allNotifications = [
        ...approvals.map(formatApprovalNotification),
        ...overdues.map(formatOverdueNotification),
        ...alerts
      ];
      
      setNotifications(allNotifications);
      setUnreadCount(allNotifications.filter(n => !n.read).length);
    } finally {
      setIsLoading(false);
    }
  }, [userId]);

  const markAsRead = useCallback((notificationId: string) => {
    setNotifications(prev =>
      prev.map(n => n.id === notificationId ? { ...n, read: true } : n)
    );
    setUnreadCount(prev => Math.max(0, prev - 1));
  }, []);

  return {
    notifications,
    unreadCount,
    isLoading,
    fetchNotifications,
    markAsRead,
    markAllAsRead
  };
};
```

### 2. Create Notification Component
```typescript
// components/SupervisorNotifications.tsx
interface SupervisorNotificationsProps {
  userId: string;
  onNotificationClick?: (notification: Notification) => void;
}

export const SupervisorNotifications: React.FC<SupervisorNotificationsProps> = ({
  userId,
  onNotificationClick
}) => {
  const {
    notifications,
    unreadCount,
    isLoading,
    fetchNotifications,
    markAsRead
  } = useSupervisorNotifications(userId);

  useEffect(() => {
    fetchNotifications();
    const interval = setInterval(fetchNotifications, 60000); // Refresh every minute
    return () => clearInterval(interval);
  }, [fetchNotifications]);

  return (
    <NotificationDropdown
      notifications={notifications}
      unreadCount={unreadCount}
      isLoading={isLoading}
      onNotificationClick={(notification) => {
        markAsRead(notification.id);
        onNotificationClick?.(notification);
      }}
    />
  );
};
```

## Implementation Steps (1 hour)

### Phase 1: Extract Hook (20 minutes)
- [ ] Create `hooks/useSupervisorNotifications.ts`
- [ ] Move notification state logic
- [ ] Extract notification fetching methods
- [ ] Add proper TypeScript types
- [ ] Implement notification formatting

### Phase 2: Create Component (20 minutes)
- [ ] Create `components/SupervisorNotifications.tsx`
- [ ] Implement notification UI component
- [ ] Add dropdown/badge functionality
- [ ] Handle notification interactions
- [ ] Style with existing design system

### Phase 3: Refactor MainAppView (15 minutes)
- [ ] Remove notification logic from MainAppView
- [ ] Import and use SupervisorNotifications
- [ ] Clean up related state and effects
- [ ] Update any dependent logic
- [ ] Verify functionality

### Phase 4: Testing (5 minutes)
- [ ] Test notification fetching
- [ ] Verify auto-refresh works
- [ ] Check mark as read functionality
- [ ] Ensure no regressions
- [ ] Test role-based visibility

## Success Criteria
- [ ] All notification logic extracted from MainAppView
- [ ] Notifications work as before
- [ ] Component is reusable
- [ ] Can be tested independently
- [ ] Clean separation of concerns

## Testing Strategy
```typescript
describe('useSupervisorNotifications', () => {
  it('fetches notifications for supervisors', async () => {
    const { result } = renderHook(() => 
      useSupervisorNotifications('supervisor-123')
    );
    
    await act(async () => {
      await result.current.fetchNotifications();
    });
    
    expect(result.current.notifications).toHaveLength(3);
    expect(result.current.unreadCount).toBe(2);
  });
});
```

## Priority Level
**HIGH** - Important for code organization and maintainability

## Estimated Effort
- **Development**: 50 minutes
- **Testing**: 10 minutes
- **Total**: 1 hour

## Labels
`refactoring`, `frontend`, `components`, `high-priority`, `1-hour`

## Related Issues
- Issue #027: Extract Timer Service from MainAppView
- Issue #016: Component Reusability Improvements

## Expected Benefits
- **Separation of Concerns**: Clean component architecture
- **Reusability**: Notifications can be used elsewhere
- **Testability**: Isolated testing possible
- **Maintainability**: Easier to modify notification logic
- **Performance**: Potential for optimization
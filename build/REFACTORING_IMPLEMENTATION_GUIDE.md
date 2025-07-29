# AZE_Gemini Refactoring Implementation Guide

## Phase 1: Extract Constants and Clean Backend (Week 1)

### 1.1 Create Constants Module

**File: `/app/build/src/constants/index.ts`**
```typescript
export const TIME = {
  SECONDS_PER_MINUTE: 60,
  SECONDS_PER_HOUR: 3600,
  SECONDS_PER_DAY: 86400,
  MINUTES_PER_HOUR: 60,
  HOURS_PER_DAY: 24,
  MILLISECONDS_PER_SECOND: 1000,
  MILLISECONDS_PER_MINUTE: 60000,
  MILLISECONDS_PER_HOUR: 3600000,
} as const;

export const TIMER = {
  REMINDER_TIMEOUT_HOURS: 8,
  UPDATE_INTERVAL_MS: 1000,
  DOUBLE_CHECK_DELAY_MS: 100,
} as const;

export const API = {
  TIMEOUT_MS: 15000,
  BASE_URL: '/api',
  CREDENTIALS: 'include' as RequestCredentials,
} as const;

export const UI = {
  DATE_FORMAT: 'de-DE',
  DATE_OPTIONS: { 
    weekday: 'long' as const, 
    year: 'numeric' as const, 
    month: '2-digit' as const, 
    day: '2-digit' as const 
  },
} as const;
```

### 1.2 Backend Cleanup Script

**File: `/app/build/api/cleanup-production.php`**
```php
<?php
// List of files to remove from production
$filesToRemove = [
    'debug-timer-stop.php',
    'debug-stop-issue.php',
    'debug-session-timer.php',
    'diagnose-timer-500.php',
    'test-claude-account.php',
    'test-timer-functionality.php',
    'automated-test-suite.php',
    'automated-test-claude.php',
    'claude-automated-login-test.php',
    'auth-callback.backup.php',
    'time-entries-fixed.php',
    'time-entries-quickfix.php',
    'fix-*.php',
    'verify-*.php',
    'execute-migration-*.php'
];

foreach ($filesToRemove as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        unlink($path);
        echo "Removed: $file\n";
    }
}
?>
```

## Phase 2: Component Decomposition (Week 2-3)

### 2.1 Extract Timer Component

**File: `/app/build/src/components/timer/TimerControl.tsx`**
```typescript
import React, { useState, useEffect, useCallback, useRef } from 'react';
import { User } from '../../types';
import { TIME, TIMER } from '../../constants';
import { api } from '../../../api';
import { formatTime } from '../../utils/time';

interface TimerControlProps {
  currentUser: User;
  currentLocation: string;
  onTimerStateChange?: (isTracking: boolean, elapsedTime: number) => void;
}

export const TimerControl: React.FC<TimerControlProps> = ({ 
  currentUser, 
  currentLocation,
  onTimerStateChange 
}) => {
  const [isTracking, setIsTracking] = useState(false);
  const [elapsedTime, setElapsedTime] = useState(0);
  const [activeTimerStartTime, setActiveTimerStartTime] = useState<number | null>(null);
  const [currentTimerId, setCurrentTimerId] = useState<number | null>(null);
  const hasCheckedForRunningTimer = useRef(false);

  // Check for running timer on mount
  useEffect(() => {
    if (currentUser && !hasCheckedForRunningTimer.current) {
      hasCheckedForRunningTimer.current = true;
      checkForRunningTimer();
    }
  }, [currentUser]);

  // Timer interval and reminder
  useEffect(() => {
    let interval: ReturnType<typeof setInterval> | null = null;
    let reminderTimeout: ReturnType<typeof setTimeout> | null = null;
    
    if (isTracking && activeTimerStartTime) {
      interval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - activeTimerStartTime) / TIME.MILLISECONDS_PER_SECOND);
        setElapsedTime(elapsed);
        onTimerStateChange?.(true, elapsed);
      }, TIMER.UPDATE_INTERVAL_MS);
      
      reminderTimeout = setTimeout(() => {
        if (isTracking) {
          alert("Erinnerung: Die Zeiterfassung läuft noch. Haben Sie vergessen, auf 'Stop' zu klicken?");
        }
      }, TIMER.REMINDER_TIMEOUT_HOURS * TIME.MILLISECONDS_PER_HOUR);
    }
    
    return () => { 
      if (interval) clearInterval(interval); 
      if (reminderTimeout) clearTimeout(reminderTimeout);
    };
  }, [isTracking, activeTimerStartTime, onTimerStateChange]);

  const checkForRunningTimer = async () => {
    try {
      const data = await api.timer.checkRunning();
      if (data.hasRunningTimer && data.runningTimer) {
        const startTime = new Date(`${data.runningTimer.date}T${data.runningTimer.startTime}`).getTime();
        setActiveTimerStartTime(startTime);
        setIsTracking(true);
        setCurrentTimerId(data.runningTimer.id);
        console.log('Running timer found on server:', data.runningTimer);
      }
    } catch (error) {
      console.warn('Failed to check for running timer:', error);
    }
  };

  const handleToggleTracking = async () => {
    if (!currentUser) return;

    try {
      if (isTracking) {
        await stopTimer();
      } else {
        await startTimer();
      }
    } catch (err) {
      const action = isTracking ? 'Stoppen' : 'Starten';
      const msg = `Fehler beim ${action} der Zeiterfassung: ${(err as Error).message}`;
      throw new Error(msg);
    }
  };

  const startTimer = async () => {
    const now = new Date();
    const data = await api.timer.start({
      userId: currentUser.id,
      username: currentUser.name,
      date: now.toISOString().split('T')[0],
      startTime: now.toTimeString().split(' ')[0],
      stopTime: null,
      location: currentLocation,
      role: currentUser.role,
      updatedBy: currentUser.name
    });

    const startTimeMs = new Date(`${data.date}T${data.startTime}`).getTime();
    setActiveTimerStartTime(startTimeMs);
    setIsTracking(true);
    setCurrentTimerId(data.id);
    setElapsedTime(0);
  };

  const stopTimer = async () => {
    if (!currentTimerId) {
      throw new Error('No timer ID available');
    }

    const now = new Date();
    await api.timer.stop({
      id: currentTimerId,
      stopTime: now.toTimeString().split(' ')[0],
      updatedBy: currentUser.name
    });

    // Update local state immediately
    setIsTracking(false);
    setActiveTimerStartTime(null);
    setCurrentTimerId(null);
    setElapsedTime(0);
    onTimerStateChange?.(false, 0);

    // Double-check timer stopped
    setTimeout(verifyTimerStopped, TIMER.DOUBLE_CHECK_DELAY_MS);
  };

  const verifyTimerStopped = async () => {
    try {
      const data = await api.timer.checkRunning();
      if (data.hasRunningTimer) {
        console.error('WARNING: Timer still running after stop!', data.runningTimer);
        // Force stop any remaining timer
        setIsTracking(false);
        setActiveTimerStartTime(null);
        setCurrentTimerId(null);
        setElapsedTime(0);
        onTimerStateChange?.(false, 0);
      }
    } catch (error) {
      console.warn('Failed to verify timer stop:', error);
    }
  };

  return (
    <section className="tracking-section" aria-label="Zeiterfassung">
      <div className="label">Zeiterfassung starten / stoppen</div>
      <div className="tracking-controls">
        <button 
          onClick={handleToggleTracking} 
          className={`toggle-button ${isTracking ? 'stop-button' : 'start-button'}`} 
          aria-live="polite"
        >
          {isTracking ? 'Stop' : 'Start'}
        </button>
        {isTracking && (
          <div className="timer-display" aria-label="Abgelaufene Zeit">
            {formatTime(elapsedTime, true)}
          </div>
        )}
      </div>
    </section>
  );
};
```

### 2.2 Extract Overtime Calculator Hook

**File: `/app/build/src/hooks/useOvertimeCalculation.ts`**
```typescript
import { useMemo } from 'react';
import { User, TimeEntry, MasterData } from '../types';
import { TIME } from '../constants';
import { calculateDurationInSeconds } from '../utils/time';

export const useOvertimeCalculation = (
  currentUser: User | null,
  timeEntries: TimeEntry[],
  masterData: Record<number, MasterData>
) => {
  const calculatedOvertimeSeconds = useMemo(() => {
    if (!currentUser || !masterData[currentUser.id]) return 0;
    
    const userMasterData = masterData[currentUser.id];
    if (!userMasterData || userMasterData.workdays.length === 0) return 0;

    const dailySollSeconds = (userMasterData.weeklyHours / userMasterData.workdays.length) * TIME.SECONDS_PER_HOUR;
    const dayMap: { [key: string]: number } = { 'Mo': 1, 'Di': 2, 'Mi': 3, 'Do': 4, 'Fr': 5, 'Sa': 6, 'So': 0 };
    const workdaysSet = new Set(userMasterData.workdays.map(d => dayMap[d as keyof typeof dayMap]));

    const dailyTotals = timeEntries
      .filter(e => e.userId === currentUser.id)
      .reduce((acc, entry) => {
        const duration = calculateDurationInSeconds(entry.startTime, entry.stopTime);
        acc[entry.date] = (acc[entry.date] || 0) + duration;
        return acc;
      }, {} as Record<string, number>);

    let totalDifference = 0;
    for (const date in dailyTotals) {
      const d = new Date(date + "T00:00:00");
      const dayOfWeek = d.getDay();
      
      if (workdaysSet.has(dayOfWeek)) {
        totalDifference += (dailyTotals[date] - dailySollSeconds);
      } else {
        totalDifference += dailyTotals[date];
      }
    }
    return totalDifference;
  }, [timeEntries, currentUser, masterData]);

  const formattedOvertime = useMemo(() => {
    const totalHours = calculatedOvertimeSeconds / TIME.SECONDS_PER_HOUR;
    const sign = totalHours >= 0 ? '+' : '-';
    return `(${sign}${Math.abs(totalHours).toFixed(2)}h)`;
  }, [calculatedOvertimeSeconds]);

  return { calculatedOvertimeSeconds, formattedOvertime };
};
```

### 2.3 Extract Supervisor Notification Logic

**File: `/app/build/src/hooks/useSupervisorNotifications.ts`**
```typescript
import { useEffect, useState } from 'react';
import { User, TimeEntry, MasterData, GlobalSettings, SupervisorNotification } from '../types';
import { TIME } from '../constants';
import { getStartOfWeek, calculateDurationInSeconds } from '../utils/time';

export const useSupervisorNotifications = (
  currentUser: User | null,
  users: User[],
  timeEntries: TimeEntry[],
  masterData: Record<number, MasterData>,
  globalSettings: GlobalSettings | null
) => {
  const [notifications, setNotifications] = useState<SupervisorNotification[]>([]);
  const [showModal, setShowModal] = useState(false);

  useEffect(() => {
    if (!currentUser || !globalSettings || !masterData || timeEntries.length === 0) return;
    
    const canCheck = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    if (!canCheck) return;

    const newNotifications: SupervisorNotification[] = [];
    const thresholdSeconds = globalSettings.overtimeThreshold * TIME.SECONDS_PER_HOUR;
    
    const lastWeekStart = getStartOfWeek(new Date(new Date().setDate(new Date().getDate() - 7)));
    const lastWeekEnd = new Date(lastWeekStart);
    lastWeekEnd.setDate(lastWeekStart.getDate() + 6);

    const subordinates = users.filter(u => u.id !== currentUser.id);

    subordinates.forEach(user => {
      const userMaster = masterData[user.id];
      if (!userMaster) return;

      const weeklySollSeconds = userMaster.weeklyHours * TIME.SECONDS_PER_HOUR;
      const userEntriesLastWeek = timeEntries.filter(e => {
        return e.userId === user.id && 
               e.date >= lastWeekStart.toISOString().split('T')[0] && 
               e.date <= lastWeekEnd.toISOString().split('T')[0];
      });
      
      const totalSecondsWorked = userEntriesLastWeek.reduce(
        (sum, e) => sum + calculateDurationInSeconds(e.startTime, e.stopTime), 
        0
      );
      const deviationSeconds = totalSecondsWorked - weeklySollSeconds;
      
      if (Math.abs(deviationSeconds) > thresholdSeconds) {
        newNotifications.push({
          employeeName: user.name,
          deviationHours: deviationSeconds / TIME.SECONDS_PER_HOUR,
        });
      }
    });

    if (newNotifications.length > 0) {
      setNotifications(newNotifications);
      setShowModal(true);
    }
  }, [currentUser, users, masterData, timeEntries, globalSettings]);

  return {
    notifications,
    showModal,
    closeModal: () => setShowModal(false)
  };
};
```

### 2.4 Refactored MainAppView

**File: `/app/build/src/views/MainAppView.refactored.tsx`**
```typescript
import React, { useState, useEffect, useCallback } from 'react';
import { api } from '../../api';
import { User, ViewState, MasterData, TimeEntry, ApprovalRequest, HistoryEntry, GlobalSettings } from '../types';
import { UI } from '../constants';

// Components
import { Logo } from '../components/common/Logo';
import { LoadingSpinner } from '../components/common/LoadingSpinner';
import { ThemeToggle } from '../components/common/ThemeToggle';
import { TimerControl } from '../components/timer/TimerControl';
import { NavigationMenu } from '../components/navigation/NavigationMenu';
import { ViewRouter } from '../components/routing/ViewRouter';
import { ModalContainer } from '../components/modals/ModalContainer';

// Hooks
import { useDataManagement } from '../hooks/useDataManagement';
import { useOvertimeCalculation } from '../hooks/useOvertimeCalculation';
import { useSupervisorNotifications } from '../hooks/useSupervisorNotifications';
import { useTheme } from '../hooks/useTheme';

export const MainAppView: React.FC = () => {
  const [viewState, setViewState] = useState<ViewState>({ current: 'main' });
  const [currentLocation] = useState('Zentrale Berlin');
  
  // Custom hooks
  const { theme, toggleTheme } = useTheme();
  const {
    currentUser,
    users,
    masterData,
    timeEntries,
    approvalRequests,
    history,
    globalSettings,
    isLoading,
    error,
    refreshData,
    updateMasterData,
    updateUserRole,
    processApprovalRequest,
    updateGlobalSettings,
    requestEntryChange
  } = useDataManagement();
  
  const { formattedOvertime } = useOvertimeCalculation(currentUser, timeEntries, masterData);
  const { notifications, showModal, closeModal } = useSupervisorNotifications(
    currentUser, users, timeEntries, masterData, globalSettings
  );

  const currentDate = new Intl.DateTimeFormat('de-DE', UI.DATE_OPTIONS).format(new Date());

  const handleLogout = useCallback(() => {
    window.location.href = '/api/auth-logout.php';
  }, []);

  if (isLoading) return <LoadingSpinner />;
  if (error) return <div className="error-message full-page-error">{error}</div>;
  if (!currentUser || !globalSettings) return <LoadingSpinner />;

  return (
    <div className="app-container">
      <div className="app-header-bar">
        <Logo />
        <h1 className="app-main-title">MP Arbeitszeiterfassung</h1>
        {currentUser && <button onClick={handleLogout} className="logout-button">Abmelden</button>}
      </div>
      
      {viewState.current === 'main' && (
        <>
          <header className="main-view-header" aria-live="polite">
            {`${currentUser.name} ${formattedOvertime} - ${currentDate}`}
          </header>
          <div className="location-display">
            Erkannter Standort: <strong>{currentLocation}</strong>
          </div>
          
          <TimerControl 
            currentUser={currentUser} 
            currentLocation={currentLocation}
            onTimerStateChange={() => refreshData()}
          />
          
          <NavigationMenu
            currentUser={currentUser}
            approvalRequests={approvalRequests}
            onNavigate={setViewState}
          />
          
          <div className="main-view-settings">
            <ThemeToggle theme={theme} toggleTheme={toggleTheme} />
          </div>
        </>
      )}
      
      {viewState.current !== 'main' && (
        <ViewRouter
          viewState={viewState}
          setViewState={setViewState}
          currentUser={currentUser}
          users={users}
          masterData={masterData}
          timeEntries={timeEntries}
          approvalRequests={approvalRequests}
          history={history}
          globalSettings={globalSettings}
          onDataUpdate={refreshData}
          handlers={{
            updateMasterData,
            updateUserRole,
            processApprovalRequest,
            updateGlobalSettings,
            requestEntryChange
          }}
        />
      )}
      
      <ModalContainer
        currentUser={currentUser}
        globalSettings={globalSettings}
        supervisorNotifications={notifications}
        showSupervisorModal={showModal}
        onCloseSupervisorModal={closeModal}
      />
    </div>
  );
};
```

## Phase 3: API Consolidation (Week 4)

### 3.1 Unified Timer API

**File: `/app/build/api/timer.php`**
```php
<?php
require_once 'db.php';
require_once 'auth_helpers.php';
require_once 'validation.php';
require_once 'error-handler.php';

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Authentication check
    $userId = checkAuth();
    if (!$userId) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'check_running') {
                handleCheckRunning($userId);
            } else {
                http_response_code(400);
                die(json_encode(['error' => 'Invalid action']));
            }
            break;
            
        case 'POST':
            if ($action === 'start') {
                handleStartTimer($userId);
            } elseif ($action === 'stop') {
                handleStopTimer($userId);
            } else {
                http_response_code(400);
                die(json_encode(['error' => 'Invalid action']));
            }
            break;
            
        default:
            http_response_code(405);
            die(json_encode(['error' => 'Method not allowed']));
    }
} catch (Exception $e) {
    handleError($e);
}

function handleCheckRunning($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT id, date, start_time, location, role 
        FROM time_entries 
        WHERE user_id = ? AND stop_time IS NULL 
        ORDER BY date DESC, start_time DESC 
        LIMIT 1
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'hasRunningTimer' => true,
            'runningTimer' => [
                'id' => $row['id'],
                'date' => $row['date'],
                'startTime' => $row['start_time'],
                'location' => $row['location'],
                'role' => $row['role']
            ]
        ]);
    } else {
        echo json_encode(['hasRunningTimer' => false]);
    }
}

function handleStartTimer($userId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $validation = validateTimerStart($data);
    if (!$validation['valid']) {
        http_response_code(400);
        die(json_encode(['error' => $validation['error']]));
    }
    
    // Check for existing running timer
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM time_entries 
        WHERE user_id = ? AND stop_time IS NULL
    ");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    
    if ($checkResult['count'] > 0) {
        http_response_code(400);
        die(json_encode(['error' => 'Timer already running']));
    }
    
    // Start new timer
    $stmt = $conn->prepare("
        INSERT INTO time_entries 
        (user_id, username, date, start_time, stop_time, location, role, updated_by) 
        VALUES (?, ?, ?, ?, NULL, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "issssss",
        $userId,
        $data['username'],
        $data['date'],
        $data['startTime'],
        $data['location'],
        $data['role'],
        $data['updatedBy']
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'id' => $conn->insert_id,
            'date' => $data['date'],
            'startTime' => $data['startTime']
        ]);
    } else {
        throw new Exception('Failed to start timer');
    }
}

function handleStopTimer($userId) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $validation = validateTimerStop($data);
    if (!$validation['valid']) {
        http_response_code(400);
        die(json_encode(['error' => $validation['error']]));
    }
    
    // Update timer
    $stmt = $conn->prepare("
        UPDATE time_entries 
        SET stop_time = ?, updated_by = ?, updated_at = NOW() 
        WHERE id = ? AND user_id = ? AND stop_time IS NULL
    ");
    
    $stmt->bind_param(
        "ssii",
        $data['stopTime'],
        $data['updatedBy'],
        $data['id'],
        $userId
    );
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to stop timer or timer not found');
    }
}
?>
```

### 3.2 Update API Module

**File: `/app/build/api.ts` (updated sections)**
```typescript
// Add to existing api object
export const api = {
  // ... existing methods
  
  timer: {
    checkRunning: async () => {
      return fetchApi('/timer.php?action=check_running', { method: 'GET' });
    },
    
    start: async (data: {
      userId: number;
      username: string;
      date: string;
      startTime: string;
      location: string;
      role: string;
      updatedBy: string;
    }) => {
      return fetchApi('/timer.php?action=start', {
        method: 'POST',
        body: JSON.stringify(data)
      });
    },
    
    stop: async (data: {
      id: number;
      stopTime: string;
      updatedBy: string;
    }) => {
      return fetchApi('/timer.php?action=stop', {
        method: 'POST',
        body: JSON.stringify(data)
      });
    }
  }
};
```

## Testing Strategy

### Unit Tests for Extracted Components

**File: `/app/build/src/components/timer/TimerControl.test.tsx`**
```typescript
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { TimerControl } from './TimerControl';
import { api } from '../../../api';

jest.mock('../../../api');

describe('TimerControl', () => {
  const mockUser = {
    id: 1,
    name: 'Test User',
    role: 'Mitarbeiter'
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  test('checks for running timer on mount', async () => {
    (api.timer.checkRunning as jest.Mock).mockResolvedValue({
      hasRunningTimer: false
    });

    render(<TimerControl currentUser={mockUser} currentLocation="Test Location" />);

    await waitFor(() => {
      expect(api.timer.checkRunning).toHaveBeenCalledTimes(1);
    });
  });

  test('starts timer when start button clicked', async () => {
    (api.timer.checkRunning as jest.Mock).mockResolvedValue({
      hasRunningTimer: false
    });
    (api.timer.start as jest.Mock).mockResolvedValue({
      id: 123,
      date: '2025-07-29',
      startTime: '10:00:00'
    });

    render(<TimerControl currentUser={mockUser} currentLocation="Test Location" />);

    const startButton = await screen.findByText('Start');
    fireEvent.click(startButton);

    await waitFor(() => {
      expect(api.timer.start).toHaveBeenCalledWith(expect.objectContaining({
        userId: mockUser.id,
        username: mockUser.name,
        location: 'Test Location'
      }));
    });
  });
});
```

## Migration Checklist

- [ ] Create constants module
- [ ] Run backend cleanup script
- [ ] Extract TimerControl component
- [ ] Extract overtime calculation hook
- [ ] Extract supervisor notification hook
- [ ] Create unified timer API
- [ ] Update API module
- [ ] Add unit tests for new components
- [ ] Remove direct fetch calls from MainAppView
- [ ] Deploy and test in staging
- [ ] Monitor for any issues
- [ ] Deploy to production

## Post-Refactoring Metrics

Track these metrics after each phase:
- Lines of code per component
- Cyclomatic complexity
- Test coverage percentage
- Build size
- Performance metrics (Lighthouse scores)
- Developer feedback on maintainability
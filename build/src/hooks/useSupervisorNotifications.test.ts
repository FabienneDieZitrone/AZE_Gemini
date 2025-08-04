import { renderHook } from '@testing-library/react';
import { useSupervisorNotifications } from './useSupervisorNotifications';

describe('useSupervisorNotifications', () => {
  const mockUsers = [
    { id: 1, username: 'admin', role: 'Admin', name: 'Admin User' },
    { id: 2, username: 'honor1', role: 'Honorarkraft', name: 'Honor User' }
  ];

  const mockMasterData = {
    1: { userId: 1, weeklyHours: 40 },
    2: { userId: 2, weeklyHours: 20 }
  };

  const mockTimeEntries = [
    { userId: 2, date: '2025-07-25', startTime: '08:00', stopTime: '18:00' }
  ];

  const mockGlobalSettings = {
    overtimeThreshold: 5
  };

  it('should not show notifications for non-supervisor roles', () => {
    const { result } = renderHook(() => 
      useSupervisorNotifications({
        currentUser: { id: 2, role: 'Honorarkraft', username: 'honor1' },
        users: mockUsers,
        masterData: mockMasterData,
        timeEntries: mockTimeEntries,
        globalSettings: mockGlobalSettings
      })
    );

    expect(result.current.showSupervisorModal).toBe(false);
    expect(result.current.supervisorNotifications).toHaveLength(0);
  });
});
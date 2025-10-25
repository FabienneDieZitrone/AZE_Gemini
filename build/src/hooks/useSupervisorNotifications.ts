import { useState, useEffect, useRef } from 'react';
import { User, MasterData, TimeEntry, GlobalSettings, SupervisorNotification, PendingOnboardingUser } from '../types';
import { getStartOfWeek, calculateDurationInSeconds } from '../utils/time';
import { TIME } from '../constants';
import { api } from '../../api';

interface UseSupervisorNotificationsProps {
  currentUser: User | null;
  users: User[];
  masterData: Record<number, MasterData>;
  timeEntries: TimeEntry[];
  globalSettings: GlobalSettings | null;
}

export const useSupervisorNotifications = ({
  currentUser,
  users,
  masterData,
  timeEntries,
  globalSettings
}: UseSupervisorNotificationsProps) => {
  const [supervisorNotifications, setSupervisorNotifications] = useState<SupervisorNotification[]>([]);
  const [pendingOnboardingUsers, setPendingOnboardingUsers] = useState<PendingOnboardingUser[]>([]);
  const [showSupervisorModal, setShowSupervisorModal] = useState(false);
  const shownOnceRef = useRef(false);

  useEffect(() => {
    if (!currentUser || !globalSettings) return;

    // Check if user has supervisor role
    const canCheck = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);
    if (!canCheck) return;

    const loadPendingOnboardingUsers = async () => {
      try {
        const response = await api.getPendingOnboardingUsers();
        if (response?.success && Array.isArray(response.pendingUsers)) {
          setPendingOnboardingUsers(response.pendingUsers);
        }
      } catch (error) {
        console.error('Failed to load pending onboarding users:', error);
      }
    };

    loadPendingOnboardingUsers();

    // Skip overtime notifications if no time entries available
    if (!masterData || timeEntries.length === 0) return;

    const notifications: SupervisorNotification[] = [];

    // ✅ FIX: Defensive access with fallback to default
    const thresholdSeconds = (globalSettings?.overtimeThreshold ?? 8.0) * TIME.SECONDS_PER_HOUR;
    
    // Calculate last week's date range
    const lastWeekStart = getStartOfWeek(new Date(new Date().setDate(new Date().getDate() - 7)));
    const lastWeekEnd = new Date(lastWeekStart);
    lastWeekEnd.setDate(lastWeekStart.getDate() + 6);

    // Get subordinates (all users except current user)
    const subordinates = users.filter(u => u.id !== currentUser.id);

    // Check each subordinate's overtime deviation
    subordinates.forEach(user => {
      const userMaster = masterData[user.id];
      if (!userMaster) return;

      // ✅ FIX: Defensive check for weeklyHours
      if (typeof userMaster.weeklyHours !== 'number' || userMaster.weeklyHours <= 0) return;

      const weeklySollSeconds = userMaster.weeklyHours * TIME.SECONDS_PER_HOUR;
      
      // Filter time entries for last week
      const userEntriesLastWeek = timeEntries.filter(e => {
        return e.userId === user.id &&
               e.date >= lastWeekStart.toISOString().split('T')[0] &&
               e.date <= lastWeekEnd.toISOString().split('T')[0];
      });

      // ✅ FIX: Filter out entries without stopTime (running timers) before calculating
      const completedEntries = userEntriesLastWeek.filter(e => e.stopTime != null && e.stopTime !== '');

      // Calculate total worked seconds
      const totalSecondsWorked = completedEntries.reduce(
        (sum, e) => sum + calculateDurationInSeconds(e.startTime, e.stopTime),
        0
      );
      
      const deviationSeconds = totalSecondsWorked - weeklySollSeconds;
      
      // Check if deviation exceeds threshold
      if (Math.abs(deviationSeconds) > thresholdSeconds) {
        notifications.push({
          employeeName: user.name,
          deviationHours: deviationSeconds / TIME.SECONDS_PER_HOUR,
        });
      }
    });

    // Show modal only once per session (after login), not on every view/interaction
    // Show if either overtime notifications OR pending onboarding users exist
    if ((notifications.length > 0 || pendingOnboardingUsers.length > 0) && !shownOnceRef.current) {
      setSupervisorNotifications(notifications);
      setShowSupervisorModal(true);
      shownOnceRef.current = true;
    }
  }, [currentUser, users, masterData, timeEntries, globalSettings]);
  // ✅ FIX: pendingOnboardingUsers REMOVED from dependency array to prevent infinite loop

  const closeSupervisorModal = () => {
    setShowSupervisorModal(false);
    setSupervisorNotifications([]);
    setPendingOnboardingUsers([]);
  };

  return {
    supervisorNotifications,
    pendingOnboardingUsers,
    showSupervisorModal,
    closeSupervisorModal
  };
};

/**
 * useTimer Hook für AZE_Gemini
 * 
 * Extrahiert aus MainAppView.tsx zur Verbesserung der Code-Organisation
 * und Einhaltung der SOLID-Prinzipien (Issue #027)
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { TIME, TIMER } from '../constants';

export interface UseTimerResult {
  isRunning: boolean;
  elapsedSeconds: number;
  displayTime: string;
  startTime: number | null;
  timerId: number | null;
  start: (startTime: number, timerId: number) => void;
  stop: () => void;
  reset: () => void;
  setFromExisting: (startTime: number, timerId: number) => void;
}

/**
 * Formatiert Sekunden in HH:MM:SS Format
 */
export const formatTime = (seconds: number): string => {
  const hours = Math.floor(seconds / TIME.SECONDS_PER_HOUR);
  const minutes = Math.floor((seconds % TIME.SECONDS_PER_HOUR) / TIME.SECONDS_PER_MINUTE);
  const secs = seconds % TIME.SECONDS_PER_MINUTE;
  
  return [hours, minutes, secs]
    .map(val => val.toString().padStart(2, '0'))
    .join(':');
};

/**
 * Custom Hook für Timer-Funktionalität
 * Verwaltet Timer-Zustand und bietet Start/Stop/Reset Funktionen
 */
export const useTimer = (): UseTimerResult => {
  const [isRunning, setIsRunning] = useState(false);
  const [startTime, setStartTime] = useState<number | null>(null);
  const [elapsedSeconds, setElapsedSeconds] = useState(0);
  const [timerId, setTimerId] = useState<number | null>(null);
  
  const intervalRef = useRef<NodeJS.Timeout | null>(null);
  const reminderTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Timer-Update-Logik
  useEffect(() => {
    if (isRunning && startTime) {
      // Update elapsed time immediately
      setElapsedSeconds(Math.floor((Date.now() - startTime) / TIME.MILLISECONDS_PER_SECOND));
      
      // Set up interval for continuous updates
      intervalRef.current = setInterval(() => {
        setElapsedSeconds(Math.floor((Date.now() - startTime) / TIME.MILLISECONDS_PER_SECOND));
      }, TIMER.UPDATE_INTERVAL_MS);
      
      // Set up 8-hour reminder
      reminderTimeoutRef.current = setTimeout(() => {
        if (isRunning && window.notificationService) {
          window.notificationService.timerReminder();
        }
      }, TIMER.REMINDER_TIMEOUT_HOURS * TIME.MILLISECONDS_PER_HOUR);
    }
    
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
      if (reminderTimeoutRef.current) {
        clearTimeout(reminderTimeoutRef.current);
        reminderTimeoutRef.current = null;
      }
    };
  }, [isRunning, startTime]);

  /**
   * Startet den Timer
   */
  const start = useCallback((newStartTime: number, newTimerId: number) => {
    setStartTime(newStartTime);
    setTimerId(newTimerId);
    setIsRunning(true);
  }, []);

  /**
   * Stoppt den Timer
   */
  const stop = useCallback(() => {
    setIsRunning(false);
    setStartTime(null);
    setTimerId(null);
    setElapsedSeconds(0);
  }, []);

  /**
   * Setzt den Timer zurück
   */
  const reset = useCallback(() => {
    setElapsedSeconds(0);
  }, []);

  /**
   * Setzt Timer von existierendem Timer (z.B. nach Seitenaktualisierung)
   */
  const setFromExisting = useCallback((existingStartTime: number, existingTimerId: number) => {
    setStartTime(existingStartTime);
    setTimerId(existingTimerId);
    setIsRunning(true);
    setElapsedSeconds(Math.floor((Date.now() - existingStartTime) / 1000));
  }, []);

  return {
    isRunning,
    elapsedSeconds,
    displayTime: formatTime(elapsedSeconds),
    startTime,
    timerId,
    start,
    stop,
    reset,
    setFromExisting
  };
};

// Type declaration for window.notificationService
declare global {
  interface Window {
    notificationService?: {
      timerReminder: () => void;
    };
  }
}
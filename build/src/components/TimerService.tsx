/**
 * TimerService Component für AZE_Gemini
 * 
 * Verwaltet die Timer-Logik und UI-Darstellung
 * Extrahiert aus MainAppView.tsx (Issue #027)
 */

import React, { useEffect, useCallback, useRef } from 'react';
import { notificationService } from '../services/NotificationService';
import { useTimer } from '../hooks/useTimer';
import { User } from '../types';

interface TimerServiceProps {
  currentUser: User | null;
  onTimerStart: (timerId: number) => void;
  onTimerStop: (timerId: number) => void;
  onError: (message: string) => void;
}

interface TimerDisplayProps {
  displayTime: string;
  isRunning: boolean;
  onToggle: () => void;
}

/**
 * Timer Display Component
 * Zeigt den Timer-Button und die verstrichene Zeit an
 */
const TimerDisplay: React.FC<TimerDisplayProps> = ({ displayTime, isRunning, onToggle }) => {
  return (
    <section className="tracking-section" aria-label="Zeiterfassung">
      <div className="label">Zeiterfassung starten / stoppen</div>
      <div className="tracking-controls">
        <button 
          onClick={onToggle} 
          className={`toggle-button ${isRunning ? 'stop-button' : 'start-button'}`} 
          aria-live="polite"
        >
          {isRunning ? 'Stop' : 'Start'}
        </button>
        {isRunning && (
          <div className="timer-display" aria-label="Abgelaufene Zeit">
            {displayTime}
          </div>
        )}
      </div>
    </section>
  );
};

/**
 * Timer Service Component
 * Hauptkomponente für Timer-Funktionalität
 */
export const TimerService: React.FC<TimerServiceProps> = ({ 
  currentUser, 
  onTimerStart, 
  onTimerStop,
  onError 
}) => {
  const timer = useTimer();
  const csrfTokenRef = useRef<string>('');

  const fetchCsrfToken = useCallback(async (): Promise<string> => {
    if (csrfTokenRef.current) return csrfTokenRef.current;
    const resp = await fetch('/api/csrf-token.php', { credentials: 'include' });
    if (!resp.ok) throw new Error('CSRF Token konnte nicht geladen werden');
    const data = await resp.json();
    const token = String(data?.csrfToken || '');
    csrfTokenRef.current = token;
    // kurze Info für den Nutzer, dass die Sicherheit initialisiert wurde
    try {
      notificationService.info('Sicherheit aktualisiert: CSRF-Token geladen', { position: 'top-center', duration: 2000, icon: '🔒' });
    } catch {}
    return token;
  }, []);

  /**
   * Prüft beim Laden auf laufenden Timer
   * TEMPORARILY DISABLED (2025-10-19): API returns empty response, causing JSON parse errors
   * TODO: Fix check_running endpoint to return valid JSON
   */
  const checkForRunningTimer = useCallback(async () => {
    if (!currentUser) return;

    // DISABLED: Causing JSON parse errors due to API issues
    console.log('[Timer] Check for running timer disabled - API needs fix');
    return;

    /* ORIGINAL CODE - RE-ENABLE AFTER API FIX:
    try {
      const response = await fetch('/api/time-entries.php?action=check_running', {
        method: 'GET',
        credentials: 'include'
      });

      if (response.ok) {
        const data = await response.json();
        if (data.hasRunningTimer && data.runningTimer) {
          const startTime = new Date(`${data.runningTimer.date}T${data.runningTimer.startTime}`).getTime();
          timer.setFromExisting(startTime, data.runningTimer.id);
        }
      }
    } catch (error) {
      console.error('Error checking for running timer:', error);
    }
    */
  }, [currentUser, timer]);

  // Check for running timer on mount - DISABLED
  useEffect(() => {
    // DISABLED: See checkForRunningTimer comment
    // if (currentUser) {
    //   checkForRunningTimer();
    // }
  }, [currentUser, checkForRunningTimer]);

  /**
   * Behandelt Timer Start/Stop
   */
  const handleToggleTracking = async () => {
    if (!currentUser) return;

    try {
      if (timer.isRunning) {
        // Stop timer
        if (!timer.timerId) {
          throw new Error('No timer ID available');
        }
        
        const now = new Date();
        const stopTime = now.toTimeString().split(' ')[0]; // HH:MM:SS format
        
        const csrf = await fetchCsrfToken();
        const response = await fetch('/api/time-entries.php?action=stop', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          credentials: 'include',
          body: JSON.stringify({ 
            id: timer.timerId,
            stopTime: stopTime,
            updatedBy: currentUser.name,
            csrf_token: csrf
          })
        });

        if (response.ok) {
          timer.stop();
          onTimerStop(timer.timerId);

          // DISABLED (2025-10-19): Double-check causes JSON parse errors
          // TODO: Re-enable after fixing check_running endpoint
          // setTimeout(async () => {
          //   try {
          //     const checkResponse = await fetch('/api/time-entries.php?action=check_running', {
          //       method: 'GET',
          //       credentials: 'include'
          //     });
          //     if (checkResponse.ok) {
          //       const checkData = await checkResponse.json();
          //       if (checkData.hasRunningTimer) {
          //         timer.stop();
          //       }
          //     }
          //   } catch (error) {
          //     console.error('Error in double-check:', error);
          //   }
          // }, 100);
        } else {
          const errorData = await response.json().catch(() => ({}));
          const err: any = new Error(errorData.message || 'Fehler beim Stoppen der Zeiterfassung');
          err.status = response.status;
          err.code = errorData.error || errorData.code;
          throw err;
        }
      } else {
        // Start timer
        const now = new Date();
        const currentDate = now.toISOString().split('T')[0];
        const startTime = now.toTimeString().split(' ')[0];
        
        const csrf = await fetchCsrfToken();
        const response = await fetch('/api/time-entries.php?action=start', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          credentials: 'include',
          body: JSON.stringify({
            userId: currentUser.id,
            date: currentDate,
            startTime: startTime,
            createdBy: currentUser.name,
            csrf_token: csrf
          })
        });

        if (response.ok) {
          const data = await response.json();

          // ✅ FIX: Validate response contains required 'id' field
          if (!data || !data.id) {
            throw new Error('Server-Response enthält keine Timer-ID');
          }

          const startTimestamp = new Date(`${currentDate}T${startTime}`).getTime();
          timer.start(startTimestamp, data.id);
          onTimerStart(data.id);
        } else {
          const errorData = await response.json().catch(() => ({}));
          const err: any = new Error(errorData.message || 'Fehler beim Starten der Zeiterfassung');
          err.status = response.status;
          err.code = errorData.error || errorData.code;
          throw err;
        }
      }
    } catch (err) {
      const action = timer.isRunning ? 'Stoppen' : 'Starten';
      const msg = `Fehler beim ${action} der Zeiterfassung: ${(err as Error).message}`;
      onError(msg);
      
      if (window.api && window.api.logError) {
        window.api.logError({
          message: msg, 
          stack: (err as Error).stack, 
          context: `TimerService.handleToggleTracking - ${action.toLowerCase()}`
        });
      }
    }
  };

  return (
    <TimerDisplay 
      displayTime={timer.displayTime}
      isRunning={timer.isRunning}
      onToggle={handleToggleTracking}
    />
  );
};

// Type declarations for window objects
declare global {
  interface Window {
    api?: {
      logError: (error: { message: string; stack?: string; context: string }) => void;
    };
  }
}

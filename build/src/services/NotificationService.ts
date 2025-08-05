/**
 * Notification Service für AZE_Gemini
 * 
 * Zentrale Verwaltung aller Benachrichtigungen
 * Ersetzt alert() durch moderne Toast-Notifications
 */

import toast, { Toaster } from 'react-hot-toast';
import { ErrorContext } from './ErrorMessageService';

export type NotificationType = 'success' | 'error' | 'info' | 'warning';

export interface NotificationOptions {
  duration?: number;
  position?: 'top-left' | 'top-center' | 'top-right' | 'bottom-left' | 'bottom-center' | 'bottom-right';
  icon?: string;
  action?: {
    label: string;
    onClick: () => void;
  };
}

export class NotificationService {
  private static instance: NotificationService;
  
  private defaultOptions: NotificationOptions = {
    duration: 4000,
    position: 'top-right'
  };
  
  private constructor() {}
  
  public static getInstance(): NotificationService {
    if (!NotificationService.instance) {
      NotificationService.instance = new NotificationService();
    }
    return NotificationService.instance;
  }
  
  public success(message: string, options?: NotificationOptions): void {
    toast.success(message, {
      duration: options?.duration || this.defaultOptions.duration,
      position: options?.position || this.defaultOptions.position,
      icon: options?.icon || '✓',
      style: {
        background: '#10b981',
        color: '#fff',
      },
    });
  }
  
  public error(errorContext: ErrorContext, options?: NotificationOptions): void {
    const message = errorContext.user;
    const action = errorContext.action;
    
    toast.error(
      (t) => (
        <div className="error-toast">
          <div className="error-message">{message}</div>
          {action && <div className="error-action">{action}</div>}
          {errorContext.support && (
            <div className="error-support">{errorContext.support}</div>
          )}
          {options?.action && (
            <button
              onClick={() => {
                toast.dismiss(t.id);
                options.action!.onClick();
              }}
              className="error-action-button"
            >
              {options.action.label}
            </button>
          )}
        </div>
      ),
      {
        duration: options?.duration || 6000,
        position: options?.position || this.defaultOptions.position,
        style: {
          background: '#ef4444',
          color: '#fff',
          maxWidth: '500px',
        },
      }
    );
  }
  
  public warning(message: string, options?: NotificationOptions): void {
    toast(message, {
      duration: options?.duration || this.defaultOptions.duration,
      position: options?.position || this.defaultOptions.position,
      icon: options?.icon || '⚠️',
      style: {
        background: '#f59e0b',
        color: '#fff',
      },
    });
  }
  
  public info(message: string, options?: NotificationOptions): void {
    toast(message, {
      duration: options?.duration || this.defaultOptions.duration,
      position: options?.position || this.defaultOptions.position,
      icon: options?.icon || 'ℹ️',
      style: {
        background: '#3b82f6',
        color: '#fff',
      },
    });
  }
  
  public dismiss(toastId?: string): void {
    if (toastId) {
      toast.dismiss(toastId);
    } else {
      toast.dismiss();
    }
  }
  
  public promise<T>(
    promise: Promise<T>,
    messages: {
      loading: string;
      success: string | ((data: T) => string);
      error: string | ((error: any) => string);
    },
    options?: NotificationOptions
  ): Promise<T> {
    return toast.promise(
      promise,
      {
        loading: messages.loading,
        success: messages.success,
        error: messages.error,
      },
      {
        position: options?.position || this.defaultOptions.position,
      }
    );
  }
  
  // Special method for timer reminder
  public timerReminder(): void {
    toast(
      (t) => (
        <div className="timer-reminder">
          <div className="reminder-icon">⏰</div>
          <div className="reminder-content">
            <div className="reminder-title">Zeiterfassung läuft noch!</div>
            <div className="reminder-message">
              Haben Sie vergessen, die Zeiterfassung zu stoppen?
            </div>
            <div className="reminder-actions">
              <button
                onClick={() => {
                  toast.dismiss(t.id);
                  // Navigate to main view
                  window.location.hash = '#main';
                }}
                className="reminder-action-primary"
              >
                Zur Zeiterfassung
              </button>
              <button
                onClick={() => toast.dismiss(t.id)}
                className="reminder-action-secondary"
              >
                Später erinnern
              </button>
            </div>
          </div>
        </div>
      ),
      {
        duration: Infinity,
        position: 'top-center',
        style: {
          background: '#fbbf24',
          color: '#000',
          maxWidth: '400px',
          padding: '16px',
        },
      }
    );
  }
  
  // Special method for local path info
  public localPathInfo(path: string): void {
    toast(
      (t) => (
        <div className="local-path-info">
          <div className="info-icon">📁</div>
          <div className="info-content">
            <div className="info-title">Lokaler Pfad</div>
            <div className="info-message">
              Web-Browser können aus Sicherheitsgründen keine lokalen Pfade öffnen.
            </div>
            <div className="info-path">{path}</div>
            <button
              onClick={() => {
                navigator.clipboard.writeText(path);
                toast.dismiss(t.id);
                this.success('Pfad in Zwischenablage kopiert');
              }}
              className="info-action"
            >
              Pfad kopieren
            </button>
          </div>
        </div>
      ),
      {
        duration: 8000,
        position: 'top-center',
        style: {
          background: '#fff',
          color: '#000',
          border: '1px solid #e5e7eb',
          maxWidth: '500px',
        },
      }
    );
  }
}

// Export singleton instance
export const notificationService = NotificationService.getInstance();

// Export Toaster component for app root
export { Toaster };
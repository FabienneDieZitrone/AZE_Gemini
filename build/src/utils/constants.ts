/**
 * Zentrale Konstanten für AZE_Gemini
 * Issue #133: Magic Numbers zu TIME_CONSTANTS Modul extrahieren
 */

// Zeit-Konstanten
export const TIME = {
  SECONDS_PER_MINUTE: 60,
  SECONDS_PER_HOUR: 3600,
  SECONDS_PER_DAY: 86400,
  MINUTES_PER_HOUR: 60,
  HOURS_PER_DAY: 24,
  DAYS_PER_WEEK: 7,
  MILLISECONDS_PER_SECOND: 1000,
  
  // Arbeitszeitspezifisch
  DEFAULT_WEEKLY_HOURS: 40,
  MAX_DAILY_HOURS: 10,
  MIN_BREAK_AFTER_HOURS: 6,
  BREAK_DURATION_MINUTES: 30,
  
  // Timer
  TIMER_REMINDER_HOURS: 8,
  TIMER_AUTO_STOP_HOURS: 12,
} as const;

// Benutzerrollen
export const USER_ROLES = {
  ADMIN: 'Admin',
  BEREICHSLEITER: 'Bereichsleiter',
  STANDORTLEITER: 'Standortleiter',
  MITARBEITER: 'Mitarbeiter',
  HONORARKRAFT: 'Honorarkraft',
} as const;

// Status-Typen
export const STATUS = {
  PENDING: 'pending',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  IN_PROGRESS: 'in_progress',
  COMPLETED: 'completed',
} as const;

// Benachrichtigungstypen
export const NOTIFICATION_TYPES = {
  OVERTIME_WARNING: 'overtime_warning',
  TIMER_REMINDER: 'timer_reminder',
  APPROVAL_REQUEST: 'approval_request',
  APPROVAL_RESPONSE: 'approval_response',
  SYSTEM_MESSAGE: 'system_message',
} as const;

// API Endpoints
export const API_ENDPOINTS = {
  BASE_URL: '/api',
  LOGIN: '/login.php',
  LOGOUT: '/auth-logout.php',
  USERS: '/users.php',
  TIME_ENTRIES: '/time-entries.php',
  APPROVALS: '/approvals.php',
  HISTORY: '/history.php',
  SETTINGS: '/settings.php',
  TIMER_START: '/timer-start.php',
  TIMER_STOP: '/timer-stop.php',
  MFA_SETUP: '/mfa/setup.php',
  MFA_VERIFY: '/mfa/verify.php',
} as const;

// Lokale Speicher-Schlüssel
export const STORAGE_KEYS = {
  ACTIVE_TIMER: 'aze_active_timer',
  USER_PREFERENCES: 'aze_user_preferences',
  THEME: 'aze_theme',
  LANGUAGE: 'aze_language',
} as const;

// UI Konstanten
export const UI = {
  DEBOUNCE_DELAY: 300,
  TOAST_DURATION: 3000,
  MODAL_ANIMATION_DURATION: 200,
  TABLE_PAGE_SIZE: 25,
  MAX_UPLOAD_SIZE_MB: 10,
} as const;

// Validierungsregeln
export const VALIDATION = {
  MIN_PASSWORD_LENGTH: 8,
  MAX_USERNAME_LENGTH: 50,
  TIME_ENTRY_MAX_HOURS: 24,
  REASON_MIN_LENGTH: 10,
  REASON_MAX_LENGTH: 500,
} as const;

// Farben für verschiedene Status
export const STATUS_COLORS = {
  [STATUS.PENDING]: '#ff9800',
  [STATUS.APPROVED]: '#4caf50',
  [STATUS.REJECTED]: '#f44336',
  [STATUS.IN_PROGRESS]: '#2196f3',
  [STATUS.COMPLETED]: '#4caf50',
} as const;

// Export-Formate
export const EXPORT_FORMATS = {
  CSV: 'csv',
  PDF: 'pdf',
  EXCEL: 'excel',
  JSON: 'json',
} as const;

// Datum-Formate
export const DATE_FORMATS = {
  DISPLAY: 'DD.MM.YYYY',
  API: 'YYYY-MM-DD',
  TIME: 'HH:mm',
  DATETIME: 'DD.MM.YYYY HH:mm',
  FILENAME: 'YYYY-MM-DD_HHmmss',
} as const;
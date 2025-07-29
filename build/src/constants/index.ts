/**
 * AZE_Gemini - Time and Application Constants
 * All magic numbers and configuration values centralized
 */

export const TIME = {
  SECONDS_PER_MINUTE: 60,
  SECONDS_PER_HOUR: 3600,
  SECONDS_PER_DAY: 86400,
  MINUTES_PER_HOUR: 60,
  HOURS_PER_DAY: 24,
  MILLISECONDS_PER_SECOND: 1000,
  MILLISECONDS_PER_MINUTE: 60000,
  MILLISECONDS_PER_HOUR: 3600000,
  MILLISECONDS_PER_DAY: 86400000,
} as const;

export const TIMER = {
  REMINDER_TIMEOUT_HOURS: 8,
  UPDATE_INTERVAL_MS: 1000,
  DOUBLE_CHECK_DELAY_MS: 100,
  AUTO_REFRESH_INTERVAL_MS: 30000,
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
  TIME_OPTIONS: {
    hour: '2-digit' as const,
    minute: '2-digit' as const,
    second: '2-digit' as const
  },
} as const;

export const ROLES = {
  ADMIN: 'Admin',
  BEREICHSLEITER: 'Bereichsleiter',
  STANDORTLEITER: 'Standortleiter',
  MITARBEITER: 'Mitarbeiter',
} as const;

export const WORK = {
  DEFAULT_DAILY_SOLL_HOURS: 8,
  DEFAULT_WEEKLY_SOLL_HOURS: 40,
} as const;
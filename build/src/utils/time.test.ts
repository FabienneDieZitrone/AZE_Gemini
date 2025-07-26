/**
 * Unit tests for time utilities
 * Testing core time calculation functions
 */
import { describe, it, expect } from 'vitest'
import { formatTime, calculateDurationInSeconds, getStartOfWeek } from './time'

describe('formatTime', () => {
  it('should format seconds correctly', () => {
    expect(formatTime(3661)).toBe('01:01:01')
    expect(formatTime(3600)).toBe('01:00:00')
    expect(formatTime(61)).toBe('00:01:01')
    expect(formatTime(0)).toBe('00:00:00')
  })

  it('should handle short format', () => {
    expect(formatTime(3661, false)).toBe('01:01')
    expect(formatTime(61, false)).toBe('00:01')
  })
})

describe('calculateDurationInSeconds', () => {
  it('should calculate duration correctly', () => {
    expect(calculateDurationInSeconds('09:00:00', '17:00:00')).toBe(28800) // 8 hours
    expect(calculateDurationInSeconds('09:30:00', '17:45:00')).toBe(29700) // 8h 15m
    // Note: This function doesn't handle midnight crossover, it calculates as negative
    expect(calculateDurationInSeconds('23:30:00', '01:30:00')).toBe(-79200) // negative when crossing midnight
  })

  it('should handle same time', () => {
    expect(calculateDurationInSeconds('09:00:00', '09:00:00')).toBe(0)
  })
})

describe('getStartOfWeek', () => {
  it('should return Monday of the week', () => {
    // Wednesday July 24, 2025
    const wednesday = new Date('2025-07-24')
    const startOfWeek = getStartOfWeek(wednesday)
    
    // Should return Monday July 21, 2025
    expect(startOfWeek.getDay()).toBe(1) // Monday
    expect(startOfWeek.getDate()).toBe(21)
    expect(startOfWeek.getMonth()).toBe(6) // July (0-indexed)
  })

  it('should handle Monday correctly', () => {
    const monday = new Date('2025-07-21')
    const startOfWeek = getStartOfWeek(monday)
    
    expect(startOfWeek.getDay()).toBe(1) // Monday
    expect(startOfWeek.getDate()).toBe(21)
  })
})
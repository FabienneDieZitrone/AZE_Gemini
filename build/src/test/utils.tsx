/**
 * Test utilities and helpers
 * Provides common test setup and mock data
 */
import React from 'react'
import { render, RenderOptions } from '@testing-library/react'
import { vi } from 'vitest'
import type { User, TimeEntry, MasterData, ApprovalRequest, GlobalSettings } from '../types'

// Mock data factories
export const createMockUser = (overrides: Partial<User> = {}): User => ({
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
  role: 'Mitarbeiter',
  ...overrides,
})

export const createMockTimeEntry = (overrides: Partial<TimeEntry> = {}): TimeEntry => ({
  id: 1,
  userId: 1,
  date: '2025-08-03',
  startTime: '09:00:00',
  stopTime: '17:00:00',
  status: 'Erfasst',
  reason: 'Reguläre Arbeitszeit',
  reasonData: {
    type: 'work',
    location: 'Büro',
    projectId: null,
    customReason: null,
  },
  ...overrides,
})

export const createMockMasterData = (overrides: Partial<MasterData> = {}): MasterData => ({
  workingHoursPerWeek: 40,
  vacationDaysPerYear: 25,
  ...overrides,
})

export const createMockApprovalRequest = (overrides: Partial<ApprovalRequest> = {}): ApprovalRequest => ({
  id: '1',
  userId: 1,
  entryId: 1,
  requestType: 'change',
  requestedChanges: {
    startTime: '08:00:00',
    stopTime: '16:00:00',
  },
  reason: 'Korrektur der Arbeitszeit',
  status: 'Ausstehend',
  createdDate: '2025-08-03',
  ...overrides,
})

export const createMockGlobalSettings = (overrides: Partial<GlobalSettings> = {}): GlobalSettings => ({
  companyName: 'Test Company',
  workingHoursPerWeek: 40,
  vacationDaysPerYear: 25,
  allowOvertime: true,
  requireApprovalForChanges: true,
  ...overrides,
})

// API Mock helpers
export const mockApiResponse = (data: any, status = 200) => {
  const mockFetch = vi.fn(() =>
    Promise.resolve(new Response(JSON.stringify(data), {
      status,
      headers: { 'Content-Type': 'application/json' }
    }))
  )
  vi.mocked(globalThis.fetch).mockImplementation(mockFetch)
  return mockFetch
}

export const mockApiError = (status = 500, message = 'Server Error') => {
  const mockFetch = vi.fn(() =>
    Promise.resolve(new Response(JSON.stringify({ message }), {
      status,
      headers: { 'Content-Type': 'application/json' }
    }))
  )
  vi.mocked(globalThis.fetch).mockImplementation(mockFetch)
  return mockFetch
}

export const mockAuthenticatedUser = () => {
  mockApiResponse(createMockUser())
}

export const mockUnauthenticatedUser = () => {
  mockApiError(401, 'Unauthorized')
}

// Custom render function with providers
interface CustomRenderOptions extends Omit<RenderOptions, 'wrapper'> {
  // Add any context providers here when needed
}

export const renderWithProviders = (
  ui: React.ReactElement,
  options: CustomRenderOptions = {}
) => {
  return render(ui, {
    ...options,
  })
}

// Re-export everything from RTL
export * from '@testing-library/react'
export { default as userEvent } from '@testing-library/user-event'
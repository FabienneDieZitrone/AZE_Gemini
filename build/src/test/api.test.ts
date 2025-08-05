/**
 * Integration tests for API service
 * Tests all API endpoints and error handling
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { api } from '../../api'
import { mockApiResponse, mockApiError, createMockTimeEntry, createMockGlobalSettings } from './utils'

describe('API Service', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('checkAuthStatus', () => {
    it('should make GET request to auth-status endpoint', async () => {
      mockApiResponse({})
      
      await api.checkAuthStatus()
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/auth-status.php'),
        expect.objectContaining({
          method: 'GET',
          credentials: 'include',
          headers: expect.any(Headers),
        })
      )
    })

    it('should handle auth check failures', async () => {
      mockApiError(401, 'Unauthorized')
      
      await expect(api.checkAuthStatus()).rejects.toThrow()
    })
  })

  describe('loginAndGetInitialData', () => {
    it('should make POST request to login endpoint', async () => {
      const mockData = { user: { id: 1, name: 'Test User' } }
      mockApiResponse(mockData)
      
      const result = await api.loginAndGetInitialData()
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/login.php'),
        expect.objectContaining({
          method: 'POST',
          credentials: 'include',
        })
      )
      expect(result).toEqual(mockData)
    })

    it('should handle login failures', async () => {
      mockApiError(401, 'Invalid credentials')
      
      await expect(api.loginAndGetInitialData()).rejects.toThrow('Invalid credentials')
    })
  })

  describe('addTimeEntry', () => {
    it('should make POST request with time entry data', async () => {
      const entryData = createMockTimeEntry()
      const { id, ...entryWithoutId } = entryData
      mockApiResponse({ id: 1 })
      
      await api.addTimeEntry(entryWithoutId)
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/time-entries.php'),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(entryWithoutId),
        })
      )
    })

    it('should handle validation errors', async () => {
      const entryData = createMockTimeEntry()
      const { id, ...entryWithoutId } = entryData
      mockApiError(400, 'Invalid time entry data')
      
      await expect(api.addTimeEntry(entryWithoutId)).rejects.toThrow('Invalid time entry data')
    })
  })

  describe('requestEntryChange', () => {
    it('should make POST request to approvals endpoint', async () => {
      const requestData = {
        entryId: 1,
        requestType: 'change' as const,
        requestedChanges: { startTime: '08:00:00', stopTime: '16:00:00' },
        reason: 'Correction needed'
      }
      mockApiResponse({ requestId: '123' })
      
      await api.requestEntryChange(requestData)
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/approvals.php'),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(requestData),
        })
      )
    })
  })

  describe('updateMasterData', () => {
    it('should make PUT request with user ID and master data', async () => {
      const masterData = { workingHoursPerWeek: 40, vacationDaysPerYear: 25 }
      mockApiResponse({})
      
      await api.updateMasterData(1, masterData)
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/masterdata.php'),
        expect.objectContaining({
          method: 'PUT',
          body: JSON.stringify({ userId: 1, ...masterData }),
        })
      )
    })
  })

  describe('updateUserRole', () => {
    it('should make PATCH request with user ID and new role', async () => {
      mockApiResponse({})
      
      await api.updateUserRole(1, 'Supervisor')
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/users.php'),
        expect.objectContaining({
          method: 'PATCH',
          body: JSON.stringify({ userId: 1, newRole: 'Supervisor' }),
        })
      )
    })
  })

  describe('processApprovalRequest', () => {
    it('should make PATCH request to process approval', async () => {
      mockApiResponse({})
      
      await api.processApprovalRequest('123', 'genehmigt')
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/approvals.php'),
        expect.objectContaining({
          method: 'PATCH',
          body: JSON.stringify({ requestId: '123', finalStatus: 'genehmigt' }),
        })
      )
    })

    it('should handle rejection status', async () => {
      mockApiResponse({})
      
      await api.processApprovalRequest('123', 'abgelehnt')
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/approvals.php'),
        expect.objectContaining({
          method: 'PATCH',
          body: JSON.stringify({ requestId: '123', finalStatus: 'abgelehnt' }),
        })
      )
    })
  })

  describe('updateGlobalSettings', () => {
    it('should make PUT request with global settings', async () => {
      const settings = createMockGlobalSettings()
      mockApiResponse({})
      
      await api.updateGlobalSettings(settings)
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/settings.php'),
        expect.objectContaining({
          method: 'PUT',
          body: JSON.stringify(settings),
        })
      )
    })
  })

  describe('logError', () => {
    it('should make POST request without authentication', async () => {
      const errorData = {
        message: 'Test error',
        stack: 'Error stack trace',
        context: 'test-context'
      }
      
      // Mock fetch directly for this test since logError doesn't use fetchApi
      const mockFetch = vi.fn(() => Promise.resolve(new Response()))
      vi.mocked(globalThis.fetch).mockImplementation(mockFetch)
      
      await api.logError(errorData)
      
      expect(globalThis.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/logs.php'),
        expect.objectContaining({
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(errorData),
        })
      )
    })

    it('should handle logging errors gracefully', async () => {
      const errorData = {
        message: 'Test error',
        context: 'test-context'
      }
      
      vi.mocked(globalThis.fetch).mockRejectedValue(new Error('Network error'))
      
      // Should not throw
      await expect(api.logError(errorData)).resolves.toBeUndefined()
    })
  })

  describe('Error Handling', () => {
    it('should handle network timeouts', async () => {
      // Mock a timeout scenario
      vi.mocked(globalThis.fetch).mockImplementation(() => 
        new Promise((_, reject) => {
          setTimeout(() => reject(new Error('timeout')), 100)
        })
      )
      
      await expect(api.checkAuthStatus()).rejects.toThrow()
    })

    it('should handle JSON parsing errors', async () => {
      vi.mocked(globalThis.fetch).mockResolvedValue(
        new Response('Invalid JSON', {
          status: 500,
          headers: { 'Content-Type': 'application/json' }
        })
      )
      
      await expect(api.checkAuthStatus()).rejects.toThrow()
    })

    it('should handle 401 errors with redirect', async () => {
      const originalLocation = window.location.href
      mockApiError(401, 'Unauthorized')
      
      await expect(api.loginAndGetInitialData()).rejects.toThrow('Session expired or invalid.')
      expect(window.location.href).toBe('/')
      
      // Reset location
      window.location.href = originalLocation
    })
  })
})
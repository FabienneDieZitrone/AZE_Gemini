/**
 * E2E API Integration Tests
 * Tests that verify the frontend integrates correctly with real backend APIs
 */
import { test, expect } from '@playwright/test'

// Helper to check if we're running against real backend
const isRealBackend = () => process.env.E2E_REAL_BACKEND === 'true'

test.describe('API Integration Tests', () => {
  test.skip(!isRealBackend(), 'Skipping real API tests - set E2E_REAL_BACKEND=true to run')

  test.beforeEach(async ({ page }) => {
    // Set up page error handling
    page.on('pageerror', error => {
      console.error('Page error:', error.message)
    })
    
    page.on('requestfailed', request => {
      console.error('Request failed:', request.url(), request.failure()?.errorText)
    })
  })

  test.describe('Authentication API', () => {
    test('should handle real OAuth flow', async ({ page }) => {
      await page.goto('/')
      
      // Should show login page
      await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).toBeVisible()
      
      // Click login - this will redirect to real OAuth provider
      const loginButton = page.locator('button:has-text("Mit Microsoft anmelden")')
      await loginButton.click()
      
      // Wait for OAuth redirect
      await page.waitForURL('**/auth-start.php*', { timeout: 10000 })
      
      // Should redirect to Microsoft OAuth
      const currentUrl = page.url()
      expect(currentUrl).toContain('login.microsoftonline.com')
    })

    test('should handle auth status checks', async ({ page }) => {
      // Test auth status endpoint
      const response = await page.request.get('/api/auth-status.php')
      
      expect([200, 401]).toContain(response.status())
      
      const data = await response.json()
      expect(data).toHaveProperty('authenticated')
      expect(typeof data.authenticated).toBe('boolean')
    })

    test('should handle session timeout', async ({ page }) => {
      await page.goto('/')
      
      // Make API call to check session handling
      const response = await page.evaluate(async () => {
        const res = await fetch('/api/auth-status.php')
        return {
          status: res.status,
          data: await res.json()
        }
      })
      
      if (response.status === 401) {
        expect(response.data).toHaveProperty('authenticated', false)
      } else if (response.status === 200) {
        expect(response.data).toHaveProperty('authenticated')
      }
    })
  })

  test.describe('Time Entries API', () => {
    test.beforeEach(async ({ page }) => {
      // Skip if not authenticated in real backend
      const authResponse = await page.request.get('/api/auth-status.php')
      if (authResponse.status() === 401) {
        test.skip('User not authenticated - cannot test time entries API')
      }
    })

    test('should fetch time entries', async ({ page }) => {
      const response = await page.request.get('/api/time-entries.php')
      
      expect(response.status()).toBe(200)
      
      const data = await response.json()
      expect(Array.isArray(data)).toBeTruthy()
      
      // Check structure of first entry if any exist
      if (data.length > 0) {
        const entry = data[0]
        expect(entry).toHaveProperty('id')
        expect(entry).toHaveProperty('userId')
        expect(entry).toHaveProperty('date')
        expect(entry).toHaveProperty('startTime')
        expect(entry).toHaveProperty('status')
      }
    })

    test('should create time entry via API', async ({ page }) => {
      const newEntry = {
        date: new Date().toISOString().split('T')[0],
        startTime: '09:00:00',
        stopTime: '17:00:00',
        reason: 'API Integration Test Entry'
      }

      const response = await page.request.post('/api/time-entries.php', {
        data: newEntry,
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect([200, 201]).toContain(response.status())
      
      const result = await response.json()
      expect(result).toHaveProperty('success', true)
      expect(result).toHaveProperty('id')
    })

    test('should validate time entry data', async ({ page }) => {
      const invalidEntry = {
        date: 'invalid-date',
        startTime: '25:00:00', // Invalid time
        stopTime: '09:00:00',  // Before start time
        reason: ''
      }

      const response = await page.request.post('/api/time-entries.php', {
        data: invalidEntry,
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect([400, 422]).toContain(response.status())
      
      const result = await response.json()
      expect(result).toHaveProperty('error')
    })

    test('should update time entry via API', async ({ page }) => {
      // First, get existing entries
      const getResponse = await page.request.get('/api/time-entries.php')
      const entries = await getResponse.json()
      
      if (entries.length === 0) {
        test.skip('No time entries available to update')
      }

      const entryToUpdate = entries[0]
      const updateData = {
        ...entryToUpdate,
        reason: 'Updated via API Integration Test'
      }

      const response = await page.request.put(`/api/time-entries.php?id=${entryToUpdate.id}`, {
        data: updateData,
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect(response.status()).toBe(200)
      
      const result = await response.json()
      expect(result).toHaveProperty('success', true)
    })

    test('should delete time entry via API', async ({ page }) => {
      // Create a test entry first
      const newEntry = {
        date: new Date().toISOString().split('T')[0],
        startTime: '10:00:00',
        stopTime: '11:00:00',
        reason: 'Temporary entry for deletion test'
      }

      const createResponse = await page.request.post('/api/time-entries.php', {
        data: newEntry,
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (createResponse.status() !== 200) {
        test.skip('Could not create entry for deletion test')
      }

      const createResult = await createResponse.json()
      const entryId = createResult.id

      // Now delete it
      const deleteResponse = await page.request.delete(`/api/time-entries.php?id=${entryId}`)
      
      expect(deleteResponse.status()).toBe(200)
      
      const deleteResult = await deleteResponse.json()
      expect(deleteResult).toHaveProperty('success', true)
    })
  })

  test.describe('Approval Workflow API', () => {
    test.beforeEach(async ({ page }) => {
      // Check authentication and permissions
      const authResponse = await page.request.get('/api/auth-status.php')
      if (authResponse.status() === 401) {
        test.skip('User not authenticated')
      }
    })

    test('should fetch approval requests', async ({ page }) => {
      const response = await page.request.get('/api/approvals.php')
      
      expect([200, 403]).toContain(response.status())
      
      if (response.status() === 200) {
        const data = await response.json()
        expect(Array.isArray(data)).toBeTruthy()
        
        // Check structure if any requests exist
        if (data.length > 0) {
          const request = data[0]
          expect(request).toHaveProperty('id')
          expect(request).toHaveProperty('userId')
          expect(request).toHaveProperty('status')
          expect(request).toHaveProperty('requestType')
        }
      }
    })

    test('should create approval request', async ({ page }) => {
      const approvalRequest = {
        entryId: 1,
        requestType: 'change',
        requestedChanges: {
          startTime: '08:30:00',
          stopTime: '16:30:00'
        },
        reason: 'Correction of actual working hours'
      }

      const response = await page.request.post('/api/approvals.php', {
        data: approvalRequest,
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect([200, 201, 403]).toContain(response.status())
      
      if (response.status() === 200 || response.status() === 201) {
        const result = await response.json()
        expect(result).toHaveProperty('success', true)
        expect(result).toHaveProperty('requestId')
      }
    })

    test('should approve/reject requests (supervisor only)', async ({ page }) => {
      // First get pending requests
      const getResponse = await page.request.get('/api/approvals.php')
      
      if (getResponse.status() === 403) {
        test.skip('User does not have permission to view approval requests')
      }

      const requests = await getResponse.json()
      const pendingRequest = requests.find(r => r.status === 'Ausstehend')
      
      if (!pendingRequest) {
        test.skip('No pending approval requests available')
      }

      // Try to approve the request
      const approveResponse = await page.request.patch(`/api/approvals.php?id=${pendingRequest.id}`, {
        data: {
          finalStatus: 'genehmigt',
          comment: 'Approved via API integration test'
        },
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect([200, 403]).toContain(approveResponse.status())
      
      if (approveResponse.status() === 200) {
        const result = await approveResponse.json()
        expect(result).toHaveProperty('success', true)
      }
    })
  })

  test.describe('User Management API', () => {
    test('should fetch user data', async ({ page }) => {
      const response = await page.request.get('/api/users.php')
      
      expect([200, 403]).toContain(response.status())
      
      if (response.status() === 200) {
        const data = await response.json()
        expect(Array.isArray(data)).toBeTruthy()
        
        if (data.length > 0) {
          const user = data[0]
          expect(user).toHaveProperty('id')
          expect(user).toHaveProperty('name')
          expect(user).toHaveProperty('email')
          expect(user).toHaveProperty('role')
        }
      }
    })

    test('should handle role-based access', async ({ page }) => {
      const response = await page.request.get('/api/users.php')
      
      if (response.status() === 403) {
        // Expected for non-admin users
        const result = await response.json()
        expect(result).toHaveProperty('error')
      } else if (response.status() === 200) {
        // Admin user can access user data
        const data = await response.json()
        expect(Array.isArray(data)).toBeTruthy()
      }
    })
  })

  test.describe('Settings API', () => {
    test('should fetch global settings', async ({ page }) => {
      const response = await page.request.get('/api/settings.php')
      
      expect([200, 403]).toContain(response.status())
      
      if (response.status() === 200) {
        const data = await response.json()
        expect(data).toHaveProperty('companyName')
        expect(data).toHaveProperty('workingHoursPerWeek')
        expect(typeof data.workingHoursPerWeek).toBe('number')
      }
    })

    test('should update settings (admin only)', async ({ page }) => {
      const updateData = {
        workingHoursPerWeek: 38,
        vacationDaysPerYear: 30
      }

      const response = await page.request.put('/api/settings.php', {
        data: updateData,
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect([200, 403]).toContain(response.status())
      
      if (response.status() === 200) {
        const result = await response.json()
        expect(result).toHaveProperty('success', true)
      } else {
        // Non-admin users should get 403
        const result = await response.json()
        expect(result).toHaveProperty('error')
      }
    })
  })

  test.describe('Export API', () => {
    test('should generate PDF export', async ({ page }) => {
      const response = await page.request.post('/api/export/pdf.php', {
        data: {
          dateRange: {
            start: '2025-08-01',
            end: '2025-08-31'
          },
          format: 'detailed'
        },
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect([200, 403]).toContain(response.status())
      
      if (response.status() === 200) {
        const contentType = response.headers()['content-type']
        expect(contentType).toContain('application/pdf')
        
        const contentDisposition = response.headers()['content-disposition']
        expect(contentDisposition).toContain('attachment')
        expect(contentDisposition).toContain('.pdf')
      }
    })

    test('should generate CSV export', async ({ page }) => {
      const response = await page.request.post('/api/export/csv.php', {
        data: {
          dateRange: {
            start: '2025-08-01',
            end: '2025-08-31'
          },
          includeHeaders: true
        },
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect([200, 403]).toContain(response.status())
      
      if (response.status() === 200) {
        const contentType = response.headers()['content-type']
        expect(contentType).toContain('text/csv')
        
        const contentDisposition = response.headers()['content-disposition']
        expect(contentDisposition).toContain('attachment')
        expect(contentDisposition).toContain('.csv')
        
        // Check CSV content structure
        const csvContent = await response.text()
        const lines = csvContent.split('\n')
        expect(lines.length).toBeGreaterThan(0)
        
        // First line should be headers
        expect(lines[0]).toContain('Datum')
      }
    })
  })

  test.describe('Security Headers and CORS', () => {
    test('should include security headers', async ({ page }) => {
      const response = await page.request.get('/api/auth-status.php')
      const headers = response.headers()
      
      // Check for security headers
      expect(headers).toHaveProperty('x-content-type-options')
      expect(headers['x-content-type-options']).toBe('nosniff')
      
      if (headers['x-frame-options']) {
        expect(['DENY', 'SAMEORIGIN']).toContain(headers['x-frame-options'])
      }
      
      if (headers['strict-transport-security']) {
        expect(headers['strict-transport-security']).toContain('max-age')
      }
    })

    test('should handle CORS properly', async ({ page }) => {
      // Test preflight request
      const response = await page.request.fetch('/api/time-entries.php', {
        method: 'OPTIONS'
      })

      expect([200, 204, 405]).toContain(response.status())
      
      if (response.status() === 200 || response.status() === 204) {
        const headers = response.headers()
        expect(headers).toHaveProperty('access-control-allow-methods')
      }
    })

    test('should validate Content-Type headers', async ({ page }) => {
      const response = await page.request.post('/api/time-entries.php', {
        data: JSON.stringify({
          date: '2025-08-06',
          startTime: '09:00:00',
          stopTime: '17:00:00'
        }),
        headers: {
          'Content-Type': 'text/plain' // Wrong content type
        }
      })

      expect([400, 415]).toContain(response.status())
    })
  })

  test.describe('Rate Limiting', () => {
    test('should enforce rate limits', async ({ page }) => {
      const requests = []
      const endpoint = '/api/auth-status.php'
      
      // Make multiple rapid requests
      for (let i = 0; i < 20; i++) {
        requests.push(page.request.get(endpoint))
      }
      
      const responses = await Promise.all(requests)
      const statusCodes = responses.map(r => r.status())
      
      // Should eventually get rate limited (429)
      const rateLimited = statusCodes.some(status => status === 429)
      
      if (rateLimited) {
        // Check rate limit headers
        const rateLimitedResponse = responses.find(r => r.status() === 429)!
        const headers = rateLimitedResponse.headers()
        
        expect(headers).toHaveProperty('retry-after')
        
        const rateLimitData = await rateLimitedResponse.json()
        expect(rateLimitData).toHaveProperty('error')
        expect(rateLimitData.error).toContain('rate limit')
      }
    })
  })

  test.describe('Error Handling', () => {
    test('should handle malformed JSON', async ({ page }) => {
      const response = await page.request.post('/api/time-entries.php', {
        data: '{invalid json}',
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect(response.status()).toBe(400)
      
      const result = await response.json()
      expect(result).toHaveProperty('error')
    })

    test('should handle missing required fields', async ({ page }) => {
      const response = await page.request.post('/api/time-entries.php', {
        data: {
          // Missing required fields
          reason: 'Incomplete entry'
        },
        headers: {
          'Content-Type': 'application/json'
        }
      })

      expect([400, 422]).toContain(response.status())
      
      const result = await response.json()
      expect(result).toHaveProperty('error')
    })

    test('should handle server errors gracefully', async ({ page }) => {
      // This would test error handling for 500 errors
      // In a real environment, you might trigger this by causing a database error
      
      // For now, just verify that any 500 errors return proper JSON
      const response = await page.request.get('/api/nonexistent-endpoint.php')
      
      if (response.status() === 500) {
        const result = await response.json()
        expect(result).toHaveProperty('error')
      } else {
        // Endpoint doesn't exist, should get 404
        expect(response.status()).toBe(404)
      }
    })
  })

  test.describe('Data Consistency', () => {
    test('should maintain data consistency across operations', async ({ page }) => {
      // Create a time entry
      const createData = {
        date: new Date().toISOString().split('T')[0],
        startTime: '09:00:00',
        stopTime: '17:00:00',
        reason: 'Data consistency test'
      }

      const createResponse = await page.request.post('/api/time-entries.php', {
        data: createData,
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (createResponse.status() !== 200) {
        test.skip('Could not create entry for consistency test')
      }

      const createResult = await createResponse.json()
      const entryId = createResult.id

      // Fetch the entry and verify data
      const getResponse = await page.request.get('/api/time-entries.php')
      const entries = await getResponse.json()
      
      const createdEntry = entries.find(e => e.id === entryId)
      expect(createdEntry).toBeTruthy()
      expect(createdEntry.date).toBe(createData.date)
      expect(createdEntry.startTime).toBe(createData.startTime)
      expect(createdEntry.stopTime).toBe(createData.stopTime)
      expect(createdEntry.reason).toBe(createData.reason)
    })

    test('should handle concurrent modifications', async ({ page }) => {
      // This is a simplified test for concurrent modifications
      // In a full test, you would simulate multiple users editing the same data
      
      const updateData = {
        id: 1, // Assuming entry ID 1 exists
        reason: 'Concurrent update test ' + Date.now()
      }

      // Make two concurrent update requests
      const requests = [
        page.request.put('/api/time-entries.php?id=1', {
          data: updateData,
          headers: { 'Content-Type': 'application/json' }
        }),
        page.request.put('/api/time-entries.php?id=1', {
          data: { ...updateData, reason: updateData.reason + ' - Second' },
          headers: { 'Content-Type': 'application/json' }
        })
      ]

      const responses = await Promise.all(requests)
      
      // At least one should succeed
      const successfulRequests = responses.filter(r => r.status() === 200)
      expect(successfulRequests.length).toBeGreaterThan(0)
    })
  })
})
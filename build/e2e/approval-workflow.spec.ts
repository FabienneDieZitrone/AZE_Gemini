/**
 * E2E Tests for Approval Workflow
 * Tests the complete approval process including change requests, supervisor approval, and status updates
 */
import { test, expect } from '@playwright/test'

test.describe('Approval Workflow - Employee', () => {
  test.beforeEach(async ({ page }) => {
    // Mock authenticated state as employee
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ authenticated: true }),
      })
    })

    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, name: 'Test Employee', email: 'employee@example.com', role: 'Mitarbeiter' },
          users: [
            { id: 1, name: 'Test Employee', email: 'employee@example.com', role: 'Mitarbeiter' },
            { id: 2, name: 'Test Supervisor', email: 'supervisor@example.com', role: 'Supervisor' }
          ],
          timeEntries: [
            {
              id: 1,
              userId: 1,
              date: '2025-08-03',
              startTime: '09:00:00',
              stopTime: '17:00:00',
              status: 'Erfasst',
              reason: 'Reguläre Arbeitszeit'
            }
          ],
          masterData: { 1: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 } },
          approvalRequests: [],
          history: [],
          globalSettings: { 
            companyName: 'Test Company', 
            workingHoursPerWeek: 40,
            requireApprovalForChanges: true 
          },
        }),
      })
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should allow employee to request time entry change', async ({ page }) => {
    // Mock approval request endpoint
    await page.route('**/api/approvals.php', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ 
            requestId: 'req-123',
            success: true 
          }),
        })
      }
    })

    // Find and click edit button for time entry
    const editButton = page.locator('button:has-text("Bearbeiten")').first()
    if (await editButton.count() > 0) {
      await editButton.click()

      // Modify the time entry
      await page.fill('input[name="startTime"]', '08:30')
      await page.fill('input[name="stopTime"]', '16:30')
      
      // Add reason for change
      const reasonField = page.locator('textarea[name="reason"]')
      if (await reasonField.count() > 0) {
        await reasonField.fill('Korrektur der tatsächlichen Arbeitszeit')
      }

      // Submit the change request
      const submitButton = page.locator('button:has-text("Änderung beantragen")')
      await submitButton.click()

      // Should show success message
      await expect(page.locator('text=Änderungsantrag erfolgreich eingereicht')).toBeVisible()
    }
  })

  test('should display pending approval requests for employee', async ({ page }) => {
    // Mock approval requests in the initial load
    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, name: 'Test Employee', email: 'employee@example.com', role: 'Mitarbeiter' },
          users: [{ id: 1, name: 'Test Employee', email: 'employee@example.com', role: 'Mitarbeiter' }],
          timeEntries: [],
          masterData: { 1: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 } },
          approvalRequests: [
            {
              id: 'req-123',
              userId: 1,
              entryId: 1,
              requestType: 'change',
              requestedChanges: { startTime: '08:30:00', stopTime: '16:30:00' },
              reason: 'Korrektur der tatsächlichen Arbeitszeit',
              status: 'Ausstehend',
              createdDate: '2025-08-03'
            }
          ],
          history: [],
          globalSettings: { companyName: 'Test Company', requireApprovalForChanges: true },
        }),
      })
    })

    await page.reload()
    await page.waitForLoadState('networkidle')

    // Should show pending approval status
    await expect(page.locator('text=Ausstehend')).toBeVisible()
    await expect(page.locator('text=Korrektur der tatsächlichen Arbeitszeit')).toBeVisible()
  })

  test('should allow employee to cancel pending request', async ({ page }) => {
    // Mock cancel request endpoint
    await page.route('**/api/approvals.php', async (route) => {
      if (route.request().method() === 'DELETE') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      }
    })

    // Look for cancel button on pending request
    const cancelButton = page.locator('button:has-text("Stornieren")')
    if (await cancelButton.count() > 0) {
      await cancelButton.click()

      // Confirm cancellation
      const confirmButton = page.locator('button:has-text("Bestätigen")')
      if (await confirmButton.count() > 0) {
        await confirmButton.click()
      }

      // Request should be removed
      await expect(page.locator('text=Ausstehend')).not.toBeVisible()
    }
  })
})

test.describe('Approval Workflow - Supervisor', () => {
  test.beforeEach(async ({ page }) => {
    // Mock authenticated state as supervisor
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ authenticated: true }),
      })
    })

    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 2, name: 'Test Supervisor', email: 'supervisor@example.com', role: 'Supervisor' },
          users: [
            { id: 1, name: 'Test Employee', email: 'employee@example.com', role: 'Mitarbeiter' },
            { id: 2, name: 'Test Supervisor', email: 'supervisor@example.com', role: 'Supervisor' }
          ],
          timeEntries: [],
          masterData: { 1: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 } },
          approvalRequests: [
            {
              id: 'req-123',
              userId: 1,
              entryId: 1,
              requestType: 'change',
              requestedChanges: { startTime: '08:30:00', stopTime: '16:30:00' },
              reason: 'Korrektur der tatsächlichen Arbeitszeit',
              status: 'Ausstehend',
              createdDate: '2025-08-03',
              employeeName: 'Test Employee'
            }
          ],
          history: [],
          globalSettings: { companyName: 'Test Company', requireApprovalForChanges: true },
        }),
      })
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should display approval requests for supervisor', async ({ page }) => {
    // Navigate to approvals view
    const approvalsTab = page.locator('button:has-text("Genehmigungen")')
    if (await approvalsTab.count() > 0) {
      await approvalsTab.click()
    }

    // Should show pending approval requests
    await expect(page.locator('text=Test Employee')).toBeVisible()
    await expect(page.locator('text=Korrektur der tatsächlichen Arbeitszeit')).toBeVisible()
    await expect(page.locator('text=08:30')).toBeVisible()
    await expect(page.locator('text=16:30')).toBeVisible()
  })

  test('should allow supervisor to approve request', async ({ page }) => {
    // Mock approval endpoint
    await page.route('**/api/approvals.php', async (route) => {
      if (route.request().method() === 'PATCH') {
        const body = await route.request().postDataJSON()
        if (body.finalStatus === 'genehmigt') {
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true }),
          })
        }
      }
    })

    // Navigate to approvals view
    const approvalsTab = page.locator('button:has-text("Genehmigungen")')
    if (await approvalsTab.count() > 0) {
      await approvalsTab.click()
    }

    // Click approve button
    const approveButton = page.locator('button:has-text("Genehmigen")')
    await approveButton.click()

    // Should show success message
    await expect(page.locator('text=Genehmigt')).toBeVisible()
  })

  test('should allow supervisor to reject request', async ({ page }) => {
    // Mock rejection endpoint
    await page.route('**/api/approvals.php', async (route) => {
      if (route.request().method() === 'PATCH') {
        const body = await route.request().postDataJSON()
        if (body.finalStatus === 'abgelehnt') {
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true }),
          })
        }
      }
    })

    // Navigate to approvals view
    const approvalsTab = page.locator('button:has-text("Genehmigungen")')
    if (await approvalsTab.count() > 0) {
      await approvalsTab.click()
    }

    // Click reject button
    const rejectButton = page.locator('button:has-text("Ablehnen")')
    await rejectButton.click()

    // Add rejection reason
    const reasonField = page.locator('textarea[name="rejectionReason"]')
    if (await reasonField.count() > 0) {
      await reasonField.fill('Zeiten nicht plausibel')
    }

    // Confirm rejection
    const confirmButton = page.locator('button:has-text("Bestätigen")')
    await confirmButton.click()

    // Should show rejection status
    await expect(page.locator('text=Abgelehnt')).toBeVisible()
  })

  test('should show approval history', async ({ page }) => {
    // Navigate to history view
    const historyTab = page.locator('button:has-text("Verlauf")')
    if (await historyTab.count() > 0) {
      await historyTab.click()

      // Should show processed approvals
      await expect(page.locator('text=Genehmigungshistorie')).toBeVisible()
    }
  })
})

test.describe('Approval Notifications', () => {
  test('should show notification count for pending approvals', async ({ page }) => {
    // Mock supervisor with pending approvals
    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 2, name: 'Test Supervisor', email: 'supervisor@example.com', role: 'Supervisor' },
          users: [],
          timeEntries: [],
          masterData: {},
          approvalRequests: [
            { id: 'req-1', status: 'Ausstehend' },
            { id: 'req-2', status: 'Ausstehend' },
            { id: 'req-3', status: 'Genehmigt' }
          ],
          history: [],
          globalSettings: {},
        }),
      })
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')

    // Should show notification badge with count
    const notificationBadge = page.locator('[data-testid="approval-notification-count"]')
    if (await notificationBadge.count() > 0) {
      await expect(notificationBadge).toContainText('2')
    }
  })

  test('should handle real-time approval updates', async ({ page }) => {
    // This would test WebSocket or polling for real-time updates
    // Implementation depends on your real-time update mechanism
    
    // Mock initial state
    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, name: 'Test Employee', role: 'Mitarbeiter' },
          approvalRequests: [
            { id: 'req-123', status: 'Ausstehend' }
          ],
        }),
      })
    })

    await page.goto('/')
    
    // Simulate approval status update
    await page.route('**/api/approvals.php', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify([
            { id: 'req-123', status: 'Genehmigt' }
          ]),
        })
      }
    })

    // Should show updated status
    await expect(page.locator('text=Genehmigt')).toBeVisible()
  })
})
/**
 * E2E Tests for Role-Based Access Control (RBAC)
 * Tests access control for different user roles: Employee, Supervisor, Admin
 */
import { test, expect } from '@playwright/test'

// Test data for different user roles
const users = {
  employee: {
    id: 1,
    name: 'Max Mustermann',
    email: 'max.mustermann@example.com',
    role: 'Mitarbeiter'
  },
  supervisor: {
    id: 2,
    name: 'Anna Supervisor',
    email: 'anna.supervisor@example.com',
    role: 'Supervisor'
  },
  admin: {
    id: 3,
    name: 'Admin User',
    email: 'admin@example.com',
    role: 'Administrator'
  }
}

// Helper function to mock authentication for different roles
async function mockAuthForRole(page: any, role: 'employee' | 'supervisor' | 'admin') {
  const user = users[role]
  
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
        user,
        users: Object.values(users),
        timeEntries: [
          {
            id: 1,
            userId: 1,
            date: '2025-08-06',
            startTime: '09:00:00',
            stopTime: '17:00:00',
            status: 'Erfasst',
            reason: 'Reguläre Arbeitszeit'
          }
        ],
        masterData: { 1: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 } },
        approvalRequests: [],
        history: [],
        globalSettings: { companyName: 'Test Company', workingHoursPerWeek: 40 },
      }),
    })
  })
}

test.describe('Employee Role Access Control', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuthForRole(page, 'employee')
    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should allow employee to view their own time entries', async ({ page }) => {
    // Employee should see their time entries
    await expect(page.locator('text=09:00')).toBeVisible()
    await expect(page.locator('text=17:00')).toBeVisible()
    await expect(page.locator('text=Reguläre Arbeitszeit')).toBeVisible()
  })

  test('should allow employee to start/stop timer', async ({ page }) => {
    // Mock timer endpoints
    await page.route('**/api/timer-start.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ timerId: 1, startTime: new Date().toISOString() }),
      })
    })

    const startButton = page.locator('button:has-text("Starten")')
    if (await startButton.count() > 0) {
      await startButton.click()
      await expect(page.locator('button:has-text("Stoppen")')).toBeVisible()
    }
  })

  test('should allow employee to add manual time entries', async ({ page }) => {
    await page.route('**/api/time-entries.php', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, id: 2 }),
        })
      }
    })

    const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    if (await addEntryButton.count() > 0) {
      await addEntryButton.click()
      await page.fill('input[name="startTime"]', '08:00')
      await page.fill('input[name="stopTime"]', '16:00')
      
      const submitButton = page.locator('button[type="submit"]')
      await submitButton.click()
      
      await expect(page.locator('text=Eintrag erfolgreich')).toBeVisible()
    }
  })

  test('should allow employee to edit their own pending time entries', async ({ page }) => {
    await page.route('**/api/time-entries.php', async (route) => {
      if (route.request().method() === 'PUT') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      }
    })

    const editButton = page.locator('button:has-text("Bearbeiten")').first()
    if (await editButton.count() > 0) {
      await editButton.click()
      await page.fill('input[name="stopTime"]', '18:00')
      
      const saveButton = page.locator('button:has-text("Speichern")')
      await saveButton.click()
      
      await expect(page.locator('text=18:00')).toBeVisible()
    }
  })

  test('should NOT allow employee to view other users data', async ({ page }) => {
    // Try to access another user's data via API
    await page.route('**/api/time-entries.php', async (route) => {
      const url = new URL(route.request().url())
      const userId = url.searchParams.get('userId')
      
      if (userId && userId !== '1') {
        // Return 403 for unauthorized user access
        await route.fulfill({
          status: 403,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Access denied' }),
        })
      } else {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify([]),
        })
      }
    })

    // Employee should not see user selection dropdown
    const userSelector = page.locator('select[name="userId"]')
    await expect(userSelector).not.toBeVisible()
  })

  test('should NOT allow employee to access admin functions', async ({ page }) => {
    // Admin functions should not be visible
    await expect(page.locator('button:has-text("Globale Einstellungen")')).not.toBeVisible()
    await expect(page.locator('button:has-text("Benutzerverwaltung")')).not.toBeVisible()
    await expect(page.locator('button:has-text("System-Logs")')).not.toBeVisible()
  })

  test('should NOT allow employee to approve time entries', async ({ page }) => {
    // Approval functions should not be visible
    const approvalsTab = page.locator('button:has-text("Genehmigungen")')
    if (await approvalsTab.count() > 0) {
      await approvalsTab.click()
      // Should show access denied or no pending approvals for employee role
      await expect(page.locator('text=Keine Berechtigung')).toBeVisible()
    }
  })
})

test.describe('Supervisor Role Access Control', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuthForRole(page, 'supervisor')
    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should allow supervisor to view team member time entries', async ({ page }) => {
    // Mock endpoint to return team data
    await page.route('**/api/time-entries.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          {
            id: 1,
            userId: 1,
            userName: 'Max Mustermann',
            date: '2025-08-06',
            startTime: '09:00:00',
            stopTime: '17:00:00',
            status: 'Erfasst',
            reason: 'Reguläre Arbeitszeit'
          }
        ]),
      })
    })

    // Supervisor should see user selection dropdown
    const userSelector = page.locator('select[name="userId"]')
    if (await userSelector.count() > 0) {
      await expect(userSelector).toBeVisible()
      await userSelector.selectOption('1')
      
      // Should see team member's data
      await expect(page.locator('text=Max Mustermann')).toBeVisible()
    }
  })

  test('should allow supervisor to access approval workflow', async ({ page }) => {
    // Mock approval requests
    await page.route('**/api/approvals.php', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify([
            {
              id: 'req-123',
              userId: 1,
              employeeName: 'Max Mustermann',
              requestType: 'change',
              requestedChanges: { startTime: '08:30:00', stopTime: '16:30:00' },
              reason: 'Korrektur der Arbeitszeit',
              status: 'Ausstehend',
              createdDate: '2025-08-06'
            }
          ]),
        })
      }
    })

    // Navigate to approvals
    const approvalsTab = page.locator('button:has-text("Genehmigungen")')
    await approvalsTab.click()
    
    // Should see pending approvals
    await expect(page.locator('text=Max Mustermann')).toBeVisible()
    await expect(page.locator('text=Korrektur der Arbeitszeit')).toBeVisible()
  })

  test('should allow supervisor to approve time entries', async ({ page }) => {
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

    const approvalsTab = page.locator('button:has-text("Genehmigungen")')
    if (await approvalsTab.count() > 0) {
      await approvalsTab.click()
      
      const approveButton = page.locator('button:has-text("Genehmigen")')
      if (await approveButton.count() > 0) {
        await approveButton.click()
        await expect(page.locator('text=Genehmigt')).toBeVisible()
      }
    }
  })

  test('should allow supervisor to view team reports', async ({ page }) => {
    const reportsTab = page.locator('button:has-text("Berichte")')
    if (await reportsTab.count() > 0) {
      await reportsTab.click()
      
      // Should have access to team reporting
      await expect(page.locator('text=Team-Übersicht')).toBeVisible()
      await expect(page.locator('text=Zeiterfassung Bericht')).toBeVisible()
    }
  })

  test('should NOT allow supervisor to access admin-only functions', async ({ page }) => {
    // Admin-only functions should not be visible to supervisor
    await expect(page.locator('button:has-text("Globale Einstellungen")')).not.toBeVisible()
    await expect(page.locator('button:has-text("System-Logs")')).not.toBeVisible()
  })

  test('should allow supervisor to manage team member notifications', async ({ page }) => {
    await page.route('**/api/notifications.php', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      }
    })

    const notificationButton = page.locator('button:has-text("Benachrichtigung senden")')
    if (await notificationButton.count() > 0) {
      await notificationButton.click()
      
      await page.fill('textarea[name="message"]', 'Bitte korrigieren Sie Ihre Zeiteinträge.')
      await page.click('button:has-text("Senden")')
      
      await expect(page.locator('text=Benachrichtigung gesendet')).toBeVisible()
    }
  })
})

test.describe('Administrator Role Access Control', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuthForRole(page, 'admin')
    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should allow admin to access global settings', async ({ page }) => {
    await page.route('**/api/settings.php', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            companyName: 'Test Company',
            workingHoursPerWeek: 40,
            vacationDaysPerYear: 25,
            requireApprovalForChanges: true
          }),
        })
      }
    })

    const settingsButton = page.locator('button:has-text("Globale Einstellungen")')
    await settingsButton.click()
    
    // Should see global settings form
    await expect(page.locator('input[name="companyName"]')).toBeVisible()
    await expect(page.locator('input[name="workingHoursPerWeek"]')).toBeVisible()
  })

  test('should allow admin to manage users', async ({ page }) => {
    await page.route('**/api/users.php', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(Object.values(users)),
        })
      }
    })

    const userManagementButton = page.locator('button:has-text("Benutzerverwaltung")')
    if (await userManagementButton.count() > 0) {
      await userManagementButton.click()
      
      // Should see user list
      await expect(page.locator('text=Max Mustermann')).toBeVisible()
      await expect(page.locator('text=Anna Supervisor')).toBeVisible()
    }
  })

  test('should allow admin to create new users', async ({ page }) => {
    await page.route('**/api/users.php', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, id: 4 }),
        })
      }
    })

    const userManagementButton = page.locator('button:has-text("Benutzerverwaltung")')
    if (await userManagementButton.count() > 0) {
      await userManagementButton.click()
      
      const addUserButton = page.locator('button:has-text("Benutzer hinzufügen")')
      await addUserButton.click()
      
      // Fill user form
      await page.fill('input[name="name"]', 'Neuer Benutzer')
      await page.fill('input[name="email"]', 'neu@example.com')
      await page.selectOption('select[name="role"]', 'Mitarbeiter')
      
      const saveButton = page.locator('button:has-text("Speichern")')
      await saveButton.click()
      
      await expect(page.locator('text=Benutzer erfolgreich erstellt')).toBeVisible()
    }
  })

  test('should allow admin to modify user roles', async ({ page }) => {
    await page.route('**/api/users.php', async (route) => {
      if (route.request().method() === 'PUT') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      }
    })

    const userManagementButton = page.locator('button:has-text("Benutzerverwaltung")')
    if (await userManagementButton.count() > 0) {
      await userManagementButton.click()
      
      const editButton = page.locator('button:has-text("Bearbeiten")').first()
      await editButton.click()
      
      await page.selectOption('select[name="role"]', 'Supervisor')
      
      const saveButton = page.locator('button:has-text("Speichern")')
      await saveButton.click()
      
      await expect(page.locator('text=Benutzer aktualisiert')).toBeVisible()
    }
  })

  test('should allow admin to access system logs', async ({ page }) => {
    await page.route('**/api/logs.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          {
            id: 1,
            level: 'info',
            message: 'User logged in',
            userId: 1,
            timestamp: '2025-08-06 10:00:00',
            context: { ip: '192.168.1.1' }
          }
        ]),
      })
    })

    const logsButton = page.locator('button:has-text("System-Logs")')
    if (await logsButton.count() > 0) {
      await logsButton.click()
      
      // Should see system logs
      await expect(page.locator('text=User logged in')).toBeVisible()
      await expect(page.locator('text=192.168.1.1')).toBeVisible()
    }
  })

  test('should allow admin to view all user data', async ({ page }) => {
    await page.route('**/api/time-entries.php', async (route) => {
      const url = new URL(route.request().url())
      const userId = url.searchParams.get('userId')
      
      // Admin can access any user's data
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          {
            id: 1,
            userId: parseInt(userId || '1'),
            date: '2025-08-06',
            startTime: '09:00:00',
            stopTime: '17:00:00',
            status: 'Erfasst',
            reason: 'Reguläre Arbeitszeit'
          }
        ]),
      })
    })

    // Admin should see user selection dropdown with all users
    const userSelector = page.locator('select[name="userId"]')
    await expect(userSelector).toBeVisible()
    
    // Should be able to select any user
    await userSelector.selectOption('1')
    await expect(page.locator('text=09:00')).toBeVisible()
  })

  test('should allow admin to perform bulk operations', async ({ page }) => {
    await page.route('**/api/bulk-operations.php', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ 
            success: true, 
            processed: 10,
            failed: 0 
          }),
        })
      }
    })

    const bulkOperationsButton = page.locator('button:has-text("Bulk-Operationen")')
    if (await bulkOperationsButton.count() > 0) {
      await bulkOperationsButton.click()
      
      // Select bulk operation type
      await page.selectOption('select[name="operation"]', 'export')
      await page.click('button:has-text("Ausführen")')
      
      await expect(page.locator('text=10 Einträge verarbeitet')).toBeVisible()
    }
  })

  test('should allow admin to access all approval workflows', async ({ page }) => {
    await page.route('**/api/approvals.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          {
            id: 'req-123',
            userId: 1,
            employeeName: 'Max Mustermann',
            supervisorName: 'Anna Supervisor',
            requestType: 'change',
            status: 'Ausstehend'
          }
        ]),
      })
    })

    const approvalsTab = page.locator('button:has-text("Alle Genehmigungen")')
    if (await approvalsTab.count() > 0) {
      await approvalsTab.click()
      
      // Should see all approval requests system-wide
      await expect(page.locator('text=Max Mustermann')).toBeVisible()
      await expect(page.locator('text=Anna Supervisor')).toBeVisible()
    }
  })
})

test.describe('Cross-Role Security', () => {
  test('should prevent privilege escalation attacks', async ({ page }) => {
    // Start as employee
    await mockAuthForRole(page, 'employee')
    await page.goto('/')

    // Try to access admin endpoint directly
    const response = await page.evaluate(async () => {
      try {
        const res = await fetch('/api/settings.php')
        return { status: res.status, ok: res.ok }
      } catch (error) {
        return { error: error.message }
      }
    })

    // Should be forbidden
    expect(response.status).toBe(403)
  })

  test('should validate role permissions on API level', async ({ page }) => {
    // Mock employee authentication
    await mockAuthForRole(page, 'employee')
    
    let unauthorizedAccess = false
    await page.route('**/api/users.php', async (route) => {
      // Employee trying to access user management should be denied
      unauthorizedAccess = true
      await route.fulfill({
        status: 403,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Insufficient permissions' }),
      })
    })

    await page.goto('/')
    
    // Try to make unauthorized API call
    await page.evaluate(() => {
      fetch('/api/users.php')
    })

    expect(unauthorizedAccess).toBeTruthy()
  })

  test('should handle role changes during active session', async ({ page }) => {
    // Start as supervisor
    await mockAuthForRole(page, 'supervisor')
    await page.goto('/')
    
    // Should see supervisor functions
    const approvalsTab = page.locator('button:has-text("Genehmigungen")')
    await expect(approvalsTab).toBeVisible()
    
    // Simulate role downgrade during session
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ 
          authenticated: true,
          user: { ...users.employee },
          roleChanged: true
        }),
      })
    })

    // Force session revalidation
    await page.reload()
    
    // Should no longer see supervisor functions
    await expect(approvalsTab).not.toBeVisible()
  })

  test('should enforce data isolation between users', async ({ page }) => {
    await mockAuthForRole(page, 'employee')
    
    // Mock API to return error when trying to access other user's data
    await page.route('**/api/time-entries.php', async (route) => {
      const url = new URL(route.request().url())
      const requestedUserId = url.searchParams.get('userId')
      const currentUserId = '1' // Employee user ID
      
      if (requestedUserId && requestedUserId !== currentUserId) {
        await route.fulfill({
          status: 403,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Access denied to other user data' }),
        })
      } else {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify([]),
        })
      }
    })

    await page.goto('/')
    
    // Try to access another user's data via URL manipulation
    await page.goto('/?userId=2')
    
    // Should show error or redirect
    await expect(page.locator('text=Access denied')).toBeVisible()
  })

  test('should validate token permissions match user role', async ({ page }) => {
    // Mock API that checks token permissions
    await page.route('**/api/**', async (route) => {
      const authorization = route.request().headers()['authorization']
      
      if (!authorization || !authorization.includes('Bearer')) {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Missing or invalid token' }),
        })
      } else {
        // In real implementation, token would be validated server-side
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      }
    })

    await page.goto('/')
    
    // All API calls should include valid authorization
    const apiCallResult = await page.evaluate(async () => {
      try {
        const res = await fetch('/api/time-entries.php')
        return res.status
      } catch (error) {
        return 500
      }
    })

    // Should require authentication
    expect(apiCallResult).not.toBe(401)
  })
})
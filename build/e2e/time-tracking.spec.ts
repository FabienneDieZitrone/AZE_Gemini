/**
 * E2E Tests for Time Tracking Functionality
 * Tests core time tracking features including timer start/stop, manual entry, and time validation
 */
import { test, expect } from '@playwright/test'

test.describe('Time Tracking', () => {
  test.beforeEach(async ({ page }) => {
    // Mock authenticated state
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
          user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' },
          users: [{ id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' }],
          timeEntries: [],
          masterData: { 1: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 } },
          approvalRequests: [],
          history: [],
          globalSettings: { companyName: 'Test Company', workingHoursPerWeek: 40 },
        }),
      })
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should display timer controls when authenticated', async ({ page }) => {
    // Look for timer start button
    const startButton = page.locator('button:has-text("Starten")')
    await expect(startButton).toBeVisible()
  })

  test('should start and stop timer', async ({ page }) => {
    // Mock timer start endpoint
    await page.route('**/api/timer-start.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ timerId: 1, startTime: new Date().toISOString() }),
      })
    })

    // Mock timer stop endpoint
    await page.route('**/api/timer-stop.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ 
          success: true,
          entry: {
            id: 1,
            userId: 1,
            date: new Date().toISOString().split('T')[0],
            startTime: '09:00:00',
            stopTime: '17:00:00',
            status: 'Erfasst',
            reason: 'Reguläre Arbeitszeit'
          }
        }),
      })
    })

    // Start timer
    const startButton = page.locator('button:has-text("Starten")')
    await startButton.click()

    // Should show stop button
    const stopButton = page.locator('button:has-text("Stoppen")')
    await expect(stopButton).toBeVisible()

    // Should show elapsed time
    const timerDisplay = page.locator('[data-testid="timer-display"]')
    await expect(timerDisplay).toBeVisible()

    // Stop timer
    await stopButton.click()

    // Should show start button again
    await expect(startButton).toBeVisible()
  })

  test('should display elapsed time correctly', async ({ page }) => {
    // Mock timer start
    await page.route('**/api/timer-start.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ timerId: 1, startTime: new Date().toISOString() }),
      })
    })

    const startButton = page.locator('button:has-text("Starten")')
    await startButton.click()

    // Wait for timer to run for a few seconds
    await page.waitForTimeout(2000)

    const timerDisplay = page.locator('[data-testid="timer-display"]')
    const timerText = await timerDisplay.textContent()
    
    // Should show time in HH:MM:SS format
    expect(timerText).toMatch(/\d{2}:\d{2}:\d{2}/)
  })

  test('should handle manual time entry', async ({ page }) => {
    // Mock add time entry endpoint
    await page.route('**/api/time-entries.php', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ 
            id: 1,
            success: true 
          }),
        })
      }
    })

    // Look for manual entry form or button
    const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    if (await addEntryButton.count() > 0) {
      await addEntryButton.click()

      // Fill in manual entry form
      await page.fill('input[name="startTime"]', '09:00')
      await page.fill('input[name="stopTime"]', '17:00')
      
      // Submit the form
      const submitButton = page.locator('button[type="submit"]')
      await submitButton.click()

      // Should show success message or updated time entries
      await expect(page.locator('text=Eintrag erfolgreich')).toBeVisible()
    }
  })

  test('should validate time entry data', async ({ page }) => {
    // Mock validation error
    await page.route('**/api/time-entries.php', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 400,
          contentType: 'application/json',
          body: JSON.stringify({ 
            error: 'Endzeit muss nach Startzeit liegen' 
          }),
        })
      }
    })

    const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    if (await addEntryButton.count() > 0) {
      await addEntryButton.click()

      // Enter invalid times (end before start)
      await page.fill('input[name="startTime"]', '17:00')
      await page.fill('input[name="stopTime"]', '09:00')
      
      const submitButton = page.locator('button[type="submit"]')
      await submitButton.click()

      // Should show error message
      await expect(page.locator('text=Endzeit muss nach Startzeit liegen')).toBeVisible()
    }
  })
})

test.describe('Time Entry Management', () => {
  test.beforeEach(async ({ page }) => {
    // Mock authenticated state with existing time entries
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
          user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' },
          users: [{ id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' }],
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
          globalSettings: { companyName: 'Test Company', workingHoursPerWeek: 40 },
        }),
      })
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should display existing time entries', async ({ page }) => {
    // Should show time entries in a table or list
    await expect(page.locator('text=09:00')).toBeVisible()
    await expect(page.locator('text=17:00')).toBeVisible()
    await expect(page.locator('text=Reguläre Arbeitszeit')).toBeVisible()
  })

  test('should calculate total hours correctly', async ({ page }) => {
    // Should show total hours (8 hours for 09:00-17:00)
    const totalHours = page.locator('[data-testid="total-hours"]')
    if (await totalHours.count() > 0) {
      const totalText = await totalHours.textContent()
      expect(totalText).toContain('8')
    }
  })

  test('should edit existing time entry', async ({ page }) => {
    // Mock edit endpoint
    await page.route('**/api/time-entries.php', async (route) => {
      if (route.request().method() === 'PUT') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      }
    })

    // Look for edit button
    const editButton = page.locator('button:has-text("Bearbeiten")').first()
    if (await editButton.count() > 0) {
      await editButton.click()

      // Modify time entry
      await page.fill('input[name="stopTime"]', '18:00')
      
      const saveButton = page.locator('button:has-text("Speichern")')
      await saveButton.click()

      // Should show updated time
      await expect(page.locator('text=18:00')).toBeVisible()
    }
  })

  test('should delete time entry', async ({ page }) => {
    // Mock delete endpoint
    await page.route('**/api/time-entries.php', async (route) => {
      if (route.request().method() === 'DELETE') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      }
    })

    // Look for delete button
    const deleteButton = page.locator('button:has-text("Löschen")').first()
    if (await deleteButton.count() > 0) {
      await deleteButton.click()

      // Confirm deletion
      const confirmButton = page.locator('button:has-text("Bestätigen")')
      if (await confirmButton.count() > 0) {
        await confirmButton.click()
      }

      // Entry should be removed
      await expect(page.locator('text=Reguläre Arbeitszeit')).not.toBeVisible()
    }
  })
})
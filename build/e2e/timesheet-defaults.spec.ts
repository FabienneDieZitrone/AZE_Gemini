import { test, expect } from '@playwright/test'

const mockInitialData = {
  currentUser: { id: 1, name: 'Admin User', role: 'Admin' },
  users: [
    { id: 1, name: 'Admin User', role: 'Admin' },
    { id: 2, name: 'User B', role: 'Mitarbeiter' }
  ],
  masterData: {
    1: { weeklyHours: 40, workdays: ['Mo','Di','Mi','Do','Fr'], canWorkFromHome: true },
    2: { weeklyHours: 40, workdays: ['Mo','Di','Mi','Do','Fr'], canWorkFromHome: false }
  },
  timeEntries: [
    { id: 10, userId: 2, username: 'User B', date: '2025-10-01', startTime: '08:00:00', stopTime: '16:00:00', location: 'Zentrale Berlin', role: 'Mitarbeiter', createdAt: '2025-10-01T08:00:00Z', updatedBy: 'System', updatedAt: '2025-10-01T16:00:00Z' }
  ],
  approvalRequests: [],
  history: [],
  globalSettings: { overtimeThreshold: 2, changeReasons: ['Korrektur'], locations: ['Zentrale Berlin'] }
}

test.beforeEach(async ({ page }) => {
  await page.route('**/api/login.php', async route => {
    await route.fulfill({ status: 200, body: JSON.stringify(mockInitialData), headers: { 'content-type': 'application/json' } })
  })
})

test('Timesheet defaults to "Alle Benutzer" for admin and shows entries', async ({ page }) => {
  await page.goto('/')
  await page.getByRole('button', { name: 'Arbeitszeiten anzeigen' }).click()

  const benutzerSelect = page.locator('select[name="benutzer"]')
  await expect(benutzerSelect).toHaveValue('Alle Benutzer')

  // Expect a row with User B
  await expect(page.getByRole('cell', { name: 'User B' })).toBeVisible()
})

test('Timesheet remembers selected user filter across reloads', async ({ page }) => {
  await page.goto('/')
  await page.getByRole('button', { name: 'Arbeitszeiten anzeigen' }).click()

  const benutzerSelect = page.locator('select[name="benutzer"]')
  await benutzerSelect.selectOption('2') // User B

  // Reload and intercept again
  await page.reload()
  await page.getByRole('button', { name: 'Arbeitszeiten anzeigen' }).click()
  await expect(benutzerSelect).toHaveValue('2')
})


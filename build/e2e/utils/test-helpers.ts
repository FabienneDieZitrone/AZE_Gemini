/**
 * Test Utility Functions for E2E Tests
 * Common helper functions for setting up test data, authentication, and assertions
 */
import { Page, expect } from '@playwright/test'

// User roles and test data
export const TEST_USERS = {
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
} as const

export type UserRole = keyof typeof TEST_USERS

// Sample time entries for testing
export const SAMPLE_TIME_ENTRIES = [
  {
    id: 1,
    userId: 1,
    userName: 'Max Mustermann',
    date: '2025-08-01',
    startTime: '09:00:00',
    stopTime: '17:00:00',
    status: 'Genehmigt',
    reason: 'Reguläre Arbeitszeit'
  },
  {
    id: 2,
    userId: 1,
    userName: 'Max Mustermann',
    date: '2025-08-02',
    startTime: '08:30:00',
    stopTime: '16:30:00',
    status: 'Erfasst',
    reason: 'Früher Arbeitsbeginn'
  },
  {
    id: 3,
    userId: 1,
    userName: 'Max Mustermann',
    date: '2025-08-03',
    startTime: '10:00:00',
    stopTime: '18:00:00',
    status: 'Ausstehend',
    reason: 'Flexible Arbeitszeit'
  }
]

// Sample approval requests
export const SAMPLE_APPROVAL_REQUESTS = [
  {
    id: 'req-123',
    userId: 1,
    employeeName: 'Max Mustermann',
    entryId: 1,
    requestType: 'change',
    requestedChanges: { startTime: '08:30:00', stopTime: '16:30:00' },
    reason: 'Korrektur der tatsächlichen Arbeitszeit',
    status: 'Ausstehend',
    createdDate: '2025-08-06'
  }
]

// Global settings for testing
export const TEST_GLOBAL_SETTINGS = {
  companyName: 'Mikropartner GmbH',
  workingHoursPerWeek: 40,
  vacationDaysPerYear: 25,
  requireApprovalForChanges: true,
  address: 'Beispielstraße 123, 12345 Beispielstadt'
}

/**
 * Set up authentication for a specific user role
 */
export async function mockAuthentication(page: Page, role: UserRole = 'employee', options?: {
  timeEntries?: typeof SAMPLE_TIME_ENTRIES,
  approvalRequests?: typeof SAMPLE_APPROVAL_REQUESTS,
  globalSettings?: Partial<typeof TEST_GLOBAL_SETTINGS>
}) {
  const user = TEST_USERS[role]
  const timeEntries = options?.timeEntries || SAMPLE_TIME_ENTRIES
  const approvalRequests = options?.approvalRequests || SAMPLE_APPROVAL_REQUESTS
  const globalSettings = { ...TEST_GLOBAL_SETTINGS, ...options?.globalSettings }

  // Mock auth status endpoint
  await page.route('**/api/auth-status.php', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ authenticated: true }),
    })
  })

  // Mock login endpoint with user data
  await page.route('**/api/login.php', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        user,
        users: Object.values(TEST_USERS),
        timeEntries: role === 'supervisor' || role === 'admin' 
          ? timeEntries 
          : timeEntries.filter(e => e.userId === user.id),
        masterData: { 
          1: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 },
          2: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 },
          3: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 }
        },
        approvalRequests: role === 'employee' 
          ? approvalRequests.filter(r => r.userId === user.id)
          : approvalRequests,
        history: [],
        globalSettings,
      }),
    })
  })
}

/**
 * Mock unauthenticated state
 */
export async function mockUnauthenticated(page: Page) {
  await page.route('**/api/auth-status.php', async (route) => {
    await route.fulfill({
      status: 401,
      contentType: 'application/json',
      body: JSON.stringify({ authenticated: false }),
    })
  })
}

/**
 * Mock API endpoints for CRUD operations
 */
export async function mockTimeEntriesAPI(page: Page, customHandlers?: {
  onCreate?: (data: any) => any,
  onUpdate?: (id: string, data: any) => any,
  onDelete?: (id: string) => any
}) {
  await page.route('**/api/time-entries.php', async (route) => {
    const method = route.request().method()
    const url = new URL(route.request().url())
    const id = url.searchParams.get('id')
    
    try {
      const requestData = method !== 'GET' && method !== 'DELETE' 
        ? await route.request().postDataJSON() 
        : null

      switch (method) {
        case 'GET':
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(SAMPLE_TIME_ENTRIES),
          })
          break

        case 'POST':
          const createResult = customHandlers?.onCreate 
            ? customHandlers.onCreate(requestData)
            : { success: true, id: Date.now() }
          
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(createResult),
          })
          break

        case 'PUT':
          const updateResult = customHandlers?.onUpdate 
            ? customHandlers.onUpdate(id!, requestData)
            : { success: true }
          
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(updateResult),
          })
          break

        case 'DELETE':
          const deleteResult = customHandlers?.onDelete 
            ? customHandlers.onDelete(id!)
            : { success: true }
          
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(deleteResult),
          })
          break

        default:
          await route.fulfill({
            status: 405,
            contentType: 'application/json',
            body: JSON.stringify({ error: 'Method not allowed' }),
          })
      }
    } catch (error) {
      await route.fulfill({
        status: 400,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Invalid request' }),
      })
    }
  })
}

/**
 * Mock approval workflow API
 */
export async function mockApprovalsAPI(page: Page, customHandlers?: {
  onApprove?: (id: string, data: any) => any,
  onReject?: (id: string, data: any) => any
}) {
  await page.route('**/api/approvals.php', async (route) => {
    const method = route.request().method()
    const url = new URL(route.request().url())
    const id = url.searchParams.get('id')

    try {
      const requestData = method !== 'GET' && method !== 'DELETE' 
        ? await route.request().postDataJSON() 
        : null

      switch (method) {
        case 'GET':
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(SAMPLE_APPROVAL_REQUESTS),
          })
          break

        case 'POST':
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ requestId: `req-${Date.now()}`, success: true }),
          })
          break

        case 'PATCH':
          if (requestData?.finalStatus === 'genehmigt') {
            const approveResult = customHandlers?.onApprove 
              ? customHandlers.onApprove(id!, requestData)
              : { success: true }
            
            await route.fulfill({
              status: 200,
              contentType: 'application/json',
              body: JSON.stringify(approveResult),
            })
          } else if (requestData?.finalStatus === 'abgelehnt') {
            const rejectResult = customHandlers?.onReject 
              ? customHandlers.onReject(id!, requestData)
              : { success: true }
            
            await route.fulfill({
              status: 200,
              contentType: 'application/json',
              body: JSON.stringify(rejectResult),
            })
          } else {
            await route.fulfill({
              status: 400,
              contentType: 'application/json',
              body: JSON.stringify({ error: 'Invalid status' }),
            })
          }
          break

        case 'DELETE':
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true }),
          })
          break

        default:
          await route.fulfill({
            status: 405,
            contentType: 'application/json',
            body: JSON.stringify({ error: 'Method not allowed' }),
          })
      }
    } catch (error) {
      await route.fulfill({
        status: 400,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Invalid request' }),
      })
    }
  })
}

/**
 * Mock timer API endpoints
 */
export async function mockTimerAPI(page: Page) {
  await page.route('**/api/timer-start.php', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ 
        timerId: Date.now(), 
        startTime: new Date().toISOString() 
      }),
    })
  })

  await page.route('**/api/timer-stop.php', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ 
        success: true,
        entry: {
          id: Date.now(),
          userId: 1,
          date: new Date().toISOString().split('T')[0],
          startTime: '09:00:00',
          stopTime: new Date().toTimeString().split(' ')[0],
          status: 'Erfasst',
          reason: 'Timer-basierte Erfassung'
        }
      }),
    })
  })
}

/**
 * Mock export API endpoints
 */
export async function mockExportAPI(page: Page) {
  await page.route('**/api/export/pdf.php', async (route) => {
    await route.fulfill({
      status: 200,
      headers: {
        'Content-Type': 'application/pdf',
        'Content-Disposition': 'attachment; filename="zeiterfassung_export.pdf"'
      },
      body: Buffer.from('Mock PDF content'),
    })
  })

  await page.route('**/api/export/csv.php', async (route) => {
    const csvData = [
      'Datum,Startzeit,Endzeit,Grund,Status',
      '2025-08-01,09:00:00,17:00:00,Reguläre Arbeitszeit,Genehmigt',
      '2025-08-02,08:30:00,16:30:00,Früher Arbeitsbeginn,Erfasst'
    ].join('\n')

    await route.fulfill({
      status: 200,
      headers: {
        'Content-Type': 'text/csv',
        'Content-Disposition': 'attachment; filename="zeiterfassung_export.csv"'
      },
      body: csvData,
    })
  })

  await page.route('**/api/export/excel.php', async (route) => {
    await route.fulfill({
      status: 200,
      headers: {
        'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition': 'attachment; filename="zeiterfassung_export.xlsx"'
      },
      body: Buffer.from('Mock Excel content'),
    })
  })
}

/**
 * Wait for page to be fully loaded and authenticated
 */
export async function waitForAuthenticated(page: Page) {
  await page.waitForLoadState('networkidle')
  
  // Wait for either login page or main app to appear
  await Promise.race([
    page.waitForSelector('text=Willkommen zur MP Arbeitszeiterfassung', { timeout: 5000 }).catch(() => null),
    page.waitForSelector('.app-logo-svg', { timeout: 5000 }).catch(() => null)
  ])
}

/**
 * Fill form fields with validation
 */
export async function fillTimeEntryForm(page: Page, data: {
  startTime: string,
  stopTime: string,
  reason?: string,
  date?: string
}) {
  if (data.date) {
    const dateInput = page.locator('input[name="date"]')
    if (await dateInput.count() > 0) {
      await dateInput.fill(data.date)
    }
  }

  await page.fill('input[name="startTime"]', data.startTime)
  await page.fill('input[name="stopTime"]', data.stopTime)
  
  if (data.reason) {
    const reasonField = page.locator('textarea[name="reason"], input[name="reason"]')
    if (await reasonField.count() > 0) {
      await reasonField.fill(data.reason)
    }
  }
}

/**
 * Assert time entry is displayed correctly
 */
export async function assertTimeEntryVisible(page: Page, entry: {
  startTime: string,
  stopTime: string,
  reason?: string,
  status?: string
}) {
  await expect(page.locator(`text=${entry.startTime}`)).toBeVisible()
  await expect(page.locator(`text=${entry.stopTime}`)).toBeVisible()
  
  if (entry.reason) {
    await expect(page.locator(`text=${entry.reason}`)).toBeVisible()
  }
  
  if (entry.status) {
    await expect(page.locator(`text=${entry.status}`)).toBeVisible()
  }
}

/**
 * Assert error message is shown
 */
export async function assertErrorMessage(page: Page, message: string) {
  await expect(page.locator(`text=${message}`)).toBeVisible()
}

/**
 * Assert success message is shown
 */
export async function assertSuccessMessage(page: Page, message: string) {
  await expect(page.locator(`text=${message}`)).toBeVisible()
}

/**
 * Mock network error for testing error handling
 */
export async function mockNetworkError(page: Page, endpoint: string) {
  await page.route(endpoint, route => route.abort('failed'))
}

/**
 * Mock server error for testing error handling
 */
export async function mockServerError(page: Page, endpoint: string, status: number = 500) {
  await page.route(endpoint, async (route) => {
    await route.fulfill({
      status,
      contentType: 'application/json',
      body: JSON.stringify({ 
        error: 'Internal server error',
        code: `HTTP_${status}` 
      }),
    })
  })
}

/**
 * Take screenshot for debugging
 */
export async function debugScreenshot(page: Page, name: string) {
  if (process.env.DEBUG_SCREENSHOTS) {
    await page.screenshot({ path: `debug-${name}-${Date.now()}.png` })
  }
}

/**
 * Generate test data with specified properties
 */
export function generateTimeEntry(overrides?: Partial<typeof SAMPLE_TIME_ENTRIES[0]>) {
  return {
    id: Date.now(),
    userId: 1,
    userName: 'Test User',
    date: new Date().toISOString().split('T')[0],
    startTime: '09:00:00',
    stopTime: '17:00:00',
    status: 'Erfasst',
    reason: 'Test entry',
    ...overrides
  }
}

/**
 * Generate approval request test data
 */
export function generateApprovalRequest(overrides?: Partial<typeof SAMPLE_APPROVAL_REQUESTS[0]>) {
  return {
    id: `req-${Date.now()}`,
    userId: 1,
    employeeName: 'Test User',
    entryId: 1,
    requestType: 'change',
    requestedChanges: { startTime: '08:00:00', stopTime: '16:00:00' },
    reason: 'Test approval request',
    status: 'Ausstehend',
    createdDate: new Date().toISOString().split('T')[0],
    ...overrides
  }
}

/**
 * Assert element is accessible
 */
export async function assertAccessible(page: Page, selector: string) {
  const element = page.locator(selector)
  
  // Check that element has proper ARIA attributes or labels
  const hasAriaLabel = await element.getAttribute('aria-label')
  const hasAriaLabelledBy = await element.getAttribute('aria-labelledby')
  const hasAssociatedLabel = await element.evaluate((el: HTMLElement) => {
    const id = el.id
    return id ? document.querySelector(`label[for="${id}"]`) !== null : false
  })
  
  expect(hasAriaLabel || hasAriaLabelledBy || hasAssociatedLabel).toBeTruthy()
}

/**
 * Test keyboard navigation
 */
export async function testKeyboardNavigation(page: Page, startSelector: string) {
  const startElement = page.locator(startSelector)
  await startElement.focus()
  
  // Tab through elements
  await page.keyboard.press('Tab')
  
  // Verify focus moved to another element
  const focusedElement = await page.evaluate(() => document.activeElement?.tagName)
  expect(['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A']).toContain(focusedElement)
}

/**
 * Simulate different device types
 */
export async function simulateDevice(page: Page, device: 'mobile' | 'tablet' | 'desktop') {
  const viewports = {
    mobile: { width: 375, height: 667 },
    tablet: { width: 768, height: 1024 },
    desktop: { width: 1920, height: 1080 }
  }
  
  await page.setViewportSize(viewports[device])
}
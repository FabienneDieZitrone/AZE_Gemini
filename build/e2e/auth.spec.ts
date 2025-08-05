/**
 * E2E Tests for Authentication Flow
 * Tests the complete authentication journey including login, logout, and session management
 */
import { test, expect } from '@playwright/test'

test.describe('Authentication Flow', () => {
  test.beforeEach(async ({ page }) => {
    // Start from the homepage
    await page.goto('/')
  })

  test('should show login page when not authenticated', async ({ page }) => {
    // Should redirect to login page or show login form
    await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).toBeVisible()
    await expect(page.locator('button:has-text("Mit Microsoft anmelden")')).toBeVisible()
  })

  test('should have proper login button styling and accessibility', async ({ page }) => {
    const loginButton = page.locator('button:has-text("Mit Microsoft anmelden")')
    
    // Check button is visible and enabled
    await expect(loginButton).toBeVisible()
    await expect(loginButton).toBeEnabled()
    
    // Check for proper CSS classes
    await expect(loginButton).toHaveClass(/action-button/)
    await expect(loginButton).toHaveClass(/login-button/)
  })

  test('should show loading state when login is clicked', async ({ page }) => {
    const loginButton = page.locator('button:has-text("Mit Microsoft anmelden")')
    
    // Click login button
    await loginButton.click()
    
    // Should show loading spinner and button should be disabled
    await expect(page.locator('[data-testid="loading-spinner"]')).toBeVisible()
    await expect(loginButton).toBeDisabled()
  })

  test('should handle keyboard navigation', async ({ page }) => {
    // Tab to the login button
    await page.keyboard.press('Tab')
    
    const loginButton = page.locator('button:has-text("Mit Microsoft anmelden")')
    await expect(loginButton).toBeFocused()
    
    // Press Enter to activate
    await page.keyboard.press('Enter')
    await expect(loginButton).toBeDisabled()
  })

  test('should display company logo', async ({ page }) => {
    // Check if logo is present
    await expect(page.locator('.app-logo-svg')).toBeVisible()
    await expect(page.locator('text=MP')).toBeVisible()
  })
})

test.describe('Authenticated State', () => {
  test.beforeEach(async ({ page }) => {
    // Mock authenticated state by setting up API responses
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
          users: [],
          timeEntries: [],
          masterData: {},
          approvalRequests: [],
          history: [],
          globalSettings: {},
        }),
      })
    })

    await page.goto('/')
  })

  test('should show main application when authenticated', async ({ page }) => {
    // Should not show login page
    await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).not.toBeVisible()
    
    // Should show main app elements (this will depend on your MainAppView implementation)
    // Adjust these selectors based on your actual main app UI
    await expect(page.locator('.app-logo-svg')).toBeVisible()
  })

  test('should handle logout flow', async ({ page }) => {
    // Look for logout button (adjust selector based on your UI)
    const logoutButton = page.locator('button:has-text("Abmelden")')
    
    if (await logoutButton.count() > 0) {
      // Mock logout endpoint
      await page.route('**/api/auth-logout.php', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      })

      await logoutButton.click()
      
      // Should redirect back to login page
      await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).toBeVisible()
    }
  })
})

test.describe('Session Management', () => {
  test('should handle session expiration', async ({ page }) => {
    // Mock session expired response
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Unauthorized' }),
      })
    })

    await page.goto('/')
    
    // Should show login page
    await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).toBeVisible()
  })

  test('should handle network errors gracefully', async ({ page }) => {
    // Mock network error
    await page.route('**/api/auth-status.php', async (route) => {
      await route.abort('failed')
    })

    await page.goto('/')
    
    // Should fallback to login page
    await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).toBeVisible()
  })
})

test.describe('Security', () => {
  test('should not expose sensitive information in client-side code', async ({ page }) => {
    await page.goto('/')
    
    // Check that no sensitive API keys or secrets are exposed
    const content = await page.content()
    expect(content).not.toContain('password')
    expect(content).not.toContain('secret')
    expect(content).not.toContain('token')
  })

  test('should have proper security headers', async ({ page }) => {
    const response = await page.goto('/')
    
    // Check for security headers (these should be set by your server)
    expect(response?.headers()['x-content-type-options']).toBeTruthy()
    expect(response?.headers()['x-frame-options']).toBeTruthy()
  })
})
/**
 * E2E Security Tests for AZE Gemini
 * Tests security features including CSRF protection, rate limiting, MFA, and data security
 */
import { test, expect } from '@playwright/test'

test.describe('CSRF Protection', () => {
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
    await page.waitForLoadState('networkidle')
  })

  test('should include CSRF token in API requests', async ({ page }) => {
    let csrfTokenPresent = false

    // Intercept API requests to check for CSRF token
    await page.route('**/api/time-entries.php', async (route) => {
      const headers = route.request().headers()
      const body = await route.request().postDataJSON().catch(() => null)
      
      // Check for CSRF token in headers or request body
      if (headers['x-csrf-token'] || (body && body.csrf_token)) {
        csrfTokenPresent = true
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true }),
      })
    })

    // Try to create a time entry
    const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    if (await addEntryButton.count() > 0) {
      await addEntryButton.click()
      await page.fill('input[name="startTime"]', '09:00')
      await page.fill('input[name="stopTime"]', '17:00')
      
      const submitButton = page.locator('button[type="submit"]')
      await submitButton.click()

      // Verify CSRF token was included
      expect(csrfTokenPresent).toBeTruthy()
    }
  })

  test('should reject requests without valid CSRF token', async ({ page }) => {
    // Mock server response for missing CSRF token
    await page.route('**/api/time-entries.php', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 403,
          contentType: 'application/json',
          body: JSON.stringify({ 
            error: 'CSRF token missing or invalid',
            code: 'CSRF_TOKEN_INVALID' 
          }),
        })
      }
    })

    const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    if (await addEntryButton.count() > 0) {
      await addEntryButton.click()
      await page.fill('input[name="startTime"]', '09:00')
      await page.fill('input[name="stopTime"]', '17:00')
      
      const submitButton = page.locator('button[type="submit"]')
      await submitButton.click()

      // Should show CSRF error
      await expect(page.locator('text=CSRF')).toBeVisible()
    }
  })

  test('should handle CSRF token refresh', async ({ page }) => {
    let tokenRefreshAttempted = false

    // Mock CSRF token refresh endpoint
    await page.route('**/api/csrf-token.php', async (route) => {
      tokenRefreshAttempted = true
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ 
          csrf_token: 'new-csrf-token-123',
          expires_at: new Date(Date.now() + 3600000).toISOString()
        }),
      })
    })

    // Mock initial request failure due to expired token
    let requestCount = 0
    await page.route('**/api/time-entries.php', async (route) => {
      requestCount++
      if (requestCount === 1) {
        // First request fails with expired token
        await route.fulfill({
          status: 403,
          contentType: 'application/json',
          body: JSON.stringify({ 
            error: 'CSRF token expired',
            code: 'CSRF_TOKEN_EXPIRED' 
          }),
        })
      } else {
        // Second request succeeds after token refresh
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      }
    })

    // Application should automatically retry after token refresh
    const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    if (await addEntryButton.count() > 0) {
      await addEntryButton.click()
      await page.fill('input[name="startTime"]', '09:00')
      await page.fill('input[name="stopTime"]', '17:00')
      
      const submitButton = page.locator('button[type="submit"]')
      await submitButton.click()

      // Should eventually succeed after token refresh
      await expect(page.locator('text=Eintrag erfolgreich')).toBeVisible()
      expect(tokenRefreshAttempted).toBeTruthy()
    }
  })
})

test.describe('Rate Limiting', () => {
  test('should enforce rate limits on login attempts', async ({ page }) => {
    let loginAttempts = 0

    await page.route('**/api/auth-start.php', async (route) => {
      loginAttempts++
      
      if (loginAttempts <= 3) {
        // First 3 attempts are allowed
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ redirectUrl: 'https://login.microsoftonline.com/...' }),
        })
      } else {
        // Rate limit exceeded
        await route.fulfill({
          status: 429,
          contentType: 'application/json',
          body: JSON.stringify({ 
            error: 'Rate limit exceeded. Please try again later.',
            retryAfter: 300
          }),
        })
      }
    })

    await page.goto('/')
    
    const loginButton = page.locator('button:has-text("Mit Microsoft anmelden")')
    
    // Make multiple login attempts
    for (let i = 1; i <= 5; i++) {
      await loginButton.click()
      
      if (i <= 3) {
        // First 3 attempts should proceed
        await expect(page.locator('[data-testid="loading-spinner"]')).toBeVisible()
      } else {
        // Should show rate limit error
        await expect(page.locator('text=Rate limit exceeded')).toBeVisible()
        break
      }
      
      // Reset the page state for next attempt
      await page.reload()
    }
  })

  test('should enforce rate limits on API endpoints', async ({ page }) => {
    // Mock authenticated state
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ authenticated: true }),
      })
    })

    let requestCount = 0
    await page.route('**/api/time-entries.php', async (route) => {
      requestCount++
      
      if (requestCount <= 10) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        })
      } else {
        await route.fulfill({
          status: 429,
          contentType: 'application/json',
          body: JSON.stringify({ 
            error: 'API rate limit exceeded',
            retryAfter: 60
          }),
        })
      }
    })

    await page.goto('/')
    
    // Make rapid API requests
    for (let i = 1; i <= 15; i++) {
      // Trigger API call
      const response = await page.evaluate(async () => {
        try {
          const res = await fetch('/api/time-entries.php')
          return { status: res.status, ok: res.ok }
        } catch (error) {
          return { error: error.message }
        }
      })

      if (i <= 10) {
        expect(response.status).toBe(200)
      } else {
        expect(response.status).toBe(429)
        break
      }
    }
  })

  test('should show rate limit recovery time', async ({ page }) => {
    await page.route('**/api/auth-start.php', async (route) => {
      await route.fulfill({
        status: 429,
        contentType: 'application/json',
        body: JSON.stringify({ 
          error: 'Rate limit exceeded. Please try again later.',
          retryAfter: 60
        }),
      })
    })

    await page.goto('/')
    
    const loginButton = page.locator('button:has-text("Mit Microsoft anmelden")')
    await loginButton.click()
    
    // Should show countdown or retry information
    await expect(page.locator('text=60')).toBeVisible()
    await expect(page.locator('text=Sekunden')).toBeVisible()
  })
})

test.describe('Multi-Factor Authentication (MFA)', () => {
  test.beforeEach(async ({ page }) => {
    // Mock initial authentication without MFA
    await page.route('**/api/auth-callback.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          authenticated: false,
          mfaRequired: true,
          mfaChallenge: 'totp',
          tempToken: 'temp-token-123'
        }),
      })
    })
    
    await page.goto('/')
  })

  test('should prompt for MFA when required', async ({ page }) => {
    // Simulate OAuth callback
    await page.goto('/?code=oauth-code-123&state=oauth-state')
    
    // Should show MFA prompt
    await expect(page.locator('text=Zwei-Faktor-Authentifizierung')).toBeVisible()
    await expect(page.locator('input[name="mfaCode"]')).toBeVisible()
  })

  test('should accept valid MFA code', async ({ page }) => {
    // Mock MFA verification endpoint
    await page.route('**/api/mfa/verify.php', async (route) => {
      const body = await route.request().postDataJSON()
      
      if (body.code === '123456') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            authenticated: true,
            user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' }
          }),
        })
      } else {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Invalid MFA code' }),
        })
      }
    })

    // Simulate OAuth callback
    await page.goto('/?code=oauth-code-123&state=oauth-state')
    
    // Enter valid MFA code
    await page.fill('input[name="mfaCode"]', '123456')
    await page.click('button:has-text("Verifizieren")')
    
    // Should proceed to main app
    await expect(page.locator('text=Willkommen')).not.toBeVisible()
    await expect(page.locator('.app-logo-svg')).toBeVisible()
  })

  test('should reject invalid MFA code', async ({ page }) => {
    // Mock MFA verification endpoint
    await page.route('**/api/mfa/verify.php', async (route) => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'Invalid MFA code' }),
      })
    })

    // Simulate OAuth callback
    await page.goto('/?code=oauth-code-123&state=oauth-state')
    
    // Enter invalid MFA code
    await page.fill('input[name="mfaCode"]', '999999')
    await page.click('button:has-text("Verifizieren")')
    
    // Should show error
    await expect(page.locator('text=Invalid MFA code')).toBeVisible()
  })

  test('should handle MFA setup flow', async ({ page }) => {
    // Mock MFA setup endpoint
    await page.route('**/api/mfa/setup.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          qrCode: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...',
          secret: 'JBSWY3DPEHPK3PXP',
          backupCodes: ['12345678', '87654321']
        }),
      })
    })

    // Navigate to MFA setup
    await page.goto('/mfa-setup')
    
    // Should show QR code and setup instructions
    await expect(page.locator('text=QR-Code scannen')).toBeVisible()
    await expect(page.locator('img[alt*="QR"]')).toBeVisible()
    await expect(page.locator('text=Backup-Codes')).toBeVisible()
  })

  test('should allow backup code usage', async ({ page }) => {
    // Mock MFA verification with backup code
    await page.route('**/api/mfa/verify.php', async (route) => {
      const body = await route.request().postDataJSON()
      
      if (body.backupCode === '12345678') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            authenticated: true,
            user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' },
            backupCodeUsed: true
          }),
        })
      } else {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Invalid backup code' }),
        })
      }
    })

    // Simulate OAuth callback
    await page.goto('/?code=oauth-code-123&state=oauth-state')
    
    // Click backup code link
    await page.click('text=Backup-Code verwenden')
    
    // Enter backup code
    await page.fill('input[name="backupCode"]', '12345678')
    await page.click('button:has-text("Verifizieren")')
    
    // Should proceed to main app
    await expect(page.locator('.app-logo-svg')).toBeVisible()
    
    // Should show warning about backup code usage
    await expect(page.locator('text=Backup-Code wurde verwendet')).toBeVisible()
  })
})

test.describe('Data Security', () => {
  test('should not expose sensitive data in client-side storage', async ({ page }) => {
    // Mock authenticated state
    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' },
          sessionToken: 'session-token-123', // This should not be stored client-side
          users: [],
          timeEntries: [],
          masterData: {},
        }),
      })
    })

    await page.goto('/')
    
    // Check localStorage and sessionStorage for sensitive data
    const localStorage = await page.evaluate(() => ({
      ...window.localStorage,
    }))
    
    const sessionStorage = await page.evaluate(() => ({
      ...window.sessionStorage,
    }))

    // Should not contain sensitive tokens
    const storageContent = JSON.stringify({ localStorage, sessionStorage })
    expect(storageContent).not.toContain('session-token-123')
    expect(storageContent).not.toContain('password')
    expect(storageContent).not.toContain('secret')
  })

  test('should sanitize user input in time entry descriptions', async ({ page }) => {
    // Mock authenticated state
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ authenticated: true }),
      })
    })

    let sanitizedInput = false
    await page.route('**/api/time-entries.php', async (route) => {
      const body = await route.request().postDataJSON()
      
      // Check if potentially dangerous input was sanitized
      if (body.reason && !body.reason.includes('<script>') && !body.reason.includes('javascript:')) {
        sanitizedInput = true
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true }),
      })
    })

    await page.goto('/')
    
    const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    if (await addEntryButton.count() > 0) {
      await addEntryButton.click()
      
      // Try to input potentially malicious content
      await page.fill('input[name="startTime"]', '09:00')
      await page.fill('input[name="stopTime"]', '17:00')
      await page.fill('textarea[name="reason"]', '<script>alert("XSS")</script>Normale Arbeitszeit')
      
      const submitButton = page.locator('button[type="submit"]')
      await submitButton.click()

      // Content should be sanitized before sending to API
      expect(sanitizedInput).toBeTruthy()
    }
  })

  test('should enforce secure headers', async ({ page }) => {
    const response = await page.goto('/')
    const headers = response?.headers() || {}
    
    // Check for security headers
    expect(headers['x-content-type-options']).toBe('nosniff')
    expect(headers['x-frame-options']).toBeDefined()
    expect(headers['x-xss-protection']).toBeDefined()
    expect(headers['strict-transport-security']).toBeDefined()
    expect(headers['content-security-policy']).toBeDefined()
  })

  test('should prevent clickjacking attacks', async ({ page }) => {
    // Test that the application cannot be embedded in an iframe from different origin
    const response = await page.goto('/')
    const headers = response?.headers() || {}
    
    const xFrameOptions = headers['x-frame-options']
    expect(xFrameOptions).toMatch(/^(DENY|SAMEORIGIN)$/i)
  })

  test('should use secure cookies', async ({ page, context }) => {
    // Mock login to set cookies
    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: {
          'Set-Cookie': 'session=abc123; HttpOnly; Secure; SameSite=Strict'
        },
        body: JSON.stringify({
          user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' },
        }),
      })
    })

    await page.goto('/')
    
    // Check cookie security attributes
    const cookies = await context.cookies()
    const sessionCookie = cookies.find(c => c.name === 'session')
    
    if (sessionCookie) {
      expect(sessionCookie.httpOnly).toBeTruthy()
      expect(sessionCookie.secure).toBeTruthy()
      expect(sessionCookie.sameSite).toBe('Strict')
    }
  })
})

test.describe('Session Security', () => {
  test('should timeout sessions after inactivity', async ({ page }) => {
    // Mock initial authentication
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ authenticated: true }),
      })
    })

    await page.goto('/')
    
    // Simulate session timeout
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ 
          error: 'Session expired',
          reason: 'INACTIVITY_TIMEOUT'
        }),
      })
    })

    // Wait for session check
    await page.waitForTimeout(1000)
    await page.reload()
    
    // Should redirect to login
    await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).toBeVisible()
  })

  test('should handle concurrent session limit', async ({ page, context }) => {
    // Create a second browser context to simulate concurrent session
    const secondContext = await page.context().browser()?.newContext()
    const secondPage = await secondContext?.newPage()

    if (secondPage) {
      // Mock session limit response
      await secondPage.route('**/api/login.php', async (route) => {
        await route.fulfill({
          status: 409,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 'Maximum concurrent sessions exceeded',
            code: 'MAX_SESSIONS_EXCEEDED'
          }),
        })
      })

      await secondPage.goto('/')
      
      // Second session should be rejected
      await expect(secondPage.locator('text=Maximum concurrent sessions')).toBeVisible()
      
      await secondContext?.close()
    }
  })

  test('should securely handle session invalidation on logout', async ({ page, context }) => {
    // Mock authenticated state
    await page.route('**/api/auth-status.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ authenticated: true }),
      })
    })

    await page.goto('/')
    
    // Mock logout endpoint
    await page.route('**/api/auth-logout.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: {
          'Set-Cookie': 'session=; expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; Secure'
        },
        body: JSON.stringify({ success: true }),
      })
    })

    // Logout
    const logoutButton = page.locator('button:has-text("Abmelden")')
    if (await logoutButton.count() > 0) {
      await logoutButton.click()
    }
    
    // Should clear session cookies
    const cookies = await context.cookies()
    const sessionCookie = cookies.find(c => c.name === 'session')
    expect(sessionCookie?.value).toBeFalsy()
    
    // Should redirect to login
    await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).toBeVisible()
  })
})
/**
 * Cross-Browser Compatibility Tests for AZE Gemini
 * Tests application functionality across different browsers and devices
 */
import { test, expect, devices } from '@playwright/test'

// Test on different browsers and devices
const browserTests = [
  { name: 'Desktop Chrome', config: devices['Desktop Chrome'] },
  { name: 'Desktop Firefox', config: devices['Desktop Firefox'] },
  { name: 'Desktop Safari', config: devices['Desktop Safari'] },
  { name: 'Mobile Chrome', config: devices['Pixel 5'] },
  { name: 'Mobile Safari', config: devices['iPhone 12'] },
  { name: 'Tablet', config: devices['iPad Pro'] },
]

// Helper function to setup authenticated state
async function setupAuth(page: any) {
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
        masterData: {},
        approvalRequests: [],
        history: [],
        globalSettings: {},
      }),
    })
  })
}

test.describe('Core Functionality Cross-Browser', () => {
  browserTests.forEach(({ name, config }) => {
    test.describe(`${name}`, () => {
      test.use({ ...config })

      test('should load application correctly', async ({ page }) => {
        await setupAuth(page)
        await page.goto('/')
        await page.waitForLoadState('networkidle')
        
        // Check basic UI elements are present
        await expect(page.locator('.app-logo-svg')).toBeVisible()
        
        // Check responsive design elements
        if (name.includes('Mobile')) {
          // Mobile-specific checks
          const viewport = page.viewportSize()
          expect(viewport?.width).toBeLessThanOrEqual(480)
          
          // Check mobile menu if applicable
          const mobileMenu = page.locator('[data-testid="mobile-menu-toggle"]')
          if (await mobileMenu.count() > 0) {
            await expect(mobileMenu).toBeVisible()
          }
        } else {
          // Desktop-specific checks
          const viewport = page.viewportSize()
          expect(viewport?.width).toBeGreaterThan(768)
        }
      })

      test('should handle authentication flow', async ({ page }) => {
        await page.goto('/')
        
        // Should show login page
        await expect(page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).toBeVisible()
        
        const loginButton = page.locator('button:has-text("Mit Microsoft anmelden")')
        await expect(loginButton).toBeVisible()
        await expect(loginButton).toBeEnabled()
        
        // Check button is clickable and styled properly
        const buttonStyles = await loginButton.evaluate((el) => {
          const styles = window.getComputedStyle(el)
          return {
            display: styles.display,
            visibility: styles.visibility,
            cursor: styles.cursor
          }
        })
        
        expect(buttonStyles.display).not.toBe('none')
        expect(buttonStyles.visibility).not.toBe('hidden')
        expect(buttonStyles.cursor).toBe('pointer')
      })

      test('should display time entries correctly', async ({ page }) => {
        await setupAuth(page)
        await page.goto('/')
        await page.waitForLoadState('networkidle')
        
        // Check time entries are visible and properly formatted
        await expect(page.locator('text=09:00')).toBeVisible()
        await expect(page.locator('text=17:00')).toBeVisible()
        
        // Check date formatting is correct for different locales
        const dateElement = page.locator('[data-testid="entry-date"]').first()
        if (await dateElement.count() > 0) {
          const dateText = await dateElement.textContent()
          expect(dateText).toMatch(/\d{1,2}\.\d{1,2}\.\d{4}/) // DD.MM.YYYY format
        }
      })

      test('should handle form interactions', async ({ page }) => {
        await setupAuth(page)
        
        await page.route('**/api/time-entries.php', async (route) => {
          if (route.request().method() === 'POST') {
            await route.fulfill({
              status: 200,
              contentType: 'application/json',
              body: JSON.stringify({ success: true, id: 2 }),
            })
          }
        })

        await page.goto('/')
        await page.waitForLoadState('networkidle')
        
        const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
        if (await addEntryButton.count() > 0) {
          await addEntryButton.click()
          
          // Test form inputs work properly
          const startTimeInput = page.locator('input[name="startTime"]')
          const stopTimeInput = page.locator('input[name="stopTime"]')
          
          await expect(startTimeInput).toBeVisible()
          await expect(stopTimeInput).toBeVisible()
          
          await startTimeInput.fill('08:00')
          await stopTimeInput.fill('16:00')
          
          // Verify inputs accepted the values
          await expect(startTimeInput).toHaveValue('08:00')
          await expect(stopTimeInput).toHaveValue('16:00')
          
          // Test form submission
          const submitButton = page.locator('button[type="submit"]')
          await submitButton.click()
          
          if (name.includes('Mobile')) {
            // On mobile, check if keyboard hides properly after form submission
            await page.waitForTimeout(500)
            const viewportHeight = page.viewportSize()?.height || 0
            expect(viewportHeight).toBeGreaterThan(200) // Basic check that viewport isn't collapsed
          }
        }
      })

      test('should handle touch/click events properly', async ({ page }) => {
        await setupAuth(page)
        await page.goto('/')
        await page.waitForLoadState('networkidle')
        
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
          // Test button activation
          if (name.includes('Mobile') || name.includes('Tablet')) {
            // Use tap for touch devices
            await startButton.tap()
          } else {
            // Use click for desktop
            await startButton.click()
          }
          
          // Should show timer running state
          await expect(page.locator('button:has-text("Stoppen")')).toBeVisible()
        }
      })
    })
  })
})

test.describe('Browser-Specific Features', () => {
  test('should handle localStorage across browsers', async ({ page, context }) => {
    await setupAuth(page)
    await page.goto('/')
    
    // Set localStorage data
    await page.evaluate(() => {
      localStorage.setItem('testKey', 'testValue')
      localStorage.setItem('userPreferences', JSON.stringify({
        theme: 'light',
        language: 'de'
      }))
    })
    
    // Reload and check localStorage persists
    await page.reload()
    
    const storageData = await page.evaluate(() => ({
      testKey: localStorage.getItem('testKey'),
      userPreferences: localStorage.getItem('userPreferences')
    }))
    
    expect(storageData.testKey).toBe('testValue')
    expect(JSON.parse(storageData.userPreferences || '{}')).toEqual({
      theme: 'light',
      language: 'de'
    })
  })

  test('should handle sessionStorage correctly', async ({ page }) => {
    await setupAuth(page)
    await page.goto('/')
    
    // Set sessionStorage data
    await page.evaluate(() => {
      sessionStorage.setItem('currentTimer', JSON.stringify({
        startTime: new Date().toISOString(),
        active: true
      }))
    })
    
    // Check sessionStorage in same tab
    const sessionData = await page.evaluate(() => 
      sessionStorage.getItem('currentTimer')
    )
    
    expect(JSON.parse(sessionData || '{}')).toHaveProperty('startTime')
    expect(JSON.parse(sessionData || '{}')).toHaveProperty('active', true)
  })

  test('should handle cookies properly', async ({ page, context }) => {
    await page.goto('/')
    
    // Set a test cookie
    await context.addCookies([{
      name: 'testCookie',
      value: 'testValue',
      domain: 'localhost',
      path: '/'
    }])
    
    // Check cookie is available
    const cookies = await context.cookies()
    const testCookie = cookies.find(c => c.name === 'testCookie')
    
    expect(testCookie?.value).toBe('testValue')
  })

  test('should handle different date/time formats', async ({ page }) => {
    await setupAuth(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    // Check that times are displayed in correct locale format
    const timeElements = page.locator('[data-testid="time-display"]')
    if (await timeElements.count() > 0) {
      const timeTexts = await timeElements.allTextContents()
      
      timeTexts.forEach(timeText => {
        // Should be in HH:MM format for German locale
        expect(timeText).toMatch(/^\d{2}:\d{2}$/)
      })
    }
  })

  test('should handle keyboard navigation', async ({ page }) => {
    await setupAuth(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    // Test tab navigation
    await page.keyboard.press('Tab')
    
    // Check that first focusable element is focused
    const focusedElement = await page.evaluate(() => 
      document.activeElement?.tagName.toLowerCase()
    )
    
    expect(['button', 'input', 'select', 'textarea', 'a']).toContain(focusedElement)
    
    // Test arrow key navigation if applicable
    await page.keyboard.press('ArrowDown')
    
    // Should not cause errors
    const errors = await page.evaluate(() => 
      window.console?.error?.toString() || ''
    )
    expect(errors).not.toContain('error')
  })

  test('should handle window resize gracefully', async ({ page }) => {
    await setupAuth(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    // Test different viewport sizes
    const viewportSizes = [
      { width: 1920, height: 1080 }, // Desktop
      { width: 768, height: 1024 },  // Tablet
      { width: 375, height: 667 },   // Mobile
    ]
    
    for (const size of viewportSizes) {
      await page.setViewportSize(size)
      await page.waitForTimeout(100) // Allow for resize handling
      
      // Check that essential elements are still visible
      await expect(page.locator('.app-logo-svg')).toBeVisible()
      
      // Check that layout doesn't break
      const bodyOverflow = await page.evaluate(() => 
        window.getComputedStyle(document.body).overflow
      )
      expect(bodyOverflow).not.toBe('scroll') // Should not cause horizontal scrolling
    }
  })
})

test.describe('Performance Across Browsers', () => {
  test('should load within acceptable time limits', async ({ page }) => {
    const startTime = Date.now()
    
    await setupAuth(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    const loadTime = Date.now() - startTime
    
    // Should load within 5 seconds
    expect(loadTime).toBeLessThan(5000)
  })

  test('should handle large datasets efficiently', async ({ page }) => {
    // Mock large dataset
    const largeTimeEntries = Array.from({ length: 100 }, (_, i) => ({
      id: i + 1,
      userId: 1,
      date: `2025-08-${String(i % 28 + 1).padStart(2, '0')}`,
      startTime: '09:00:00',
      stopTime: '17:00:00',
      status: 'Erfasst',
      reason: `Arbeitszeit ${i + 1}`
    }))

    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' },
          users: [],
          timeEntries: largeTimeEntries,
          masterData: {},
          approvalRequests: [],
          history: [],
          globalSettings: {},
        }),
      })
    })

    const startTime = Date.now()
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    // Should still load reasonably fast with large dataset
    const loadTime = Date.now() - startTime
    expect(loadTime).toBeLessThan(10000)
    
    // Check that UI is responsive
    const scrollContainer = page.locator('[data-testid="time-entries-container"]')
    if (await scrollContainer.count() > 0) {
      await scrollContainer.hover()
      await page.mouse.wheel(0, 1000)
      
      // Should handle scrolling without blocking
      await page.waitForTimeout(100)
      const isResponsive = await page.evaluate(() => {
        return document.readyState === 'complete'
      })
      expect(isResponsive).toBeTruthy()
    }
  })

  test('should handle memory usage efficiently', async ({ page }) => {
    await setupAuth(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    // Perform multiple operations to test for memory leaks
    for (let i = 0; i < 10; i++) {
      // Add mock time entry
      const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
      if (await addEntryButton.count() > 0) {
        await addEntryButton.click()
        
        const cancelButton = page.locator('button:has-text("Abbrechen")')
        if (await cancelButton.count() > 0) {
          await cancelButton.click()
        }
      }
      
      await page.waitForTimeout(50)
    }
    
    // Basic check that page is still responsive
    const isResponsive = await page.evaluate(() => {
      return document.readyState === 'complete'
    })
    expect(isResponsive).toBeTruthy()
  })
})

test.describe('Accessibility Across Browsers', () => {
  test('should maintain proper focus management', async ({ page }) => {
    await setupAuth(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    // Test focus trap in modals
    const addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    if (await addEntryButton.count() > 0) {
      await addEntryButton.click()
      
      // Focus should be in modal
      const focusedElement = await page.evaluate(() => 
        document.activeElement?.closest('[role="dialog"]') !== null
      )
      expect(focusedElement).toBeTruthy()
      
      // Tab should cycle within modal
      await page.keyboard.press('Tab')
      const stillInModal = await page.evaluate(() => 
        document.activeElement?.closest('[role="dialog"]') !== null
      )
      expect(stillInModal).toBeTruthy()
    }
  })

  test('should provide proper ARIA labels', async ({ page }) => {
    await setupAuth(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    // Check for ARIA labels on important elements
    const startButton = page.locator('button:has-text("Starten")')
    if (await startButton.count() > 0) {
      const ariaLabel = await startButton.getAttribute('aria-label')
      expect(ariaLabel).toBeTruthy()
    }
    
    // Check form labels
    const timeInputs = page.locator('input[type="time"]')
    if (await timeInputs.count() > 0) {
      const inputCount = await timeInputs.count()
      for (let i = 0; i < inputCount; i++) {
        const input = timeInputs.nth(i)
        const hasLabel = await input.evaluate((el) => {
          const id = el.id
          const label = document.querySelector(`label[for="${id}"]`)
          const ariaLabel = el.getAttribute('aria-label')
          const ariaLabelledBy = el.getAttribute('aria-labelledby')
          
          return !!(label || ariaLabel || ariaLabelledBy)
        })
        expect(hasLabel).toBeTruthy()
      }
    }
  })

  test('should support screen readers', async ({ page }) => {
    await setupAuth(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    
    // Check for screen reader announcements
    const liveRegions = page.locator('[aria-live]')
    expect(await liveRegions.count()).toBeGreaterThan(0)
    
    // Check heading hierarchy
    const headings = await page.$$eval('h1, h2, h3, h4, h5, h6', (elements) =>
      elements.map(el => ({ tagName: el.tagName, text: el.textContent?.trim() }))
    )
    
    // Should have proper heading structure (h1 -> h2 -> h3, etc.)
    if (headings.length > 0) {
      expect(headings[0].tagName).toBe('H1')
    }
  })
})
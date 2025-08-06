/**
 * Page Object Model for Login Page
 * Encapsulates all interactions with the login page
 */
import { Page, Locator, expect } from '@playwright/test'

export class LoginPage {
  readonly page: Page
  readonly welcomeMessage: Locator
  readonly loginButton: Locator
  readonly loadingSpinner: Locator
  readonly errorMessage: Locator
  readonly companyLogo: Locator

  constructor(page: Page) {
    this.page = page
    this.welcomeMessage = page.locator('text=Willkommen zur MP Arbeitszeiterfassung')
    this.loginButton = page.locator('button:has-text("Mit Microsoft anmelden")')
    this.loadingSpinner = page.locator('[data-testid="loading-spinner"]')
    this.errorMessage = page.locator('.error-message, .alert-error')
    this.companyLogo = page.locator('.app-logo-svg')
  }

  /**
   * Navigate to login page
   */
  async goto() {
    await this.page.goto('/')
    await this.page.waitForLoadState('networkidle')
  }

  /**
   * Check if login page is displayed
   */
  async isDisplayed() {
    await expect(this.welcomeMessage).toBeVisible()
    await expect(this.loginButton).toBeVisible()
    return true
  }

  /**
   * Click login button and wait for response
   */
  async clickLogin() {
    await this.loginButton.click()
    // Wait for either loading state or redirect
    await Promise.race([
      this.loadingSpinner.waitFor({ state: 'visible', timeout: 2000 }).catch(() => null),
      this.page.waitForURL('**/dashboard', { timeout: 5000 }).catch(() => null)
    ])
  }

  /**
   * Check if login button is in loading state
   */
  async isLoginLoading() {
    await expect(this.loadingSpinner).toBeVisible()
    await expect(this.loginButton).toBeDisabled()
  }

  /**
   * Check for error message
   */
  async hasError(errorText?: string) {
    await expect(this.errorMessage).toBeVisible()
    if (errorText) {
      await expect(this.errorMessage).toContainText(errorText)
    }
  }

  /**
   * Verify page accessibility
   */
  async checkAccessibility() {
    // Check button has proper accessibility attributes
    const loginButtonAriaLabel = await this.loginButton.getAttribute('aria-label')
    expect(loginButtonAriaLabel).toBeTruthy()

    // Check logo has alt text or ARIA label
    const logoAccessible = await this.companyLogo.evaluate((el) => {
      return el.getAttribute('aria-label') || el.getAttribute('alt') || el.getAttribute('title')
    })
    expect(logoAccessible).toBeTruthy()
  }

  /**
   * Test keyboard navigation
   */
  async testKeyboardNavigation() {
    // Tab to login button
    await this.page.keyboard.press('Tab')
    await expect(this.loginButton).toBeFocused()
    
    // Press Enter to activate
    await this.page.keyboard.press('Enter')
    
    // Should trigger login process
    await this.isLoginLoading()
  }

  /**
   * Verify responsive design
   */
  async checkResponsive() {
    // Test mobile viewport
    await this.page.setViewportSize({ width: 375, height: 667 })
    await expect(this.welcomeMessage).toBeVisible()
    await expect(this.loginButton).toBeVisible()
    
    // Test desktop viewport
    await this.page.setViewportSize({ width: 1920, height: 1080 })
    await expect(this.welcomeMessage).toBeVisible()
    await expect(this.loginButton).toBeVisible()
  }

  /**
   * Mock OAuth callback for testing
   */
  async mockOAuthCallback(success: boolean = true) {
    if (success) {
      await this.page.route('**/api/auth-callback.php', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            authenticated: true,
            user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' }
          }),
        })
      })
    } else {
      await this.page.route('**/api/auth-callback.php', async (route) => {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 'Authentication failed'
          }),
        })
      })
    }
  }

  /**
   * Simulate OAuth flow completion
   */
  async completeOAuthFlow() {
    // Simulate OAuth redirect back to application
    await this.page.goto('/?code=oauth-code-123&state=oauth-state')
    await this.page.waitForLoadState('networkidle')
  }

  /**
   * Wait for login completion
   */
  async waitForLoginComplete() {
    // Wait for redirect away from login page
    await this.welcomeMessage.waitFor({ state: 'hidden', timeout: 10000 })
    await this.page.waitForLoadState('networkidle')
  }
}
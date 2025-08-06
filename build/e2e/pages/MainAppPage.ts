/**
 * Page Object Model for Main Application Page
 * Encapsulates interactions with the main application interface after login
 */
import { Page, Locator, expect } from '@playwright/test'

export class MainAppPage {
  readonly page: Page
  readonly logo: Locator
  readonly userProfile: Locator
  readonly logoutButton: Locator
  readonly navigationMenu: Locator
  
  // Navigation tabs
  readonly timesheetTab: Locator
  readonly approvalsTab: Locator
  readonly historyTab: Locator
  readonly settingsTab: Locator
  
  // Timer controls
  readonly startTimerButton: Locator
  readonly stopTimerButton: Locator
  readonly timerDisplay: Locator
  
  // Time entry management
  readonly addEntryButton: Locator
  readonly timeEntriesContainer: Locator
  readonly timeEntryRows: Locator
  
  // Notifications
  readonly notificationArea: Locator
  readonly errorNotification: Locator
  readonly successNotification: Locator

  constructor(page: Page) {
    this.page = page
    this.logo = page.locator('.app-logo-svg')
    this.userProfile = page.locator('.user-profile, .user-name')
    this.logoutButton = page.locator('button:has-text("Abmelden")')
    this.navigationMenu = page.locator('.navigation-menu, nav')
    
    // Navigation
    this.timesheetTab = page.locator('button:has-text("Zeiterfassung")')
    this.approvalsTab = page.locator('button:has-text("Genehmigungen")')
    this.historyTab = page.locator('button:has-text("Verlauf")')
    this.settingsTab = page.locator('button:has-text("Einstellungen")')
    
    // Timer
    this.startTimerButton = page.locator('button:has-text("Starten")')
    this.stopTimerButton = page.locator('button:has-text("Stoppen")')
    this.timerDisplay = page.locator('[data-testid="timer-display"]')
    
    // Time entries
    this.addEntryButton = page.locator('button:has-text("Eintrag hinzufügen")')
    this.timeEntriesContainer = page.locator('[data-testid="time-entries-container"]')
    this.timeEntryRows = page.locator('.time-entry-row, tr')
    
    // Notifications
    this.notificationArea = page.locator('.notification-area, .toast-container')
    this.errorNotification = page.locator('.error-notification, .toast-error')
    this.successNotification = page.locator('.success-notification, .toast-success')
  }

  /**
   * Navigate to main app and verify it's loaded
   */
  async goto() {
    await this.page.goto('/')
    await this.waitForLoad()
  }

  /**
   * Wait for main app to be fully loaded
   */
  async waitForLoad() {
    await this.page.waitForLoadState('networkidle')
    await expect(this.logo).toBeVisible()
  }

  /**
   * Check if user is logged in
   */
  async isLoggedIn() {
    await expect(this.logo).toBeVisible()
    // Should not see login welcome message
    await expect(this.page.locator('text=Willkommen zur MP Arbeitszeiterfassung')).not.toBeVisible()
    return true
  }

  /**
   * Get current user information
   */
  async getCurrentUser() {
    if (await this.userProfile.count() > 0) {
      return await this.userProfile.textContent()
    }
    return null
  }

  /**
   * Navigate to different sections
   */
  async navigateToTimesheet() {
    if (await this.timesheetTab.count() > 0) {
      await this.timesheetTab.click()
      await this.page.waitForLoadState('networkidle')
    }
  }

  async navigateToApprovals() {
    if (await this.approvalsTab.count() > 0) {
      await this.approvalsTab.click()
      await this.page.waitForLoadState('networkidle')
    }
  }

  async navigateToHistory() {
    if (await this.historyTab.count() > 0) {
      await this.historyTab.click()
      await this.page.waitForLoadState('networkidle')
    }
  }

  async navigateToSettings() {
    if (await this.settingsTab.count() > 0) {
      await this.settingsTab.click()
      await this.page.waitForLoadState('networkidle')
    }
  }

  /**
   * Timer operations
   */
  async startTimer() {
    if (await this.startTimerButton.count() > 0) {
      await this.startTimerButton.click()
      // Wait for timer to start
      await expect(this.stopTimerButton).toBeVisible()
      if (await this.timerDisplay.count() > 0) {
        await expect(this.timerDisplay).toBeVisible()
      }
    }
  }

  async stopTimer() {
    if (await this.stopTimerButton.count() > 0) {
      await this.stopTimerButton.click()
      // Wait for timer to stop
      await expect(this.startTimerButton).toBeVisible()
    }
  }

  async getTimerValue() {
    if (await this.timerDisplay.count() > 0) {
      return await this.timerDisplay.textContent()
    }
    return null
  }

  async isTimerRunning() {
    return await this.stopTimerButton.isVisible()
  }

  /**
   * Time entry operations
   */
  async addTimeEntry() {
    if (await this.addEntryButton.count() > 0) {
      await this.addEntryButton.click()
      // Wait for modal or form to appear
      await this.page.waitForTimeout(500)
    }
  }

  async getTimeEntries() {
    const entries = []
    const rows = await this.timeEntryRows.all()
    
    for (const row of rows) {
      const cells = await row.locator('td, .cell').all()
      if (cells.length > 0) {
        const entry = {
          date: await cells[0]?.textContent() || '',
          startTime: await cells[1]?.textContent() || '',
          stopTime: await cells[2]?.textContent() || '',
          reason: await cells[3]?.textContent() || '',
          status: await cells[4]?.textContent() || ''
        }
        entries.push(entry)
      }
    }
    
    return entries
  }

  async editTimeEntry(index: number = 0) {
    const editButtons = this.page.locator('button:has-text("Bearbeiten")')
    if (await editButtons.count() > index) {
      await editButtons.nth(index).click()
      await this.page.waitForTimeout(500)
    }
  }

  async deleteTimeEntry(index: number = 0) {
    const deleteButtons = this.page.locator('button:has-text("Löschen")')
    if (await deleteButtons.count() > index) {
      await deleteButtons.nth(index).click()
      // Wait for confirmation modal
      await this.page.waitForTimeout(500)
      
      const confirmButton = this.page.locator('button:has-text("Bestätigen")')
      if (await confirmButton.count() > 0) {
        await confirmButton.click()
      }
    }
  }

  /**
   * Notification handling
   */
  async waitForSuccessMessage(message?: string) {
    await expect(this.successNotification).toBeVisible()
    if (message) {
      await expect(this.successNotification).toContainText(message)
    }
  }

  async waitForErrorMessage(message?: string) {
    await expect(this.errorNotification).toBeVisible()
    if (message) {
      await expect(this.errorNotification).toContainText(message)
    }
  }

  async hasNotification(type: 'success' | 'error', message?: string) {
    const notification = type === 'success' ? this.successNotification : this.errorNotification
    await expect(notification).toBeVisible()
    
    if (message) {
      await expect(notification).toContainText(message)
    }
    
    return true
  }

  /**
   * Export operations
   */
  async exportToPDF() {
    const pdfExportButton = this.page.locator('button:has-text("PDF Export")')
    if (await pdfExportButton.count() > 0) {
      const downloadPromise = this.page.waitForEvent('download')
      await pdfExportButton.click()
      return await downloadPromise
    }
    return null
  }

  async exportToCSV() {
    const csvExportButton = this.page.locator('button:has-text("CSV Export")')
    if (await csvExportButton.count() > 0) {
      const downloadPromise = this.page.waitForEvent('download')
      await csvExportButton.click()
      return await downloadPromise
    }
    return null
  }

  async exportToExcel() {
    const excelExportButton = this.page.locator('button:has-text("Excel Export")')
    if (await excelExportButton.count() > 0) {
      const downloadPromise = this.page.waitForEvent('download')
      await excelExportButton.click()
      return await downloadPromise
    }
    return null
  }

  /**
   * Logout functionality
   */
  async logout() {
    if (await this.logoutButton.count() > 0) {
      await this.logoutButton.click()
      // Wait for redirect to login page
      await this.page.waitForLoadState('networkidle')
    }
  }

  /**
   * Check responsive design
   */
  async checkMobileLayout() {
    await this.page.setViewportSize({ width: 375, height: 667 })
    
    // Mobile menu toggle should be visible
    const mobileMenuToggle = this.page.locator('[data-testid="mobile-menu-toggle"]')
    if (await mobileMenuToggle.count() > 0) {
      await expect(mobileMenuToggle).toBeVisible()
    }
    
    // Main content should be responsive
    await expect(this.logo).toBeVisible()
  }

  async checkTabletLayout() {
    await this.page.setViewportSize({ width: 768, height: 1024 })
    
    // Elements should still be visible and accessible
    await expect(this.logo).toBeVisible()
    if (await this.navigationMenu.count() > 0) {
      await expect(this.navigationMenu).toBeVisible()
    }
  }

  async checkDesktopLayout() {
    await this.page.setViewportSize({ width: 1920, height: 1080 })
    
    // Full desktop layout should be visible
    await expect(this.logo).toBeVisible()
    if (await this.navigationMenu.count() > 0) {
      await expect(this.navigationMenu).toBeVisible()
    }
  }

  /**
   * Accessibility checks
   */
  async checkAccessibility() {
    // Check that interactive elements have proper ARIA labels
    const interactiveElements = await this.page.locator('button, input, select, textarea').all()
    
    for (const element of interactiveElements) {
      const hasLabel = await element.evaluate((el: HTMLElement) => {
        const ariaLabel = el.getAttribute('aria-label')
        const ariaLabelledBy = el.getAttribute('aria-labelledby')
        const id = el.id
        const associatedLabel = id ? document.querySelector(`label[for="${id}"]`) : null
        
        return !!(ariaLabel || ariaLabelledBy || associatedLabel)
      })
      
      if (!hasLabel) {
        const tagName = await element.evaluate(el => el.tagName)
        const text = await element.textContent()
        console.warn(`Element ${tagName} without proper label: "${text}"`)
      }
    }
  }

  /**
   * Test keyboard navigation
   */
  async testKeyboardNavigation() {
    // Start from first focusable element
    await this.page.keyboard.press('Tab')
    
    // Navigate through several elements
    for (let i = 0; i < 5; i++) {
      const focusedElement = await this.page.evaluate(() => {
        const active = document.activeElement
        return active ? {
          tagName: active.tagName,
          className: active.className,
          id: active.id,
          text: active.textContent?.trim().substring(0, 50)
        } : null
      })
      
      expect(focusedElement).toBeTruthy()
      await this.page.keyboard.press('Tab')
    }
  }

  /**
   * Check for JavaScript errors
   */
  async hasJavaScriptErrors() {
    const errors: string[] = []
    
    this.page.on('pageerror', error => {
      errors.push(error.message)
    })
    
    this.page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text())
      }
    })
    
    // Wait a bit for any errors to surface
    await this.page.waitForTimeout(1000)
    
    return errors
  }

  /**
   * Wait for loading states to complete
   */
  async waitForAllLoading() {
    // Wait for any loading spinners to disappear
    const loadingSpinners = this.page.locator('[data-testid*="loading"], .loading, .spinner')
    if (await loadingSpinners.count() > 0) {
      await loadingSpinners.first().waitFor({ state: 'hidden', timeout: 10000 })
    }
    
    await this.page.waitForLoadState('networkidle')
  }
}
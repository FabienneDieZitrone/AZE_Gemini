/**
 * Page Object Model for Time Entry Modal/Form
 * Encapsulates interactions with time entry creation and editing forms
 */
import { Page, Locator, expect } from '@playwright/test'

export class TimeEntryModal {
  readonly page: Page
  readonly modal: Locator
  readonly modalTitle: Locator
  readonly closeButton: Locator
  
  // Form fields
  readonly dateInput: Locator
  readonly startTimeInput: Locator
  readonly stopTimeInput: Locator
  readonly reasonTextarea: Locator
  readonly statusSelect: Locator
  
  // Form buttons
  readonly saveButton: Locator
  readonly cancelButton: Locator
  readonly deleteButton: Locator
  
  // Validation messages
  readonly validationError: Locator
  readonly fieldErrors: Locator

  constructor(page: Page) {
    this.page = page
    this.modal = page.locator('[role="dialog"], .modal, .time-entry-modal')
    this.modalTitle = page.locator('.modal-title, h2, h3')
    this.closeButton = page.locator('button[aria-label="Close"], .modal-close, button:has-text("×")')
    
    // Form fields
    this.dateInput = page.locator('input[name="date"], input[type="date"]')
    this.startTimeInput = page.locator('input[name="startTime"], input[name="start"], input[type="time"]').first()
    this.stopTimeInput = page.locator('input[name="stopTime"], input[name="stop"], input[type="time"]').last()
    this.reasonTextarea = page.locator('textarea[name="reason"], input[name="reason"]')
    this.statusSelect = page.locator('select[name="status"]')
    
    // Buttons
    this.saveButton = page.locator('button[type="submit"], button:has-text("Speichern")')
    this.cancelButton = page.locator('button:has-text("Abbrechen")')
    this.deleteButton = page.locator('button:has-text("Löschen")')
    
    // Validation
    this.validationError = page.locator('.error-message, .validation-error, .field-error')
    this.fieldErrors = page.locator('.field-error, .input-error')
  }

  /**
   * Wait for modal to appear
   */
  async waitForModal() {
    await expect(this.modal).toBeVisible()
    await this.page.waitForTimeout(300) // Allow for animation
  }

  /**
   * Check if modal is displayed
   */
  async isDisplayed() {
    return await this.modal.isVisible()
  }

  /**
   * Get modal title
   */
  async getTitle() {
    if (await this.modalTitle.count() > 0) {
      return await this.modalTitle.textContent()
    }
    return null
  }

  /**
   * Fill all form fields
   */
  async fillForm(data: {
    date?: string
    startTime: string
    stopTime: string
    reason?: string
    status?: string
  }) {
    // Fill date if provided and field exists
    if (data.date && await this.dateInput.count() > 0) {
      await this.dateInput.fill(data.date)
    }

    // Fill required time fields
    await this.startTimeInput.fill(data.startTime)
    await this.stopTimeInput.fill(data.stopTime)

    // Fill optional reason
    if (data.reason && await this.reasonTextarea.count() > 0) {
      await this.reasonTextarea.fill(data.reason)
    }

    // Select status if provided and field exists
    if (data.status && await this.statusSelect.count() > 0) {
      await this.statusSelect.selectOption(data.status)
    }
  }

  /**
   * Fill individual form fields
   */
  async fillDate(date: string) {
    if (await this.dateInput.count() > 0) {
      await this.dateInput.fill(date)
    }
  }

  async fillStartTime(time: string) {
    await this.startTimeInput.fill(time)
  }

  async fillStopTime(time: string) {
    await this.stopTimeInput.fill(time)
  }

  async fillReason(reason: string) {
    if (await this.reasonTextarea.count() > 0) {
      await this.reasonTextarea.fill(reason)
    }
  }

  async selectStatus(status: string) {
    if (await this.statusSelect.count() > 0) {
      await this.statusSelect.selectOption(status)
    }
  }

  /**
   * Get current form values
   */
  async getFormValues() {
    const values: any = {
      startTime: await this.startTimeInput.inputValue(),
      stopTime: await this.stopTimeInput.inputValue()
    }

    if (await this.dateInput.count() > 0) {
      values.date = await this.dateInput.inputValue()
    }

    if (await this.reasonTextarea.count() > 0) {
      values.reason = await this.reasonTextarea.inputValue()
    }

    if (await this.statusSelect.count() > 0) {
      values.status = await this.statusSelect.inputValue()
    }

    return values
  }

  /**
   * Form submission
   */
  async save() {
    await this.saveButton.click()
    // Wait for modal to close or validation to appear
    await Promise.race([
      this.modal.waitFor({ state: 'hidden', timeout: 5000 }),
      this.validationError.waitFor({ state: 'visible', timeout: 2000 }).catch(() => null)
    ])
  }

  async cancel() {
    await this.cancelButton.click()
    await this.modal.waitFor({ state: 'hidden' })
  }

  async delete() {
    if (await this.deleteButton.count() > 0) {
      await this.deleteButton.click()
      // Wait for confirmation dialog
      await this.page.waitForTimeout(500)
      
      // Confirm deletion if confirmation dialog appears
      const confirmButton = this.page.locator('button:has-text("Bestätigen"), button:has-text("Löschen")')
      if (await confirmButton.count() > 0) {
        await confirmButton.click()
      }
      
      await this.modal.waitFor({ state: 'hidden' })
    }
  }

  async close() {
    if (await this.closeButton.count() > 0) {
      await this.closeButton.click()
    } else {
      // Try ESC key as fallback
      await this.page.keyboard.press('Escape')
    }
    await this.modal.waitFor({ state: 'hidden' })
  }

  /**
   * Validation checks
   */
  async hasValidationError(message?: string) {
    await expect(this.validationError).toBeVisible()
    if (message) {
      await expect(this.validationError).toContainText(message)
    }
    return true
  }

  async hasFieldError(field: 'date' | 'startTime' | 'stopTime' | 'reason', message?: string) {
    const fieldSelectors = {
      date: this.dateInput,
      startTime: this.startTimeInput,
      stopTime: this.stopTimeInput,
      reason: this.reasonTextarea
    }

    const fieldElement = fieldSelectors[field]
    const fieldError = this.page.locator('.field-error').filter({ has: fieldElement })
    
    await expect(fieldError).toBeVisible()
    if (message) {
      await expect(fieldError).toContainText(message)
    }
    return true
  }

  async getValidationErrors() {
    const errors = []
    const errorElements = await this.fieldErrors.all()
    
    for (const error of errorElements) {
      const text = await error.textContent()
      if (text) {
        errors.push(text.trim())
      }
    }
    
    return errors
  }

  /**
   * Form validation tests
   */
  async testRequiredFields() {
    // Try to submit empty form
    await this.saveButton.click()
    
    // Should show validation errors for required fields
    const errors = await this.getValidationErrors()
    expect(errors.length).toBeGreaterThan(0)
  }

  async testTimeValidation() {
    // Test invalid time format
    await this.fillStartTime('25:00') // Invalid hour
    await this.fillStopTime('17:00')
    await this.saveButton.click()
    
    await this.hasValidationError()
  }

  async testTimeLogic() {
    // Test end time before start time
    await this.fillStartTime('17:00')
    await this.fillStopTime('09:00')
    await this.saveButton.click()
    
    await this.hasValidationError('Endzeit muss nach Startzeit liegen')
  }

  /**
   * Accessibility tests
   */
  async checkAccessibility() {
    // Check modal has proper ARIA attributes
    const modalRole = await this.modal.getAttribute('role')
    expect(modalRole).toBe('dialog')

    // Check form labels
    const inputs = [this.dateInput, this.startTimeInput, this.stopTimeInput, this.reasonTextarea]
    
    for (const input of inputs) {
      if (await input.count() > 0) {
        const hasLabel = await input.evaluate((el: HTMLElement) => {
          const id = el.id
          const ariaLabel = el.getAttribute('aria-label')
          const ariaLabelledBy = el.getAttribute('aria-labelledby')
          const associatedLabel = id ? document.querySelector(`label[for="${id}"]`) : null
          
          return !!(ariaLabel || ariaLabelledBy || associatedLabel)
        })
        expect(hasLabel).toBeTruthy()
      }
    }
  }

  /**
   * Focus management
   */
  async checkFocusTrap() {
    // Focus should be within modal
    await this.startTimeInput.focus()
    
    // Tab through elements - focus should stay in modal
    const focusableElements = await this.modal.locator('button, input, select, textarea').count()
    
    for (let i = 0; i < focusableElements + 1; i++) {
      await this.page.keyboard.press('Tab')
      
      const focusedElement = await this.page.evaluate(() => {
        return document.activeElement?.closest('[role="dialog"]') !== null
      })
      
      expect(focusedElement).toBeTruthy()
    }
  }

  /**
   * Keyboard navigation
   */
  async testKeyboardNavigation() {
    // ESC should close modal
    await this.page.keyboard.press('Escape')
    await this.modal.waitFor({ state: 'hidden' })
  }

  async testFormKeyboardInteraction() {
    // Enter in text field should not submit form
    await this.reasonTextarea.focus()
    await this.page.keyboard.press('Enter')
    
    // Modal should still be visible
    await expect(this.modal).toBeVisible()

    // Tab navigation should work properly
    await this.startTimeInput.focus()
    await this.page.keyboard.press('Tab')
    await expect(this.stopTimeInput).toBeFocused()
  }

  /**
   * Data persistence
   */
  async testDataPersistence() {
    const testData = {
      startTime: '09:00',
      stopTime: '17:00',
      reason: 'Test entry data persistence'
    }

    // Fill form
    await this.fillForm(testData)
    
    // Close and reopen modal (if this is an edit scenario)
    await this.cancel()
    
    // Reopen modal and check if data persists (implementation dependent)
    // This would be specific to how your application handles draft data
  }

  /**
   * Responsive behavior
   */
  async checkMobileLayout() {
    await this.page.setViewportSize({ width: 375, height: 667 })
    
    // Modal should still be properly sized and accessible on mobile
    await expect(this.modal).toBeVisible()
    
    // Form fields should be accessible
    await expect(this.startTimeInput).toBeVisible()
    await expect(this.stopTimeInput).toBeVisible()
    
    // Buttons should be accessible
    await expect(this.saveButton).toBeVisible()
    await expect(this.cancelButton).toBeVisible()
  }

  /**
   * Animation handling
   */
  async waitForAnimations() {
    // Wait for any CSS animations to complete
    await this.page.waitForTimeout(500)
    
    // Check if modal is fully visible
    const isVisible = await this.modal.isVisible()
    const opacity = await this.modal.evaluate((el: HTMLElement) => {
      return window.getComputedStyle(el).opacity
    })
    
    expect(isVisible).toBeTruthy()
    expect(opacity).toBe('1')
  }

  /**
   * Auto-save functionality (if implemented)
   */
  async testAutoSave() {
    // Fill some data and wait
    await this.fillStartTime('09:00')
    await this.page.waitForTimeout(2000) // Wait for auto-save trigger
    
    // Check if data is auto-saved (implementation dependent)
    // This would need to be implemented based on your auto-save logic
  }
}
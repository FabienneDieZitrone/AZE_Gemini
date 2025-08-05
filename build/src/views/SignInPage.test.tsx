/**
 * Unit tests for SignInPage component
 * Tests authentication UI and login flow
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, fireEvent } from '@testing-library/react'
import { renderWithProviders, userEvent } from '../test/utils'
import { SignInPage } from './SignInPage'

// Mock window.location
const mockLocation = {
  href: '',
}

Object.defineProperty(window, 'location', {
  value: mockLocation,
  writable: true,
})

describe('SignInPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockLocation.href = ''
  })

  it('should render welcome message and login button', () => {
    renderWithProviders(<SignInPage />)
    
    expect(screen.getByText('Willkommen zur MP Arbeitszeiterfassung')).toBeInTheDocument()
    expect(screen.getByText('Bitte melden Sie sich an, um fortzufahren.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /mit microsoft anmelden/i })).toBeInTheDocument()
  })

  it('should render logo', () => {
    renderWithProviders(<SignInPage />)
    
    // Assuming Logo component has a testable element
    expect(screen.getByRole('img')).toBeInTheDocument()
  })

  it('should redirect to auth endpoint when login button is clicked', async () => {
    const user = userEvent.setup()
    renderWithProviders(<SignInPage />)
    
    const loginButton = screen.getByRole('button', { name: /mit microsoft anmelden/i })
    await user.click(loginButton)
    
    expect(window.location.href).toBe('/api/auth-start.php')
  })

  it('should show loading spinner and disable button during login', async () => {
    const user = userEvent.setup()
    renderWithProviders(<SignInPage />)
    
    const loginButton = screen.getByRole('button', { name: /mit microsoft anmelden/i })
    
    // Click the button
    await user.click(loginButton)
    
    // Button should be disabled
    expect(loginButton).toBeDisabled()
    
    // Should show loading spinner instead of text
    expect(screen.getByTestId('loading-spinner')).toBeInTheDocument()
  })

  it('should prevent multiple login attempts', async () => {
    const user = userEvent.setup()
    renderWithProviders(<SignInPage />)
    
    const loginButton = screen.getByRole('button', { name: /mit microsoft anmelden/i })
    
    // First click
    await user.click(loginButton)
    expect(window.location.href).toBe('/api/auth-start.php')
    
    // Reset to simulate that redirect didn't happen immediately
    mockLocation.href = ''
    
    // Second click should not work (button is disabled)
    await user.click(loginButton)
    expect(window.location.href).toBe('')
  })

  it('should have proper accessibility attributes', () => {
    renderWithProviders(<SignInPage />)
    
    const loginButton = screen.getByRole('button', { name: /mit microsoft anmelden/i })
    expect(loginButton).toHaveClass('action-button', 'login-button')
  })

  it('should handle keyboard navigation', async () => {
    const user = userEvent.setup()
    renderWithProviders(<SignInPage />)
    
    const loginButton = screen.getByRole('button', { name: /mit microsoft anmelden/i })
    
    // Focus the button
    await user.tab()
    expect(loginButton).toHaveFocus()
    
    // Press Enter
    await user.keyboard('{Enter}')
    expect(window.location.href).toBe('/api/auth-start.php')
  })
})
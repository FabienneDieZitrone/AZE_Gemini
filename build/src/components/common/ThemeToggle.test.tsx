/**
 * Unit tests for ThemeToggle component
 */
import { describe, it, expect, vi } from 'vitest'
import { screen } from '@testing-library/react'
import { renderWithProviders, userEvent } from '../../test/utils'
import { ThemeToggle } from './ThemeToggle'
import type { Theme } from '../../types'

describe('ThemeToggle', () => {
  const mockToggleTheme = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('should render dark mode toggle', () => {
    renderWithProviders(<ThemeToggle theme="light" toggleTheme={mockToggleTheme} />)
    
    expect(screen.getByLabelText(/dark mode umschalten/i)).toBeInTheDocument()
    expect(screen.getByText('Dark Mode')).toBeInTheDocument()
  })

  it('should show unchecked state for light theme', () => {
    renderWithProviders(<ThemeToggle theme="light" toggleTheme={mockToggleTheme} />)
    
    const checkbox = screen.getByRole('checkbox')
    expect(checkbox).not.toBeChecked()
  })

  it('should show checked state for dark theme', () => {
    renderWithProviders(<ThemeToggle theme="dark" toggleTheme={mockToggleTheme} />)
    
    const checkbox = screen.getByRole('checkbox')
    expect(checkbox).toBeChecked()
  })

  it('should call toggleTheme when clicked', async () => {
    const user = userEvent.setup()
    renderWithProviders(<ThemeToggle theme="light" toggleTheme={mockToggleTheme} />)
    
    const checkbox = screen.getByRole('checkbox')
    await user.click(checkbox)
    
    expect(mockToggleTheme).toHaveBeenCalledTimes(1)
  })

  it('should have proper accessibility attributes', () => {
    renderWithProviders(<ThemeToggle theme="light" toggleTheme={mockToggleTheme} />)
    
    const checkbox = screen.getByRole('checkbox')
    expect(checkbox).toHaveAttribute('id', 'theme-switch')
    
    const label = screen.getByLabelText(/dark mode umschalten/i)
    expect(label).toBeInTheDocument()
  })

  it('should handle keyboard navigation', async () => {
    const user = userEvent.setup()
    renderWithProviders(<ThemeToggle theme="light" toggleTheme={mockToggleTheme} />)
    
    const checkbox = screen.getByRole('checkbox')
    
    // Focus the checkbox
    await user.tab()
    expect(checkbox).toHaveFocus()
    
    // Press space to toggle
    await user.keyboard(' ')
    expect(mockToggleTheme).toHaveBeenCalledTimes(1)
  })

  it('should have proper CSS classes', () => {
    renderWithProviders(<ThemeToggle theme="light" toggleTheme={mockToggleTheme} />)
    
    expect(document.querySelector('.theme-toggle')).toBeInTheDocument()
    expect(document.querySelector('.toggle-switch-background')).toBeInTheDocument()
    expect(document.querySelector('.slider.round')).toBeInTheDocument()
  })

  it('should work with both theme values', () => {
    const themes: Theme[] = ['light', 'dark']
    
    themes.forEach(theme => {
      const { rerender } = renderWithProviders(<ThemeToggle theme={theme} toggleTheme={mockToggleTheme} />)
      
      const checkbox = screen.getByRole('checkbox')
      expect(checkbox.checked).toBe(theme === 'dark')
      
      rerender(<></>)
    })
  })
})
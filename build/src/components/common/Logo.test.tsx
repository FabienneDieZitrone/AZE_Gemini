/**
 * Unit tests for Logo component
 */
import { describe, it, expect } from 'vitest'
import { screen } from '@testing-library/react'
import { renderWithProviders } from '../../test/utils'
import { Logo } from './Logo'

describe('Logo', () => {
  it('should render SVG logo', () => {
    renderWithProviders(<Logo />)
    
    const logo = screen.getByRole('img', { hidden: true })
    expect(logo).toBeInTheDocument()
    expect(logo.tagName).toBe('svg')
  })

  it('should have correct dimensions', () => {
    renderWithProviders(<Logo />)
    
    const logo = screen.getByRole('img', { hidden: true })
    expect(logo).toHaveAttribute('width', '50')
    expect(logo).toHaveAttribute('height', '50')
    expect(logo).toHaveAttribute('viewBox', '0 0 50 50')
  })

  it('should have proper CSS classes', () => {
    renderWithProviders(<Logo />)
    
    const logo = screen.getByRole('img', { hidden: true })
    expect(logo).toHaveClass('app-logo-svg')
  })

  it('should display MP text', () => {
    renderWithProviders(<Logo />)
    
    expect(screen.getByText('MP')).toBeInTheDocument()
  })

  it('should have proper text styling attributes', () => {
    renderWithProviders(<Logo />)
    
    const text = screen.getByText('MP')
    expect(text).toHaveAttribute('textAnchor', 'middle')
    expect(text).toHaveAttribute('dominantBaseline', 'middle')
    expect(text).toHaveAttribute('fontSize', '24')
    expect(text).toHaveAttribute('fontWeight', 'bold')
  })

  it('should have rounded corners on background', () => {
    renderWithProviders(<Logo />)
    
    const rect = document.querySelector('rect')
    expect(rect).toHaveAttribute('rx', '8')
    expect(rect).toHaveClass('logo-bg')
  })
})
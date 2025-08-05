/**
 * Unit tests for App component
 * Tests authentication flow and initial application state
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderWithProviders, mockApiResponse, mockApiError } from './test/utils'
import App from './App'
import * as api from '../api'

// Mock the API module
vi.mock('../api', () => ({
  api: {
    checkAuthStatus: vi.fn(),
  },
}))

describe('App', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('should show loading spinner initially', () => {
    vi.mocked(api.api.checkAuthStatus).mockImplementation(() => new Promise(() => {})) // Never resolves
    
    renderWithProviders(<App />)
    
    expect(screen.getByTestId('loading-spinner')).toBeInTheDocument()
  })

  it('should show SignInPage when user is not authenticated', async () => {
    vi.mocked(api.api.checkAuthStatus).mockRejectedValue(new Error('Unauthorized'))
    
    renderWithProviders(<App />)
    
    await waitFor(() => {
      expect(screen.getByText('Willkommen zur MP Arbeitszeiterfassung')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /mit microsoft anmelden/i })).toBeInTheDocument()
    })
  })

  it('should show MainAppView when user is authenticated', async () => {
    vi.mocked(api.api.checkAuthStatus).mockResolvedValue(undefined)
    
    renderWithProviders(<App />)
    
    // The MainAppView will render but might show loading initially
    // We need to wait for the auth check to complete
    await waitFor(() => {
      expect(screen.queryByTestId('loading-spinner')).not.toBeInTheDocument()
    })
    
    // Since MainAppView requires additional data fetching, we'll just verify
    // that we're not on the sign-in page anymore
    expect(screen.queryByText('Willkommen zur MP Arbeitszeiterfassung')).not.toBeInTheDocument()
  })

  it('should handle auth check errors gracefully', async () => {
    vi.mocked(api.api.checkAuthStatus).mockRejectedValue(new Error('Network error'))
    
    renderWithProviders(<App />)
    
    await waitFor(() => {
      expect(screen.getByText('Willkommen zur MP Arbeitszeiterfassung')).toBeInTheDocument()
    })
  })

  it('should call checkAuthStatus on mount', () => {
    vi.mocked(api.api.checkAuthStatus).mockResolvedValue(undefined)
    
    renderWithProviders(<App />)
    
    expect(api.api.checkAuthStatus).toHaveBeenCalledTimes(1)
  })
})
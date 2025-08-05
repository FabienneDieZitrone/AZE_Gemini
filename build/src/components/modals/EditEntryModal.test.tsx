/**
 * Unit tests for EditEntryModal component
 * Tests modal functionality for editing time entries
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderWithProviders, userEvent, createMockTimeEntry } from '../../test/utils'
import { EditEntryModal } from './EditEntryModal'

// Mock props interface based on component usage
interface EditEntryModalProps {
  entry: any
  isOpen: boolean
  onClose: () => void
  onSave: (updatedEntry: any) => void
  onRequestChange?: (changeRequest: any) => void
}

describe('EditEntryModal', () => {
  const mockOnClose = vi.fn()
  const mockOnSave = vi.fn()
  const mockOnRequestChange = vi.fn()
  const mockEntry = createMockTimeEntry()

  const defaultProps: EditEntryModalProps = {
    entry: mockEntry,
    isOpen: true,
    onClose: mockOnClose,
    onSave: mockOnSave,
    onRequestChange: mockOnRequestChange,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('should render when open', () => {
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    // Should show modal content
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('should not render when closed', () => {
    renderWithProviders(<EditEntryModal {...defaultProps} isOpen={false} />)
    
    // Should not show modal content
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('should display current entry values', () => {
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    // Should show current time values
    const startTimeInput = screen.getByDisplayValue('09:00:00')
    const stopTimeInput = screen.getByDisplayValue('17:00:00')
    
    expect(startTimeInput).toBeInTheDocument()
    expect(stopTimeInput).toBeInTheDocument()
  })

  it('should allow editing time values', async () => {
    const user = userEvent.setup()
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    const startTimeInput = screen.getByDisplayValue('09:00:00')
    const stopTimeInput = screen.getByDisplayValue('17:00:00')
    
    // Change start time
    await user.clear(startTimeInput)
    await user.type(startTimeInput, '08:30:00')
    
    // Change stop time
    await user.clear(stopTimeInput)
    await user.type(stopTimeInput, '16:30:00')
    
    expect(startTimeInput).toHaveValue('08:30:00')
    expect(stopTimeInput).toHaveValue('16:30:00')
  })

  it('should save changes when save button is clicked', async () => {
    const user = userEvent.setup()
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    const startTimeInput = screen.getByDisplayValue('09:00:00')
    const saveButton = screen.getByRole('button', { name: /speichern/i })
    
    // Make changes
    await user.clear(startTimeInput)
    await user.type(startTimeInput, '08:30:00')
    
    // Save changes
    await user.click(saveButton)
    
    expect(mockOnSave).toHaveBeenCalledWith(
      expect.objectContaining({
        ...mockEntry,
        startTime: '08:30:00'
      })
    )
  })

  it('should request approval for changes when approval is required', async () => {
    const user = userEvent.setup()
    const propsWithApproval = {
      ...defaultProps,
      requireApproval: true
    }
    
    renderWithProviders(<EditEntryModal {...propsWithApproval} />)
    
    const startTimeInput = screen.getByDisplayValue('09:00:00')
    const requestChangeButton = screen.getByRole('button', { name: /änderung beantragen/i })
    
    // Make changes
    await user.clear(startTimeInput)
    await user.type(startTimeInput, '08:30:00')
    
    // Add reason for change
    const reasonTextarea = screen.getByLabelText(/grund für änderung/i)
    await user.type(reasonTextarea, 'Korrektur der tatsächlichen Arbeitszeit')
    
    // Request change
    await user.click(requestChangeButton)
    
    expect(mockOnRequestChange).toHaveBeenCalledWith(
      expect.objectContaining({
        entryId: mockEntry.id,
        requestType: 'change',
        requestedChanges: expect.objectContaining({
          startTime: '08:30:00'
        }),
        reason: 'Korrektur der tatsächlichen Arbeitszeit'
      })
    )
  })

  it('should close modal when cancel button is clicked', async () => {
    const user = userEvent.setup()
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    const cancelButton = screen.getByRole('button', { name: /abbrechen/i })
    await user.click(cancelButton)
    
    expect(mockOnClose).toHaveBeenCalledTimes(1)
  })

  it('should close modal when clicking outside', async () => {
    const user = userEvent.setup()
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    const modal = screen.getByRole('dialog')
    const overlay = modal.parentElement
    
    if (overlay) {
      await user.click(overlay)
      expect(mockOnClose).toHaveBeenCalledTimes(1)
    }
  })

  it('should validate time format', async () => {
    const user = userEvent.setup()
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    const startTimeInput = screen.getByDisplayValue('09:00:00')
    const saveButton = screen.getByRole('button', { name: /speichern/i })
    
    // Enter invalid time format
    await user.clear(startTimeInput)
    await user.type(startTimeInput, '25:00:00') // Invalid hour
    
    await user.click(saveButton)
    
    // Should show validation error
    await waitFor(() => {
      expect(screen.getByText(/ungültiges zeitformat/i)).toBeInTheDocument()
    })
    
    // Should not call onSave
    expect(mockOnSave).not.toHaveBeenCalled()
  })

  it('should validate that stop time is after start time', async () => {
    const user = userEvent.setup()
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    const startTimeInput = screen.getByDisplayValue('09:00:00')
    const stopTimeInput = screen.getByDisplayValue('17:00:00')
    const saveButton = screen.getByRole('button', { name: /speichern/i })
    
    // Set stop time before start time
    await user.clear(startTimeInput)
    await user.type(startTimeInput, '17:00:00')
    await user.clear(stopTimeInput)
    await user.type(stopTimeInput, '09:00:00')
    
    await user.click(saveButton)
    
    // Should show validation error
    await waitFor(() => {
      expect(screen.getByText(/endzeit muss nach startzeit liegen/i)).toBeInTheDocument()
    })
    
    // Should not call onSave
    expect(mockOnSave).not.toHaveBeenCalled()
  })

  it('should handle keyboard navigation', async () => {
    const user = userEvent.setup()
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    // Should be able to tab through form elements
    await user.tab()
    expect(screen.getByDisplayValue('09:00:00')).toHaveFocus()
    
    await user.tab()
    expect(screen.getByDisplayValue('17:00:00')).toHaveFocus()
    
    // Should close modal on Escape key
    await user.keyboard('{Escape}')
    expect(mockOnClose).toHaveBeenCalledTimes(1)
  })

  it('should disable save button when no changes are made', () => {
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    const saveButton = screen.getByRole('button', { name: /speichern/i })
    expect(saveButton).toBeDisabled()
  })

  it('should enable save button when changes are made', async () => {
    const user = userEvent.setup()
    renderWithProviders(<EditEntryModal {...defaultProps} />)
    
    const startTimeInput = screen.getByDisplayValue('09:00:00')
    const saveButton = screen.getByRole('button', { name: /speichern/i })
    
    // Initially disabled
    expect(saveButton).toBeDisabled()
    
    // Make a change
    await user.clear(startTimeInput)
    await user.type(startTimeInput, '08:30:00')
    
    // Should be enabled after change
    expect(saveButton).toBeEnabled()
  })

  it('should show loading state during save operation', async () => {
    const user = userEvent.setup()
    const slowOnSave = vi.fn(() => new Promise(resolve => setTimeout(resolve, 1000)))
    
    renderWithProviders(<EditEntryModal {...defaultProps} onSave={slowOnSave} />)
    
    const startTimeInput = screen.getByDisplayValue('09:00:00')
    const saveButton = screen.getByRole('button', { name: /speichern/i })
    
    // Make a change
    await user.clear(startTimeInput)
    await user.type(startTimeInput, '08:30:00')
    
    // Click save
    await user.click(saveButton)
    
    // Should show loading state
    expect(screen.getByTestId('loading-spinner')).toBeInTheDocument()
    expect(saveButton).toBeDisabled()
  })
})
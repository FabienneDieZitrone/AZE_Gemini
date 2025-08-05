/**
 * Tests für ErrorDisplay Component
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ErrorDisplay, InlineError } from '../ErrorDisplay';
import { errorMessageService } from '../../../services/ErrorMessageService';
import { notificationService } from '../../../services/NotificationService';

// Mock the services
jest.mock('../../../services/ErrorMessageService');
jest.mock('../../../services/NotificationService');

// Mock clipboard API
Object.assign(navigator, {
  clipboard: {
    writeText: jest.fn(() => Promise.resolve()),
  },
});

describe('ErrorDisplay', () => {
  const mockError = {
    message: 'Test error message',
    stack: 'Error stack trace',
    code: 'TEST_ERROR'
  };

  const mockErrorContext = {
    code: 'TEST_001',
    technical: 'Technical error message',
    user: 'Benutzerfreundliche Fehlermeldung',
    action: 'Bitte versuchen Sie es erneut',
    support: 'Fehlercode: TEST_001'
  };

  beforeEach(() => {
    jest.clearAllMocks();
    (errorMessageService.getErrorMessage as jest.Mock).mockReturnValue(mockErrorContext);
    (errorMessageService.formatErrorForClipboard as jest.Mock).mockReturnValue('Formatted error text');
  });

  it('should render error message correctly', () => {
    render(<ErrorDisplay error={mockError} />);
    
    expect(screen.getByText('Benutzerfreundliche Fehlermeldung')).toBeInTheDocument();
    expect(screen.getByText('Bitte versuchen Sie es erneut')).toBeInTheDocument();
    expect(screen.getByText('Fehlercode: TEST_001')).toBeInTheDocument();
  });

  it('should call onRetry when retry button is clicked', () => {
    const onRetry = jest.fn();
    render(<ErrorDisplay error={mockError} onRetry={onRetry} />);
    
    fireEvent.click(screen.getByText('Erneut versuchen'));
    expect(onRetry).toHaveBeenCalledTimes(1);
  });

  it('should call onDismiss when dismiss button is clicked', () => {
    const onDismiss = jest.fn();
    render(<ErrorDisplay error={mockError} onDismiss={onDismiss} />);
    
    fireEvent.click(screen.getByText('Schließen'));
    expect(onDismiss).toHaveBeenCalledTimes(1);
  });

  it('should copy error details to clipboard', async () => {
    render(<ErrorDisplay error={mockError} />);
    
    fireEvent.click(screen.getByText('Details kopieren'));
    
    await waitFor(() => {
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('Formatted error text');
      expect(notificationService.success).toHaveBeenCalledWith('Fehlerdetails in Zwischenablage kopiert');
    });
  });

  it('should handle clipboard copy failure', async () => {
    const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
    (navigator.clipboard.writeText as jest.Mock).mockRejectedValueOnce(new Error('Copy failed'));
    
    render(<ErrorDisplay error={mockError} />);
    
    fireEvent.click(screen.getByText('Details kopieren'));
    
    await waitFor(() => {
      expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to copy error details:', expect.any(Error));
    });
    
    consoleErrorSpy.mockRestore();
  });

  it('should apply custom className', () => {
    const { container } = render(<ErrorDisplay error={mockError} className="custom-class" />);
    
    expect(container.querySelector('.error-display.custom-class')).toBeInTheDocument();
  });

  it('should show technical details in development mode', () => {
    const originalEnv = process.env.NODE_ENV;
    process.env.NODE_ENV = 'development';
    
    render(<ErrorDisplay error={mockError} />);
    
    const detailsButton = screen.getByText('Technische Details');
    expect(detailsButton).toBeInTheDocument();
    
    fireEvent.click(detailsButton);
    
    expect(screen.getByText(/"code": "TEST_001"/)).toBeInTheDocument();
    
    process.env.NODE_ENV = originalEnv;
  });

  it('should not show technical details in production mode', () => {
    const originalEnv = process.env.NODE_ENV;
    process.env.NODE_ENV = 'production';
    
    render(<ErrorDisplay error={mockError} />);
    
    expect(screen.queryByText('Technische Details')).not.toBeInTheDocument();
    
    process.env.NODE_ENV = originalEnv;
  });

  it('should not render action if not provided', () => {
    const errorContextWithoutAction = { ...mockErrorContext, action: undefined };
    (errorMessageService.getErrorMessage as jest.Mock).mockReturnValue(errorContextWithoutAction);
    
    render(<ErrorDisplay error={mockError} />);
    
    expect(screen.queryByText('Bitte versuchen Sie es erneut')).not.toBeInTheDocument();
  });

  it('should not render support code if not provided', () => {
    const errorContextWithoutSupport = { ...mockErrorContext, support: undefined };
    (errorMessageService.getErrorMessage as jest.Mock).mockReturnValue(errorContextWithoutSupport);
    
    render(<ErrorDisplay error={mockError} />);
    
    expect(screen.queryByText('Fehlercode: TEST_001')).not.toBeInTheDocument();
  });
});

describe('InlineError', () => {
  it('should render error message', () => {
    render(<InlineError error="Dies ist ein Fehler" />);
    
    expect(screen.getByText('Dies ist ein Fehler')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toBeInTheDocument();
  });

  it('should not render if no error', () => {
    const { container } = render(<InlineError error={undefined} />);
    
    expect(container.firstChild).toBeNull();
  });

  it('should set aria-describedby if fieldName is provided', () => {
    render(<InlineError error="Fehler" fieldName="email" />);
    
    const alert = screen.getByRole('alert');
    expect(alert).toHaveAttribute('aria-describedby', 'email-input');
  });

  it('should not set aria-describedby if fieldName is not provided', () => {
    render(<InlineError error="Fehler" />);
    
    const alert = screen.getByRole('alert');
    expect(alert).not.toHaveAttribute('aria-describedby');
  });
});
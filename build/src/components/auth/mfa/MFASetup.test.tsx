import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi } from 'vitest';
import { MFASetup } from './MFASetup';

// Mock QRCodeSVG component
vi.mock('qrcode.react', () => ({
  QRCodeSVG: ({ value, size }: { value: string; size: number }) => (
    <div data-testid="qr-code" data-value={value} data-size={size}>
      QR Code Mock
    </div>
  )
}));

describe('MFASetup', () => {
  const mockOnComplete = vi.fn();
  const mockOnCancel = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render QR code step initially', () => {
    render(<MFASetup onComplete={mockOnComplete} onCancel={mockOnCancel} />);
    
    expect(screen.getByText('Zwei-Faktor-Authentifizierung einrichten')).toBeInTheDocument();
    expect(screen.getByText('Scannen Sie den QR-Code mit Ihrer Authenticator-App:')).toBeInTheDocument();
    expect(screen.getByTestId('qr-code')).toBeInTheDocument();
    expect(screen.getByText('Weiter')).toBeInTheDocument();
    expect(screen.getByText('Abbrechen')).toBeInTheDocument();
  });

  it('should proceed to verification step when clicking next', () => {
    render(<MFASetup onComplete={mockOnComplete} onCancel={mockOnCancel} />);
    
    fireEvent.click(screen.getByText('Weiter'));
    
    expect(screen.getByText('Code verifizieren')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('000000')).toBeInTheDocument();
  });

  it('should enable verify button when 6-digit code is entered', () => {
    render(<MFASetup onComplete={mockOnComplete} onCancel={mockOnCancel} />);
    
    fireEvent.click(screen.getByText('Weiter'));
    
    const verifyButton = screen.getByText('Verifizieren');
    expect(verifyButton).toBeDisabled();
    
    const codeInput = screen.getByPlaceholderText('000000');
    fireEvent.change(codeInput, { target: { value: '123456' } });
    
    expect(verifyButton).not.toBeDisabled();
  });

  it('should show backup codes after verification', () => {
    render(<MFASetup onComplete={mockOnComplete} onCancel={mockOnCancel} />);
    
    // Navigate to verification step
    fireEvent.click(screen.getByText('Weiter'));
    
    // Enter code and verify
    const codeInput = screen.getByPlaceholderText('000000');
    fireEvent.change(codeInput, { target: { value: '123456' } });
    fireEvent.click(screen.getByText('Verifizieren'));
    
    expect(screen.getByText('Backup-Codes speichern')).toBeInTheDocument();
    expect(screen.getByText('MFA aktivieren')).toBeInTheDocument();
  });

  it('should call onCancel when cancel is clicked', () => {
    render(<MFASetup onComplete={mockOnComplete} onCancel={mockOnCancel} />);
    
    fireEvent.click(screen.getByText('Abbrechen'));
    
    expect(mockOnCancel).toHaveBeenCalledTimes(1);
  });

  it('should call onComplete with backup codes when setup is finished', () => {
    render(<MFASetup onComplete={mockOnComplete} onCancel={mockOnCancel} />);
    
    // Navigate through all steps
    fireEvent.click(screen.getByText('Weiter'));
    
    const codeInput = screen.getByPlaceholderText('000000');
    fireEvent.change(codeInput, { target: { value: '123456' } });
    fireEvent.click(screen.getByText('Verifizieren'));
    
    fireEvent.click(screen.getByText('MFA aktivieren'));
    
    expect(mockOnComplete).toHaveBeenCalledTimes(1);
    expect(mockOnComplete).toHaveBeenCalledWith(expect.any(Array));
    
    const backupCodes = mockOnComplete.mock.calls[0][0];
    expect(backupCodes).toHaveLength(10);
    expect(backupCodes.every((code: string) => code.length === 8)).toBe(true);
  });
});
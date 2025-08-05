/**
 * Tests für ErrorMessageService
 */

import { ErrorMessageService } from '../ErrorMessageService';

describe('ErrorMessageService', () => {
  let service: ErrorMessageService;

  beforeEach(() => {
    service = ErrorMessageService.getInstance();
  });

  describe('getInstance', () => {
    it('should return singleton instance', () => {
      const instance1 = ErrorMessageService.getInstance();
      const instance2 = ErrorMessageService.getInstance();
      expect(instance1).toBe(instance2);
    });
  });

  describe('getErrorMessage', () => {
    it('should return correct message for AUTH_FAILED', () => {
      const error = { code: 'AUTH_FAILED' };
      const result = service.getErrorMessage(error);
      
      expect(result.code).toBe('AUTH_001');
      expect(result.user).toBe('Anmeldung fehlgeschlagen. Bitte überprüfen Sie Ihre Anmeldedaten.');
      expect(result.action).toBe('Versuchen Sie es erneut oder wenden Sie sich an Ihren Administrator.');
      expect(result.support).toBe('Fehlercode: AUTH_001');
    });

    it('should return correct message for NETWORK_ERROR', () => {
      const error = { code: 'NETWORK_ERROR' };
      const result = service.getErrorMessage(error);
      
      expect(result.code).toBe('NET_001');
      expect(result.user).toBe('Verbindungsfehler. Bitte überprüfen Sie Ihre Internetverbindung.');
    });

    it('should handle offline detection', () => {
      const originalOnline = navigator.onLine;
      Object.defineProperty(navigator, 'onLine', {
        writable: true,
        value: false
      });

      const error = { message: 'Some error' };
      const result = service.getErrorMessage(error);
      
      expect(result.code).toBe('NET_001');
      
      Object.defineProperty(navigator, 'onLine', {
        writable: true,
        value: originalOnline
      });
    });

    it('should handle HTTP status codes', () => {
      const error401 = { status: 401 };
      const result401 = service.getErrorMessage(error401);
      expect(result401.code).toBe('AUTH_002');

      const error403 = { status: 403 };
      const result403 = service.getErrorMessage(error403);
      expect(result403.code).toBe('PERM_001');

      const error500 = { status: 500 };
      const result500 = service.getErrorMessage(error500);
      expect(result500.code).toBe('DATA_002');
    });

    it('should handle timeout errors', () => {
      const error = { name: 'TimeoutError' };
      const result = service.getErrorMessage(error);
      expect(result.code).toBe('NET_002');
    });

    it('should return fallback for unknown errors', () => {
      const error = { message: 'Unknown error occurred' };
      const result = service.getErrorMessage(error);
      
      expect(result.code).toBe('GEN_001');
      expect(result.user).toBe('Ein unerwarteter Fehler ist aufgetreten.');
      expect(result.support).toMatch(/^Fehlercode: GEN_001 \(Ref: .+\)$/);
    });

    it('should handle field context', () => {
      const error = { code: 'VALIDATION_REQUIRED' };
      const context = { field: 'email' };
      const result = service.getErrorMessage(error, context);
      
      expect(result.field).toBe('email');
    });
  });

  describe('getSuccessMessage', () => {
    it('should return correct success message', () => {
      const result = service.getSuccessMessage('SAVE_SUCCESS');
      
      expect(result.code).toBe('SUCCESS_001');
      expect(result.user).toBe('Ihre Daten wurden erfolgreich gespeichert.');
    });

    it('should handle unknown success types', () => {
      const result = service.getSuccessMessage('UNKNOWN_SUCCESS');
      
      expect(result.code).toBe('SUCCESS_000');
      expect(result.user).toBe('Aktion erfolgreich durchgeführt.');
    });
  });

  describe('formatErrorForClipboard', () => {
    it('should format error correctly for clipboard', () => {
      const errorContext = {
        code: 'TEST_001',
        technical: 'Test error',
        user: 'Test Fehler',
        support: 'Fehlercode: TEST_001'
      };
      
      const result = service.formatErrorForClipboard(errorContext);
      
      expect(result).toContain('Fehler: Test Fehler');
      expect(result).toContain('Support-Code: Fehlercode: TEST_001');
      expect(result).toContain('Zeitstempel:');
      expect(result).toContain('Browser:');
    });

    it('should include additional info if provided', () => {
      const errorContext = {
        code: 'TEST_001',
        technical: 'Test error',
        user: 'Test Fehler'
      };
      
      const additionalInfo = { userId: 123, action: 'save' };
      const result = service.formatErrorForClipboard(errorContext, additionalInfo);
      
      expect(result).toContain('Zusatzinfo: {"userId":123,"action":"save"}');
    });
  });

  describe('logUnmappedError', () => {
    it('should log unmapped errors', () => {
      const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();
      const error = { 
        message: 'Unmapped error', 
        name: 'TestError',
        stack: 'Error stack trace'
      };
      
      // Force unmapped error
      service.getErrorMessage(error);
      
      expect(consoleWarnSpy).toHaveBeenCalledWith(
        'Unmapped error (dev):',
        expect.objectContaining({
          timestamp: expect.any(String),
          error: expect.objectContaining({
            message: 'Unmapped error',
            name: 'TestError'
          })
        })
      );
      
      consoleWarnSpy.mockRestore();
    });
  });
});
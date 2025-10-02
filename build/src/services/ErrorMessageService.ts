/**
 * Error Message Service für AZE_Gemini
 * 
 * Bietet benutzerfreundliche, deutsche Fehlermeldungen
 * mit Handlungsempfehlungen und Support-Codes
 */

export interface ErrorContext {
  code: string;
  technical: string;
  user: string;
  action?: string;
  support?: string;
  field?: string;
}

export class ErrorMessageService {
  private static instance: ErrorMessageService;
  
  private errorMap: Map<string, ErrorContext> = new Map([
    // Authentication Errors
    ['AUTH_FAILED', {
      code: 'AUTH_001',
      technical: 'Authentication failed',
      user: 'Anmeldung fehlgeschlagen. Bitte überprüfen Sie Ihre Anmeldedaten.',
      action: 'Versuchen Sie es erneut oder wenden Sie sich an Ihren Administrator.',
      support: 'Fehlercode: AUTH_001'
    }],
    ['AUTH_EXPIRED', {
      code: 'AUTH_002', 
      technical: 'Session token expired',
      user: 'Ihre Sitzung ist aus Sicherheitsgründen abgelaufen.',
      action: 'Bitte melden Sie sich erneut an.',
      support: 'Fehlercode: AUTH_002'
    }],
    
    // Network Errors
    ['NETWORK_ERROR', {
      code: 'NET_001',
      technical: 'Network request failed',
      user: 'Verbindungsfehler. Bitte überprüfen Sie Ihre Internetverbindung.',
      action: 'Versuchen Sie es in einigen Momenten erneut.',
      support: 'Fehlercode: NET_001'
    }],
    ['TIMEOUT', {
      code: 'NET_002',
      technical: 'Request timeout',
      user: 'Die Anfrage hat zu lange gedauert.',
      action: 'Bitte versuchen Sie es erneut. Bei wiederholten Problemen wenden Sie sich an den Support.',
      support: 'Fehlercode: NET_002'
    }],
    // CSRF Errors
    ['CSRF_TOKEN_INVALID', {
      code: 'SEC_001',
      technical: 'Invalid or missing CSRF token',
      user: 'Sicherheitsüberprüfung fehlgeschlagen. Bitte Seite aktualisieren und erneut versuchen.',
      action: 'Aktualisieren Sie die Seite (F5) und melden Sie sich ggf. neu an.',
      support: 'Fehlercode: SEC_001'
    }],
    
    // Validation Errors
    ['VALIDATION_REQUIRED', {
      code: 'VAL_001',
      technical: 'Required field validation failed',
      user: 'Bitte füllen Sie alle Pflichtfelder aus.',
      action: 'Überprüfen Sie die markierten Felder.',
    }],
    ['VALIDATION_FORMAT', {
      code: 'VAL_002',
      technical: 'Format validation failed',
      user: 'Das eingegebene Format ist ungültig.',
      action: 'Bitte überprüfen Sie Ihre Eingabe.',
    }],
    ['VALIDATION_HOURS', {
      code: 'VAL_003',
      technical: 'Hours validation failed',
      user: 'Die eingegebenen Stunden sind ungültig.',
      action: 'Gültige Werte: 0,25 bis 24 Stunden.',
    }],
    ['VALIDATION_DATE_FUTURE', {
      code: 'VAL_004',
      technical: 'Future date validation failed',
      user: 'Zeiteinträge für zukünftige Daten sind nicht erlaubt.',
      action: 'Bitte wählen Sie das heutige oder ein vergangenes Datum.',
    }],
    ['VALIDATION_DATE_OLD', {
      code: 'VAL_005',
      technical: 'Date too old validation failed',
      user: 'Zeiteinträge älter als 30 Tage können nicht mehr bearbeitet werden.',
      action: 'Bitte wenden Sie sich an Ihren Vorgesetzten.',
    }],
    
    // Timer Errors
    ['TIMER_ALREADY_RUNNING', {
      code: 'TMR_001',
      technical: 'Timer already running',
      user: 'Die Zeiterfassung läuft bereits.',
      action: 'Bitte stoppen Sie zuerst die laufende Zeiterfassung.',
      support: 'Fehlercode: TMR_001'
    }],
    ['TIMER_NOT_RUNNING', {
      code: 'TMR_002',
      technical: 'Timer not running',
      user: 'Es läuft keine Zeiterfassung.',
      action: 'Starten Sie eine neue Zeiterfassung.',
      support: 'Fehlercode: TMR_002'
    }],
    
    // Permission Errors
    ['PERMISSION_DENIED', {
      code: 'PERM_001',
      technical: 'Permission denied',
      user: 'Sie haben keine Berechtigung für diese Aktion.',
      action: 'Bitte wenden Sie sich an Ihren Administrator.',
      support: 'Fehlercode: PERM_001'
    }],
    
    // Data Errors
    ['NOT_FOUND', {
      code: 'DATA_001',
      technical: 'Resource not found',
      user: 'Die angeforderten Daten wurden nicht gefunden.',
      action: 'Bitte aktualisieren Sie die Seite.',
      support: 'Fehlercode: DATA_001'
    }],
    ['DATABASE_ERROR', {
      code: 'DATA_002',
      technical: 'Database error',
      user: 'Ein Datenbankfehler ist aufgetreten.',
      action: 'Bitte versuchen Sie es später erneut. Bei wiederholten Problemen kontaktieren Sie den Support.',
      support: 'Fehlercode: DATA_002'
    }],
    
    // Success Messages (for replacements of alerts)
    ['SAVE_SUCCESS', {
      code: 'SUCCESS_001',
      technical: 'Data saved successfully',
      user: 'Ihre Daten wurden erfolgreich gespeichert.',
    }],
    ['DELETE_SUCCESS', {
      code: 'SUCCESS_002',
      technical: 'Data deleted successfully',
      user: 'Der Eintrag wurde erfolgreich gelöscht.',
    }],
    ['APPROVAL_SENT', {
      code: 'SUCCESS_003',
      technical: 'Approval request sent',
      user: 'Ihre Änderung wurde erfasst und zur Genehmigung weitergeleitet.',
    }],
    ['ROLE_UPDATED', {
      code: 'SUCCESS_004',
      technical: 'Role updated successfully',
      user: 'Die Rolle wurde erfolgreich geändert.',
    }],
    ['SETTINGS_SAVED', {
      code: 'SUCCESS_005',
      technical: 'Settings saved successfully',
      user: 'Die Einstellungen wurden erfolgreich gespeichert.',
    }],
    ['MASTERDATA_SAVED', {
      code: 'SUCCESS_006',
      technical: 'Master data saved successfully',
      user: 'Die Stammdaten wurden erfolgreich gespeichert.',
    }],
  ]);
  
  private constructor() {}
  
  public static getInstance(): ErrorMessageService {
    if (!ErrorMessageService.instance) {
      ErrorMessageService.instance = new ErrorMessageService();
    }
    return ErrorMessageService.instance;
  }
  
  public getErrorMessage(error: any, context?: any): ErrorContext {
    // Handle API errors with error codes
    if (error.code && this.errorMap.has(error.code)) {
      const errorContext = this.errorMap.get(error.code)!;
      
      // Add field information if available
      if (context?.field) {
        errorContext.field = context.field;
      }
      
      return errorContext;
    }

    // Detect CSRF-related messages
    if (typeof error?.message === 'string' && /csrf/i.test(error.message)) {
      return this.errorMap.get('CSRF_TOKEN_INVALID')!;
    }

    // Handle network errors
    if (error.name === 'NetworkError' || !navigator.onLine) {
      return this.errorMap.get('NETWORK_ERROR')!;
    }
    
    // Handle timeout errors
    if (error.name === 'TimeoutError' || error.message?.includes('timeout')) {
      return this.errorMap.get('TIMEOUT')!;
    }
    
    // Handle HTTP status codes
    if (error.status) {
      switch (error.status) {
        case 401:
          return this.errorMap.get('AUTH_EXPIRED')!;
        case 403:
          return this.errorMap.get('PERMISSION_DENIED')!;
        case 404:
          return this.errorMap.get('NOT_FOUND')!;
        case 500:
        case 502:
        case 503:
          return this.errorMap.get('DATABASE_ERROR')!;
      }
    }
    
    // Log unmapped errors for future improvement
    this.logUnmappedError(error);
    
    // Default fallback
    return {
      code: 'GEN_001',
      technical: error.message || 'Unknown error',
      user: 'Ein unerwarteter Fehler ist aufgetreten.',
      action: 'Bitte versuchen Sie es erneut oder kontaktieren Sie den Support.',
      support: `Fehlercode: GEN_001 (Ref: ${this.generateErrorId()})`
    };
  }
  
  public getSuccessMessage(type: string, context?: any): ErrorContext {
    const message = this.errorMap.get(type);
    if (message) {
      // Customize message with context if needed
      if (context?.name) {
        return {
          ...message,
          user: message.user.replace('{name}', context.name)
        };
      }
      return message;
    }
    
    return {
      code: 'SUCCESS_000',
      technical: 'Success',
      user: 'Aktion erfolgreich durchgeführt.'
    };
  }
  
  private logUnmappedError(error: any): void {
    const errorInfo = {
      timestamp: new Date().toISOString(),
      error: {
        message: error.message,
        stack: error.stack,
        name: error.name,
        code: error.code,
        status: error.status
      },
      userAgent: navigator.userAgent,
      url: window.location.href
    };
    
    // In production, send to error tracking service
    if (process.env.NODE_ENV === 'production') {
      // TODO: Send to error tracking service
      console.error('Unmapped error:', errorInfo);
    } else {
      console.warn('Unmapped error (dev):', errorInfo);
    }
  }
  
  private generateErrorId(): string {
    return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
  }
  
  public formatErrorForClipboard(errorContext: ErrorContext, additionalInfo?: any): string {
    const lines = [
      `Fehler: ${errorContext.user}`,
      errorContext.support ? `Support-Code: ${errorContext.support}` : '',
      `Zeitstempel: ${new Date().toISOString()}`,
      `Browser: ${navigator.userAgent}`,
      additionalInfo ? `Zusatzinfo: ${JSON.stringify(additionalInfo)}` : ''
    ].filter(Boolean);
    
    return lines.join('\n');
  }
}

// Export singleton instance
export const errorMessageService = ErrorMessageService.getInstance();

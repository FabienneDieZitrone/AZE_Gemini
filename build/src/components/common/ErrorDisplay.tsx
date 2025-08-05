/**
 * ErrorDisplay Component für AZE_Gemini
 * 
 * Zeigt benutzerfreundliche Fehlermeldungen mit
 * Handlungsempfehlungen und Support-Informationen
 */

import React, { useState } from 'react';
import { errorMessageService, ErrorContext } from '../../services/ErrorMessageService';
import { notificationService } from '../../services/NotificationService';

interface ErrorDisplayProps {
  error: any;
  onRetry?: () => void;
  onDismiss?: () => void;
  className?: string;
}

export const ErrorDisplay: React.FC<ErrorDisplayProps> = ({ 
  error, 
  onRetry, 
  onDismiss,
  className = ''
}) => {
  const [showDetails, setShowDetails] = useState(false);
  const errorContext = errorMessageService.getErrorMessage(error);
  
  const handleCopyError = async () => {
    const errorText = errorMessageService.formatErrorForClipboard(errorContext, {
      originalError: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    });
    
    try {
      await navigator.clipboard.writeText(errorText);
      notificationService.success('Fehlerdetails in Zwischenablage kopiert');
    } catch (err) {
      console.error('Failed to copy error details:', err);
    }
  };
  
  return (
    <div 
      className={`error-display ${className}`} 
      role="alert"
      aria-live="assertive"
    >
      <div className="error-display__container">
        <div className="error-display__icon">
          <svg 
            className="error-display__icon-svg" 
            fill="none" 
            viewBox="0 0 24 24" 
            stroke="currentColor"
          >
            <path 
              strokeLinecap="round" 
              strokeLinejoin="round" 
              strokeWidth={2} 
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" 
            />
          </svg>
        </div>
        
        <div className="error-display__content">
          <h3 className="error-display__title">
            {errorContext.user}
          </h3>
          
          {errorContext.action && (
            <p className="error-display__action">
              {errorContext.action}
            </p>
          )}
          
          <div className="error-display__footer">
            <div className="error-display__footer-left">
              {errorContext.support && (
                <span className="error-display__code">
                  {errorContext.support}
                </span>
              )}
            </div>
            
            <div className="error-display__footer-right">
              {onRetry && (
                <button 
                  onClick={onRetry}
                  className="error-display__button error-display__button--primary"
                  aria-label="Erneut versuchen"
                >
                  Erneut versuchen
                </button>
              )}
              
              <button 
                onClick={handleCopyError}
                className="error-display__button error-display__button--secondary"
                aria-label="Fehlerdetails kopieren"
              >
                Details kopieren
              </button>
              
              {onDismiss && (
                <button 
                  onClick={onDismiss}
                  className="error-display__button error-display__button--text"
                  aria-label="Fehlermeldung schließen"
                >
                  Schließen
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
      
      {/* Technical details for development */}
      {process.env.NODE_ENV === 'development' && (
        <div className="error-display__dev-details">
          <button
            onClick={() => setShowDetails(!showDetails)}
            className="error-display__dev-toggle"
            aria-expanded={showDetails}
          >
            {showDetails ? '▼' : '▶'} Technische Details
          </button>
          
          {showDetails && (
            <pre className="error-display__dev-content">
              {JSON.stringify({
                code: errorContext.code,
                technical: errorContext.technical,
                originalError: {
                  message: error.message,
                  stack: error.stack,
                  name: error.name,
                  ...(error.response && { response: error.response })
                }
              }, null, 2)}
            </pre>
          )}
        </div>
      )}
    </div>
  );
};

// Inline error display for form fields
interface InlineErrorProps {
  error: string | undefined;
  fieldName?: string;
}

export const InlineError: React.FC<InlineErrorProps> = ({ error, fieldName }) => {
  if (!error) return null;
  
  return (
    <div 
      className="inline-error"
      role="alert"
      aria-live="polite"
      aria-describedby={fieldName ? `${fieldName}-input` : undefined}
    >
      <svg 
        className="inline-error__icon" 
        fill="currentColor" 
        viewBox="0 0 20 20"
      >
        <path 
          fillRule="evenodd" 
          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" 
          clipRule="evenodd" 
        />
      </svg>
      <span className="inline-error__text">{error}</span>
    </div>
  );
};
/**
 * ErrorBoundary Component für AZE_Gemini
 * 
 * Fängt React-Fehler ab und zeigt benutzerfreundliche Fehlermeldungen
 * Teil der Lösung für Issue #50 und #135
 */

import React, { Component, ErrorInfo, ReactNode } from 'react';
import { ErrorDisplay } from './ErrorDisplay';
import { errorMessageService } from '../../services/ErrorMessageService';
import './ErrorDisplay.css';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

export class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null
    };
  }

  static getDerivedStateFromError(error: Error): State {
    // Update state so the next render will show the fallback UI
    return {
      hasError: true,
      error,
      errorInfo: null
    };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Log error to error reporting service
    console.error('ErrorBoundary caught an error:', error, errorInfo);
    
    // Update state with error info
    this.setState({
      error,
      errorInfo
    });
    
    // Log to backend if available
    if (typeof window !== 'undefined' && 'api' in window) {
      (window as any).api?.logError({
        message: error.message,
        stack: error.stack,
        context: 'ErrorBoundary',
        componentStack: errorInfo.componentStack
      });
    }
  }

  handleReset = () => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null
    });
  };

  handleReload = () => {
    window.location.reload();
  };

  render() {
    if (this.state.hasError) {
      // Custom fallback provided
      if (this.props.fallback) {
        return <>{this.props.fallback}</>;
      }

      const error = this.state.error || new Error('Unbekannter Fehler');
      
      // Create error context for display
      const errorContext = errorMessageService.getErrorMessage({
        ...error,
        code: 'REACT_ERROR',
        message: error.message || 'Ein unerwarteter Fehler ist in der Anwendung aufgetreten'
      });

      return (
        <div className="error-boundary">
          <div className="error-boundary__container">
            <div className="error-boundary__icon">
              <svg 
                width="64" 
                height="64" 
                viewBox="0 0 24 24" 
                fill="none" 
                stroke="currentColor" 
                strokeWidth="2"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
            </div>
            
            <h1 className="error-boundary__title">
              Ein Fehler ist aufgetreten
            </h1>
            
            <p className="error-boundary__message">
              Die Anwendung hat einen unerwarteten Fehler festgestellt. 
              Ihre Daten wurden nicht beeinträchtigt.
            </p>

            <ErrorDisplay 
              error={error}
              onRetry={this.handleReset}
              className="error-boundary__display"
            />
            
            <div className="error-boundary__actions">
              <button 
                onClick={this.handleReload}
                className="error-boundary__button error-boundary__button--primary"
              >
                Seite neu laden
              </button>
              
              <button 
                onClick={this.handleReset}
                className="error-boundary__button error-boundary__button--secondary"
              >
                Erneut versuchen
              </button>
              
              <button 
                onClick={() => window.history.back()}
                className="error-boundary__button error-boundary__button--text"
              >
                Zurück
              </button>
            </div>

            {/* Development mode: show component stack */}
            {process.env.NODE_ENV === 'development' && this.state.errorInfo && (
              <details className="error-boundary__dev-info">
                <summary>Entwickler-Informationen</summary>
                <pre className="error-boundary__stack">
                  <strong>Component Stack:</strong>
                  {this.state.errorInfo.componentStack}
                </pre>
              </details>
            )}
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

// HOC for functional components
export function withErrorBoundary<P extends object>(
  Component: React.ComponentType<P>,
  fallback?: ReactNode
) {
  const WithErrorBoundaryComponent = (props: P) => (
    <ErrorBoundary fallback={fallback}>
      <Component {...props} />
    </ErrorBoundary>
  );

  WithErrorBoundaryComponent.displayName = `withErrorBoundary(${Component.displayName || Component.name})`;

  return WithErrorBoundaryComponent;
}
/**
 * ErrorDebugOverlay - Displays error details in UI for debugging
 * TEMPORARY DEBUG COMPONENT - Remove after fixing GEN_001 error
 */
import React, { useEffect, useState } from 'react';

interface CapturedError {
  timestamp: string;
  message: string;
  name: string;
  stack?: string;
  fullError: any;
}

export const ErrorDebugOverlay: React.FC = () => {
  const [errors, setErrors] = useState<CapturedError[]>([]);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    // Intercept window errors
    const errorHandler = (event: ErrorEvent) => {
      const error: CapturedError = {
        timestamp: new Date().toISOString(),
        message: event.error?.message || event.message || 'Unknown error',
        name: event.error?.name || 'Error',
        stack: event.error?.stack,
        fullError: event.error
      };

      setErrors(prev => [...prev, error]);
      setVisible(true);

      console.log('🔴 ERROR CAPTURED:', error);
    };

    // Intercept unhandled promise rejections
    const rejectionHandler = (event: PromiseRejectionEvent) => {
      const error: CapturedError = {
        timestamp: new Date().toISOString(),
        message: event.reason?.message || String(event.reason) || 'Promise rejection',
        name: event.reason?.name || 'PromiseRejection',
        stack: event.reason?.stack,
        fullError: event.reason
      };

      setErrors(prev => [...prev, error]);
      setVisible(true);

      console.log('🔴 REJECTION CAPTURED:', error);
    };

    window.addEventListener('error', errorHandler);
    window.addEventListener('unhandledrejection', rejectionHandler);

    return () => {
      window.removeEventListener('error', errorHandler);
      window.removeEventListener('unhandledrejection', rejectionHandler);
    };
  }, []);

  if (!visible || errors.length === 0) return null;

  return (
    <div style={{
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      background: 'rgba(0,0,0,0.9)',
      zIndex: 999999,
      padding: '20px',
      overflow: 'auto',
      color: '#fff',
      fontFamily: 'monospace',
      fontSize: '14px'
    }}>
      <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
          <h1 style={{ color: '#ff5555' }}>🔴 ERROR DEBUG OVERLAY</h1>
          <button
            onClick={() => setVisible(false)}
            style={{
              background: '#44475a',
              border: 'none',
              color: '#fff',
              padding: '10px 20px',
              cursor: 'pointer',
              borderRadius: '4px'
            }}
          >
            Close (Error still logged in Console)
          </button>
        </div>

        {errors.map((error, index) => (
          <div key={index} style={{
            background: '#282a36',
            padding: '20px',
            marginBottom: '20px',
            borderRadius: '8px',
            border: '2px solid #ff5555'
          }}>
            <div style={{ marginBottom: '10px' }}>
              <strong style={{ color: '#ff79c6' }}>Error #{index + 1}</strong>
              <span style={{ color: '#6272a4', marginLeft: '20px' }}>{error.timestamp}</span>
            </div>

            <div style={{ marginBottom: '15px' }}>
              <div style={{ color: '#8be9fd', marginBottom: '5px' }}>Error Name:</div>
              <div style={{ color: '#f8f8f2', background: '#44475a', padding: '10px', borderRadius: '4px' }}>
                {error.name}
              </div>
            </div>

            <div style={{ marginBottom: '15px' }}>
              <div style={{ color: '#8be9fd', marginBottom: '5px' }}>Error Message:</div>
              <div style={{ color: '#ffb86c', background: '#44475a', padding: '10px', borderRadius: '4px', whiteSpace: 'pre-wrap' }}>
                {error.message}
              </div>
            </div>

            {error.stack && (
              <div style={{ marginBottom: '15px' }}>
                <div style={{ color: '#8be9fd', marginBottom: '5px' }}>Stack Trace:</div>
                <pre style={{
                  color: '#f8f8f2',
                  background: '#191a21',
                  padding: '15px',
                  borderRadius: '4px',
                  overflow: 'auto',
                  fontSize: '12px',
                  lineHeight: '1.5'
                }}>
                  {error.stack}
                </pre>
              </div>
            )}

            <div>
              <div style={{ color: '#8be9fd', marginBottom: '5px' }}>Full Error Object (JSON):</div>
              <pre style={{
                color: '#50fa7b',
                background: '#191a21',
                padding: '15px',
                borderRadius: '4px',
                overflow: 'auto',
                fontSize: '12px',
                lineHeight: '1.5'
              }}>
                {JSON.stringify(error.fullError, null, 2)}
              </pre>
            </div>
          </div>
        ))}

        <div style={{ textAlign: 'center', marginTop: '30px', color: '#6272a4' }}>
          <p>Copy the error details above and send to developer</p>
          <button
            onClick={() => {
              const errorText = errors.map((e, i) =>
                `ERROR #${i+1}\n` +
                `Timestamp: ${e.timestamp}\n` +
                `Name: ${e.name}\n` +
                `Message: ${e.message}\n` +
                `Stack:\n${e.stack || 'No stack'}\n\n`
              ).join('\n---\n\n');
              navigator.clipboard.writeText(errorText);
              alert('Error details copied to clipboard!');
            }}
            style={{
              background: '#50fa7b',
              border: 'none',
              color: '#282a36',
              padding: '10px 30px',
              cursor: 'pointer',
              borderRadius: '4px',
              fontWeight: 'bold',
              marginTop: '10px'
            }}
          >
            📋 Copy All Errors to Clipboard
          </button>
        </div>
      </div>
    </div>
  );
};

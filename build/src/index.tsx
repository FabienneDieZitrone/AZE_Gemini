/**
 * Titel: Arbeitszeiterfassung Anwendungs-Einstiegspunkt
 * Version: 10.0 (BFF-Architektur)
 * Letzte Aktualisierung: 10.11.2024
 * Autor: MP-IT
 * Status: Final
 * Datei: /src/index.tsx
 * Beschreibung: Minimaler, sauberer Einstiegspunkt. Die Authentisierungslogik wurde vollständig in das PHP-Backend (BFF) verlagert.
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import '../index.css';

// Minimal global client error logger → sends errors to /api/logs.php (no auth required)
try {
  const sendClientLog = (payload: any) => {
    try {
      fetch('/api/logs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ level: 'error', ...payload, ts: new Date().toISOString() })
      }).catch(() => {});
    } catch {}
  };
  
  // Uncaught runtime errors
  window.addEventListener('error', (event) => {
    const err = event?.error as Error | undefined;
    sendClientLog({
      message: err?.message || event?.message || 'window.error',
      stack: err?.stack,
      filename: (event as any)?.filename,
      lineno: (event as any)?.lineno,
      colno: (event as any)?.colno,
      type: 'window.error'
    });
  });
  
  // Unhandled promise rejections
  window.addEventListener('unhandledrejection', (event) => {
    const reason: any = (event as any)?.reason;
    sendClientLog({
      message: (reason && (reason.message || String(reason))) || 'unhandledrejection',
      stack: reason && reason.stack,
      type: 'window.unhandledrejection'
    });
  });
} catch {}

const container = document.getElementById('root');
if (container) {
  const root = createRoot(container);
  root.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}

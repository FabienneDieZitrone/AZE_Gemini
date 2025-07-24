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

const container = document.getElementById('root');
if (container) {
  const root = createRoot(container);
  root.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}